<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorCampusonlineBundle\Tests;

use Dbp\Relay\MonoConnectorCampusonlineBundle\Rest\Connection;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class ConnectionTest extends TestCase
{
    /**
     * @var Connection
     */
    private $conn;

    protected function setUp(): void
    {
        $this->conn = new Connection('http://localhost', 'nope', 'nope');
        $this->mockResponses([]);
    }

    private function mockResponses(array $responses)
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $this->conn->setClientHandler($stack);
    }

    public function testFetchToken()
    {
        $this->mockResponses([
            new Response(200, ['Content-Type' => 'application/json'], '{"access_token": "foobar"}'),
        ]);
        $client = $this->conn->getClient();
        $this->assertNotNull($client);
    }
}
