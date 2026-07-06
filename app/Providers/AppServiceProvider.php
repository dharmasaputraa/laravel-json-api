<?php

namespace App\Providers;

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Illuminate\Pagination\Paginator;
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
        $this->configurePagination();
        $this->configureScramble();
    }

    /**
     * Configure pagination to use JSON:API bracket notation.
     *
     * Reads the current page from `page[number]` (the JSON:API standard)
     * instead of the default `page` query parameter.
     */
    protected function configurePagination(): void
    {
        Paginator::currentPageResolver(function (string $pageName = 'page') {
            $page = request()->input('page.number');

            if ($page !== null) {
                return $page;
            }

            // Fallback: support legacy ?page=N format
            return request()->input($pageName);
        });
    }

    protected function configureScramble(): void
    {
        Scramble::registerApi('v1', [
            'api_path' => 'api/v1',
            'info' => [
                'version' => '1.0.0',
                'title' => 'JSON API Spec — Practice Project',
                'description' => 'API documentation for JSON:API spec practice. Demonstrates Approach A (JsonApiResource) and Approach B (Manual envelope) for Post resources.',
            ],
        ])
            ->expose(ui: '/docs/v1', document: '/docs/v1.json');
    }
}
