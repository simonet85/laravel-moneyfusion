<?php

namespace Simonet85\LaravelMoneyFusion\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Simonet85\LaravelMoneyFusion\MoneyFusionServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [MoneyFusionServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        // Base de test configurable : SQLite en mémoire par défaut, ou toute autre
        // connexion via DB_CONNECTION / DB_HOST / DB_PORT / DB_DATABASE / DB_USERNAME / DB_PASSWORD.
        $connection = env('DB_CONNECTION', 'sqlite');

        $app['config']->set('database.default', $connection);
        $app['config']->set("database.connections.$connection", array_merge(
            $app['config']->get("database.connections.$connection", []),
            $connection === 'sqlite'
                ? ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']
                : [
                    'host' => env('DB_HOST', '127.0.0.1'),
                    'port' => env('DB_PORT'),
                    'database' => env('DB_DATABASE'),
                    'username' => env('DB_USERNAME'),
                    'password' => env('DB_PASSWORD'),
                ],
        ));

        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $app['config']->set('moneyfusion.api_url', 'https://www.pay.moneyfusion.net/TestApp/key/pay/');
        $app['config']->set('moneyfusion.app_key', 'TestApp/TestKey');
        $app['config']->set('moneyfusion.check_payment_url', 'https://pay.moneyfusion.net/paiementNotif');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
