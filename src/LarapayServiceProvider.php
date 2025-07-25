<?php

namespace PhpMonsters\Larapay;

use Illuminate\Support\ServiceProvider;
use PhpMonsters\Larapay\Console\InstallCommand;
use PhpMonsters\Larapay\Contracts\LarapayTransaction as LarapayTransactionContract;
use PhpMonsters\Larapay\Models\LarapayTransaction;

class LarapayServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     */
    public function boot(): void
    {
        $this->registerResources();
        $this->registerPublishing();
        $this->registerModelBindings();
    }

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->singleton('larapay', function ($app) {
            return new Factory();
        });
    }

    /**
     * Register package resources.
     */
    protected function registerResources(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../views/', 'larapay');

        $this->publishes([
            __DIR__ . '/../translations/' => $this->app->langPath('vendor/larapay'),
        ], 'translations');

        $this->loadTranslationsFrom(__DIR__ . '/../translations', 'larapay');
    }

    /**
     * Register package publishing.
     */
    protected function registerPublishing(): void
    {
        $this->publishes([
            __DIR__ . '/../config/larapay.php' => config_path('larapay.php')
        ], 'config');

        $this->publishes([
            __DIR__ . '/../views/' => resource_path('views/vendor/larapay'),
        ], 'views');

        $this->publishes([
            __DIR__ . '/../database/migrations/create_larapay_transaction_table.php.stub' => database_path('migrations/' . date('Y_m_d_His', time()) . '_create_larapay_transaction_table.php'),
        ], 'migrations');
    }

    /**
     * Register model bindings.
     */
    protected function registerModelBindings(): void
    {
        $this->app->bind(LarapayTransactionContract::class, LarapayTransaction::class);
    }

}
