<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorCampusonlineBundle\Rest\TuitionFee;

use Dbp\Relay\MonoConnectorCampusonlineBundle\Rest\Connection;
use League\Uri\UriTemplate;

class TuitionFeeApi
{
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function getVersion(): VersionData
    {
        $client = $this->connection->getClient();

        $uriTemplate = new UriTemplate('co/tuition-fee-payment-interface/api/version');
        $uri = (string) $uriTemplate->expand();
        $response = $client->get($uri);

        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $versionData = new VersionData();
        $versionData->name = $data['name'];
        $versionData->version = $data['version'];

        return $versionData;
    }

    public function getAuthenticatedVersion(): VersionData
    {
        $client = $this->connection->getClient();
        $uriTemplate = new UriTemplate('co/tuition-fee-payment-interface/api/authenticated-version');
        $uri = (string) $uriTemplate->expand();
        $response = $client->get($uri);

        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $versionData = new VersionData();
        $versionData->name = $data['name'];
        $versionData->version = $data['version'];

        return $versionData;
    }

    public function getCurrentFee(string $obfuscatedId): TuitionFeeData
    {
        $client = $this->connection->getClient();
        $uriTemplate = new UriTemplate('co/tuition-fee-payment-interface/api/open-fees/{obfuscatedId}/current');
        $uri = (string) $uriTemplate->expand([
            'obfuscatedId' => $obfuscatedId,
        ]);
        $response = $client->get($uri);
        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        $fee = new TuitionFeeData();
        $fee->setAmount($data['amount']);
        $fee->setSemesterKey($data['semesterKey']);

        return $fee;
    }

    public function getSemesterFee(string $obfuscatedId, string $semesterKey): TuitionFeeData
    {
        $client = $this->connection->getClient();
        $uriTemplate = new UriTemplate('co/tuition-fee-payment-interface/api/open-fees/{obfuscatedId}/semester/{semesterKey}');
        $uri = (string) $uriTemplate->expand([
            'obfuscatedId' => $obfuscatedId,
            'semesterKey' => $semesterKey,
        ]);
        $response = $client->get($uri);
        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        $fee = new TuitionFeeData();
        $fee->setAmount($data['amount']);
        $fee->setSemesterKey($data['semesterKey']);

        return $fee;
    }

    /**
     * @return TuitionFeeData[]
     */
    public function getFees(string $obfuscatedId): array
    {
        $client = $this->connection->getClient();
        $uriTemplate = new UriTemplate('co/tuition-fee-payment-interface/api/open-fees/{obfuscatedId}');
        $uri = (string) $uriTemplate->expand([
            'obfuscatedId' => $obfuscatedId,
        ]);
        $response = $client->get($uri);
        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $fees = [];
        foreach ($data['items'] as $item) {
            $fee = new TuitionFeeData();
            $fee->setAmount($item['amount']);
            $fee->setSemesterKey($item['semesterKey']);
            $fees[] = $fee;
        }

        return $fees;
    }

    public function registerPayment(string $obfuscatedId, float $amount): void
    {
        $client = $this->connection->getClient();
        $uriTemplate = new UriTemplate('co/tuition-fee-payment-interface/api/payment-registrations');
        $uri = (string) $uriTemplate->expand();

        $client->post($uri, [
            'form_params' => [
                'personUid' => $obfuscatedId,
                'amount' => $amount,
            ],
        ]);
    }
}
