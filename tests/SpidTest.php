<?php

declare(strict_types=1);

namespace Infocamere\Telemaco\Tests;

use PHPUnit\Framework\TestCase;
use Infocamere\Telemaco\TelemacoClient;
use Infocamere\Telemaco\Utils\Spid;

final class SpidTest extends TestCase
{
    public function testSpidAruba()
    {
        $telemaco = new TelemacoClient();

        $telemaco->loginSpid('msimonetti', '$7e8waCQ', 'aruba');

        $fondo = $telemaco->fondo();

        $this->assertEquals('0,00', $fondo);
    }

    /*public function testSpidPoste()
    {
        $telemaco = new TelemacoClient();

        $telemaco->loginSpid('m.simonetti@ibs.ve.it', '$7e8waCQ', 'poste');

        $fondo = $telemaco->fondo();

        $this->assertEquals('0,00', $fondo);
    }

    public function testSpidInfocert()
    {
        $telemaco = new TelemacoClient();

        $telemaco->loginSpid('m.simonetti@ibs.ve.it', '$7e8waCQ', 'infocert');

        $fondo = $telemaco->fondo();

        $this->assertEquals('0,00', $fondo);
    }*/
}