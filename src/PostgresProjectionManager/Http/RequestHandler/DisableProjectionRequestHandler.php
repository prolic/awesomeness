<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager\Http\RequestHandler;

use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Server\Router;
use Amp\Promise;
use Prooph\PostgresProjectionManager\Internal\ProjectionManager;
use function Amp\call;

/** @internal */
class DisableProjectionRequestHandler implements RequestHandler
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
                yield $this->projectionManager->disableProjection($name);
            } catch (\Throwable $e) {
                return new Response(404, ['Content-Type' => 'text/html'], 'Not found');
            }

            return new Response(202, ['Content-Type' => 'text/html'], 'OK');
        });
    }
}
