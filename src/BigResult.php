<?php

namespace Phlib\DbHelper;

use Phlib\DbHelper\Exception\InvalidArgumentException;
use Phlib\Db\Adapter;

/**
 * @package Phlib\DbHelper
 * @licence LGPL-3.0
 */
class BigResult
{
    /**
     * @var Adapter
     */
    protected $adapter;

    /**
     * @var array
     */
    protected $options;

    /**
     * @param Adapter $adapter
     * @param array $options {
     *     @var int $long_query_time   Default 7200
     *     @var int $net_write_timeout Default 7200
     * }
     */
    public function __construct(Adapter $adapter, array $options = [])
    {
        $this->adapter = $adapter;
        $this->options = $options + [
            'long_query_time'   => 7200,
            'net_write_timeout' => 7200
        ];
    }

    /**
     * @param Adapter $adapter
     * @param string $select
     * @param array $bind
     * @param null $rowLimit
     * @return \PDOStatement
     */
    public static function execute(Adapter $adapter, $select, array $bind = [], $rowLimit = null)
    {
        return (new static($adapter))->query($select, $bind, $rowLimit);
    }

    /**
     * Execute query and return the unbuffered statement.
     *
     * @param string $select
     * @param array $bind
     * @param int $inspectedRowLimit
     * @return \PDOStatement
     */
    public function query($select, array $bind = [], $inspectedRowLimit = null)
    {
        if ($inspectedRowLimit !== null) {
            $inspectedRows = $this->getInspectedRows($select, $bind);
            if ($inspectedRows > $inspectedRowLimit) {
                throw new InvalidArgumentException("Number of rows inspected exceeds '$inspectedRowLimit'");
            }
        }

        $longQueryTime   = $this->options['long_query_time'];
        $netWriteTimeout = $this->options['net_write_timeout'];

        $adapter = clone $this->adapter;
        $adapter->query("SET @@long_query_time={$longQueryTime}, @@net_write_timeout={$netWriteTimeout}");
        $adapter->disableBuffering();

        $stmt = $adapter->prepare($select);
        $stmt->execute($bind);

        return $stmt;
    }

    /**
     * @param string $select
     * @param array $bind
     * @return int
     */
    protected function getInspectedRows($select, array $bind)
    {
        return (new QueryPlanner($this->adapter, $select, $bind))
            ->getNumberOfRowsInspected();
    }
}
