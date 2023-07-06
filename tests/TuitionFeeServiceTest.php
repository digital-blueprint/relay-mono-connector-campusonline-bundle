<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorCampusonlineBundle\Tests;

use Dbp\Relay\MonoConnectorCampusonlineBundle\Service\Tools;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class TuitionFeeServiceTest extends KernelTestCase
{
    public function testConvertSemesterToSemesterKey()
    {
        $this->assertSame(Tools::convertSemesterToSemesterKey('22W'), '2022W');
        $this->assertSame(Tools::convertSemesterToSemesterKey('49W'), '2049W');
        $this->assertSame(Tools::convertSemesterToSemesterKey('50S'), '1950S');
        $this->assertSame(Tools::convertSemesterToSemesterKey('2050S'), '2050S');
    }

    public function testTranslations()
    {
        self::bootKernel();
        $container = static::getContainer();

        /** @var TranslatorInterface $t */
        $t = $container->get('translator');
        $result = $t->trans(
            'dbp_relay_mono_connector_campusonline.tuition_fee.alternate_name', ['familyName' => 'foo'], null, 'en');
        $this->assertStringContainsString('foo', $result);
        $result = $t->trans(
            'dbp_relay_mono_connector_campusonline.tuition_fee.alternate_name_suffix', ['familyName' => 'foo'], null, 'en');
        $this->assertStringContainsString('foo', $result);
    }
}
