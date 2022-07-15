<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorCampusonlineBundle\Service;

abstract class AbstractCampusonlineService
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
        $config = [];
        if (array_key_exists($type, $this->config['payment_types'])) {
            $config = $this->config['payment_types'][$type];
        }

        return $config;
    }
}
