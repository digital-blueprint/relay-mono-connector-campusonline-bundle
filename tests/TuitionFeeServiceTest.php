<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorCampusonlineBundle\Tests;

use Dbp\Relay\MonoConnectorCampusonlineBundle\Service\Tools;
use PHPUnit\Framework\TestCase;

class TuitionFeeServiceTest extends TestCase
{
    public function testConvertSemesterToSemesterKey()
    {
        $this->assertSame(Tools::convertSemesterToSemesterKey('22W'), '2022W');
        $this->assertSame(Tools::convertSemesterToSemesterKey('49W'), '2049W');
        $this->assertSame(Tools::convertSemesterToSemesterKey('50S'), '1950S');
        $this->assertSame(Tools::convertSemesterToSemesterKey('2050S'), '2050S');
    }
}
