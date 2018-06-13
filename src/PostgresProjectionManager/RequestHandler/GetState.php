<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager\RequestHandler;

use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Server\Router;
use Amp\Promise;
use Prooph\PostgresProjectionManager\Internal\ProjectionManager;
use function Amp\call;

/** @internal */
class GetStateRequestHandler implements RequestHandler
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
        $projectionName = $args['name'];

        return call(function () use ($projectionName) {
            try {
                $state = yield $this->projectionManager->getState($projectionName);
            } catch (\Throwable $e) {
                return new Response(404, ['Content-Type' => 'text/html'], 'Not found');
            }

            return new Response(200, ['Content-Type' => 'application'], \json_encode($state));
        });
    }
}
