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
    private TuitionFeeApi $tuitionFeeApi;

    protected function setUp(): void
    {
        $connection = new Connection('http://localhost', 'nope', 'nope');
        $this->tuitionFeeApi = new TuitionFeeApi($connection);
        $this->mockResponses([]);
    }

    private function mockResponses(array $responses): MockHandler
    {
        $mockHandler = new MockHandler($responses);
        $stack = HandlerStack::create($mockHandler);
        $this->tuitionFeeApi->getConnection()->setClientHandler($stack);

        return $mockHandler;
    }

    public function testGetVersion()
    {
        // no authentication required
        $this->mockResponses([
            new Response(200, ['Content-Type' => 'application/json'], '{"name":"tuinx","version":"1.0.0-SNAPSHOT"}'),
        ]);
        $version = $this->tuitionFeeApi->getVersion();
        $this->assertSame($version->getName(), 'tuinx');
        $this->assertSame($version->getVersion(), '1.0.0-SNAPSHOT');

        $this->mockResponses([
            new Response(201, ['Content-Type' => 'application/json'], '{"access_token":"testtoken"}'),
            new Response(200, ['Content-Type' => 'application/json'], '{"name":"tuinx","version":"1.0.0-SNAPSHOT"}'),
        ]);
        $version = $this->tuitionFeeApi->getAuthenticatedVersion();
        $this->assertSame($version->getName(), 'tuinx');
        $this->assertSame($version->getVersion(), '1.0.0-SNAPSHOT');
    }

    public function testGetCurrentFee()
    {
        $this->mockResponses([
            new Response(201, ['Content-Type' => 'application/json'], '{"access_token":"testtoken"}'),
            new Response(200, ['Content-Type' => 'application/json'], '{"amount":-16,"semesterKey":"2022S"}'),
        ]);
        $fee = $this->tuitionFeeApi->getCurrentFee('DEADBEEF');
        $this->assertSame($fee->getAmount(), -16.0);
        $this->assertSame($fee->getSemesterKey(), '2022S');
    }

    public function testGetCurrentFeeBrokenAPI()
    {
        // This is a real internal error we got. Re-use it to test the error parsing.
        $this->mockResponses([
            new Response(201, ['Content-Type' => 'application/json'], '{"access_token":"testtoken"}'),
            new Response(500, ['Content-Type' => 'application/json'], '{"detail": "java.lang.IllegalStateException: RESTEASY004575: Input stream was empty, there is no entity - RESTEASY003765: Response is closed.", "status": 500, "title": "Failed parsing response from public API", "type": "at.swgt.rest.client.exception.CoPublicApiWebApplicationException"}'),
        ]);

        $this->expectException(ApiException::class);
        $this->tuitionFeeApi->getCurrentFee('DEADBEEF');
    }

    public function testGetCurrentFeeNotFound()
    {
        $this->mockResponses([
            new Response(201, ['Content-Type' => 'application/json'], '{"access_token":"testtoken"}'),
            new Response(404, ['Content-Type' => 'application/json'], '{"status":404,"title":"Not Found","type":"exception:at.swgt.rest.client.exception.CoPublicApiWebApplicationException"}'),
        ]);
        $this->expectException(ApiException::class);
        $this->tuitionFeeApi->getCurrentFee('DEADBEEF');
    }

    public function testGetSemesterFee()
    {
        $this->mockResponses([
            new Response(201, ['Content-Type' => 'application/json'], '{"access_token":"testtoken"}'),
            new Response(200, ['Content-Type' => 'application/json'], '{"amount":0,"semesterKey":"2023S"}'),
        ]);
        $fee = $this->tuitionFeeApi->getSemesterFee('DEADBEEF', '2023S');
        $this->assertSame($fee->getAmount(), 0.0);
        $this->assertSame($fee->getSemesterKey(), '2023S');
    }

    public function testGetSemesterInvalidKey()
    {
        $this->mockResponses([
            new Response(201, ['Content-Type' => 'application/json'], '{"access_token":"testtoken"}'),
            new Response(400, ['Content-Type' => 'application/json'], '{"status":400,"title":"semesterKey invalid","type":"exception:javax.ws.rs.BadRequestException"}'),
        ]);
        $this->expectException(ApiException::class);
        $this->tuitionFeeApi->getSemesterFee('DEADBEEF', 'INVALID');
    }

    public function testGetFees()
    {
        $this->mockResponses([
            new Response(201, ['Content-Type' => 'application/json'], '{"access_token":"testtoken"}'),
            new Response(200, ['Content-Type' => 'application/json'], '{"items":[{"amount":-16,"semesterKey":"1950W"},{"amount":-0.5,"semesterKey":"1951S"},{"amount":0,"semesterKey":"1951W"}],"totalAmount":-16.5}'),
        ]);

        $feeList = $this->tuitionFeeApi->getAllFees('DEADBEEF');
        $this->assertCount(3, $feeList->getItems());
        $this->assertSame(-16.5, $feeList->getTotalAmount());
        $this->assertSame($feeList->getItems()[0]->getSemesterKey(), '1950W');
    }

    public function testGetFeesEmpty()
    {
        $this->mockResponses([
            new Response(201, ['Content-Type' => 'application/json'], '{"access_token":"testtoken"}'),
            new Response(200, ['Content-Type' => 'application/json'], '{"totalAmount":0.0}'),
        ]);

        $feeList = $this->tuitionFeeApi->getAllFees('DEADBEEF');
        $this->assertCount(0, $feeList->getItems());
        $this->assertSame(0.0, $feeList->getTotalAmount());
    }

    public function testGetFeesNotFound()
    {
        $this->mockResponses([
            new Response(201, ['Content-Type' => 'application/json'], '{"access_token":"testtoken"}'),
            new Response(404, ['Content-Type' => 'application/json'], '{"status":404,"title":"Not Found","type":"exception:at.swgt.rest.client.exception.CoPublicApiWebApplicationException"}'),
        ]);

        $this->expectException(ApiException::class);
        $this->tuitionFeeApi->getAllFees('DOESNTEXIST');
    }

    public function testRegisterPaymentForSemester()
    {
        $mockHandler = $this->mockResponses([
            new Response(201, ['Content-Type' => 'application/json'], '{"access_token":"testtoken"}'),
            new Response(201, ['Content-Type' => 'application/json'], ''),
        ]);

        $this->tuitionFeeApi->registerPaymentForSemester('DEADBEEF', 1.25, '2022W');
        $this->assertSame((string) $mockHandler->getLastRequest()->getBody(), '{"personUid":"DEADBEEF","amount":1.25,"semesterKey":"2022W"}');
    }

    public function testRegisterPaymentForSemesterPersonNotFound()
    {
        $this->mockResponses([
            new Response(201, ['Content-Type' => 'application/json'], '{"access_token":"testtoken"}'),
            new Response(404, ['Content-Type' => 'application/json'], '{"status":404,"title":"Not Found","type":"exception:at.swgt.rest.client.exception.CoPublicApiWebApplicationException"}'),
        ]);
        $this->expectException(ApiException::class);
        $this->tuitionFeeApi->registerPaymentForSemester('NOTFOUND', 1.25, '2022W');
    }

    public function testRegisterPaymentForSemesterInvalidAmount()
    {
        $this->mockResponses([
            new Response(201, ['Content-Type' => 'application/json'], '{"access_token":"testtoken"}'),
            new Response(400, ['Content-Type' => 'application/json'], '{"status":400,"title":"amount too small","type":"exception:javax.ws.rs.BadRequestException"}'),
        ]);
        $this->expectException(ApiException::class);
        $this->tuitionFeeApi->registerPaymentForSemester('DEADBEEF', 0, '2022W');
    }

    public function testRegisterPaymentForCurrentSemester()
    {
        $mockHandler = $this->mockResponses([
            new Response(201, ['Content-Type' => 'application/json'], '{"access_token":"testtoken"}'),
            new Response(201, ['Content-Type' => 'application/json'], ''),
        ]);

        $this->tuitionFeeApi->registerPaymentForCurrentSemester('DEADBEEF', 1.25);
        $this->assertSame((string) $mockHandler->getLastRequest()->getBody(), '{"personUid":"DEADBEEF","amount":1.25}');
    }

    public function testRegisterPaymentForCurrentSemesterPersonNotFound()
    {
        $this->mockResponses([
            new Response(201, ['Content-Type' => 'application/json'], '{"access_token":"testtoken"}'),
            new Response(404, ['Content-Type' => 'application/json'], '{"status":404,"title":"Not Found","type":"exception:at.swgt.rest.client.exception.CoPublicApiWebApplicationException"}'),
        ]);
        $this->expectException(ApiException::class);
        $this->tuitionFeeApi->registerPaymentForCurrentSemester('NOTFOUND', 1.25);
    }

    public function testRegisterPaymentForCurrentSemesterInvalidAmount()
    {
        $this->mockResponses([
            new Response(201, ['Content-Type' => 'application/json'], '{"access_token":"testtoken"}'),
            new Response(400, ['Content-Type' => 'application/json'], '{"status":400,"title":"amount too small","type":"exception:javax.ws.rs.BadRequestException"}'),
        ]);
        $this->expectException(ApiException::class);
        $this->tuitionFeeApi->registerPaymentForCurrentSemester('DEADBEEF', 0);
    }
}
