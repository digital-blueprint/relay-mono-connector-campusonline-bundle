<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorCampusonlineBundle\Tests;

use Dbp\Relay\BasePersonBundle\Entity\Person;
use Dbp\Relay\BasePersonBundle\Service\DummyPersonProvider;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\TestUtils\TestUserSession;
use Dbp\Relay\MonoBundle\ApiPlatform\Payment;
use Dbp\Relay\MonoBundle\Persistence\PaymentPersistence;
use Dbp\Relay\MonoBundle\Persistence\PaymentStatus;
use Dbp\Relay\MonoConnectorCampusonlineBundle\Service\ConfigurationService;
use Dbp\Relay\MonoConnectorCampusonlineBundle\Service\Tools;
use Dbp\Relay\MonoConnectorCampusonlineBundle\Service\TuitionFeeService;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Contracts\Translation\TranslatorInterface;

class TuitionFeeServiceTest extends KernelTestCase
{
    private TuitionFeeService $tuitionFeeService;

    protected function setUp(): void
    {
        $dummyPersonProvider = new DummyPersonProvider();
        $person = new Person();
        $person->setIdentifier('testuser');
        $person->setGivenName('John');
        $person->setFamilyName('Doe');
        $dummyPersonProvider->setCurrentPerson($person);
        $config = new ConfigurationService();
        $config->setConfig([
            'payment_types' => [
                'test_payment_type' => [
                    'api_url' => 'http://localhost',
                    'client_id' => 'nope',
                    'client_secret' => 'nope',
                ],
            ],
        ]);
        $translator = self::getContainer()->get(TranslatorInterface::class);
        $this->tuitionFeeService = new TuitionFeeService($translator,
            new TestUserSession('testuser'), $dummyPersonProvider, $config);
    }

    private function getAuthResponses(): array
    {
        return [
            new Response(200, ['Content-Type' => 'application/json'], '{"authServerUrl":"http://localhost/co/public/sec/auth/realms/CAMPUSonline_SP","clientId":"co-public-rest-api-app-user","frontendUrl":"http://localhost/co/public/app","openApiSource":"default","version":"2.3.0-ef6baff"}'),
            new Response(200, ['Content-Type' => 'application/json'], '{"token_endpoint":"http://localhost/co/public/sec/auth/realms/CAMPUSonline/protocol/openid-connect/token"}'),
            new Response(201, ['Content-Type' => 'application/json'], '{"access_token":"testtoken"}'),
        ];
    }

    private function mockResponses(array $responses)
    {
        $mockHandler = new MockHandler($responses);
        $clientHandler = HandlerStack::create($mockHandler);
        $this->tuitionFeeService->setClientHandler($clientHandler, 'bla');
    }

    public function testConvertSemesterToSemesterKey()
    {
        $this->assertSame(Tools::convertSemesterToSemesterKey('22W'), '2022W');
        $this->assertSame(Tools::convertSemesterToSemesterKey('49W'), '2049W');
        $this->assertSame(Tools::convertSemesterToSemesterKey('50S'), '1950S');
        $this->assertSame(Tools::convertSemesterToSemesterKey('2050S'), '2050S');
    }

    public function testNotify()
    {
        $paymentPersistence = new PaymentPersistence();
        $paymentPersistence->setIdentifier('test_payment_persistence');
        $paymentPersistence->setType('test_payment_type');
        $paymentPersistence->setData('22S');
        $paymentPersistence->setAmount('300');
        $paymentPersistence->setLocalIdentifier('user-id');
        $paymentPersistence->setPaymentStatus(PaymentStatus::COMPLETED);

        $this->mockResponses([
            new Response(200, ['Content-Type' => 'application/json'], '{"amount":300,"semesterKey":"2022S"}'),
            new Response(201, ['Content-Type' => 'application/json'], ''),
        ]);

        $this->assertTrue($this->tuitionFeeService->notify($paymentPersistence));
    }

    public function testNotifyWrongAmount()
    {
        $paymentPersistence = new PaymentPersistence();
        $paymentPersistence->setIdentifier('test_payment_persistence');
        $paymentPersistence->setType('test_payment_type');
        $paymentPersistence->setData('22S');
        $paymentPersistence->setAmount('301');
        $paymentPersistence->setLocalIdentifier('user-id');
        $paymentPersistence->setPaymentStatus(PaymentStatus::COMPLETED);

        $this->mockResponses([
            new Response(200, ['Content-Type' => 'application/json'], '{"amount":300,"semesterKey":"2022S"}'),
            new Response(201, ['Content-Type' => 'application/json'], ''),
        ]);

        $this->expectExceptionMessage('Amount being payed is larger');
        $this->tuitionFeeService->notify($paymentPersistence);
    }

    public function testTranslations()
    {
        self::bootKernel();
        $container = static::getContainer();

        /** @var TranslatorInterface $t */
        $t = $container->get('translator');
        $result = $t->trans(
            'dbp_relay_mono_connector_campusonline.tuition_fee.alternate_name', ['familyName' => 'foo'], null, 'en');
        $this->assertStringContainsString('foo', $result);
        $result = $t->trans(
            'dbp_relay_mono_connector_campusonline.tuition_fee.alternate_name_suffix', ['familyName' => 'foo'], null, 'en');
        $this->assertStringContainsString('foo', $result);
    }

    public function testUpdateData(): void
    {
        $this->mockResponses([
            new Response(200, ['Content-Type' => 'application/json'], '{"amount":300,"semesterKey":"2022S"}'),
        ]);

        $paymentPersistence = new PaymentPersistence();
        $paymentPersistence->setIdentifier('test_payment_persistence');
        $paymentPersistence->setType('test_payment_type');
        $paymentPersistence->setData('22S');

        $this->tuitionFeeService->updateData($paymentPersistence);

        $this->assertSame($paymentPersistence->getAmount(), '300');
        $this->assertSame($paymentPersistence->getCurrency(), 'EUR');
        $this->assertSame($paymentPersistence->getGivenName(), 'John');
        $this->assertSame($paymentPersistence->getFamilyName(), 'Doe');
        $this->assertSame($paymentPersistence->getHonorificSuffix(), 'title');
    }

    public function testUpdateDataAmountToSmall(): void
    {
        $this->mockResponses([
            new Response(200, ['Content-Type' => 'application/json'], '{"amount":0,"semesterKey":"2022S"}'),
        ]);

        $paymentPersistence = new PaymentPersistence();
        $paymentPersistence->setIdentifier('test_payment_persistence');
        $paymentPersistence->setType('test_payment_type');
        $paymentPersistence->setData('22S');

        try {
            $this->tuitionFeeService->updateData($paymentPersistence);
            $this->fail('Expected an 400 ApiError');
        } catch (ApiError $apiError) {
            $this->assertSame($apiError->getStatusCode(), HttpResponse::HTTP_BAD_REQUEST);
            $this->assertSame($apiError->getErrorId(), 'mono:start-payment-amount-too-low');
        }
    }

    public function testUpdateEntity()
    {
        $paymentPersistence = new PaymentPersistence();
        $paymentPersistence->setData('22S');
        $payment = new Payment();
        $payment->setFamilyName('family');
        $payment->setGivenName('given');
        $this->assertTrue($this->tuitionFeeService->updateEntity($paymentPersistence, $payment));
        $this->assertSame('Tuition fee (2022S) for family, given', $payment->getAlternateName());
        $payment->setHonorificSuffix('suffix');
        $this->assertTrue($this->tuitionFeeService->updateEntity($paymentPersistence, $payment));
        $this->assertSame('Tuition fee (2022S) for family, given, suffix', $payment->getAlternateName());
    }

    public function testCleanup()
    {
        $paymentPersistence = new PaymentPersistence();
        $this->assertTrue($this->tuitionFeeService->cleanup($paymentPersistence));
    }

    public function testCheckConnectionNoAuth()
    {
        $this->mockResponses([
            new Response(200, ['Content-Type' => 'application/json'], '{"name":"tuinx","version":"1.0.0-SNAPSHOT"}'),
        ]);
        $this->tuitionFeeService->checkConnectionNoAuth();
        $this->assertTrue(true);
    }

    public function testCheckConnection()
    {
        $this->mockResponses([
            new Response(200, ['Content-Type' => 'application/json'], '{"name":"tuinx","version":"1.0.0-SNAPSHOT"}'),
        ]);
        $this->tuitionFeeService->checkConnection();
        $this->assertTrue(true);
    }

    public function testCheckBackendConnection()
    {
        $this->mockResponses([
            new Response(404, ['Content-Type' => 'application/json'], '{"status":404,"title":"Not Found","type":"exception:at.swgt.rest.client.exception.CoPublicApiWebApplicationException"}'),
        ]);
        $this->tuitionFeeService->checkBackendConnection();
        $this->assertTrue(true);
    }
}
