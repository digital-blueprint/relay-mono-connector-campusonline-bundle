<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorCampusonlineBundle\Service;

use Dbp\Relay\CoreBundle\API\UserSessionInterface;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\MonoBundle\Entity\Payment;
use Dbp\Relay\MonoBundle\Entity\PaymentPersistence;
use Dbp\Relay\MonoBundle\Service\BackendServiceInterface;
use Dbp\Relay\MonoConnectorCampusonlineBundle\TuitionFee\ApiException;
use Dbp\Relay\MonoConnectorCampusonlineBundle\TuitionFee\Connection;
use Dbp\Relay\MonoConnectorCampusonlineBundle\TuitionFee\TuitionFeeApi;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

class TuitionFeeService extends AbstractPaymentTypesService implements BackendServiceInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var LdapService
     */
    private $ldapService;

    /**
     * @var UserSessionInterface
     */
    private $userSession;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    public function __construct(
        LoggerInterface $logger,
        TranslatorInterface $translator,
        LdapService $ldapService,
        UserSessionInterface $userSession
    ) {
        $this->logger = $logger;
        $this->translator = $translator;
        $this->ldapService = $ldapService;
        $this->userSession = $userSession;
    }

    public function checkConnectionNoAuth()
    {
        foreach ($this->getTypes() as $type) {
            $api = $this->getApiByType($type);
            $api->getVersion();
        }
    }

    public function checkConnection()
    {
        foreach ($this->getTypes() as $type) {
            $api = $this->getApiByType($type);
            $api->getAuthenticatedVersion();
        }
    }

    public function updateData(PaymentPersistence &$payment): bool
    {
        $changed = false;

        $updateExpiration = new \DateTime('-1 minute');
        if (
            !$payment->getDataUpdatedAt()
            || $payment->getDataUpdatedAt() <= $updateExpiration
        ) {
            $userIdentifier = $this->userSession->getUserIdentifier();
            if ($userIdentifier === null) {
                throw new ApiError(Response::HTTP_UNAUTHORIZED, 'No user identifier!');
            }

            $type = $payment->getType();
            $ldapData = $this->ldapService->getDataByIdentifier($type, $userIdentifier);

            $payment->setLocalIdentifier($ldapData->obfuscatedId);
            $payment->setGivenName($ldapData->givenName);
            $payment->setFamilyName($ldapData->familyName);

            $api = $this->getApiByType($payment->getType());
            $obfuscatedId = $payment->getLocalIdentifier();
            $semesterKey = Tools::convertSemesterToSemesterKey($payment->getData());

            try {
                $tuitionFeeData = $api->getSemesterFee($obfuscatedId, $semesterKey);
            } catch (ApiException $e) {
                $this->logger->error('Communication error with backend!', ['exception' => $e]);
                throw new ApiError(Response::HTTP_INTERNAL_SERVER_ERROR, 'Communication error with backend!');
            }
            $payment->setAmount((string) $tuitionFeeData->getAmount());
            $payment->setCurrency(Payment::PRICE_CURRENCY_EUR);

            $changed = true;
        }

        // These things never hit the backend and are translated, so try to update them always
        $semesterKey = Tools::convertSemesterToSemesterKey($payment->getData());
        $parameters = [
            'semesterKey' => $semesterKey,
            'givenName' => $payment->getGivenName(),
            'familyName' => $payment->getFamilyName(),
        ];
        $alternateName = $this->translator->trans('dbp_relay_mono_connector_campusonline.tuition_fee.alternate_name', $parameters);
        if ($payment->getAlternateName() !== $alternateName) {
            $changed = true;
            $payment->setAlternateName($alternateName);
        }

        return $changed;
    }

    public function notify(PaymentPersistence &$payment): bool
    {
        $notified = false;

        if (!$payment->getNotifiedAt()) {
            if ($payment->getPaymentStatus() === Payment::PAYMENT_STATUS_COMPLETED) {
                $type = $payment->getType();
                $api = $this->getApiByType($type);
                $obfuscatedId = $payment->getLocalIdentifier();
                $amount = (float) $payment->getAmount();
                $semesterKey = Tools::convertSemesterToSemesterKey($payment->getData());
                try {
                    $api->registerPaymentForSemester($obfuscatedId, $amount, $semesterKey);
                } catch (ApiException $e) {
                    $this->logger->error('Communication error with backend!', ['exception' => $e]);
                    throw new ApiError(Response::HTTP_INTERNAL_SERVER_ERROR, 'Communication error with backend!');
                }
            }
            $notified = true;
        }

        return $notified;
    }

    public function cleanup(PaymentPersistence &$payment): bool
    {
        return true;
    }

    private function getApiByType(string $type): TuitionFeeApi
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

        $api = new TuitionFeeApi($connection);
        $api->setLogger($this->logger);

        return $api;
    }
}
