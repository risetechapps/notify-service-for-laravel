<?php

namespace RiseTechApps\Notify\Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RiseTechApps\Notify\NotifyServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make('config')->set('notify.key', 'test-api-key');
        $this->app->make('config')->set('notify.routes', false);
        $this->app->make('config')->set('cache.default', 'array');
        $this->app->make('config')->set('session.driver', 'array');
    }

    public function createApplication(): Application
    {
        // O manifesto de pacotes do Laravel é gravado em bootstrap/cache, que
        // fica fora do git: sem a pasta, a suíte não roda num clone limpo.
        if (! is_dir($cache = dirname(__DIR__).'/bootstrap/cache')) {
            mkdir($cache, 0755, true);
        }

        $app = new Application(dirname(__DIR__));

        // Ambiente da suíte em tests/.env.testing (versionado): sem um .env,
        // o Laravel avisava em todo teste que não achou o arquivo.
        $app->useEnvironmentPath(__DIR__)->loadEnvironmentFrom('.env.testing');

        $app->singleton(Kernel::class, \Illuminate\Foundation\Console\Kernel::class);

        $app->singleton(
            ExceptionHandler::class,
            Handler::class
        );

        $app->make(Kernel::class)->bootstrap();

        $app->register(NotifyServiceProvider::class);
        $app->boot();

        return $app;
    }
}
