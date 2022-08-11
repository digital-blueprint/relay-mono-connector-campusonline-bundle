<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorCampusonlineBundle\Service;

use Dbp\Relay\CoreBundle\API\UserSessionInterface;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\MonoBundle\Entity\Payment;
use Dbp\Relay\MonoBundle\Entity\PaymentPersistence;
use Dbp\Relay\MonoBundle\Service\BackendServiceInterface;
use Dbp\Relay\MonoConnectorCampusonlineBundle\Rest\Connection;
use Dbp\Relay\MonoConnectorCampusonlineBundle\Rest\TuitionFee\TuitionFeeApi;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

class TuitionFeeService extends AbstractCampusonlineService implements BackendServiceInterface, LoggerAwareInterface
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

    public function __construct(
        LoggerInterface $logger,
        LdapService $ldapService,
        UserSessionInterface $userSession
    ) {
        $this->logger = $logger;
        $this->ldapService = $ldapService;
        $this->userSession = $userSession;
    }

    public function updateData(PaymentPersistence &$payment): bool
    {
        $updateExpiration = new \DateTime('-1 minute');
        if (
            !$payment->getDataUpdatedAt()
            || $payment->getDataUpdatedAt() <= $updateExpiration
        ) {
            $userIdentifier = $this->userSession->getUserIdentifier();
            if (!$userIdentifier) {
                throw ApiError::withDetails(Response::HTTP_UNAUTHORIZED, 'User identifier empty!', 'mono:user-identifier-empty');
            }

            $type = $payment->getType();
            $ldapConfig = $this->getConfigByType($type);
            $this->ldapService->setConfig($ldapConfig);
            $ldapData = $this->ldapService->getDataByIdentifier($userIdentifier);

            $payment->setLocalIdentifier($ldapData->obfuscatedId);
            $payment->setGivenName($ldapData->givenName);
            $payment->setFamilyName($ldapData->familyName);

            try {
                $api = $this->getApiByType($payment->getType());
                $obfuscatedId = $payment->getLocalIdentifier();
                $semesterKey = $this->convertSemesterToSemesterKey($payment->getData());
                $tuitionFeeData = $api->getSemesterFee($obfuscatedId, $semesterKey);
            } catch (\Exception $e) {
                throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Communication error with backend!', 'mono:backend-communication-error', ['message' => $e->getMessage()]);
            }
            $payment->setAmount((string) $tuitionFeeData->getAmountAbs());
            $payment->setCurrency(Payment::PRICE_CURRENCY_EUR);
            $payment->setAlternateName('Studienbeitrag ('.$semesterKey.') für '.$ldapData->givenName.' '.$ldapData->familyName);

            return true;
        }

        return false;
    }

    public function notify(PaymentPersistence &$payment): bool
    {
        $notified = false;

        if (!$payment->getNotifiedAt()) {
            try {
                $type = $payment->getType();
                $api = $this->getApiByType($type);
                $obfuscatedId = $payment->getLocalIdentifier();
                $amount = (float) $payment->getAmount();
                $notified = $api->registerPayment($obfuscatedId, $amount);
            } catch (\Exception $e) {
                throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Communication error with backend!', 'mono:backend-communication-error', ['message' => $e->getMessage()]);
            }
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

    private function convertSemesterToSemesterKey(string $semester): string
    {
        $term = preg_replace('/^[^SW]*([SW])[^SW]*$/', '$1', $semester);
        $year = preg_replace('/^[^0-9]*([0-9]{2,4})[^0-9]*$/', '$1', $semester);
        if (strlen($year) === 2) {
            // first tuition fee in CAMPUSonline is "1950W"
            if ((int) $year >= 50) {
                $year = '19'.$year;
            } else {
                $year = '20'.$year;
            }
        }
        $semesterKey = $year.$term;

        return $semesterKey;
    }
}
