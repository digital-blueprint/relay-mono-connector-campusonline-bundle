<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorCampusonlineBundle\Tests;

use Dbp\Relay\BasePersonBundle\Entity\Person;
use Dbp\Relay\BasePersonBundle\Service\DummyPersonProvider;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\TestUtils\TestUserSession;
use Dbp\Relay\MonoBundle\Persistence\PaymentPersistence;
use Dbp\Relay\MonoConnectorCampusonlineBundle\Service\Tools;
use Dbp\Relay\MonoConnectorCampusonlineBundle\Service\TuitionFeeService;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\Translation\Translator;
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
        $this->tuitionFeeService = new TuitionFeeService(new Translator('de'),
            new TestUserSession('testuser'), $dummyPersonProvider);
        $this->tuitionFeeService->setConfig([
            'payment_types' => [
                'test_payment_type' => [
                    'api_url' => 'http://localhost',
                    'client_id' => 'nope',
                    'client_secret' => 'nope',
                ],
            ],
        ]);
    }

    private function mockResponses(array $responses)
    {
        $mockHandler = new MockHandler($responses);
        $clientHandler = HandlerStack::create($mockHandler);
        $this->tuitionFeeService->setClientHandler($clientHandler);
    }

    public function testConvertSemesterToSemesterKey()
    {
        $this->assertSame(Tools::convertSemesterToSemesterKey('22W'), '2022W');
        $this->assertSame(Tools::convertSemesterToSemesterKey('49W'), '2049W');
        $this->assertSame(Tools::convertSemesterToSemesterKey('50S'), '1950S');
        $this->assertSame(Tools::convertSemesterToSemesterKey('2050S'), '2050S');
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
            new Response(201, ['Content-Type' => 'application/json'], '{"access_token":"testtoken"}'),
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
            new Response(201, ['Content-Type' => 'application/json'], '{"access_token":"testtoken"}'),
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
}
