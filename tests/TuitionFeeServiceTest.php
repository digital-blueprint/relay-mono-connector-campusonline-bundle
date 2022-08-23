<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorCampusonlineBundle\Tests;

use Dbp\Relay\MonoConnectorCampusonlineBundle\Service\TuitionFeeService;
use PHPUnit\Framework\TestCase;

class TuitionFeeServiceTest extends TestCase
{
    public function testConvertSemesterToSemesterKey()
    {
        $this->assertSame(TuitionFeeService::convertSemesterToSemesterKey('22W'), '2022W');
        $this->assertSame(TuitionFeeService::convertSemesterToSemesterKey('49W'), '2049W');
        $this->assertSame(TuitionFeeService::convertSemesterToSemesterKey('50S'), '1950S');
        $this->assertSame(TuitionFeeService::convertSemesterToSemesterKey('2050S'), '2050S');
    }
}
