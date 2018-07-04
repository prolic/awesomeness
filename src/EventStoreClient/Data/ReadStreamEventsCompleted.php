<?php

declare(strict_types=1);
// Generated by the protocol buffer compiler.  DO NOT EDIT!
// source: ClientMessageDtos.proto

namespace Prooph\EventStoreClient\Data;

use Google\Protobuf\Internal\GPBUtil;

/**
 * Generated from protobuf message <code>Prooph.EventStoreClient.Data.ReadStreamEventsCompleted</code>
 */
class ReadStreamEventsCompleted extends \Google\Protobuf\Internal\Message
{
    /**
     * Generated from protobuf field <code>repeated .Prooph.EventStoreClient.Data.ResolvedIndexedEvent events = 1;</code>
     */
    private $events;
    /**
     * Generated from protobuf field <code>.Prooph.EventStoreClient.Data.ReadStreamEventsCompleted.ReadStreamResult result = 2;</code>
     */
    private $result = 0;
    /**
     * Generated from protobuf field <code>int64 next_event_number = 3;</code>
     */
    private $next_event_number = 0;
    /**
     * Generated from protobuf field <code>int64 last_event_number = 4;</code>
     */
    private $last_event_number = 0;
    /**
     * Generated from protobuf field <code>bool is_end_of_stream = 5;</code>
     */
    private $is_end_of_stream = false;
    /**
     * Generated from protobuf field <code>int64 last_commit_position = 6;</code>
     */
    private $last_commit_position = 0;
    /**
     * Generated from protobuf field <code>string error = 7;</code>
     */
    private $error = '';

    public function __construct()
    {
        \GPBMetadata\ClientMessageDtos::initOnce();
        parent::__construct();
    }

    /**
     * Generated from protobuf field <code>repeated .Prooph.EventStoreClient.Data.ResolvedIndexedEvent events = 1;</code>
     * @return \Google\Protobuf\Internal\RepeatedField
     */
    public function getEvents()
    {
        return $this->events;
    }

    /**
     * Generated from protobuf field <code>repeated .Prooph.EventStoreClient.Data.ResolvedIndexedEvent events = 1;</code>
     * @param \Prooph\EventStoreClient\Data\ResolvedIndexedEvent[]|\Google\Protobuf\Internal\RepeatedField $var
     * @return $this
     */
    public function setEvents($var)
    {
        $arr = GPBUtil::checkRepeatedField($var, \Google\Protobuf\Internal\GPBType::MESSAGE, \Prooph\EventStoreClient\Data\ResolvedIndexedEvent::class);
        $this->events = $arr;

        return $this;
    }

    /**
     * Generated from protobuf field <code>.Prooph.EventStoreClient.Data.ReadStreamEventsCompleted.ReadStreamResult result = 2;</code>
     * @return int
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * Generated from protobuf field <code>.Prooph.EventStoreClient.Data.ReadStreamEventsCompleted.ReadStreamResult result = 2;</code>
     * @param int $var
     * @return $this
     */
    public function setResult($var)
    {
        GPBUtil::checkEnum($var, \Prooph\EventStoreClient\Data\ReadStreamEventsCompleted_ReadStreamResult::class);
        $this->result = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>int64 next_event_number = 3;</code>
     * @return int|string
     */
    public function getNextEventNumber()
    {
        return $this->next_event_number;
    }

    /**
     * Generated from protobuf field <code>int64 next_event_number = 3;</code>
     * @param int|string $var
     * @return $this
     */
    public function setNextEventNumber($var)
    {
        GPBUtil::checkInt64($var);
        $this->next_event_number = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>int64 last_event_number = 4;</code>
     * @return int|string
     */
    public function getLastEventNumber()
    {
        return $this->last_event_number;
    }

    /**
     * Generated from protobuf field <code>int64 last_event_number = 4;</code>
     * @param int|string $var
     * @return $this
     */
    public function setLastEventNumber($var)
    {
        GPBUtil::checkInt64($var);
        $this->last_event_number = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>bool is_end_of_stream = 5;</code>
     * @return bool
     */
    public function getIsEndOfStream()
    {
        return $this->is_end_of_stream;
    }

    /**
     * Generated from protobuf field <code>bool is_end_of_stream = 5;</code>
     * @param bool $var
     * @return $this
     */
    public function setIsEndOfStream($var)
    {
        GPBUtil::checkBool($var);
        $this->is_end_of_stream = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>int64 last_commit_position = 6;</code>
     * @return int|string
     */
    public function getLastCommitPosition()
    {
        return $this->last_commit_position;
    }

    /**
     * Generated from protobuf field <code>int64 last_commit_position = 6;</code>
     * @param int|string $var
     * @return $this
     */
    public function setLastCommitPosition($var)
    {
        GPBUtil::checkInt64($var);
        $this->last_commit_position = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>string error = 7;</code>
     * @return string
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * Generated from protobuf field <code>string error = 7;</code>
     * @param string $var
     * @return $this
     */
    public function setError($var)
    {
        GPBUtil::checkString($var, true);
        $this->error = $var;

        return $this;
    }
}
