<?php

namespace Yaremenkosa\Esia;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;

class EsiaServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(
            EsiaProvider::class,
            function (Container $container) {
                $config = $container->make('config');

                return new EsiaProvider(
                    $container->make('request'),
                    $config->get('services.esia')
                );
            }
        );
    }
}