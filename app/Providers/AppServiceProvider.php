<?php

namespace App\Providers;

use App\Events\JobFailedEvent;
use App\Service;
use App\WorkshopImages;
use App\Workshop;
use App\Booking;
use App\Observers\ServiceObserver;
use App\Observers\WorkshopObserver;
use App\Observers\BookingObserver;
use App\Observers\WorkshopImagesObserver;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Schema::defaultStringLength(191);
        Service::observe(ServiceObserver::class);
        WorkshopImages::observe(WorkshopImagesObserver::class);

        Queue::failing(function (JobFailedEvent $event) {

//          Fire JobFailed Event
            event($event);
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        if ($this->app->environment() == 'local') {
            $this->app->register('Hesto\MultiAuth\MultiAuthServiceProvider');
        }
    }
}
