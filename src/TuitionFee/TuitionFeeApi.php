<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorCampusonlineBundle\TuitionFee;

use GuzzleHttp\Exception\RequestException;
use League\Uri\UriTemplate;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class TuitionFeeApi implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private $connection;

    /**
     * @var LoggerInterface
     */
    private $auditLogger;

    /**
     * @var array
     */
    private $loggingContext;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
        $this->logger = new NullLogger();
        $this->auditLogger = new NullLogger();
        $this->loggingContext = [];
    }

    public function setAuditLogger(LoggerInterface $auditLogger): void
    {
        $this->auditLogger = $auditLogger;
    }

    public function setLoggingContext(array $loggingContext): void
    {
        $this->loggingContext = $loggingContext;
    }

    private function withLoggingContext(array $context = []): array
    {
        return array_merge($this->loggingContext, $context);
    }

    private function createResponseError(RequestException $e): ApiException
    {
        $response = $e->getResponse();
        if ($response === null) {
            $this->auditLogger->error('CO: unknown error', $this->withLoggingContext());

            return new ApiException('Unknown error');
        }
        $data = (string) $response->getBody();
        $this->auditLogger->error('CO: parse error response', $this->withLoggingContext(['data' => $data]));

        try {
            $json = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            return new ApiException($e->getMessage());
        }

        $status = $json['status'] ?? '???';
        $title = $json['title'] ?? 'Unknown error';
        $type = $json['type'] ?? 'unknown type';
        $message = "[$status] $title ($type)";

        $error = new ApiException($message);
        $error->httpStatusCode = $response->getStatusCode();

        return $error;
    }

    /**
     * Returns the API version information (even if not authenticated).
     */
    public function getVersion(): Version
    {
        $client = $this->connection->getClient(false);

        $uriTemplate = new UriTemplate('co/tuition-fee-payment-interface/api/version');
        $uri = (string) $uriTemplate->expand();

        $this->auditLogger->debug('CO: getVersion', $this->withLoggingContext());
        try {
            $response = $client->get($uri);
        } catch (RequestException $e) {
            throw $this->createResponseError($e);
        }

        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->auditLogger->debug('CO: parse getVersion response', $this->withLoggingContext(['data' => $data]));
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

        $this->auditLogger->debug('CO: getAuthenticatedVersion', $this->withLoggingContext());

        try {
            $response = $client->get($uri);
        } catch (RequestException $e) {
            throw $this->createResponseError($e);
        }

        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->auditLogger->debug('CO: parse getAuthenticatedVersion response', $this->withLoggingContext(['data' => $data]));
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
        $inputs = [
            'obfuscatedId' => $obfuscatedId,
        ];
        $uri = (string) $uriTemplate->expand($inputs);

        $this->auditLogger->debug('CO: getCurrentFee', $this->withLoggingContext(['inputs' => $inputs]));

        try {
            $response = $client->get($uri);
        } catch (RequestException $e) {
            throw $this->createResponseError($e);
        }

        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->auditLogger->debug('CO: parse getCurrentFee response', $this->withLoggingContext(['data' => $data]));
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
        $inputs = [
            'obfuscatedId' => $obfuscatedId,
            'semesterKey' => $semesterKey,
        ];
        $uri = (string) $uriTemplate->expand($inputs);

        $this->auditLogger->debug('CO: getSemesterFee', $this->withLoggingContext(['inputs' => $inputs]));

        try {
            $response = $client->get($uri);
        } catch (RequestException $e) {
            throw $this->createResponseError($e);
        }

        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->auditLogger->debug('CO: parse getSemesterFee response', $this->withLoggingContext(['data' => $data]));

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
        $inputs = [
            'obfuscatedId' => $obfuscatedId,
        ];
        $uri = (string) $uriTemplate->expand($inputs);

        $this->auditLogger->debug('CO: getAllFees', $this->withLoggingContext(['inputs' => $inputs]));

        try {
            $response = $client->get($uri);
        } catch (RequestException $e) {
            throw $this->createResponseError($e);
        }

        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->auditLogger->debug('CO: parse getAllFees response', $this->withLoggingContext(['data' => $data]));

        $items = [];
        foreach (($data['items'] ?? []) as $item) {
            $fee = new OpenFee($item['semesterKey'], $item['amount']);
            $items[] = $fee;
        }
        $feeList = new OpenFeeList($items, $data['totalAmount']);

        return $feeList;
    }

    /**
     * Enter new payment for given semester.
     *
     * @param string $obfuscatedId nr_obfuscated
     * @param float  $amount       Amount in Euro
     */
    public function registerPaymentForSemester(string $obfuscatedId, float $amount, string $semesterKey): void
    {
        $client = $this->connection->getClient();
        $uriTemplate = new UriTemplate('co/tuition-fee-payment-interface/api/payment-registrations');
        $uri = (string) $uriTemplate->expand();

        $inputs = [
            'personUid' => $obfuscatedId,
            'amount' => $amount,
            'semesterKey' => $semesterKey,
        ];

        $this->auditLogger->debug('CO: registerPaymentForSemester', $this->withLoggingContext(['inputs' => $inputs]));

        try {
            $response = $client->post($uri, ['json' => $inputs]);
        } catch (RequestException $e) {
            throw $this->createResponseError($e);
        }

        $this->auditLogger->debug('CO: registerPaymentForSemester response', $this->withLoggingContext([
            'status' => $response->getStatusCode(),
            'headers' => $response->getHeaders(),
            'body' => (string) $response->getBody(),
        ]));

        // The API docs say that it returns 201 when the payment is complete, so check just to be sure
        if ($response->getStatusCode() !== 201) {
            throw new ApiException('Wrong status code: '.$response->getStatusCode());
        }
    }

    /**
     * Enter new payment for the current semester.
     *
     * @param string $obfuscatedId nr_obfuscated
     * @param float  $amount       Amount in Euro
     */
    public function registerPaymentForCurrentSemester(string $obfuscatedId, float $amount): void
    {
        $client = $this->connection->getClient();
        $uriTemplate = new UriTemplate('co/tuition-fee-payment-interface/api/payment-registrations');
        $uri = (string) $uriTemplate->expand();

        $inputs = [
            'personUid' => $obfuscatedId,
            'amount' => $amount,
        ];
        $this->auditLogger->debug('CO: registerPaymentForCurrentSemester', $this->withLoggingContext(['inputs' => $inputs]));

        try {
            $response = $client->post($uri, ['json' => $inputs]);
        } catch (RequestException $e) {
            throw $this->createResponseError($e);
        }

        $this->auditLogger->debug('CO: registerPaymentForSemester response', $this->withLoggingContext([
            'status' => $response->getStatusCode(),
            'headers' => $response->getHeaders(),
            'body' => (string) $response->getBody(),
        ]));

        // The API docs say that it returns 201 when the payment is complete, so check just to be sure
        if ($response->getStatusCode() !== 201) {
            throw new ApiException('Wrong status code: '.$response->getStatusCode());
        }
    }

    /**
     * For unit testing only.
     */
    public function getConnection(): Connection
    {
        return $this->connection;
    }
}
