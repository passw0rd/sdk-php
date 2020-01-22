<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: purekit.proto

namespace Purekit;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 * Generated from protobuf message <code>purekit.VerifyPasswordRequest</code>
 */
class VerifyPasswordRequest extends \Google\Protobuf\Internal\Message
{
    /**
     * Generated from protobuf field <code>uint32 version = 1;</code>
     */
    private $version = 0;
    /**
     * Generated from protobuf field <code>bytes request = 2;</code>
     */
    private $request = '';

    /**
     * Constructor.
     *
     * @param array $data {
     *     Optional. Data for populating the Message object.
     *
     *     @type int $version
     *     @type string $request
     * }
     */
    public function __construct($data = NULL) {
        \GPBMetadata\Purekit::initOnce();
        parent::__construct($data);
    }

    /**
     * Generated from protobuf field <code>uint32 version = 1;</code>
     * @return int
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * Generated from protobuf field <code>uint32 version = 1;</code>
     * @param int $var
     * @return $this
     */
    public function setVersion($var)
    {
        GPBUtil::checkUint32($var);
        $this->version = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>bytes request = 2;</code>
     * @return string
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Generated from protobuf field <code>bytes request = 2;</code>
     * @param string $var
     * @return $this
     */
    public function setRequest($var)
    {
        GPBUtil::checkString($var, False);
        $this->request = $var;

        return $this;
    }

}
