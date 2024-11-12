<?php

use PHPUnit\Framework\TestCase;

require_once 'utils/utils.php';

/**
 * @covers Utils
 */
final class UtilsTest extends TestCase {

    private $utilsClass;

    public function setUp(): void
    {
        $this->utilsClass = new Utils();
    }

    public function testGetCountryNameForMx() {
        $countryCode = 'MX';
        $expectedResult = 'Mexico';

        $result = $this->utilsClass->getCountryName($countryCode);

        $this->assertEquals($expectedResult, $result);
    }

    public function testGetCountryNameForCo() {
        $countryCode = 'CO';
        $expectedResult = 'Colombia';

        $result = $this->utilsClass->getCountryName($countryCode);

        $this->assertEquals($expectedResult, $result);
    }

    public function testGetCountryNameForPe() {
        $countryCode = 'PE';
        $expectedResult = 'Peru';

        $result = $this->utilsClass->getCountryName($countryCode);

        $this->assertEquals($expectedResult, $result);
    }

    public function testGetCountryNameForDefault() {
        $countryCode = 'ARG';
        $expectedResult = '';

        $result = $this->utilsClass->getCountryName($countryCode);

        $this->assertEquals($expectedResult, $result);
    }
}
