<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorCampusonlineBundle\Service;

use Dbp\CampusonlineApi\Rest\Tools;
use Dbp\Relay\MonoBundle\Entity\Payment;
use Dbp\Relay\MonoBundle\Entity\PaymentPersistence;
use Dbp\Relay\MonoBundle\Service\BackendServiceInterface;
use Dbp\Relay\MonoConnectorCampusonlineBundle\Rest\TuitionFee\TuitionFeeData;
use GuzzleHttp\Exception\RequestException;
use League\Uri\UriTemplate;
use Psr\Http\Message\ResponseInterface;

class TuitionFeeService extends AbstractCampusonlineService implements BackendServiceInterface
{
    private const FIELD_AMOUNT = 'amount';
    private const FIELD_SEMESTER_KEY = 'semesterKey';

    public function updateData(PaymentPersistence &$payment): bool
    {
        if (!$payment->getDataUpdatedAt()) {
            $api = $this->getApiByType($payment->getType());
            $tuitionFeeData = $this->getCurrentOpenFeeByObfuscatedId($api, $payment);
            $payment->setAmount((string) $tuitionFeeData->getAmountAbs());
            $payment->setCurrency(Payment::PRICE_CURRENCY_EUR);

            return true;
        }

        return false;
    }

    private function getCurrentOpenFeeByObfuscatedId($api, PaymentPersistence $payment): ?TuitionFeeData
    {
        $tuitionFeeData = null;

        $uriTemplate = new UriTemplate('co/tuition-fee-payment-interface/api/open-fees/{obfuscatedId}/current');
        $uri = (string) $uriTemplate->expand([
            'obfuscatedId' => $payment->getLocalIdentifier(),
        ]);
        try {
            $client = $api->getClient();
            $response = $client->get($uri);
            $tuitionFeeData = $this->parseTuitionFeeDataResponse($response);
        } catch (RequestException $e) {
        }

        return $tuitionFeeData;
    }

    private function parseTuitionFeeDataResponse(ResponseInterface $response): TuitionFeeData
    {
        $content = (string) $response->getBody();
        $json = Tools::decodeJSON($content, true);

        $tuitionFeeData = new TuitionFeeData();
        $tuitionFeeData->setAmount($json[self::FIELD_AMOUNT] ?? null);
        $tuitionFeeData->setSemesterKey($json[self::FIELD_SEMESTER_KEY] ?? null);

        return $tuitionFeeData;
    }

    public function notify(PaymentPersistence &$payment): bool
    {
        if (!$payment->getNotifiedAt()) {
            $api = $this->getApiByType($payment->getType());

            return $this->registerPayment($api, $payment);
        }

        return false;
    }

    private function registerPayment($api, PaymentPersistence $payment)
    {
        $uriTemplate = new UriTemplate('co/tuition-fee-payment-interface/api/payment-registrations');
        $uri = (string) $uriTemplate->expand([
            'obfuscatedId' => $payment->getLocalIdentifier(),
        ]);
        try {
            $client = $api->getClient();
            /** @var ResponseInterface $response */
            $response = $client->post($uri, [
                'form_params' => [
                    'personUid' => $payment->getLocalIdentifier(),
                    'amount' => $payment->getAmount(),
                ],
            ]);

            return $response->getStatusCode() === 200;
        } catch (RequestException $e) {
        }

        return false;
    }
}
