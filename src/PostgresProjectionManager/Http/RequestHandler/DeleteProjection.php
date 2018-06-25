<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager\Http\RequestHandler;

use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Server\Router;
use Amp\Promise;
use Amp\Success;
use Prooph\EventStore\ProjectionManagement\CreateProjectionResult;
use Prooph\PostgresProjectionManager\Messages\DeleteMessage;
use Prooph\PostgresProjectionManager\ProjectionManager;
use Throwable;
use function Amp\call;

/** @internal */
class DeleteProjection implements RequestHandler
{
    /** @var ProjectionManager */
    private $projectionManager;

    public function __construct(ProjectionManager $projectionManager)
    {
        $this->projectionManager = $projectionManager;
    }

    public function handleRequest(Request $request): Promise
    {
        return call(function () use ($request) {
            $args = $request->getAttribute(Router::class);
            $name = $args['name'];

            $rawQuery = $request->getUri()->getQuery();

            if (! $rawQuery) {
                return new Success(new Response(500, ['Content-Type' => 'text/plain'], 'Error'));
            }

            \parse_str($rawQuery, $query);

            $deleteStateStream = (bool) $query['deleteStateStream'] ?? false;
            $deleteCheckpointStream = (bool) $query['deleteCheckpointStream'] ?? false;
            $deleteEmittedStreams = (bool) $query['deleteEmittedStreams'] ?? false;

            try {
                $message = new DeleteMessage(
                    $name,
                    $deleteStateStream,
                    $deleteCheckpointStream,
                    $deleteEmittedStreams
                );
                /** @var CreateProjectionResult $result */
                yield $this->projectionManager->handle($message);
            } catch (Throwable $e) {
                return new Response(500, ['Content-Type' => 'text/plain'], 'Error');
            }

            return new Response(201, ['Content-Type' => 'text/plain'], 'OK');
        });
    }
}
