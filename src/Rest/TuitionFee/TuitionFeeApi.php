<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorCampusonlineBundle\Rest\TuitionFee;

use Dbp\Relay\MonoConnectorCampusonlineBundle\Rest\Connection;
use League\Uri\UriTemplate;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

class TuitionFeeApi implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
        $this->logger = new NullLogger();
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
        $this->logger->debug('get semester open fee: '.$uri, $data);

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
        $this->logger->debug('get open fees: '.$uri, $data);
        $fees = [];
        foreach ($data['items'] as $item) {
            $fee = new TuitionFeeData();
            $fee->setAmount($item['amount']);
            $fee->setSemesterKey($item['semesterKey']);
            $fees[] = $fee;
        }

        return $fees;
    }

    public function registerPayment(string $obfuscatedId, float $amount): bool
    {
        $client = $this->connection->getClient();
        $uriTemplate = new UriTemplate('co/tuition-fee-payment-interface/api/payment-registrations');
        $uri = (string) $uriTemplate->expand();

        $data = [
            'json' => [
                'personUid' => $obfuscatedId,
                'amount' => $amount,
            ],
        ];
        $this->logger->debug('register payment request: '.$uri, $data);
        $response = $client->post($uri, $data);
        $this->logger->debug('register payment response', [
            'headers' => $response->getHeaders(),
            'body' => (string) $response->getBody(),
        ]);

        return $response->getStatusCode() === 201;
    }
}
