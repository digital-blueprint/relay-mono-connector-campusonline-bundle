<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorCampusonlineBundle\Service;

use Dbp\Relay\BasePersonBundle\API\PersonProviderInterface;
use Dbp\Relay\CoreBundle\API\UserSessionInterface;
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

class TuitionFeeService extends AbstractPaymentTypesService implements BackendServiceInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public const PERSON_TITLE_LOCAL_DATA_ATTRIBUTE = 'title';

    private UserSessionInterface $userSession;
    private TranslatorInterface $translator;
    private LoggerInterface $auditLogger;
    private PersonProviderInterface $personProvider;

    /**
     * @var callable|null
     */
    private $clientHandler;

    public function __construct(
        TranslatorInterface $translator,
        UserSessionInterface $userSession,
        PersonProviderInterface $personProvider
    ) {
        $this->translator = $translator;
        $this->userSession = $userSession;
        $this->logger = new NullLogger();
        $this->auditLogger = new NullLogger();
        $this->personProvider = $personProvider;
    }

    public function setAuditLogger(LoggerInterface $auditLogger): void
    {
        $this->auditLogger = $auditLogger;
    }

    /**
     * For unit testing only.
     */
    public function setClientHandler(?callable $handler): void
    {
        $this->clientHandler = $handler;
    }

    public function checkConnectionNoAuth()
    {
        foreach ($this->getTypes() as $type) {
            $api = $this->getApiByType($type, null);
            $api->getVersion();
        }
    }

    public function checkConnection()
    {
        foreach ($this->getTypes() as $type) {
            $api = $this->getApiByType($type, null);
            $api->getAuthenticatedVersion();
        }
    }

    public function checkBackendConnection()
    {
        // In case the API is working, but the connection to the CO backend
        // is broken, then getAuthenticatedVersion() will succeed, but everything else
        // will fail with a 5xx. Use the getCurrentFee API with a non-existing ID to also cover that case.
        foreach ($this->getTypes() as $type) {
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

    public function updateData(PaymentPersistence $paymentPersistence): bool
    {
        $payment = $paymentPersistence;
        $changed = false;

        $updateExpiration = new \DateTimeImmutable('-1 minute', new \DateTimeZone('UTC'));
        if (
            !$payment->getDataUpdatedAt()
            || $payment->getDataUpdatedAt() <= $updateExpiration
        ) {
            $this->auditLogger->debug('CO: Updating the payment data', $this->getLoggingContext($payment));
            $userIdentifier = $this->userSession->getUserIdentifier();
            if ($userIdentifier === null) {
                throw new ApiError(Response::HTTP_UNAUTHORIZED, 'No user identifier!');
            }

            $personProviderOptions = [];
            $person = $this->personProvider->getPerson($userIdentifier,
                Options::requestLocalDataAttributes($personProviderOptions, [self::PERSON_TITLE_LOCAL_DATA_ATTRIBUTE]));

            $payment->setLocalIdentifier($person->getIdentifier());
            $payment->setGivenName($person->getGivenName());
            $payment->setFamilyName($person->getFamilyName());
            $payment->setHonorificSuffix($person->getLocalDataValue(self::PERSON_TITLE_LOCAL_DATA_ATTRIBUTE));

            $api = $this->getApiByType($payment->getType(), $payment);
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

    public function updateEntity(PaymentPersistence $paymentPersistence, Payment $payment): bool
    {
        $semesterKey = Tools::convertSemesterToSemesterKey($paymentPersistence->getData());
        $parameters = [
            'semesterKey' => $semesterKey,
            'givenName' => $payment->getGivenName(),
            'familyName' => $payment->getFamilyName(),
            'honorificSuffix' => $payment->getHonorificSuffix(),
        ];
        if ($parameters['honorificSuffix'] !== null) {
            $alternateName = $this->translator->trans('dbp_relay_mono_connector_campusonline.tuition_fee.alternate_name_suffix', $parameters);
        } else {
            $alternateName = $this->translator->trans('dbp_relay_mono_connector_campusonline.tuition_fee.alternate_name', $parameters);
        }
        $payment->setAlternateName($alternateName);

        return true;
    }

    public function notify(PaymentPersistence $paymentPersistence): bool
    {
        $payment = $paymentPersistence;
        // This is just a sanity check, we should never be called in another state
        if ($payment->getNotifiedAt() !== null || $payment->getPaymentStatus() !== PaymentStatus::COMPLETED) {
            throw new \RuntimeException('notify called in an invalid state');
        }

        $this->auditLogger->debug('CO: Registering semester payment', $this->getLoggingContext($payment));
        $type = $payment->getType();
        $api = $this->getApiByType($type, $payment);
        $obfuscatedId = $payment->getLocalIdentifier();
        $amount = (float) $payment->getAmount();
        $semesterKey = Tools::convertSemesterToSemesterKey($payment->getData());
        try {
            $api->registerPaymentForSemester($obfuscatedId, $amount, $semesterKey);
        } catch (ApiException $e) {
            $this->logger->error('Communication error with backend!', ['exception' => $e]);
            throw new ApiError(Response::HTTP_INTERNAL_SERVER_ERROR, 'Communication error with backend!');
        }

        return true;
    }

    public function cleanup(PaymentPersistence $paymentPersistence): bool
    {
        return true;
    }

    private function getLoggingContext(PaymentPersistence $payment): array
    {
        return ['relay-mono-payment-id' => $payment->getIdentifier()];
    }

    public function getApiByType(string $type, ?PaymentPersistence $payment): TuitionFeeApi
    {
        $config = $this->getConfigByType($type);
        $baseUrl = $config['api_url'] ?? '';
        $clientId = $config['client_id'] ?? '';
        $clientSecret = $config['client_secret'] ?? '';

        $connection = new Connection(
            $baseUrl,
            $clientId,
            $clientSecret
        );

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
}
