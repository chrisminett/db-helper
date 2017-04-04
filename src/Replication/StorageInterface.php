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
     * @param string $host
     * @return int
     */
    public function getSecondsBehind($host);

    /**
     * @param string $host
     * @param integer $value
     * @return $this
     */
    public function setSecondsBehind($host, $value);

    /**
     * @param string $host
     * @return \integer[]
     */
    public function getHistory($host);

    /**
     * @param string $host
     * @param integer[] $values
     * @return $this
     */
    public function setHistory($host, array $values);
}
