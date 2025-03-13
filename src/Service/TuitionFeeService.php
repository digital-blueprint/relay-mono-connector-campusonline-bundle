<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorCampusonlineBundle\Service;

use Dbp\Relay\BasePersonBundle\API\PersonProviderInterface;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\Rest\Options;
use Dbp\Relay\MonoBundle\ApiPlatform\Payment;
use Dbp\Relay\MonoBundle\BackendServiceProvider\BackendServiceInterface;
use Dbp\Relay\MonoBundle\Persistence\PaymentPersistence;
use Dbp\Relay\MonoBundle\Persistence\PaymentStatus;
use Dbp\Relay\MonoConnectorCampusonlineBundle\TuitionFee\ApiException;
use Dbp\Relay\MonoConnectorCampusonlineBundle\TuitionFee\Connection;
use Dbp\Relay\MonoConnectorCampusonlineBundle\TuitionFee\TuitionFeeApi;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

class TuitionFeeService implements BackendServiceInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public const PERSON_TITLE_LOCAL_DATA_ATTRIBUTE = 'title';

    private TranslatorInterface $translator;
    private LoggerInterface $auditLogger;
    private PersonProviderInterface $personProvider;

    /**
     * @var callable|null
     */
    private $clientHandler;
    private ConfigurationService $config;
    private ?string $token;

    public function __construct(
        TranslatorInterface $translator,
        PersonProviderInterface $personProvider,
        ConfigurationService $config,
    ) {
        $this->translator = $translator;
        $this->logger = new NullLogger();
        $this->auditLogger = new NullLogger();
        $this->personProvider = $personProvider;
        $this->config = $config;
        $this->token = null;
    }

    public function setAuditLogger(LoggerInterface $auditLogger): void
    {
        $this->auditLogger = $auditLogger;
    }

    /**
     * For unit testing only.
     */
    public function setClientHandler(?callable $handler, ?string $token): void
    {
        $this->clientHandler = $handler;
        $this->token = $token;
    }

    public function checkConnectionNoAuth(): void
    {
        foreach ($this->config->getTypes() as $type) {
            $api = $this->getApiByType($type, null);
            $api->getVersion();
        }
    }

    public function checkConnection(): void
    {
        foreach ($this->config->getTypes() as $type) {
            $api = $this->getApiByType($type, null);
            $api->getAuthenticatedVersion();
        }
    }

    public function checkBackendConnection(): void
    {
        // In case the API is working, but the connection to the CO backend
        // is broken, then getAuthenticatedVersion() will succeed, but everything else
        // will fail with a 5xx. Use the getCurrentFee API with a non-existing ID to also cover that case.
        foreach ($this->config->getTypes() as $type) {
            $api = $this->getApiByType($type, null);
            try {
                $api->getCurrentFee(uniqid('relay-health-check', true));
            } catch (ApiException $e) {
                if ($e->httpStatusCode !== 404) {
                    throw $e;
                }
            }
        }
    }

    public function updateData(string $paymentBackendType, PaymentPersistence $paymentPersistence): bool
    {
        $payment = $paymentPersistence;
        $changed = false;

        $updateExpiration = new \DateTimeImmutable('-1 minute', new \DateTimeZone('UTC'));
        if (
            !$payment->getDataUpdatedAt()
            || $payment->getDataUpdatedAt() <= $updateExpiration
        ) {
            $this->auditLogger->debug('CO: Updating the payment data', $this->getLoggingContext($payment));

            $personProviderOptions = [];
            $currentPerson = $this->personProvider->getCurrentPerson(
                Options::requestLocalDataAttributes($personProviderOptions, [self::PERSON_TITLE_LOCAL_DATA_ATTRIBUTE]));
            if ($currentPerson === null) {
                throw new ApiError(Response::HTTP_FORBIDDEN, 'Forbidden');
            }

            $payment->setLocalIdentifier($currentPerson->getIdentifier());
            $payment->setGivenName($currentPerson->getGivenName());
            $payment->setFamilyName($currentPerson->getFamilyName());
            $payment->setHonorificSuffix($currentPerson->getLocalDataValue(self::PERSON_TITLE_LOCAL_DATA_ATTRIBUTE));

            $api = $this->getApiByType($paymentBackendType, $payment);
            $obfuscatedId = $payment->getLocalIdentifier();
            $semesterKey = Tools::convertSemesterToSemesterKey($payment->getData());

            try {
                $tuitionFeeData = $api->getSemesterFee($obfuscatedId, $semesterKey);
            } catch (ApiException $e) {
                $this->logger->error('Communication error with backend!', ['exception' => $e]);
                throw new ApiError(Response::HTTP_INTERNAL_SERVER_ERROR, 'Communication error with backend!');
            }
            $amount = $tuitionFeeData->getAmount();
            // The /payment-registrations CO API returns an error for everything smaller then 1.0. To avoid starting
            // a payment that we can never report back fail early here.
            if ($amount < 1.0) {
                $this->auditLogger->error('CO: amount too small, aborting', $this->getLoggingContext($payment));
                throw ApiError::withDetails(Response::HTTP_BAD_REQUEST, 'Amount must be greater than or equal to 1', 'mono:start-payment-amount-too-low');
            }
            $payment->setAmount((string) $amount);
            $payment->setCurrency('EUR');

            $changed = true;
        }

        $this->auditLogger->debug('CO: Updating the payment data is done, changed: '.$changed, $this->getLoggingContext($payment));

        return $changed;
    }

    public function updateEntity(string $paymentBackendType, PaymentPersistence $paymentPersistence, Payment $payment): bool
    {
        $semesterKey = Tools::convertSemesterToSemesterKey($paymentPersistence->getData());
        $parameters = [
            'semesterKey' => $semesterKey,
            'givenName' => $payment->getGivenName(),
            'familyName' => $payment->getFamilyName(),
            'honorificSuffix' => $payment->getHonorificSuffix(),
        ];
        if ($parameters['honorificSuffix'] !== null) {
            $alternateName = $this->translator->trans('tuition_fee.payment_title_with_suffix', $parameters, 'dbp_relay_mono_connector_campusonline');
        } else {
            $alternateName = $this->translator->trans('tuition_fee.payment_title', $parameters, 'dbp_relay_mono_connector_campusonline');
        }
        $payment->setAlternateName($alternateName);

        return true;
    }

    public function notify(string $paymentBackendType, PaymentPersistence $paymentPersistence): bool
    {
        $payment = $paymentPersistence;
        // This is just a sanity check, we should never be called in another state
        if ($payment->getNotifiedAt() !== null || $payment->getPaymentStatus() !== PaymentStatus::COMPLETED) {
            throw new \RuntimeException('notify called in an invalid state');
        }

        $this->auditLogger->debug('CO: Registering semester payment', $this->getLoggingContext($payment));
        $api = $this->getApiByType($paymentBackendType, $payment);
        $obfuscatedId = $payment->getLocalIdentifier();
        $amount = (float) $payment->getAmount();
        $semesterKey = Tools::convertSemesterToSemesterKey($payment->getData());

        // This shouldn't really happen, but if it does, we know something went wrong
        $openAmount = $api->getSemesterFee($obfuscatedId, $semesterKey)->getAmount();
        if ($amount > $openAmount) {
            $this->auditLogger->error('CO: Amount being payed is larger than the owed amount ('.$amount.' > '.$openAmount.'), aborting!', $this->getLoggingContext($payment));
            throw new \RuntimeException('CO: Amount being payed is larger than the owed amount ('.$amount.' > '.$openAmount.'), aborting!');
        }

        try {
            $api->registerPaymentForSemester($obfuscatedId, $amount, $semesterKey);
        } catch (ApiException $e) {
            $this->logger->error('Communication error with backend!', ['exception' => $e]);
            throw new ApiError(Response::HTTP_INTERNAL_SERVER_ERROR, 'Communication error with backend!');
        }

        return true;
    }

    public function cleanup(string $paymentBackendType, PaymentPersistence $paymentPersistence): bool
    {
        return true;
    }

    /**
     * @return mixed[]
     */
    private function getLoggingContext(PaymentPersistence $payment): array
    {
        return ['relay-mono-payment-id' => $payment->getIdentifier()];
    }

    public function getApiByType(string $type, ?PaymentPersistence $payment): TuitionFeeApi
    {
        $config = $this->config->getPaymentTypeConfig($type);
        $baseUrl = $config['api_url'] ?? '';
        $clientId = $config['client_id'] ?? '';
        $clientSecret = $config['client_secret'] ?? '';

        $connection = new Connection(
            $baseUrl,
            $clientId,
            $clientSecret
        );
        $connection->setLogger($this->logger);

        if ($this->token !== null) {
            $connection->setToken($this->token);
        }

        if ($this->clientHandler !== null) {
            $connection->setClientHandler($this->clientHandler);
        }

        $api = new TuitionFeeApi($connection);
        $api->setLogger($this->logger);
        $api->setAuditLogger($this->auditLogger);
        if ($payment !== null) {
            $api->setLoggingContext($this->getLoggingContext($payment));
        }

        return $api;
    }

    public function getPaymentBackendTypes(): array
    {
        return $this->config->getTypes();
    }
}
