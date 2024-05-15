<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorCampusonlineBundle\TuitionFee;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

class Connection implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private $baseUrl;
    private $clientId;
    private $clientSecret;

    /**
     * @var ?callable
     */
    private $clientHandler;

    /**
     * @var ?string
     */
    private $token;

    public function __construct(string $baseUrl, string $clientId, string $clientSecret)
    {
        $this->baseUrl = $baseUrl;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->logger = new NullLogger();
    }

    public function setClientHandler(?callable $handler): void
    {
        $this->clientHandler = $handler;
    }

    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    public function getClient(bool $authenticated = true): Client
    {
        $stack = HandlerStack::create($this->clientHandler);
        $base_uri = $this->baseUrl;
        if (substr($base_uri, -1) !== '/') {
            $base_uri .= '/';
        }

        $client_options = [
            'base_uri' => $base_uri,
            'handler' => $stack,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ];

        if ($authenticated) {
            $token = $this->getToken();
            $client_options['headers']['Authorization'] = 'Bearer '.$token;
        }

        if ($this->logger !== null) {
            $stack->push(Tools::createLoggerMiddleware($this->logger));
        }

        $client = new Client($client_options);

        return $client;
    }

    private function getToken(): string
    {
        if ($this->token === null) {
            $this->refreshToken();
            assert($this->token !== null);
        }

        return $this->token;
    }

    private function refreshToken(): void
    {
        $stack = HandlerStack::create($this->clientHandler);
        $base_uri = $this->baseUrl;
        if (substr($base_uri, -1) !== '/') {
            $base_uri .= '/';
        }
        $client_options = [
            'handler' => $stack,
            'base_uri' => $base_uri,
        ];
        if ($this->logger !== null) {
            $stack->push(Tools::createLoggerMiddleware($this->logger));
        }
        $client = new Client($client_options);

        try {
            $response = $client->get('co/public/api/environment');
        } catch (RequestException $e) {
            throw new ApiException($e->getMessage());
        }
        $data = $response->getBody()->getContents();
        $environment = json_decode($data, true, flags: JSON_THROW_ON_ERROR);
        $authServerUrl = $environment['authServerUrl'] ?? null;
        if ($authServerUrl === null) {
            throw new ApiException('authServerUrl missing');
        }

        $response = $client->get($authServerUrl.'/.well-known/openid-configuration');
        $data = $response->getBody()->getContents();
        $openIdConfig = json_decode($data, true, flags: JSON_THROW_ON_ERROR);
        $tokenEndpoint = $openIdConfig['token_endpoint'] ?? null;
        if ($tokenEndpoint === null) {
            throw new ApiException('token_endpoint missing');
        }

        try {
            $response = $client->post($tokenEndpoint, [
                'form_params' => [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'grant_type' => 'client_credentials',
                ],
            ]);
        } catch (RequestException $e) {
            throw new ApiException($e->getMessage());
        }
        $data = $response->getBody()->getContents();

        $token = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
        $this->token = $token['access_token'];
    }
}
