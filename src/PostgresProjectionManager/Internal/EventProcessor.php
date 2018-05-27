<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager\Internal;

use Prooph\EventStore\RecordedEvent;

class EventProcessor
{
    /** @var callable */
    private $notify;
    /** @var callable[] */
    private $eventHandlers = [];
    /** @var callable|null */
    private $anyEventHandler;
    /** @var callable[] */
    private $transformers = [];
    /** @var callable|null */
    private $catalogEventTransformer;
    /** @var array */
    private $sources = [
        'allStreams' => false,
        'allEvents' => true,
        'byStreams' => false,
        'categories' => [],
        'streams' => [],
        'catalogStream' => null,
        'events' => [],
        'options' => [
            'definesStateTransform' => false,
            'definesCatalogTransform' => false,
            'producesResults' => false,
            'definesFold' => false,
            'resultStreamName' => null,
        ],
        'version' => 1,
    ];
    /** @var callable */
    private $initStateHandler;
    /** @var array */
    private $projectionState;

    public function __construct(callable $notify)
    {
        $this->notify = $notify;

        $this->initStateHandler = function () {
            return [];
        };
    }

    public function definesStateTransform(): void
    {
        $this->sources['options']['definesStateTransform'] = true;
        $this->sources['options']['producesResults'] = true;
    }

    public function options(array $options): void
    {
        foreach ($options as $key => $value) {
            if (! array_key_exists($key, $this->sources['options'])) {
                throw new \InvalidArgumentException('key ' . $key . ' does not exist in options');
            }

            $this->sources['options'][$key] = $value;
        }
    }

    public function onEvent(string $eventName, callable $eventHandler): void
    {
        $this->eventHandlers[$eventName] = $eventHandler;
        $this->sources['allEvents'] = false;
        $this->sources['events'][] = $eventName;
        $this->sources['options']['definesFold'] = true;
    }

    public function onInitState(callable $initHandler): void
    {
        $this->initStateHandler = $initHandler;
        $this->sources['options']['definesFold'] = true;
    }

    public function onAny(callable $eventHandler): void
    {
        $this->sources['allEvents'] = true;
        $this->anyEventHandler = $eventHandler;
        $this->sources['options']['definesFold'] = true;
    }

    public function callHandler(callable $handler, array $state, RecordedEvent $event): array
    {
        $newState = $handler($state, $event);

        if (! is_array($newState)) {
            $newState = $state;
        }

        return $newState;
    }

    public function processEvent(RecordedEvent $event): void
    {
        $state = $this->projectionState;

        if (null !== $this->anyEventHandler) {
            $state = $this->callHandler($this->anyEventHandler, $state, $event);
        }

        if (isset($this->eventHandlers[$event->eventType()])) {
            $state = $this->callHandler($this->eventHandlers[$event->eventType()], $state, $event);
        }

        $this->projectionState = $state;
    }

    public function fromStream(string $sourceStream): void
    {
        $this->sources['streams'][] = $sourceStream;
    }

    public function fromCategory(string $sourceCategory): void
    {
        $this->sources['categories'][] = $sourceCategory;
    }

    public function fromStreamsMatching(callable $filter): void
    {
        $this->sources['catalogStream'] = '$all';
        $this->sources['options']['definesCatalogTransform'] = true;
        $this->catalogEventTransformer = function (string $streamId, RecordedEvent $event) use (&$filter) {
            return $filter($streamId, $event) ? $streamId : null;
        };
        $this->byStream();
    }

    public function byStream(): void
    {
        $this->sources['byStreams'] = true;
    }

    public function outputState(): void
    {
        $this->sources['options']['producesResults'] = true;
    }

    public function chainTransformBy(callable $by): void
    {
        $this->transformers[] = $by;
        $this->sources['options']['definesStateTransform'] = true;
        $this->sources['options']['producesResults'] = true;
    }

    public function fromAll(): void
    {
        $this->sources['allStreams'] = true;
    }

    public function emit(string $streamName, string $eventType, string $data, string $metadata = '', bool $isJson = false): void
    {
        $handler = $this->notify;
        $handler($streamName, $eventType, $data, $metadata, $isJson);
    }
}
