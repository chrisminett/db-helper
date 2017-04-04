<?php

namespace Phlib\DbHelper\Tests\Replication;

use Phlib\DbHelper\Replication\StorageInterface;

/**
 * @package Phlib\Db
 * @licence LGPL-3.0
 */
class StorageMock implements StorageInterface
{
    /**
     * @inheritdoc
     */
    public static function createFromConfig(array $config)
    {
        return new static;
    }

    /**
     * @inheritdoc
     */
    public function getSecondsBehind($host)
    {
    }

    /**
     * @inheritdoc
     */
    public function setSecondsBehind($host, $value)
    {
    }

    /**
     * @inheritdoc
     */
    public function getHistory($host)
    {
    }

    /**
     * @inheritdoc
     */
    public function setHistory($host, array $values)
    {
    }
}
