<?php

namespace App\Providers;

use App\Policies\DocumentAccessPolicy;
use Illuminate\Support\Facades\Gate;
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
        Gate::define('documents.view', [DocumentAccessPolicy::class, 'view']);
        Gate::define('documents.process', [DocumentAccessPolicy::class, 'process']);
        Gate::define('documents.manage', [DocumentAccessPolicy::class, 'manage']);
        Gate::define('documents.export', [DocumentAccessPolicy::class, 'export']);
    }
}
