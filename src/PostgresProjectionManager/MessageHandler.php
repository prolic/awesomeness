<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager;

use Amp\Loop;
use Amp\Parallel\Sync\Channel;
use Generator;
use Prooph\PostgresProjectionManager\Messages\DeleteMessage;
use Prooph\PostgresProjectionManager\Messages\DisableMessage;
use Prooph\PostgresProjectionManager\Messages\EnableMessage;
use Prooph\PostgresProjectionManager\Messages\GetConfigMessage;
use Prooph\PostgresProjectionManager\Messages\GetDefinitionMessage;
use Prooph\PostgresProjectionManager\Messages\GetStateMessage;
use Prooph\PostgresProjectionManager\Messages\GetStatisticsMessage;
use Prooph\PostgresProjectionManager\Messages\ResetMessage;
use Prooph\PostgresProjectionManager\Messages\Response;
use Psr\Log\LoggerInterface;

class MessageHandler
{
    /** @var Channel */
    private $channel;
    /** @var ProjectionRunner */
    private $projectionRunner;
    /** @var LoggerInterface */
    private $logger;

    public function __construct(Channel $channel, ProjectionRunner $projectionRunner, LoggerInterface $logger)
    {
        $this->channel = $channel;
        $this->projectionRunner = $projectionRunner;
        $this->logger = $logger;
    }

    public function __invoke(): Generator
    {
        $message = yield $this->channel->receive();

        switch (true) {
            case $message instanceof GetConfigMessage:
                $config = $this->projectionRunner->getConfig();
                yield $this->channel->send(new Response($config));
                break;
            case $message instanceof DisableMessage:
                yield $this->projectionRunner->disable();
                yield $this->channel->send(new Response());
                break;
            case $message instanceof EnableMessage:
                yield $this->projectionRunner->enable($message->enableRunAs());
                yield $this->channel->send(new Response());
                break;
            case $message instanceof GetDefinitionMessage:
                $definition = $this->projectionRunner->getDefinition();
                yield $this->channel->send(new Response($definition));
                break;
            case $message instanceof ResetMessage:
                $this->projectionRunner->reset($message->enableRunAs());
                yield $this->channel->send(new Response());
                break;
            case $message instanceof GetStateMessage:
                $state = $this->projectionRunner->getState();
                yield $this->channel->send(new Response($state));
                break;
            case $message instanceof GetStatisticsMessage:
                $stats = yield $this->projectionRunner->getStatistics();
                yield $this->channel->send(new Response($stats));
                break;
            case $message instanceof DeleteMessage:
                $this->projectionRunner->delete(
                    $message->deleteStateStream(),
                    $message->deleteCheckpointStream(),
                    $message->deleteEmittedStreams()
                );
                yield $this->channel->send(new Response());
                break;
            default:
                $this->logger->error('Received invalid message: ' . \serialize($message));
                break;
        }

        Loop::defer($this);
    }
}
