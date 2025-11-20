<?php

namespace App\Providers;

use App\Models\Admin;
use App\Models\Coach;
use App\Models\CalendarEvent;
use App\Observers\CalendarEventObserver;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // if (app()->environment('local')) {
        //     URL::forceScheme('http'); // Or 'https' if you need SSL
        // }
        URL::forceScheme('https');
        Relation::morphMap([
            "Admin" => Admin::class,
            "Coach" => Coach::class,
        ]);

        // Register Calendar Event Observer
        CalendarEvent::observe(CalendarEventObserver::class);
    }
}
