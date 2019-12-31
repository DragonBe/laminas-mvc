# Dispatching PSR-7 Middleware

[PSR-7](http://www.php-fig.org/psr/psr-7/) defines interfaces for HTTP messages,
and is now being adopted by many frameworks; Laminas itself offers a
parallel microframework targeting PSR-7 with [Mezzio](https://docs.mezzio.dev/mezzio).
What if you want to dispatch PSR-7 middleware from laminas-mvc?

laminas-mvc currently uses [laminas-http](https://github.com/laminas/laminas-http)
for its HTTP transport layer, and the objects it defines are not compatible with
PSR-7, meaning the basic MVC layer does not and cannot make use of PSR-7
currently.

However, starting with version 2.7.0, laminas-mvc offers
`Laminas\Mvc\MiddlewareListener`. This [dispatch](mvc-event.md#mvceventevent_dispatch-dispatch)
listener listens prior to the default `DispatchListener`, and executes if the
route matches contain a "middleware" parameter, and the service that resolves to
is callable. When those conditions are met, it uses the [PSR-7 bridge](https://github.com/laminas/laminas-psr7bridge)
to convert the laminas-http request and response objects into PSR-7 instances, and
then invokes the middleware.

## Mapping routes to middleware

The first step is to map a route to PSR-7 middleware. This looks like any other
[routing](routing.md) configuration, with one small change: instead of providing
a "controller" in the routing defaults, you provide "middleware":

```php
// Via configuration:
return [
    'router' =>
        'routes' => [
            'home' => [
                'type' => 'literal',
                'options' => [
                    'route' => '/',
                    'defaults' => [
                        'middleware' => 'Application\Middleware\IndexMiddleware',
                    ],
                ],
            ],
        ],
    ],
];

// Manually:
$route = Literal::factory([
    'route' => '/',
    'defaults' => [
        'middleware' => 'Application\Middleware\IndexMiddleware',
    ],
]);
```

Middleware may be provided as PHP callables, or as service names.

> ### No action required
> 
> Unlike action controllers, middleware typically is single purpose, and, as
> such, does not require a default `action` parameter.

## Middleware services

In a normal laminas-mvc dispatch cycle, controllers are pulled from a dedicated
`ControllerManager`. Middleware, however, are pulled from the application
service manager.

Middleware retrieved *must* be PHP callables. The `MiddlewareListener` will
create an error response if non-callable middleware is indicated.

## Writing middleware

When dispatching middleware, the `MiddlewareListener` calls it with two
arguments, the PSR-7 request and response, respectively. As such, your
middleware signature should look like the following:

```php
namespace Application\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class IndexMiddleware
{
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response)
    {
        // do some work
    }
}
```

From there, you can pull information from the composed request, and manipulate
the response.

> ### Routing parameters
>
> At the time of the 2.7.0 release, route match parameters are not yet injected
> into the PSR-7 `ServerRequest` instance, and are thus not available as request
> attributes..

## Middleware return values

Ideally, your middleware should return a PSR-7 response. When it does, it is
converted back to a laminas-http response and returned by the `MiddlewareListener`,
causing the application to short-circuit and return the response immediately.

You can, however, return arbitrary values. If you do, the result is pushed into
the `MvcEvent` as the event result, allowing later dispatch listeners to
manipulate the results.
