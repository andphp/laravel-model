<?php

namespace AndPHP\Console;

use Illuminate\Support\ServiceProvider;
use AndPHP\Console\Commands\ModelCommand;

/**
 * Created by PhpStorm.
 * User: DaXiong
 * Date: 2021/4/2
 * Time: 3:01 AM
 */
class ModelCommandServiceProvider extends ServiceProvider
{
    /**
     * Boot the service provider.
     *
     * @return void
     */
    public function boot()
    {
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('command.andphp', function () {
            return new ModelCommand;
        });

        $this->commands(['command.andphp']);
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['command.andphp'];
    }
}
