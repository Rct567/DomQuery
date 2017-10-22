<?php

namespace Rct567\DomQuery\Tests;

use Rct567\DomQuery\DomQuery;

class DomQueryAttributesTest extends \PHPUnit\Framework\TestCase
{
    /*
     * Test create single node and get attribute value
     */
    public function testSingleNodeAttr()
    {
        $this->assertEquals('hello', DomQuery::create('<a title="hello"></a>')->attr('title'));
    }

    /*
     * Test create single node and change attribute
     */
    public function testSingleNodeAttrChange()
    {
        $this->assertEquals('oke', DomQuery::create('<a title="hello"></a>')->attr('title', 'oke')->attr('title'));

        $this->assertEquals('<a title="oke"></a>', (string) DomQuery::create('<a title="hello"></a>')->attr('title', 'oke'));
    }

    /*
     * Test remove attribute
     */
    public function testRemoveAttribute()
    {
        $dom = DomQuery::create('<a title="hello">Some text</a>');
        $dom->removeAttr('title');
        $this->assertEquals('<a>Some text</a>', (string) $dom);
    }

    /*
     * Test add class name
     */
    public function testAddClass()
    {
        $dom = DomQuery::create('<a href="hello" class=""></a><a class="before"></a>');
        $dom->find('a')->addClass('after');
        $this->assertEquals('<a href="hello" class="after"></a><a class="before after"></a>', (string) $dom);
    }

    /*
     * Test has class
     */
    public function testHasClass()
    {
        $dom = DomQuery::create('<a href="hello" class=""></a><a class="before"></a>');
        $this->assertFalse($dom->find('a')->first()->hasClass('before'));
        $this->assertTrue($dom->find('a')->last()->hasClass('before'));
    }

    /*
     * Test remove class name
     */
    public function testRemoveClass()
    {
        $dom = DomQuery::create('<a class=""></a><a class="before"></a><a class="before stay"></a>');
        $dom->find('a')->removeClass('before');
        $this->assertEquals('<a class=""></a><a class=""></a><a class="stay"></a>', (string) $dom);
    }

    /*
     * Test remove multiple class name
     */
    public function testRemoveMultipleClass()
    {
        $dom = DomQuery::create('<a class="before go stay"></a><a class="go before"></a>');
        $dom->find('a')->removeClass('before go');
        $this->assertEquals('<a class="stay"></a><a class=""></a>', (string) $dom);
    }

    /*
     * Test change text
     */
    public function testSingleNodeTextChange()
    {
        $dom = DomQuery::create('<a title="hello">Some text</a>');
        $dom->text('Changed text');
        $this->assertEquals('<a title="hello">Changed text</a>', (string) $dom);
        $this->assertEquals('Changed text', $dom->text());
    }
}
