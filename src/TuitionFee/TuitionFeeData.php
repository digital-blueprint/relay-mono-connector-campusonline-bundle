<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorCampusonlineBundle\TuitionFee;

class TuitionFeeData
{
    /**
     * @var float
     */
    protected $amount;

    /**
     * Amount in Euro.
     *
     * @param float $amount
     */
    public function setAmount(?float $amount): void
    {
        $this->amount = $amount;
    }

    /**
     * @return float
     */
    public function getAmount(): ?float
    {
        return $this->amount;
    }

    /**
     * Semester Key e.g. "2021W".
     *
     * @var string
     */
    protected $semesterKey;

    /**
     * @param string $semesterKey
     */
    public function setSemesterKey(?string $semesterKey): void
    {
        $this->semesterKey = $semesterKey;
    }

    /**
     * @return string
     */
    public function getSemesterKey(): ?string
    {
        return $this->semesterKey;
    }
}
