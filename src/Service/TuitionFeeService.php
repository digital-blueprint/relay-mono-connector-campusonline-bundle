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

class TuitionFeeService extends CampusonlineService implements BackendServiceInterface
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

        $uriTemplate = new UriTemplate('/QSYSTEM_TUG/co/tuition-fee-payment-interface/api/open-fees/{obfuscatedId}/current');
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
}
