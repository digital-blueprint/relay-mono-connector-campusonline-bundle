<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorCampusonlineBundle\TuitionFee;

use GuzzleHttp\Exception\RequestException;
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

    private static function createResponseError(RequestException $e): ApiException
    {
        $response = $e->getResponse();
        if ($response === null) {
            return new ApiException('Unknown error');
        }
        $data = (string) $response->getBody();
        $json = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
        $status = $json['status'] ?? '???';
        $title = $json['title'] ?? 'Unknown error';
        $type = $json['type'] ?? 'unknown type';
        $message = "[$status] $title ($type)";

        return new ApiException($message);
    }

    /**
     * Returns the API version information (even if not authenticated).
     */
    public function getVersion(): Version
    {
        $client = $this->connection->getClient(false);

        $uriTemplate = new UriTemplate('co/tuition-fee-payment-interface/api/version');
        $uri = (string) $uriTemplate->expand();
        try {
            $response = $client->get($uri);
        } catch (RequestException $e) {
            throw self::createResponseError($e);
        }

        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $versionData = new Version($data['name'], $data['version']);

        return $versionData;
    }

    /**
     * Returns the API version information.
     */
    public function getAuthenticatedVersion(): Version
    {
        $client = $this->connection->getClient();
        $uriTemplate = new UriTemplate('co/tuition-fee-payment-interface/api/version/authenticated-version');
        $uri = (string) $uriTemplate->expand();

        try {
            $response = $client->get($uri);
        } catch (RequestException $e) {
            throw self::createResponseError($e);
        }

        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $versionData = new Version($data['name'], $data['version']);

        return $versionData;
    }

    /**
     * Returns outstanding fees for person and current semester.
     *
     * @param string $obfuscatedId nr_obfuscated
     */
    public function getCurrentFee(string $obfuscatedId): OpenFee
    {
        $client = $this->connection->getClient();
        $uriTemplate = new UriTemplate('co/tuition-fee-payment-interface/api/open-fees/{obfuscatedId}/current');
        $uri = (string) $uriTemplate->expand([
            'obfuscatedId' => $obfuscatedId,
        ]);

        try {
            $response = $client->get($uri);
        } catch (RequestException $e) {
            throw self::createResponseError($e);
        }

        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        $fee = new OpenFee($data['semesterKey'], $data['amount']);

        return $fee;
    }

    /**
     * Returns the outstanding fee for person and given semester.
     *
     * @param string $obfuscatedId nr_obfuscated
     * @param string $semesterKey  semester key e.g. "2021W"
     */
    public function getSemesterFee(string $obfuscatedId, string $semesterKey): OpenFee
    {
        $client = $this->connection->getClient();
        $uriTemplate = new UriTemplate('co/tuition-fee-payment-interface/api/open-fees/{obfuscatedId}/semester/{semesterKey}');
        $uri = (string) $uriTemplate->expand([
            'obfuscatedId' => $obfuscatedId,
            'semesterKey' => $semesterKey,
        ]);

        try {
            $response = $client->get($uri);
        } catch (RequestException $e) {
            throw self::createResponseError($e);
        }

        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->logger->debug('get semester open fee: '.$uri, $data);

        $fee = new OpenFee($data['semesterKey'], $data['amount']);

        return $fee;
    }

    /**
     * Returns a list of outstanding fees for a person for all semesters.
     *
     * @param string $obfuscatedId nr_obfuscated
     */
    public function getAllFees(string $obfuscatedId): OpenFeeList
    {
        $client = $this->connection->getClient();
        $uriTemplate = new UriTemplate('co/tuition-fee-payment-interface/api/open-fees/{obfuscatedId}');
        $uri = (string) $uriTemplate->expand([
            'obfuscatedId' => $obfuscatedId,
        ]);

        try {
            $response = $client->get($uri);
        } catch (RequestException $e) {
            throw self::createResponseError($e);
        }

        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->logger->debug('get open fees: '.$uri, $data);

        $items = [];
        foreach (($data['items'] ?? []) as $item) {
            $fee = new OpenFee($item['semesterKey'], $item['amount']);
            $items[] = $fee;
        }
        $feeList = new OpenFeeList($items, $data['totalAmount']);

        return $feeList;
    }

    /**
     * Enter new payment for the current semester.
     *
     * @param string $obfuscatedId nr_obfuscated
     * @param float  $amount       Amount in Euro
     */
    public function registerPaymentForCurrentSemester(string $obfuscatedId, float $amount)
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

        try {
            $response = $client->post($uri, $data);
        } catch (RequestException $e) {
            throw self::createResponseError($e);
        }

        $this->logger->debug('register payment response', [
            'status' => $response->getStatusCode(),
            'headers' => $response->getHeaders(),
            'body' => (string) $response->getBody(),
        ]);

        // The API docs say that it returns 201 when the payment is complete, so check just to be sure
        if ($response->getStatusCode() !== 201) {
            throw new ApiException('Wrong status code: '.$response->getStatusCode());
        }
    }
}
