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
use Amp\Postgres\Pool as PostgresPool;
use Amp\Socket;
use DateTimeZone;
use Monolog\Logger;
use Prooph\PostgresProjectionManager\Http\RequestHandler;

Logger::setTimezone(new DateTimeZone('UTC'));

Loop::run(function () {
    // start projection manager
    $logHandler = new StreamHandler(new ResourceOutputStream(\STDOUT));
    $logHandler->setFormatter(new ConsoleFormatter());
    $logHandler->setLevel(Logger::DEBUG);

    $logger = new Logger('PROJECTIONS');
    $logger->pushHandler($logHandler);

    $postgresPool = new PostgresPool('host=localhost user=postgres dbname=new_event_store password=postgres');
    $projectionManager = new Internal\ProjectionManager($postgresPool, $logger);

    yield $projectionManager->start();

    // start http server
    $servers = [
        Socket\listen('0.0.0.0:1337'),
        Socket\listen('[::]:1337'),
    ];

    $logHandler = new StreamHandler(new ResourceOutputStream(\STDOUT));
    $logHandler->setFormatter(new ConsoleFormatter());
    $logHandler->setLevel(Logger::DEBUG);

    $logger = new Logger('HTTP');
    $logger->pushHandler($logHandler);

    $router = new Router();
    $router->addRoute('GET', '/', new CallableRequestHandler(function () {
        return new Response(Status::OK, ['content-type' => 'text/plain'], 'Prooph PDO Projection Manager');
    }));
    $router->addRoute('GET', '/projection/{name}/command/disable', new RequestHandler\DisableProjectionRequestHandler($projectionManager));
    $router->addRoute('GET', '/projection/{name}/command/enable', new RequestHandler\EnableProjectionRequestHandler($projectionManager));

    $server = new Server($servers, $router, $logger);
    yield $server->start();

    // Stop the server when SIGINT is received (this is technically optional, but it is best to call Server::stop()).
    Loop::onSignal(SIGINT, function (string $watcherId) use ($server, $projectionManager, $logger) {
        $logger->info('Receive SIGINT - shutting down');
        Loop::cancel($watcherId);
        yield $server->stop();
        yield $projectionManager->stop();
    });
});
