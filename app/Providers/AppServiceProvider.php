<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Ai\Contracts\AiProviderInterface;
use App\Services\Ai\FallbackAiService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AiProviderInterface::class, FallbackAiService::class);
        $this->app->singleton(\App\Services\Duplicate\DuplicateDetectorInterface::class, \App\Services\Duplicate\FuzzyDuplicateDetector::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
