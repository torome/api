<?php

namespace Dingo\Api\Provider;

use ReflectionClass;
use Illuminate\Support\ServiceProvider;
use FastRoute\RouteParser\Std as StdRouteParser;
use Dingo\Api\Routing\Adapter\Lumen as LumenAdapter;
use FastRoute\Dispatcher\GroupCountBased as GcbDispatcher;
use FastRoute\DataGenerator\GroupCountBased as GcbDataGenerator;

class LumenServiceProvider extends ServiceProvider
{
    /**
     * Boot the service provider.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->routeMiddleware([
            'api.auth' => 'Dingo\Api\Http\Middleware\Auth',
            'api.limiting' => 'Dingo\Api\Http\Middleware\RateLimit',
        ]);
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $reflection = new ReflectionClass($this->app);

        $this->app->instance('app.middleware', $this->gatherAppMiddleware($reflection));

        $this->addRequestMiddlewareToBeginning($reflection);

        $this->app->register('Dingo\Api\Provider\ApiServiceProvider');

        $this->app->singleton('api.router.adapter', function ($app) {
            return new LumenAdapter($app, new StdRouteParser, new GcbDataGenerator, 'FastRoute\Dispatcher\GroupCountBased');
        });
    }

    /**
     * Add the request middleware to the beginning of the middleware stack on the
     * Lumen application instance.
     *
     * @param \ReflectionClass $reflection
     *
     * @return void
     */
    protected function addRequestMiddlewareToBeginning(ReflectionClass $reflection)
    {
        $property = $reflection->getProperty('middleware');
        $property->setAccessible(true);

        $middleware = $property->getValue($this->app);

        array_unshift($middleware, 'Dingo\Api\Http\Middleware\Request');

        $property->setValue($this->app, $middleware);
        $property->setAccessible(false);
    }

    /**
     * Gather the application middleware besides this one so that we can send
     * our request through them, exactly how the developer wanted.
     *
     * @param \ReflectionClass $reflection
     *
     * @return array
     */
    protected function gatherAppMiddleware(ReflectionClass $reflection)
    {
        $property = $reflection->getProperty('middleware');
        $property->setAccessible(true);

        $middleware = $property->getValue($this->app);

        return $middleware;
    }
}
