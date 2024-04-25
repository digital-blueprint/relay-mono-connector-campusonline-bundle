<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorCampusonlineBundle\Service;

class ConfigurationService
{
    private array $config = [];

    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    /**
     * @return string[]
     */
    public function getTypes(): array
    {
        return array_keys($this->config['payment_types']);
    }

    /**
     * @return array<string,string>
     */
    public function getPaymentTypeConfig(string $type): array
    {
        if (array_key_exists($type, $this->config['payment_types'])) {
            return $this->config['payment_types'][$type];
        } else {
            throw new \RuntimeException('Unknown payment type: '.$type);
        }
    }
}
