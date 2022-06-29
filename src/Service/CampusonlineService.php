<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorCampusonlineBundle\Service;

use Dbp\CampusonlineApi\Rest\Api;
use Dbp\Relay\MonoConnectorCampusonlineBundle\Rest\Connection;
use Psr\Log\LoggerInterface;

class CampusonlineService
{
    /**
     * @var Api[]
     */
    private $api = [];

    /**
     * @var array
     */
    private $config = [];

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @return void
     */
    public function setConfig(array $config)
    {
        $this->config = $config;
    }

    /**
     * @return void
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
        if ($this->api !== null) {
            $this->api->setLogger($logger);
        }
    }

    protected function getApiByType(string $type): Connection
    {
        if (!array_key_exists($type, $this->api)) {
            $config = $this->getConfigByType($type);
            $clientId = $config['client_id'] ?? '';
            $clientSecret = $config['client_secret'] ?? '';
            $baseUrl = $config['api_url'] ?? '';
            $api = new Connection($baseUrl, $clientId, $clientSecret);
            if ($this->logger !== null) {
                $api->setLogger($this->logger);
            }
            $this->api[$type] = $api;
        }

        return $this->api[$type];
    }

    protected function getConfigByType(string $type): array
    {
        $config = [];
        if (array_key_exists($type, $this->config['payment_types'])) {
            $config = $this->config['payment_types'][$type];
        }

        return $config;
    }
}
