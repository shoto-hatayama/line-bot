<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\CallFoodApi;

class CallFoodApiServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
        $this->app->bind('CallFoodApi', function($app){
            return $app->make(CallFoodApi::class);
        });

    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
