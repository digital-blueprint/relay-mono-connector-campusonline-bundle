<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorCampusonlineBundle\TuitionFee;

class OpenFee
{
    /**
     * Semester Key e.g. "2021W".
     *
     * @var string
     */
    protected $semesterKey;

    /**
     * Amount in Euro.
     *
     * @var float
     */
    protected $amount;

    public function __construct(string $semesterKey, float $amount)
    {
        $this->semesterKey = $semesterKey;
        $this->amount = $amount;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getSemesterKey(): string
    {
        return $this->semesterKey;
    }
}
