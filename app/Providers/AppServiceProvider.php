<?php

namespace App\Providers;

use App\Policies\DocumentAccessPolicy;
use Illuminate\Pagination\Paginator;
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
        foreach ($this->documentGates() as $ability => $method) {
            Gate::define($ability, [DocumentAccessPolicy::class, $method]);
        }

        Paginator::defaultView('vendor.pagination.light');
        Paginator::defaultSimpleView('vendor.pagination.simple-light');
    }

    /**
     * Get document-related ability to policy method mappings.
     *
     * @return array<string, string>
     */
    protected function documentGates(): array
    {
        return [
            'documents.intake' => 'intake',
            'documents.view' => 'view',
            'documents.process' => 'process',
            'documents.manage' => 'manage',
            'documents.export' => 'export',
        ];
    }
}
