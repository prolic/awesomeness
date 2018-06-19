<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager\RequestHandler;

use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Server\Router;
use Amp\Promise;
use Amp\Success;
use Prooph\EventStore\Internal\Principal;
use Prooph\EventStore\ProjectionManagement\CreateProjectionResult;
use Prooph\PostgresProjectionManager\Messages\CreateProjectionMessage;
use Prooph\PostgresProjectionManager\ProjectionManager;
use Throwable;
use function Amp\call;

/** @internal */
class CreateProjection implements RequestHandler
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
            $mode = $args['mode'];

            $rawQuery = $request->getUri()->getQuery();

            if (! $rawQuery) {
                return new Success(new Response(500, ['Content-Type' => 'text/plain'], 'Error'));
            }

            \parse_str($rawQuery, $query);

            $name = $query['name'] ?? '';
            $enabled = (bool) $query['enabled'] ?? false;
            $type = \strtoupper($query['type']) ?? 'PHP';
            $query = yield $request->getBody()->buffer();
            $checkpoints = (bool) $query['checkpoints'] ?? true;
            $emit = (bool) $query['emit'] ?? false;
            $trackEmittedStreams = (bool) $query['trackemittedstreams'] ?? false;
            $runAs = new Principal('$admin', []); // @todo fetch from acl checks

            if (empty($query)) {
                return new Success(new Response(500, ['Content-Type' => 'text/plain'], 'Error'));
            }

            switch (\strtolower($mode)) {
                case 'transient':
                    $mode = 'Transient';
                    break;
                case 'onetime':
                    $mode = 'OneTime';
                    break;
                case 'continuous':
                    $mode = 'Continuous';
                    break;
                default:
                    return new Success(new Response(500, ['Content-Type' => 'text/plain'], 'Error'));
            }

            if ('' === $name || 'PHP' !== $type) {
                return new Success(new Response(500, ['Content-Type' => 'text/plain'], 'Error'));
            }

            try {
                $message = new CreateProjectionMessage(
                    $mode,
                    $name,
                    $query,
                    $runAs,
                    $enabled,
                    $type,
                    $checkpoints,
                    $emit,
                    $trackEmittedStreams
                );
                /** @var CreateProjectionResult $result */
                $result = yield $this->projectionManager->handle($message);
            } catch (Throwable $e) {
                return new Response(500, ['Content-Type' => 'text/plain'], 'Error');
            }

            if ($result->equals(CreateProjectionResult::conflict())) {
                return new Response(409, ['Content-Type' => 'text/plain'], 'Conflict');
            }

            return new Response(201, ['Content-Type' => 'text/plain'], 'OK');
        });
    }
}
