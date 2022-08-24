<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorCampusonlineBundle\Service;

abstract class AbstractPaymentTypesService
{
    /**
     * @var array
     */
    private $config = [];

    /**
     * @return void
     */
    public function setConfig(array $config)
    {
        $this->config = $config;
    }

    protected function getConfigByType(string $type): array
    {
        if (array_key_exists($type, $this->config['payment_types'])) {
            return $this->config['payment_types'][$type];
        } else {
            throw new \RuntimeException('Unknown payment type: '.$type);
        }
    }
}
