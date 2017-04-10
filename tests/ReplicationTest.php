<?php

namespace Phlib\DbHelper\Tests;

use Phlib\DbHelper\Replication;
use Phlib\DbHelper\Replication\StorageInterface;
use Phlib\Db\Adapter\AdapterInterface;
use phpmock\phpunit\PHPMock;

/**
 * @package Phlib\Db
 * @licence LGPL-3.0
 */
class ReplicationTest extends \PHPUnit_Framework_TestCase
{
    use PHPMock;

    /**
     * @var AdapterInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $master;

    /**
     * @var \Phlib\DbHelper\Replication\StorageInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $storage;

    protected function setUp()
    {
        $this->master = $this->createMock(AdapterInterface::class);
        $this->master->expects($this->any())
            ->method('getConfig')
            ->will($this->returnValue(['host' => '127.0.0.1']));

        $this->storage = $this->createMock(StorageInterface::class);

        parent::setUp();
    }

    public function tearDown()
    {
        parent::tearDown();
        $this->storage = null;
        $this->master  = null;
    }

    public function testCreateFromConfigSuccessfully()
    {
        $config = $this->getDefaultConfig();
        $replication = Replication::createFromConfig($config);
        $this->assertInstanceOf(Replication::class, $replication);
    }

    /**
     * @expectedException \Phlib\DbHelper\Exception\InvalidArgumentException
     */
    public function testCreateFromConfigWithInvalidStorageClass()
    {
        $config = $this->getDefaultConfig();
        $config['storage']['class'] = '\My\Unknown\Class';
        Replication::createFromConfig($config);
    }

    /**
     * @expectedException \Phlib\DbHelper\Exception\InvalidArgumentException
     */
    public function testCreateFromConfigWithInvalidStorageMethod()
    {
        $config = $this->getDefaultConfig();
        $config['storage']['class'] = '\stdClass';
        Replication::createFromConfig($config);
    }

    /**
     * @return array
     */
    public function getDefaultConfig()
    {
        return [
            'host'     => '10.0.0.1',
            'username' => 'foo',
            'password' => 'bar',
            'slaves'   => [
                [
                    'host'     => '10.0.0.2',
                    'username' => 'foo',
                    'password' => 'bar'
                ]
            ],
            'storage' => [
                'class' => \Phlib\DbHelper\Tests\Replication\StorageMock::class,
                'args'  => [[]]
            ],
        ];
    }

    /**
     * @expectedException \Phlib\DbHelper\Exception\InvalidArgumentException
     */
    public function testConstructDoesNotAllowEmptySlaves()
    {
        new Replication($this->master, [], $this->storage);
    }

    public function testGettingStorageReturnsSameInstance()
    {
        $slave = $this->createMock(AdapterInterface::class);
        $replication = new Replication($this->master, [$slave], $this->storage);
        $this->assertSame($this->storage, $replication->getStorage());
    }

    /**
     * @expectedException \Phlib\DbHelper\Exception\InvalidArgumentException
     */
    public function testConstructChecksSlaves()
    {
        $slaves = [new \stdClass()];
        new Replication($this->master, $slaves, $this->storage);
    }

    public function testSetWeighting()
    {
        $weighting = 12345;
        $replication = new Replication($this->master, [$this->createMock(AdapterInterface::class)], $this->storage);
        $replication->setWeighting($weighting);
        $this->assertEquals($weighting, $replication->getWeighting());
    }

    public function testSetMaximumSleep()
    {
        $maxSleep = 123456;
        $replication = new Replication($this->master, [$this->createMock(AdapterInterface::class)], $this->storage);
        $replication->setMaximumSleep($maxSleep);
        $this->assertEquals($maxSleep, $replication->getMaximumSleep());
    }

    /**
     * @param string $method
     * @dataProvider monitorRecordsToStorageDataProvider
     */
    public function testMonitorRecordsToStorage($method)
    {
        $this->storage->expects($this->once())->method($method);
        $slave = $this->createMock(AdapterInterface::class);
        $this->setupSlave($slave, ['Seconds_Behind_Master' => 20]);
        $replication = new Replication($this->master, [$slave], $this->storage);
        $replication->monitor();
    }

    public function monitorRecordsToStorageDataProvider()
    {
        return [
            ['setSecondsBehind'],
            ['setHistory']
        ];
    }

    public function testHistoryGetsTrimmed()
    {
        $maxEntries = Replication::MAX_HISTORY;

        $history = array_pad([], $maxEntries, 20);
        $slave   = $this->createMock(AdapterInterface::class);
        $this->setupSlave($slave, ['Seconds_Behind_Master' => 5]);

        $this->storage->expects($this->any())
            ->method('getHistory')
            ->will($this->returnValue($history));

        $this->storage->expects($this->once())
            ->method('setHistory')
            ->with($this->anything(), $this->countOf($maxEntries));

        $replication = new Replication($this->master, [$slave], $this->storage);
        $replication->monitor();
    }

    public function testHistoryGetsNewSlaveValue()
    {
        $maxEntries = Replication::MAX_HISTORY;
        $newValue   = 5;

        $history = array_pad([], $maxEntries / 2, 20);
        $slave   = $this->createMock(AdapterInterface::class);
        $this->setupSlave($slave, ['Seconds_Behind_Master' => $newValue]);

        $this->storage->expects($this->any())
            ->method('getHistory')
            ->will($this->returnValue($history));

        $this->storage->expects($this->once())
            ->method('setHistory')
            ->with($this->anything(), $this->contains($newValue));

        $replication = new Replication($this->master, [$slave], $this->storage);
        $replication->monitor();
    }

    public function testFetchStatusMakesCorrectCall()
    {
        $pdoStatement = $this->createMock(\PDOStatement::class);
        $pdoStatement->expects($this->any())
            ->method('fetch')
            ->will($this->returnValue(['Seconds_Behind_Master' => 10]));

        /** @var AdapterInterface|\PHPUnit_Framework_MockObject_MockObject $slave */
        $slave = $this->createMock(AdapterInterface::class);
        $slave->expects($this->once())
            ->method('query')
            ->with($this->equalTo('SHOW SLAVE STATUS'))
            ->will($this->returnValue($pdoStatement));

        $replication = new Replication($this->master, [$slave], $this->storage);
        $replication->fetchStatus($slave);
    }

    /**
     * @param array $data
     * @expectedException \Phlib\DbHelper\Exception\RuntimeException
     * @dataProvider fetchStatusErrorsWithBadReturnedDataDataProvider
     */
    public function testFetchStatusErrorsWithBadReturnedData($data)
    {
        $pdoStatement = $this->createMock(\PDOStatement::class);
        $pdoStatement->expects($this->any())
            ->method('fetch')
            ->will($this->returnValue($data));

        /** @var AdapterInterface|\PHPUnit_Framework_MockObject_MockObject $slave */
        $slave = $this->createMock(AdapterInterface::class);
        $slave->expects($this->any())
            ->method('query')
            ->will($this->returnValue($pdoStatement));

        $replication = new Replication($this->master, [$slave], $this->storage);
        $replication->fetchStatus($slave);
    }

    public function fetchStatusErrorsWithBadReturnedDataDataProvider()
    {
        return [
            [false],
            [['FooColumn' => 'bar']],
            [['Seconds_Behind_Master' => null]]
        ];
    }

    public function testThrottleWithNoSlaveLag()
    {
        $this->storage->expects($this->any())
            ->method('getSecondsBehind')
            ->will($this->returnValue(0));

        $usleep = $this->getFunctionMock('\Phlib\DbHelper', 'usleep');
        $usleep->expects($this->once())
            ->with($this->equalTo(0));

        $slave = $this->createMock(AdapterInterface::class);
        (new Replication($this->master, [$slave], $this->storage))->throttle();
    }

    public function testThrottleWithSlaveLag()
    {
        $this->storage->expects($this->any())
            ->method('getSecondsBehind')
            ->will($this->returnValue(500));

        $usleep = $this->getFunctionMock('\Phlib\DbHelper', 'usleep');
        $usleep->expects($this->once())
            ->with($this->greaterThan(0));

        $slave = $this->createMock(AdapterInterface::class);
        (new Replication($this->master, [$slave], $this->storage))->throttle();
    }

    /**
     * @param AdapterInterface|\PHPUnit_Framework_MockObject_MockObject $slave
     * @param mixed $return
     */
    protected function setupSlave(\PHPUnit_Framework_MockObject_MockObject $slave, $return)
    {
        $pdoStatement = $this->createMock(\PDOStatement::class);
        $pdoStatement->expects($this->any())
            ->method('fetch')
            ->will($this->returnValue($return));

        $slave->expects($this->any())
            ->method('query')
            ->will($this->returnValue($pdoStatement));
    }
}