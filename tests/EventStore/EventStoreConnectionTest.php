<?php

declare(strict_types=1);

namespace ProophTest\EventStore;

use PHPUnit\Exception;
use PHPUnit\Framework\TestCase;
use Prooph\EventStore\Data\EventData;
use Prooph\EventStore\Data\EventId;
use Prooph\EventStore\Data\ExpectedVersion;
use Prooph\EventStore\EventStoreConnection;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Exception\InvalidArgumentException;
use Prooph\PdoEventStore\PdoEventStoreConnection;

abstract class EventStoreConnectionTest extends TestCase
{
    public function setUp()
    {
        $this->cleanEventStore();
    }

    /** @test */
    public function it_connects(): void
    {
        $connection = $this->getEventStoreConnection();
        $connection->connect();

        try {
            $this->assertAttributeInstanceOf(\PDO::class, 'connection', $connection);
        } catch (Exception $e) {
            if ('Attribute "connection" not found in object.' === $e->getMessage()) {
                $this->markTestSkipped();
            }
            throw $e;
        }
    }

    /** @test */
    public function it_closes(): void
    {
        $connection = $this->getEventStoreConnection();
        $connection->connect();
        $connection->close();

        try {
            $this->assertAttributeSame(null, 'connection', $connection);
        } catch (Exception $e) {
            if ('Attribute "connection" not found in object.' === $e->getMessage()) {
                $this->markTestSkipped();
            }
            throw $e;
        }
    }

    /** @test */
    public function it_deletes_stream(): void
    {
        $connection = $this->getEventStoreConnection();
        if (! $connection instanceof PdoEventStoreConnection) {
            $this->markTestSkipped(\sprintf('The "%s" implementation is not tested yet.', \get_class($connection)));
        }

        $connection->connect();
        $connection->appendToStream('test_stream', ExpectedVersion::NoStream, [$this->getEvent()]);
        $connection->deleteStream('test_stream', false);

        $stream = $this->getStream('test_stream');
        $this->assertEquals(true, $stream['mark_deleted']);
        $this->assertEquals(false, $stream['deleted']);
        $this->assertEquals(1, \count($stream['events']));
    }

    /** @test */
    public function it_hard_deletes_stream(): void
    {
        $connection = $this->getEventStoreConnection();
        if (! $connection instanceof PdoEventStoreConnection) {
            $this->markTestSkipped(\sprintf('The "%s" implementation is not tested yet.', \get_class($connection)));
        }

        $connection->connect();
        $connection->appendToStream('test_stream', ExpectedVersion::NoStream, [$this->getEvent()]);
        $connection->deleteStream('test_stream', true);

        $stream = $this->getStream('test_stream');
        $this->assertEquals(false, $stream['mark_deleted']);
        $this->assertEquals(true, $stream['deleted']);
        $this->assertEquals(0, \count($stream['events']));
    }

    /** @test */
    public function it_throws_trying_to_delete_stream_with_empty_stream_name(): void
    {
        $this->expectException(InvalidArgumentException::class);
    }

    /** @test */
    public function it_throws_trying_to_delete_stream_without_permission(): void
    {
        $this->expectException(AccessDenied::class);
    }

    /** @test */
    public function it_appends_to_stream(): void
    {
    }

    /** @test */
    public function it_throws_trying_to_append_to_stream_with_empty_stream_name(): void
    {
        $this->expectException(InvalidArgumentException::class);
    }

    /** @test */
    public function it_throws_trying_to_append_to_stream_without_permission(): void
    {
        $this->expectException(AccessDenied::class);
    }

    /** @test */
    public function it_reads_event(): void
    {
    }

    /** @test */
    public function it_throws_trying_to_read_event_with_empty_stream_name(): void
    {
        $this->expectException(InvalidArgumentException::class);
    }

    /** @test */
    public function it_throws_trying_to_read_event_without_permission(): void
    {
        $this->expectException(AccessDenied::class);
    }

    /** @test */
    public function it_reads_stream_events_forward(): void
    {
    }

    /** @test */
    public function it_throws_trying_to_read_stream_events_forward_with_empty_stream_name(): void
    {
        $this->expectException(InvalidArgumentException::class);
    }

    /** @test */
    public function it_throws_trying_to_read_stream_events_forward_with_start_smaller_zero(): void
    {
        $this->expectException(InvalidArgumentException::class);
    }

    /** @test */
    public function it_throws_trying_to_read_stream_events_forward_with_count_smaller_zero(): void
    {
        $this->expectException(InvalidArgumentException::class);
    }

    /** @test */
    public function it_throws_trying_to_read_stream_events_forward_with_count_bigger_max_read_size(): void
    {
        $this->expectException(InvalidArgumentException::class);
    }

    /** @test */
    public function it_throws_trying_to_read_stream_events_forward_without_permission(): void
    {
        $this->expectException(AccessDenied::class);
    }

    /** @test */
    public function it_reads_stream_events_backward(): void
    {
    }

    /** @test */
    public function it_throws_trying_to_read_stream_events_backward_with_empty_stream_name(): void
    {
        $this->expectException(InvalidArgumentException::class);
    }

    /** @test */
    public function it_throws_trying_to_read_stream_events_backward_with_start_smaller_zero(): void
    {
        $this->expectException(InvalidArgumentException::class);
    }

    /** @test */
    public function it_throws_trying_to_read_stream_events_backward_with_count_smaller_zero(): void
    {
        $this->expectException(InvalidArgumentException::class);
    }

    /** @test */
    public function it_throws_trying_to_read_stream_events_backward_with_count_bigger_max_read_size(): void
    {
        $this->expectException(InvalidArgumentException::class);
    }

    /** @test */
    public function it_throws_trying_to_read_stream_events_backward_without_permission(): void
    {
        $this->expectException(AccessDenied::class);
    }

    /** @test */
    public function it_sets_stream_metadata(): void
    {
    }

    /** @test */
    public function it_throws_trying_to_set_stream_metadata_with_empty_stream_name(): void
    {
        $this->expectException(InvalidArgumentException::class);
    }

    /** @test */
    public function it_throws_trying_to_set_stream_metadata_with_given_meta_stream(): void
    {
        $this->expectException(InvalidArgumentException::class);
    }

    /** @test */
    public function it_throws_trying_to_set_stream_metadata_without_permission(): void
    {
        $this->expectException(AccessDenied::class);
    }

    /** @test */
    public function it_gets_stream_metadata(): void
    {
    }

    /** @test */
    public function it_gets_stream_metadata_for_unknown_streams(): void
    {
    }

    /** @test */
    public function it_gets_stream_metadata_for_deleted_streams(): void
    {
    }

    /** @test */
    public function it_throws_trying_to_get_stream_metadata_with_empty_stream_name(): void
    {
        $this->expectException(InvalidArgumentException::class);
    }

    /** @test */
    public function it_throws_trying_to_get_stream_metadata_without_permission(): void
    {
        $this->expectException(AccessDenied::class);
    }

    /** @test */
    public function it_sets_system_settings(): void
    {
    }

    /** @test */
    public function it_throws_trying_to_set_system_settings_without_permission(): void
    {
        $this->expectException(AccessDenied::class);
    }

    abstract protected function getEventStoreConnection(): EventStoreConnection;

    abstract protected function cleanEventStore(): void;

    abstract protected function getStream(string $name): array;

    private function getEvent(): EventData
    {
        return new EventData(
            EventId::generate(),
            'userCreated',
            true,
            \json_encode(['user' => 'Sacha Prlc', 'email' => 'saschaprolic@googlemail.com']),
            ''
        );
    }
}
