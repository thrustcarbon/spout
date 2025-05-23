<?php

namespace Box\Spout\Common\Helper\Escaper;

use PHPUnit\Framework\TestCase;

/**
 * Class ODSTest
 */
class ODSTest extends TestCase
{
    /**
     * @return array
     */
    public static function dataProviderForTestEscape()
    {
        return [
            ['test', 'test'],
            ['carl\'s "pokemon"', 'carl&#039;s &quot;pokemon&quot;'],
            ["\n", "\n"],
            ["\r", "\r"],
            ["\t", "\t"],
            ["\v", '�'],
            ["\f", '�'],
        ];
    }

    /**
     * @dataProvider dataProviderForTestEscape
     *
     * @param string $stringToEscape
     * @param string $expectedEscapedString
     * @return void
     */
    public function testEscape($stringToEscape, $expectedEscapedString)
    {
        $escaper = new ODS();
        $escapedString = $escaper->escape($stringToEscape);

        $this->assertEquals($expectedEscapedString, $escapedString, 'Incorrect escaped string');
    }
}
