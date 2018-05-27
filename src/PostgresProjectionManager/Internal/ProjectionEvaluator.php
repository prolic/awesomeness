<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager\Internal;

use Prooph\EventStore\RecordedEvent;
use Prooph\PostgresProjectionManager\Internal\Exception\QueryEvaluationError;
use Throwable;
use const JSON_ERROR_NONE;
use function array_map;
use function json_decode;
use function json_encode;
use function json_last_error;

/** @internal */
class ProjectionEvaluator
{
    /** @var EventProcessor */
    private $eventProcessor;

    public function __construct(callable $notify)
    {
        $this->eventProcessor = new EventProcessor($notify);
    }

    public function evaluate(string $query): EventProcessor
    {
        $scope = new class($this) {
            private $context;

            public function __construct(ProjectionEvaluator $context)
            {
                $this->context = $context;
            }

            public function options(array $options): object
            {
                return $this->context->options($options);
            }

            public function fromAll(): object
            {
                return $this->context->fromAll();
            }

            public function fromCategory(string $category): object
            {
                return $this->context->fromCategory($category);
            }

            public function fromStream(string $streamName): object
            {
                return $this->context->fromStream($streamName);
            }

            public function fromStreams(string ...$streamNames): object
            {
                return $this->context->fromStreams(...$streamNames);
            }

            public function fromCategories(string ...$categories): object
            {
                return $this->context->fromCategories(...$categories);
            }

            public function fromStreamsMatching(callable $filter): object
            {
                return $this->context->fromStreamsMatching($filter);
            }
        };

        try {
            eval('$scope->' . $query);
        } catch (Throwable $e) {
            throw QueryEvaluationError::with($e->getMessage());
        }

        return $this->eventProcessor;
    }

    public function transformBy(callable $by): object
    {
        $this->eventProcessor->chainTransformBy($by);

        return new class($this) {
            private $context;

            public function __construct(ProjectionEvaluator $context)
            {
                $this->context = $context;
            }

            public function transformBy(callable $by): object
            {
                return $this->context->transformBy($by);
            }

            public function filterBy(callable $by): object
            {
                return $this->context->filterBy($by);
            }

            public function outputState(): object
            {
                return $this->context->outputState();
            }

            public function outputTo(string $target): void
            {
                $this->context->outputTo($target);
            }
        };
    }

    public function filterBy(callable $by): object
    {
        $this->eventProcessor->chainTransformBy(function ($s) use ($by) {
            $result = $by($s);

            return $result ? $s : null;
        });

        return new class($this) {
            private $context;

            public function __construct(ProjectionEvaluator $context)
            {
                $this->context = $context;
            }

            public function transformBy(callable $by): object
            {
                return $this->context->transformBy($by);
            }

            public function filterBy(callable $by): object
            {
                return $this->context->filterBy($by);
            }

            public function outputState(): object
            {
                return $this->context->outputState();
            }

            public function outputTo(string $target): void
            {
                $this->context->outputTo($target);
            }
        };
    }

    public function definesStateTransform(): void
    {
        $this->eventProcessor->definesStateTransform();
    }

    public function outputTo(string $resultStream): void
    {
        $this->eventProcessor->definesStateTransform();
        $this->eventProcessor->options([
            'resultStreamName' => $resultStream,
        ]);
    }

    public function outputState(): object
    {
        $this->eventProcessor->outputState();

        return new class($this) {
            private $context;

            public function __construct(ProjectionEvaluator $context)
            {
                $this->context = $context;
            }

            public function transformBy(callable $by): object
            {
                return $this->context->transformBy($by);
            }

            public function filterBy(callable $by): object
            {
                return $this->context->filterBy($by);
            }

            public function outputTo(string $target): void
            {
                $this->context->outputTo($target);
            }
        };
    }

    public function when(array $handlers): object
    {
        foreach ($handlers as $name => $handler) {
            if ($name === '$init') {
                $this->eventProcessor->onInitState($handler);
            } elseif ($name === '$any') {
                $this->eventProcessor->onAny($handler);
            } else {
                $this->eventProcessor->onEvent($name, $handler);
            }
        }

        return new class($this) {
            private $context;

            public function __construct(ProjectionEvaluator $context)
            {
                $this->context = $context;
            }

            public function definesStateTransform(): object
            {
                $this->context->definesStateTransform();
            }

            public function transformBy(callable $by): object
            {
                return $this->context->transformBy($by);
            }

            public function filterBy(callable $by): object
            {
                return $this->context->filterBy($by);
            }

            public function outputTo(string $target): void
            {
                $this->context->outputTo($target);
            }
        };
    }

    public function fromStream(string $stream)
    {
        $this->eventProcessor->fromStream($stream);

        return new class($this) {
            private $context;

            public function __construct(ProjectionEvaluator $context)
            {
                $this->context = $context;
            }

            public function when(array $handlers): object
            {
                return $this->context->when($handlers);
            }

            public function outputState(): object
            {
                return $this->context->outputState();
            }
        };
    }

    public function fromCategory(string $category)
    {
        $this->eventProcessor->fromCategory($category);

        return new class($this) {
            private $context;

            public function __construct(ProjectionEvaluator $context)
            {
                $this->context = $context;
            }

            public function when(array $handlers): object
            {
                return $this->context->when($handlers);
            }

            public function outputState(): object
            {
                return $this->context->outputState();
            }
        };
    }

    public function fromAll(): object
    {
        $this->eventProcessor->fromAll();

        return new class($this) {
            private $context;

            public function __construct(ProjectionEvaluator $context)
            {
                $this->context = $context;
            }

            public function when(array $handlers): object
            {
                return $this->context->when($handlers);
            }

            public function outputState(): object
            {
                return $this->context->outputState();
            }
        };
    }

    public function fromStreamsMatching(callable $filter)
    {
        $this->eventProcessor->fromStreamsMatching($filter);

        return new class($this) {
            private $context;

            public function __construct(ProjectionEvaluator $context)
            {
                $this->context = $context;
            }

            public function when(array $handlers): object
            {
                return $this->context->when($handlers);
            }
        };
    }

    public function fromStreams(string ...$streams): object
    {
        foreach ($streams as $stream) {
            $this->eventProcessor->fromStream($stream);
        }

        return new class($this) {
            private $context;

            public function __construct(ProjectionEvaluator $context)
            {
                $this->context = $context;
            }

            public function when(array $handlers): object
            {
                return $this->context->when($handlers);
            }

            public function outputState(): object
            {
                return $this->context->outputState();
            }
        };
    }

    public function fromCategories(string ...$categories): object
    {
        $categories = array_map(function ($category): string {
            return '$ce-' . $category;
        }, $categories);

        return $this->fromStreams(...$categories);
    }

    public function emit(string $streamName, string $eventType, string $data, string $metadata = '', bool $isJson = false): void
    {
        $this->eventProcessor->emit($streamName, $eventType, $data, $metadata, false);
    }

    public function linkTo(string $streamName, RecordedEvent $event, string $metadata = ''): void
    {
        $this->eventProcessor->emit($streamName, '$>', $event->eventNumber() . '@' . $event->streamId(), $metadata, false);
    }

    public function copyTo(string $streamName, RecordedEvent $event, string $metadata = ''): void
    {
        $newMetadata = [];
        $emRaw = $event->metaData();

        if ($emRaw) {
            $em = json_decode($emRaw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                foreach ($em as $key => $value) {
                    if (substr($key, 0, 1) !== '$' || $key === '$correlationId') {
                        $m[$key] = $value;
                    }
                }
            }
        }

        if ($metadata) {
            $em = json_decode($metadata, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                foreach ($em as $key => $value) {
                    if (substr($key, 0, 1) !== '$') {
                        $m[$key] = $value;
                    }
                }
            }
        }

        $this->eventProcessor->emit($streamName, $event->eventType(), $event->data(), json_encode($newMetadata), false);
    }

    public function linkStreamTo(string $streamName, string $linkedStreamName, string $metadata = ''): void
    {
        $this->eventProcessor->emit($streamName, '$@', $linkedStreamName, $metadata, false);
    }

    public function options(array $options): object
    {
        $this->eventProcessor->options($options);

        return new class($this) {
            private $context;

            public function __construct(ProjectionEvaluator $context)
            {
                $this->context = $context;
            }

            public function fromAll(): object
            {
                return $this->context->fromAll();
            }

            public function fromCategory(string $category): object
            {
                return $this->context->fromCategory($category);
            }

            public function fromStream(string $streamName): object
            {
                return $this->context->fromStream($streamName);
            }

            public function fromStreams(string ...$streamNames): object
            {
                return $this->context->fromStreams(...$streamNames);
            }

            public function fromCategories(string ...$categories): object
            {
                return $this->context->fromCategories(...$categories);
            }

            public function fromStreamsMatching(callable $filter): object
            {
                return $this->context->fromStreamsMatching($filter);
            }
        };
    }
}
