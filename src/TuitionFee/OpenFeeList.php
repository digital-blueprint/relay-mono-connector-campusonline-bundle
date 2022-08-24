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
     * @return OpenFee[]
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * @param OpenFee[] $items
     */
    public function setItems(array $items): void
    {
        $this->items = $items;
    }

    public function getTotalAmount(): float
    {
        return $this->totalAmount;
    }

    public function setTotalAmount(float $totalAmount): void
    {
        $this->totalAmount = $totalAmount;
    }
}
