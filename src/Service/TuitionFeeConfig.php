<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorCampusonlineBundle\Service;

class TuitionFeeConfig
{
    /**
     * @param mixed[] $config
     */
    public function __construct(private array $config)
    {
    }

    public function getApiUrl(): string
    {
        return $this->config['api_url'];
    }

    public function getClientId(): string
    {
        return $this->config['client_id'];
    }

    public function getClientSecret(): string
    {
        return $this->config['client_secret'];
    }
}
