<?php

namespace Simonet85\LaravelMoneyFusion;

use Illuminate\Support\ServiceProvider;
use Simonet85\LaravelMoneyFusion\Console\TestPaymentCommand;
use Simonet85\LaravelMoneyFusion\Console\CheckPaymentCommand;

class MoneyFusionServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publier la configuration
        $this->publishes([
            __DIR__.'/../config/moneyfusion.php' => config_path('moneyfusion.php'),
        ], 'moneyfusion-config');

        // Publier les migrations
        $this->publishes([
            __DIR__.'/../database/migrations/create_moneyfusion_payments_table.php' 
                => database_path('migrations/'.date('Y_m_d_His').'_create_moneyfusion_payments_table.php'),
        ], 'moneyfusion-migrations');

        // Publier les vues
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/moneyfusion'),
        ], 'moneyfusion-views');

        // Charger les migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Charger les routes
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        // Charger les vues
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'moneyfusion');

        // Enregistrer les commandes
        if ($this->app->runningInConsole()) {
            $this->commands([
                TestPaymentCommand::class,
                CheckPaymentCommand::class,
            ]);
        }
    }

    /**
     * Register services.
     */
    public function register(): void
    {
        // Fusionner la configuration
        $this->mergeConfigFrom(
            __DIR__.'/../config/moneyfusion.php', 'moneyfusion'
        );

        // Enregistrer le service
        $this->app->singleton('moneyfusion', function ($app) {
            return new MoneyFusionService();
        });

        // Alias
        $this->app->alias('moneyfusion', MoneyFusionService::class);
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides(): array
    {
        return ['moneyfusion', MoneyFusionService::class];
    }
        
}