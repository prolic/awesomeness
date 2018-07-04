<?php

declare(strict_types=1);
// Generated by the protocol buffer compiler.  DO NOT EDIT!
// source: ClientMessageDtos.proto

namespace Prooph\EventStoreClient\Data;

use Google\Protobuf\Internal\GPBUtil;

/**
 * Generated from protobuf message <code>Prooph.EventStoreClient.Data.ReadStreamEvents</code>
 */
class ReadStreamEvents extends \Google\Protobuf\Internal\Message
{
    /**
     * Generated from protobuf field <code>string event_stream_id = 1;</code>
     */
    private $event_stream_id = '';
    /**
     * Generated from protobuf field <code>int64 from_event_number = 2;</code>
     */
    private $from_event_number = 0;
    /**
     * Generated from protobuf field <code>int32 max_count = 3;</code>
     */
    private $max_count = 0;
    /**
     * Generated from protobuf field <code>bool resolve_link_tos = 4;</code>
     */
    private $resolve_link_tos = false;
    /**
     * Generated from protobuf field <code>bool require_master = 5;</code>
     */
    private $require_master = false;

    public function __construct()
    {
        \GPBMetadata\ClientMessageDtos::initOnce();
        parent::__construct();
    }

    /**
     * Generated from protobuf field <code>string event_stream_id = 1;</code>
     * @return string
     */
    public function getEventStreamId()
    {
        return $this->event_stream_id;
    }

    /**
     * Generated from protobuf field <code>string event_stream_id = 1;</code>
     * @param string $var
     * @return $this
     */
    public function setEventStreamId($var)
    {
        GPBUtil::checkString($var, true);
        $this->event_stream_id = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>int64 from_event_number = 2;</code>
     * @return int|string
     */
    public function getFromEventNumber()
    {
        return $this->from_event_number;
    }

    /**
     * Generated from protobuf field <code>int64 from_event_number = 2;</code>
     * @param int|string $var
     * @return $this
     */
    public function setFromEventNumber($var)
    {
        GPBUtil::checkInt64($var);
        $this->from_event_number = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>int32 max_count = 3;</code>
     * @return int
     */
    public function getMaxCount()
    {
        return $this->max_count;
    }

    /**
     * Generated from protobuf field <code>int32 max_count = 3;</code>
     * @param int $var
     * @return $this
     */
    public function setMaxCount($var)
    {
        GPBUtil::checkInt32($var);
        $this->max_count = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>bool resolve_link_tos = 4;</code>
     * @return bool
     */
    public function getResolveLinkTos()
    {
        return $this->resolve_link_tos;
    }

    /**
     * Generated from protobuf field <code>bool resolve_link_tos = 4;</code>
     * @param bool $var
     * @return $this
     */
    public function setResolveLinkTos($var)
    {
        GPBUtil::checkBool($var);
        $this->resolve_link_tos = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>bool require_master = 5;</code>
     * @return bool
     */
    public function getRequireMaster()
    {
        return $this->require_master;
    }

    /**
     * Generated from protobuf field <code>bool require_master = 5;</code>
     * @param bool $var
     * @return $this
     */
    public function setRequireMaster($var)
    {
        GPBUtil::checkBool($var);
        $this->require_master = $var;

        return $this;
    }
}
