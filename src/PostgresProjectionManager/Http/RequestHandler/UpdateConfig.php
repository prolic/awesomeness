<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager\Http\RequestHandler;

use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Server\Router;
use Amp\Promise;
use Prooph\EventStore\ProjectionManagement\ProjectionConfig;
use Prooph\PostgresProjectionManager\Exception\ProjectionNotFound;
use Prooph\PostgresProjectionManager\Messages\UpdateConfigMessage;
use Prooph\PostgresProjectionManager\ProjectionManager;
use Throwable;
use function Amp\call;

/** @internal */
class UpdateConfig implements RequestHandler
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

        return call(function () use ($projectionName, $request) {
            $config = yield $request->getBody()->buffer();
            $config = \json_decode($config, true);

            if (\json_last_error() !== \JSON_ERROR_NONE) {
                return new Response(400, ['Content-Type' => 'text/plain'], 'Invalid request');
            }

            try {
                $config = ProjectionConfig::fromArray($config);
            } catch (Throwable $e) {
                return new Response(400, ['Content-Type' => 'text/plain'], 'Invalid request');
            }

            try {
                $message = new UpdateConfigMessage($projectionName, $config);
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
