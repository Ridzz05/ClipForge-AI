<?php

namespace App\Providers;

use App\Services\FfprobeService;
use App\Services\Reframe\FaceTrackingService;
use App\Services\Reframe\FfmpegService;
use App\Services\Scoring\OllamaService;
use App\Services\WhisperService;
use App\Services\YtDlpService;
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

        $this->app->singleton(YtDlpService::class, fn () => YtDlpService::fromConfig());

        $this->app->singleton(
            WhisperService::class,
            fn ($app) => WhisperService::fromConfig($app->make(HttpFactory::class)),
        );

        $this->app->singleton(
            OllamaService::class,
            fn ($app) => OllamaService::fromConfig($app->make(HttpFactory::class)),
        );

        $this->app->singleton(FfmpegService::class, fn () => FfmpegService::fromConfig());

        $this->app->singleton(
            FaceTrackingService::class,
            fn ($app) => FaceTrackingService::fromConfig($app->make(HttpFactory::class)),
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
