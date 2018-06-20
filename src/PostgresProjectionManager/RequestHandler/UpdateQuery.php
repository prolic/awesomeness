<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager\RequestHandler;

use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Server\Router;
use Amp\Promise;
use Prooph\PostgresProjectionManager\Exception\ProjectionNotFound;
use Prooph\PostgresProjectionManager\Messages\UpdateQueryMessage;
use Prooph\PostgresProjectionManager\ProjectionManager;
use Throwable;
use function Amp\call;

/** @internal */
class UpdateQuery implements RequestHandler
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

        \parse_str($request->getUri()->getQuery(), $query);

        $type = $query['type'] ?? 'PHP';
        $emitEnabled = $query['emitEnabled'] ?? false;

        return call(function () use ($projectionName, $request, $type, $emitEnabled) {
            $query = yield $request->getBody()->buffer();

            try {
                $message = new UpdateQueryMessage($projectionName, $type, $query, $emitEnabled);
                yield $this->projectionManager->handle($message);
            } catch (ProjectionNotFound $e) {
                return new Response(404, ['Content-Type' => 'text/plain'], 'Not Found');
            } catch (Throwable $e) {
                return new Response(500, ['Content-Type' => 'text/plain'], 'Error');
            }

            return new Response(200, ['Content-Type' => 'text/plain'], 'OK');
        });
    }
}
