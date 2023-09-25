<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorCampusonlineBundle\Tests;

use Dbp\Relay\MonoConnectorCampusonlineBundle\TuitionFee\ApiException;
use Dbp\Relay\MonoConnectorCampusonlineBundle\TuitionFee\Connection;
use Dbp\Relay\MonoConnectorCampusonlineBundle\TuitionFee\TuitionFeeApi;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class TuitionFeeApiTest extends TestCase
{
    /**
     * @var Connection
     */
    private $conn;

    private $api;

    protected function setUp(): void
    {
        $this->conn = new Connection('http://localhost', 'nope', 'nope');
        $this->mockResponses([]);
        $this->conn->setToken('dummy');
        $this->api = new TuitionFeeApi($this->conn);
    }

    private function mockResponses(array $responses)
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $this->conn->setClientHandler($stack);
    }

    public function testGetVersion()
    {
        $this->mockResponses([
            new Response(200, ['Content-Type' => 'application/json'], '{"name":"tuinx","version":"1.0.0-SNAPSHOT"}'),
        ]);
        $version = $this->api->getVersion();
        $this->assertSame($version->getName(), 'tuinx');
        $this->assertSame($version->getVersion(), '1.0.0-SNAPSHOT');

        $this->mockResponses([
            new Response(200, ['Content-Type' => 'application/json'], '{"name":"tuinx","version":"1.0.0-SNAPSHOT"}'),
        ]);
        $version = $this->api->getAuthenticatedVersion();
        $this->assertSame($version->getName(), 'tuinx');
        $this->assertSame($version->getVersion(), '1.0.0-SNAPSHOT');
    }

    public function testGetCurrentFee()
    {
        $this->mockResponses([
            new Response(200, ['Content-Type' => 'application/json'], '{"amount":-16,"semesterKey":"2022S"}'),
        ]);
        $fee = $this->api->getCurrentFee('DEADBEEF');
        $this->assertSame($fee->getAmount(), -16.0);
        $this->assertSame($fee->getSemesterKey(), '2022S');
    }

    public function testGetCurrentFeeBrokenAPI()
    {
        // This is a real internal error we got. Re-use it to test the error parsing.
        $this->mockResponses([
            new Response(500, ['Content-Type' => 'application/json'], '{"detail": "java.lang.IllegalStateException: RESTEASY004575: Input stream was empty, there is no entity - RESTEASY003765: Response is closed.", "status": 500, "title": "Failed parsing response from public API", "type": "at.swgt.rest.client.exception.CoPublicApiWebApplicationException"}'),
        ]);

        $this->expectException(ApiException::class);
        $this->api->getCurrentFee('DEADBEEF');
    }

    public function testGetCurrentFeeNotFound()
    {
        $this->mockResponses([
            new Response(404, ['Content-Type' => 'application/json'], '{"status":404,"title":"Not Found","type":"exception:at.swgt.rest.client.exception.CoPublicApiWebApplicationException"}'),
        ]);
        $this->expectException(ApiException::class);
        $this->api->getCurrentFee('DEADBEEF');
    }

    public function testGetSemesterFee()
    {
        $this->mockResponses([
            new Response(200, ['Content-Type' => 'application/json'], '{"amount":0,"semesterKey":"2023S"}'),
        ]);
        $fee = $this->api->getSemesterFee('DEADBEEF', '2023S');
        $this->assertSame($fee->getAmount(), 0.0);
        $this->assertSame($fee->getSemesterKey(), '2023S');
    }

    public function testGetSemesterInvalidKey()
    {
        $this->mockResponses([
            new Response(400, ['Content-Type' => 'application/json'], '{"status":400,"title":"semesterKey invalid","type":"exception:javax.ws.rs.BadRequestException"}'),
        ]);
        $this->expectException(ApiException::class);
        $this->api->getSemesterFee('DEADBEEF', 'INVALID');
    }

    public function testGetFees()
    {
        $this->mockResponses([
            new Response(200, ['Content-Type' => 'application/json'], '{"items":[{"amount":-16,"semesterKey":"1950W"},{"amount":-0.5,"semesterKey":"1951S"},{"amount":0,"semesterKey":"1951W"}],"totalAmount":-16.5}'),
        ]);

        $feeList = $this->api->getAllFees('DEADBEEF');
        $this->assertCount(3, $feeList->getItems());
        $this->assertSame(-16.5, $feeList->getTotalAmount());
        $this->assertSame($feeList->getItems()[0]->getSemesterKey(), '1950W');
    }

    public function testGetFeesEmpty()
    {
        $this->mockResponses([
            new Response(200, ['Content-Type' => 'application/json'], '{"totalAmount":0.0}'),
        ]);

        $feeList = $this->api->getAllFees('DEADBEEF');
        $this->assertCount(0, $feeList->getItems());
        $this->assertSame(0.0, $feeList->getTotalAmount());
    }

    public function testGetFeesNotFound()
    {
        $this->mockResponses([
            new Response(404, ['Content-Type' => 'application/json'], '{"status":404,"title":"Not Found","type":"exception:at.swgt.rest.client.exception.CoPublicApiWebApplicationException"}'),
        ]);

        $this->expectException(ApiException::class);
        $this->api->getAllFees('DOESNTEXIST');
    }

    public function testRegisterPaymentForSemester()
    {
        $mockHandler = new MockHandler([
            new Response(201, ['Content-Type' => 'application/json'], ''),
        ]);
        $this->conn->setClientHandler(HandlerStack::create($mockHandler));

        $this->api->registerPaymentForSemester('DEADBEEF', 1.25, '2022W');
        $this->assertSame((string) $mockHandler->getLastRequest()->getBody(), '{"personUid":"DEADBEEF","amount":1.25,"semesterKey":"2022W"}');
    }

    public function testRegisterPaymentForSemesterPersonNotFound()
    {
        $this->mockResponses([
            new Response(404, ['Content-Type' => 'application/json'], '{"status":404,"title":"Not Found","type":"exception:at.swgt.rest.client.exception.CoPublicApiWebApplicationException"}'),
        ]);
        $this->expectException(ApiException::class);
        $this->api->registerPaymentForSemester('NOTFOUND', 1.25, '2022W');
    }

    public function testRegisterPaymentForSemesterInvalidAmount()
    {
        $this->mockResponses([
            new Response(400, ['Content-Type' => 'application/json'], '{"status":400,"title":"amount too small","type":"exception:javax.ws.rs.BadRequestException"}'),
        ]);
        $this->expectException(ApiException::class);
        $this->api->registerPaymentForSemester('DEADBEEF', 0, '2022W');
    }

    public function testRegisterPaymentForCurrentSemester()
    {
        $mockHandler = new MockHandler([
            new Response(201, ['Content-Type' => 'application/json'], ''),
        ]);
        $this->conn->setClientHandler(HandlerStack::create($mockHandler));

        $this->api->registerPaymentForCurrentSemester('DEADBEEF', 1.25);
        $this->assertSame((string) $mockHandler->getLastRequest()->getBody(), '{"personUid":"DEADBEEF","amount":1.25}');
    }

    public function testRegisterPaymentForCurrentSemesterPersonNotFound()
    {
        $this->mockResponses([
            new Response(404, ['Content-Type' => 'application/json'], '{"status":404,"title":"Not Found","type":"exception:at.swgt.rest.client.exception.CoPublicApiWebApplicationException"}'),
        ]);
        $this->expectException(ApiException::class);
        $this->api->registerPaymentForCurrentSemester('NOTFOUND', 1.25);
    }

    public function testRegisterPaymentForCurrentSemesterInvalidAmount()
    {
        $this->mockResponses([
            new Response(400, ['Content-Type' => 'application/json'], '{"status":400,"title":"amount too small","type":"exception:javax.ws.rs.BadRequestException"}'),
        ]);
        $this->expectException(ApiException::class);
        $this->api->registerPaymentForCurrentSemester('DEADBEEF', 0);
    }
}
