<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager;

require __DIR__ . '/../../vendor/autoload.php';

use Amp\ByteStream\ResourceOutputStream;
use Amp\Http\Server\RequestHandler\CallableRequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Server\Router;
use Amp\Http\Server\Server;
use Amp\Http\Status;
use Amp\Log\ConsoleFormatter;
use Amp\Log\StreamHandler;
use Amp\Loop;
use Amp\Postgres\ConnectionPool;
use Amp\Socket;
use Monolog\Logger;
use Prooph\PostgresProjectionManager\Http\RequestHandler;

Loop::run(function () {
    // start projection manager
    $logHandler = new StreamHandler(new ResourceOutputStream(\STDOUT));
    $logHandler->setFormatter(new ConsoleFormatter());

    $logger = new Logger('projection-manager');
    $logger->pushHandler($logHandler);

    $postgresPool = new ConnectionPool('host=localhost user=postgres dbname=new_event_store password=postgres');
    $projectionManager = new ProjectionManager($postgresPool, $logger);

    yield $projectionManager->start();

    // start http server
    $servers = [
        Socket\listen('0.0.0.0:1337'),
        Socket\listen('[::]:1337'),
    ];

    $logHandler = new StreamHandler(new ResourceOutputStream(\STDOUT));
    $logHandler->setFormatter(new ConsoleFormatter());

    $logger = new Logger('http-server');
    $logger->pushHandler($logHandler);

    $router = new Router();
    $router->addRoute('GET', '/', new CallableRequestHandler(function () {
        return new Response(Status::OK, ['content-type' => 'text/plain'], 'Prooph PDO Projection Manager');
    }));
    $router->addRoute('GET', '/projection/{name}/command/stop', new RequestHandler\StopProjectionRequestHandler($projectionManager));
    // @todo add other routes

    $server = new Server($servers, $router, $logger);
    yield $server->start();

    // Stop the server when SIGINT is received (this is technically optional, but it is best to call Server::stop()).
    Loop::onSignal(SIGINT, function (string $watcherId) use ($server, $projectionManager) {
        Loop::cancel($watcherId);
        yield $server->stop();
        yield $projectionManager->stop();
    });
});
