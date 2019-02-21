<?php

namespace Rct567\DomQuery\Tests;

use Rct567\DomQuery\DomQuery;

class DomQueryAttributesTest extends \PHPUnit\Framework\TestCase
{
    /*
     * Test get attribute value
     */
    public function testGetAttributeValue()
    {
        $this->assertEquals('hello', DomQuery::create('<a title="hello"></a>')->attr('title'));
    }

    /*
     * Test get attribute value on non existing element
     */
    public function testGetAttributeValueOnNonElements()
    {
        $this->assertNull(DomQuery::create('<a title="hello"></a>')->find('nope')->attr('title'));
    }

    /*
     * Test change attribute
     */
    public function testSetAttributeValue()
    {
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
     * Test remove multiple attribute
     */
    public function testRemoveMultipleAttribute()
    {
        $dom = DomQuery::create('<a title="hello" alt="x">Some text</a>');
        $dom->removeAttr('title alt');
        $this->assertEquals('<a>Some text</a>', (string) $dom);
    }

    /*
     * Test remove multiple attribute using array
     */
    public function testRemoveMultipleAttributeArray()
    {
        $dom = DomQuery::create('<a title="hello" alt="x">Some text</a>');
        $dom->removeAttr(['title', 'alt']);
        $this->assertEquals('<a>Some text</a>', (string) $dom);
    }

    /*
     * Test add class name
     */
    public function testAddClass()
    {
        $dom = DomQuery::create('<a></a><a href="hello" class=""></a><a class="before"></a>');
        $dom->find('a')->addClass('after');
        $this->assertEquals('<a class="after"></a><a href="hello" class="after"></a><a class="before after"></a>', (string) $dom);
    }

    /*
     * Test add multiple class names
     */
    public function testAddMultipleClasses()
    {
        $dom = DomQuery::create('<a href="hello" class=""></a><a class="before"></a>');
        $dom->find('a')->addClass('after after2');
        $this->assertEquals('<a href="hello" class="after after2"></a><a class="before after after2"></a>', (string) $dom);
    }

    /*
     * Test add multiple class names using array
     */
    public function testAddMultipleClassesArray()
    {
        $dom = DomQuery::create('<a href="hello" class=""></a><a class="before"></a>');
        $dom->find('a')->addClass(['after', 'after2']);
        $this->assertEquals('<a href="hello" class="after after2"></a><a class="before after after2"></a>', (string) $dom);
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
     * Test remove multiple class names
     */
    public function testRemoveMultipleClass()
    {
        $dom = DomQuery::create('<a class="before go stay"></a><a class="go before"></a>');
        $dom->find('a')->removeClass('before go');
        $this->assertEquals('<a class="stay"></a><a class=""></a>', (string) $dom);
    }

    /*
     * Test remove multiple class names using array
     */
    public function testRemoveMultipleClassArray()
    {
        $dom = DomQuery::create('<a class="before go stay"></a><a class="go before"></a>');
        $dom->find('a')->removeClass(['before', 'go']);
        $this->assertEquals('<a class="stay"></a><a class=""></a>', (string) $dom);
    }

    /*
     * Test remove all class names
     */
    public function testRemoveAllClass()
    {
        $dom = DomQuery::create('<a class="before go stay"></a><a class="go before"></a>');
        $dom->find('a')->removeClass();
        $this->assertEquals('<a class=""></a><a class=""></a>', (string) $dom);
    }

    /*
     * Test toggle class name
     */
    public function testToggleClass()
    {
        $dom = DomQuery::create('<a></a><a class="b"></a>');
        $dom->find('a')->toggleClass('b');
        $this->assertEquals('<a class="b"></a><a class=""></a>', (string) $dom);
    }

    /*
     * Test toggle multiple class names
     */
    public function testToggleMultipleClassArray()
    {
        $dom = DomQuery::create('<a></a><a class="b"></a><a class="a b"></a>');
        $dom->find('a')->toggleClass(['a', 'b']);
        $this->assertEquals('<a class="a b"></a><a class="a"></a><a class=""></a>', (string) $dom);
    }

    /*
     * Test set text
     */
    public function testSetText()
    {
        $dom = DomQuery::create('<a></a>')->text('HI');
        $this->assertEquals('<a>HI</a>', (string) $dom);
    }

    /*
     * Test change text
     */
    public function testTextChange()
    {
        $dom = DomQuery::create('<a>Some text</a>');
        $dom->text('Changed text');
        $this->assertEquals('<a>Changed text</a>', (string) $dom);
        $this->assertEquals('Changed text', $dom->text());
    }
}
