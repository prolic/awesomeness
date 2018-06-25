<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager\Http;

use Amp\Http\Server\RequestHandler\CallableRequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Server\Router as HttpRouter;
use Amp\Http\Status;
use Prooph\PostgresProjectionManager\ProjectionManager;

class RouterBuilder
{
    public function __invoke(ProjectionManager $projectionManager): HttpRouter
    {
        $router = new HttpRouter();

        $router->addRoute(Method::Get, '/', new CallableRequestHandler(function () {
            return new Response(Status::OK, ['content-type' => 'text/plain'], 'Prooph PDO Projection Manager');
        }));
        $router->addRoute(Method::Get, '/projection/{name}/config', new RequestHandler\GetConfig($projectionManager));
        $router->addRoute(Method::Post, '/projection/{name}/config', new RequestHandler\UpdateConfig($projectionManager));
        $router->addRoute(Method::Get, '/projection/{name}/command/disable', new RequestHandler\DisableProjection($projectionManager));
        $router->addRoute(Method::Get, '/projection/{name}/command/enable', new RequestHandler\EnableProjection($projectionManager));
        $router->addRoute(Method::Get, '/projection/{name}/command/reset', new RequestHandler\ResetProjection($projectionManager));
        $router->addRoute(Method::Get, '/projection/{name}/query', new RequestHandler\GetDefinition($projectionManager));
        $router->addRoute(Method::Put, '/projection/{name}/query', new RequestHandler\UpdateQuery($projectionManager));
        $router->addRoute(Method::Get, '/projection/{name}/state', new RequestHandler\GetState($projectionManager));
        $router->addRoute(Method::Get, '/projection/{name}/statistics', new RequestHandler\GetStatistics($projectionManager));
        $router->addRoute(Method::Post, '/projections/{mode}', new RequestHandler\CreateProjection($projectionManager));
        $router->addRoute(Method::Delete, '/projection/{name}', new RequestHandler\DeleteProjection($projectionManager));

        return $router;
    }
}
