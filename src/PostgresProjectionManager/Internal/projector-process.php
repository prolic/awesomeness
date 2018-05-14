<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager\Internal;

use Amp\Log\ConsoleFormatter;
use Amp\Loop;
use Amp\Parallel\Sync\Channel;
use Amp\Postgres\Pool;
use Generator;
use Monolog\Handler\SyslogHandler;
use Monolog\Logger;
use Prooph\EventStore\Exception\RuntimeException;

return function (Channel $channel): Generator {
    Loop::run(function () use ($channel) {
        [
            'prooph_connection_string' => $connectionString,
            'prooph_projection_id' => $projectionId,
            'prooph_projection_name' => $projectionName,
            'prooph_log_level' => $logLevel,
        ] = getenv();

        $pool = new Pool($connectionString);

        $logHandler = new SyslogHandler('myfacility', 'local6');
        $logHandler->setFormatter(new ConsoleFormatter());
        $logHandler->setLevel(Logger::class . '::' . $logLevel);

        $logger = new Logger('PROJECTOR-' . $projectionName);
        $logger->pushHandler($logHandler);

        $projector = new Projector($pool, $projectionName, $projectionId, $logger);
        try {
            $logger->debug('constructed');
            while (true) {
                $operation = yield $channel->receive();

                switch ($operation) {
            case 'tryStart':
                $logger->debug('try starting...');
                yield $projector->tryStart();
                $logger->debug('try started');
                break;
            case 'enable':
                $logger->debug('starting...');
                yield $projector->start();
                $logger->debug('started');
                break;
            case 'disable':
                $logger->debug('stopping...');
                yield $projector->stop();
                $logger->debug('stopped');
                break;
            default:
                throw new RuntimeException('Invalid operation passed to projector');
        }
            }
        } catch (\Throwable $e) {
            $logger->error($e->getMessage());
        }
    });
};
