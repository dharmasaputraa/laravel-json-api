<?php

namespace App\Providers;

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
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
        $this->configureScramble();
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