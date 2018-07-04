<?php

declare(strict_types=1);
// Generated by the protocol buffer compiler.  DO NOT EDIT!
// source: ClientMessageDtos.proto

namespace Prooph\EventStoreClient\Data;

use Google\Protobuf\Internal\GPBUtil;

/**
 * Generated from protobuf message <code>Prooph.EventStoreClient.Data.NotHandled</code>
 */
class NotHandled extends \Google\Protobuf\Internal\Message
{
    /**
     * Generated from protobuf field <code>.Prooph.EventStoreClient.Data.NotHandled.NotHandledReason reason = 1;</code>
     */
    private $reason = 0;
    /**
     * Generated from protobuf field <code>bytes additional_info = 2;</code>
     */
    private $additional_info = '';

    public function __construct()
    {
        \GPBMetadata\ClientMessageDtos::initOnce();
        parent::__construct();
    }

    /**
     * Generated from protobuf field <code>.Prooph.EventStoreClient.Data.NotHandled.NotHandledReason reason = 1;</code>
     * @return int
     */
    public function getReason()
    {
        return $this->reason;
    }

    /**
     * Generated from protobuf field <code>.Prooph.EventStoreClient.Data.NotHandled.NotHandledReason reason = 1;</code>
     * @param int $var
     * @return $this
     */
    public function setReason($var)
    {
        GPBUtil::checkEnum($var, \Prooph\EventStoreClient\Data\NotHandled_NotHandledReason::class);
        $this->reason = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>bytes additional_info = 2;</code>
     * @return string
     */
    public function getAdditionalInfo()
    {
        return $this->additional_info;
    }

    /**
     * Generated from protobuf field <code>bytes additional_info = 2;</code>
     * @param string $var
     * @return $this
     */
    public function setAdditionalInfo($var)
    {
        GPBUtil::checkString($var, false);
        $this->additional_info = $var;

        return $this;
    }
}
