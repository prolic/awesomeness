<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager\Http\RequestHandler;

use function Amp\call;
use Amp\Coroutine;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Server\Router;
use Amp\Promise;
use Amp\Success;
use Prooph\PostgresProjectionManager\ProjectionManager;

class StopProjectionRequestHandler implements RequestHandler
{
    /** @var ProjectionManager */
    private $projectionManager;

    public function __construct(ProjectionManager $projectionManager)
    {
        $this->projectionManager = $projectionManager;
    }

    public function handleRequest(Request $request): Promise
    {
        $args = $request->getAttribute(Router::class);
        $name = $args['name'];

        return call(function () use ($name) {
            try {
                yield $this->projectionManager->stopProjection($name);
            } catch (\Throwable $e) {
                return new Response(404, ['Content-Type' => 'text/html'], 'Not found');
            }

            return new Response(202, ['Content-Type' => 'text/html'], 'OK');
        });
    }
}
