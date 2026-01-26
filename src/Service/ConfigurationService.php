<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorCampusonlineBundle\Service;

class ConfigurationService
{
    /**
     * @var mixed[]
     */
    private array $config = [];

    /**
     * @param mixed[] $config
     */
    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    /**
     * @return string[]
     */
    public function getTypes(): array
    {
        return array_keys($this->config['tuition_fees']);
    }

    public function getTuitionFeeConfig(string $type): TuitionFeeConfig
    {
        if (array_key_exists($type, $this->config['tuition_fees'])) {
            return new TuitionFeeConfig($this->config['tuition_fees'][$type]);
        }
        throw new \RuntimeException('Unknown payment type: '.$type);
    }
}
