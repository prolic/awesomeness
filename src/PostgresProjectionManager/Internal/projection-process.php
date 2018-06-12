<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager\Internal;

use Amp\Log\ConsoleFormatter;
use Amp\Loop;
use Amp\Parallel\Sync;
use Amp\Postgres\Pool;
use Monolog\Logger;
use Prooph\EventStore\Exception\RuntimeException;
use Throwable;
use const PHP_EOL;
use const PHP_OUTPUT_HANDLER_CLEANABLE;
use const PHP_OUTPUT_HANDLER_FLUSHABLE;
use const SIGINT;
use const SIGTERM;
use const STDERR;
use const STDIN;
use const STDOUT;

// Doesn't exist in phpdbg...
if (\function_exists('cli_set_process_title')) {
    @\cli_set_process_title('projection-process');
}

// Redirect all output written using echo, print, printf, etc. to STDERR.
\ob_start(function ($data) {
    \fwrite(STDERR, $data);

    return '';
}, 1, PHP_OUTPUT_HANDLER_CLEANABLE | PHP_OUTPUT_HANDLER_FLUSHABLE);

(function () {
    $path = \dirname(__DIR__, 3) . '/vendor/autoload.php';

    if (! \file_exists($path)) {
        \fwrite(STDERR, 'Could not locate autoload.php at file: ' . $path . PHP_EOL);
    }

    require $path;
})();

Loop::run(function () use ($argc, $argv) {
    $channel = new Sync\ChannelledSocket(STDIN, STDOUT);

    try {
        [
            'prooph_connection_string' => $connectionString,
            'prooph_projection_id' => $projectionId,
            'prooph_projection_name' => $projectionName,
            'prooph_log_level' => $logLevel,
        ] = \getenv();

        $pool = new Pool($connectionString);

        $logHandler = new EchoHandler();
        $logHandler->setFormatter(new ConsoleFormatter());
        $logHandler->setLevel($logLevel);

        $logger = new Logger('PROJECTOR-' . $projectionName . ' - ' . \posix_getpid());
        $logger->pushHandler($logHandler);

        $projectionRunner = new ProjectionRunner($pool, $projectionName, $projectionId, $logger);
        yield $projectionRunner->bootstrap();

        $shutdown = function (string $watcherId) {
            // do nothing, we wait for shutdown command from projection manager
        };

        Loop::unreference(Loop::onSignal(SIGINT, $shutdown));
        Loop::unreference(Loop::onSignal(SIGTERM, $shutdown));

        while (true) {
            $operation = yield $channel->receive();

            switch ($operation) {
                case 'enable':
                    $logger->info('enabling projection');
                    yield $projectionRunner->enable();
                    break;
                case 'disable':
                    $logger->info('disabling projection');
                    yield $projectionRunner->disable();
                    break;
                case 'state':
                    $state = $projectionRunner->getState();
                    yield $channel->send($state);
                    break;
                case 'shutdown':
                    yield $projectionRunner->shutdown();
                    break 2; // break the loop
                default:
                    throw new RuntimeException('Invalid operation passed to projector');
            }
        }

        $result = new Sync\ExitSuccess(0);
    } catch (Sync\ChannelException $exception) {
        exit(1); // Parent context died, simply exit.
    } catch (Throwable $exception) {
        $logger->err($exception->getMessage());
        $result = new Sync\ExitFailure($exception);
    }

    try {
        try {
            yield $channel->send($result);
        } catch (Sync\SerializationException $exception) {
            // Serializing the result failed. Send the reason why.
            yield $channel->send(new Sync\ExitFailure($exception));
        }
    } catch (Throwable $exception) {
        exit(1); // Parent context died, simply exit.
    }
});
