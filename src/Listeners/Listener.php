<?php

namespace Yungts97\LaravelUserActivityLog\Listeners;

use Yungts97\LaravelUserActivityLog\Models\Log;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;


class Listener
{
    protected $event;
    protected string $event_name;

    public function __construct(string $event_name = "")
    {
        $this->event_name = $event_name;
    }

    public function handle($event)
    {
        $this->event = $event;
        $this->logging();
    }

    protected function logging()
    {
        if (!$this->isLoggable()) return;

        // get the request info
        $request_info = [
            'ip'         => request()->header('X-Forwarded-For') ?? request()->ip(),
            'user_agent' => request()->userAgent(),
        ];

        // insert log record
        Log::create([
            'user_id'       => Auth::id(),
            'log_datetime'  => date('Y-m-d H:i:s'),
            'log_type'      => $this->event_name,
            'table_name'    => $this->getTableName(),
            'request_info'  => $request_info,
            'data'          => $this->getData(),
        ]);
    }

    protected function isLoggable()
    {
        // always skip retrieve event for authenticatable model. Exp. App\Models\User
        if ($this->isRetrieveAuthenticatableModel()) return false;

        // skip if authenticable model is not loggable
        if ($this->isNotLoggableAuthenticatableModel()) return false;
        
        return config("user-activity-log.events.{$this->event_name}", false);
    }

    protected function getTableName()
    {
        return null;
    }

    protected function getData()
    {
        return null;
    }

    private function isRetrieveAuthenticatableModel()
    {
        return
            $this->event_name === 'retrieve' &&
            $this->event?->model instanceof Authenticatable;
    }

    private function isNotLoggableAuthenticatableModel()
    {
        $this->useLoggeduserAuthGuard();
        if(is_null(Auth::id()))
            return true;

        return config('user-activity-log.user_model', '\App\Models\User') !== get_class(auth()?->user());
    }

    private function useLoggeduserAuthGuard()
    {
        foreach (config('auth.guards') as $key => $guard) {
            if (auth()->guard($key)->check()) {
                return auth()->shouldUse($key);
            }
        }
    }
}
