<?php

namespace Phlib\DbHelper\Replication;

/**
 * @package Phlib\Db
 * @licence LGPL-3.0
 */
interface StorageInterface
{
    /**
     * @param array $config
     * @return static
     */
    public static function createFromConfig(array $config);

    /**
     * @return integer
     */
    public function getSecondsBehind($host);

    /**
     * @param string $host
     * @param integer $value
     * @return $this
     */
    public function setSecondsBehind($host, $value);

    /**
     * @return integer[]
     */
    public function getHistory($host);

    /**
     * @param string $host
     * @param integer[] $values
     * @return $this
     */
    public function setHistory($host, array $values);
}
