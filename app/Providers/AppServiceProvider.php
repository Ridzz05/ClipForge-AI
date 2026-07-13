<?php

namespace App\Providers;

use App\Services\FfprobeService;
use App\Services\WhisperService;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(FfprobeService::class, fn () => FfprobeService::fromConfig());

        $this->app->singleton(
            WhisperService::class,
            fn ($app) => WhisperService::fromConfig($app->make(HttpFactory::class)),
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
