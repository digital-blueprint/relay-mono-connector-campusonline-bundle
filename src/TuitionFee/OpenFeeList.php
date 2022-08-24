<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorCampusonlineBundle\TuitionFee;

class OpenFeeList
{
    /**
     * @var OpenFee[]
     */
    protected $items;

    /**
     * Total of all open fees.
     *
     * @var float
     */
    protected $totalAmount;

    /**
     * @param OpenFee[] $items
     */
    public function __construct(array $items, float $totalAmount)
    {
        $this->items = $items;
        $this->totalAmount = $totalAmount;
    }

    /**
     * @return OpenFee[]
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function getTotalAmount(): float
    {
        return $this->totalAmount;
    }
}
