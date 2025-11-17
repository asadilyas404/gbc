<?php

namespace Zaryab\OracleSchemaSync;

use Illuminate\Support\ServiceProvider;

class OracleSchemaSyncServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Publish config file
        $this->publishes([
            __DIR__ . '/../config/oracle.php' => config_path('oracle.php'),
        ], 'config');

        // Optionally, you can merge default config
        $this->mergeConfigFrom(
            __DIR__ . '/../config/oracle.php',
            'oracle'
        );
    }

    public function register()
    {
        // Register command if running in console
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\syncTableColumns::class,
            ]);
        }
    }
}
