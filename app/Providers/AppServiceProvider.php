<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register SalesOrder Observer for automated workflow triggers
        \App\Models\SalesOrder::observe(\App\Observers\SalesOrderObserver::class);

        // Extract subfolder path from APP_URL dynamically (e.g. /scm-erp)
        $appUrl = config('app.url');
        $appPath = rtrim(parse_url($appUrl, PHP_URL_PATH) ?? '', '/');

        // Make all generated URLs include the subfolder prefix
        if (!empty($appPath)) {
            \Illuminate\Support\Facades\URL::forceRootUrl(rtrim($appUrl, '/'));
        }

        // Register Livewire routes under the correct subfolder path
        \Livewire\Livewire::setScriptRoute(function ($handle) use ($appPath) {
            return \Illuminate\Support\Facades\Route::get($appPath . '/livewire/livewire.js', $handle);
        });

        \Livewire\Livewire::setUpdateRoute(function ($handle) use ($appPath) {
            return \Illuminate\Support\Facades\Route::post($appPath . '/livewire/update', $handle);
        });

        // Make project name fully dynamic globally
        config(['app.name' => setting('website_name', config('app.name', 'SCM ERP'))]);

        // Register Auth Event Listeners for System Logs
        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Auth\Events\Login::class,
            function (\Illuminate\Auth\Events\Login $event) {
                \App\Models\SystemLog::create([
                    'user_id' => $event->user->id,
                    'action' => 'login',
                    'module' => 'Auth',
                    'description' => "User " . $event->user->name . " logged in successfully.",
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
            }
        );

        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Auth\Events\Logout::class,
            function (\Illuminate\Auth\Events\Logout $event) {
                if ($event->user) {
                    \App\Models\SystemLog::create([
                        'user_id' => $event->user->id,
                        'action' => 'logout',
                        'module' => 'Auth',
                        'description' => "User " . $event->user->name . " logged out successfully.",
                        'ip_address' => request()->ip(),
                        'user_agent' => request()->userAgent(),
                    ]);
                }
            }
        );
    }
}
