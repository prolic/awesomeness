<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager\RequestHandler;

use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Server\Router;
use Amp\Promise;
use Prooph\PostgresProjectionManager\ProjectionManager;
use function Amp\call;

/** @internal */
class ResetProjection implements RequestHandler
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

        $query = $request->getUri()->getQuery();

        if ($query) {
            \parse_str($query, $query);
            $enableRunAs = $query['enableRunAs'] ?? null;
        } else {
            $enableRunAs = null;
        }

        return call(function () use ($name, $enableRunAs) {
            try {
                yield $this->projectionManager->resetProjection($name, $enableRunAs);
            } catch (\Throwable $e) {
                return new Response(404, ['Content-Type' => 'text/plain'], 'Not Found');
            }

            return new Response(200, ['Content-Type' => 'text/html'], 'OK');
        });
    }
}
