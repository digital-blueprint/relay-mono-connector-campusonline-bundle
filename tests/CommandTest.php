<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorCampusonlineBundle\Tests;

use Dbp\Relay\MonoConnectorCampusonlineBundle\Command\ListTuitionFeesCommand;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class CommandTest extends KernelTestCase
{
    public function testRestartExecute()
    {
        $kernel = static::createKernel();
        $application = new Application($kernel);

        $command = $application->find('dbp:relay:mono-connector-campusonline:list-tuition-fees');
        assert($command instanceof ListTuitionFeesCommand);
        $commandTester = new CommandTester($command);

        $responses = [
            new Response(200, ['Content-Type' => 'application/json'], '{"items":[{"amount":-16,"semesterKey":"1950W"},{"amount":-0.5,"semesterKey":"1951S"},{"amount":0,"semesterKey":"1951W"}],"totalAmount":-16.5}'),
            new Response(200, ['Content-Type' => 'application/json'], '{"amount":-0.5,"semesterKey":"1951S"}'),
        ];

        $mockHandler = new MockHandler($responses);
        $clientHandler = HandlerStack::create($mockHandler);
        $command->setClientHandler($clientHandler, 'bla');

        $res = $commandTester->execute(['payment-type' => 'foobar', 'obfuscated-id' => 'user-id']);
        $this->assertSame(0, $res);
    }
}
