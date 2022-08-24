<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorCampusonlineBundle\TuitionFee;

class Version
{
    /**
     * For example "tuinx".
     *
     * @var string
     */
    protected $name;

    /**
     * For example "1.0.0-SNAPSHOT".
     *
     * @var string
     */
    protected $version;

    public function __construct(string $name, string $version)
    {
        $this->name = $name;
        $this->version = $version;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
