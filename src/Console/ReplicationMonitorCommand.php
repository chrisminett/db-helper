<?php

namespace Phlib\DbHelper\Console;

use Phlib\DbHelper\Replication;
use Phlib\ConsoleProcess\Command\DaemonCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

/**
 * @package Phlib\Db
 * @licence LGPL-3.0
 */
class ReplicationMonitorCommand extends DaemonCommand
{
    /**
     * @var Replication
     */
    protected $replication;

    protected function configure()
    {
        $this->setName('replication:monitor')
            ->setDescription('CLI for monitoring MySQL slave status.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function onAfterDaemonizeChild(InputInterface $input, OutputInterface $output)
    {
        $this->replication = $this->getReplication();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->replication->monitor();
    }

    /**
     * @return Replication
     */
    protected function getReplication()
    {
        $config = $this->getHelper('configuration')->fetch();
        return Replication::createFromConfig($config);
    }

    /**
     * @return StreamOutput
     */
    protected function createChildOutput()
    {
        $filename = getcwd() . '/replication-monitor.log';
        return new StreamOutput(fopen($filename, 'a'));
    }
}
