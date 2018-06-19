<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager\RequestHandler;

use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Server\Router;
use Amp\Promise;
use Prooph\PostgresProjectionManager\Exception\ProjectionNotFound;
use Prooph\PostgresProjectionManager\Messages\EnableMessage;
use Prooph\PostgresProjectionManager\ProjectionManager;
use Throwable;
use function Amp\call;

/** @internal */
class EnableProjection implements RequestHandler
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

        $rawQuery = $request->getUri()->getQuery();

        if ($rawQuery) {
            \parse_str($rawQuery, $query);
            $enableRunAs = $query['enableRunAs'] ?? null;
        } else {
            $enableRunAs = null;
        }

        return call(function () use ($name, $enableRunAs) {
            try {
                $message = new EnableMessage($name, $enableRunAs);
                yield $this->projectionManager->handle($message);
            } catch (ProjectionNotFound $e) {
                return new Response(404, ['Content-Type' => 'text/plain'], 'Not Found');
            } catch (Throwable $e) {
                return new Response(500, ['Content-Type' => 'text/plain'], 'Error');
            }

            return new Response(202, ['Content-Type' => 'text/html'], 'OK');
        });
    }
}
