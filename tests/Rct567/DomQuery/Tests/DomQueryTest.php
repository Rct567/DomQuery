<?php

namespace Rct567\DomQuery\Tests;

use Rct567\DomQuery\DomQuery;

class DomQueryTest extends \PHPUnit\Framework\TestCase
{
    /*
     * Test filters, first and last (with method and pseudo selector)
     */
    public function testFirstAndLastFilters()
    {
        $html = '<div id="main" class="root">
            <div class="level-a" id="first-child-a">first-child-a</div>
            <div class="level-a" id="second-child-a">
                <p>Hai</p>
                <div class="level-b"></div>
                <div class="level-b"></div>
                <div class="level-b">
                    <div class="level-c"></div>
                </div>
            </div>
            <div class="level-a" id="last-child-a">last-child-a</div>
        </div>';

        $dom = new DomQuery($html);
        $this->assertEquals('main', $dom->find('div')->first()->getAttribute('id'));
        $this->assertEquals('main', $dom->children()->first()->getAttribute('id'));

        // first and last method, check id
        $this->assertEquals('first-child-a', $dom->find('.root div')->first()->attr('id'));
        $this->assertEquals('last-child-a', $dom->find('.root div')->last()->attr('id'));

        // first and last via pseudo selector, check id
        $this->assertEquals('first-child-a', $dom->find('.root div:first')->attr('id')); // id of first div inside .root
        $this->assertEquals('last-child-a', $dom->find('.root div:last')->attr('id')); // id of last div inside .root

        // first and last via get method, check id
        $this->assertEquals('first-child-a', $dom->find('.root div')->get(0)->textContent);
        $this->assertEquals('last-child-a', $dom->find('.root div')->get(-1)->textContent);
    }

    /*
     * Test basic usage examples from readme
     */
    public function testBasicUsageReadmeExamples()
    {
        $dom = new DomQuery('<div><h1 class="title">Hello</h1></div>');

        $this->assertEquals('Hello', $dom->find('div')->text());
        $this->assertEquals('<div><h1 class="title">Hello</h1></div>', $dom->find('div')->prop('outerHTML'));
        $this->assertEquals('<h1 class="title">Hello</h1>', $dom->find('div')->html());
        $this->assertEquals('title', $dom->find('div > h1')->class);
        $this->assertEquals('title', $dom->find('div > h1')->attr('class'));
        $this->assertEquals('h1', $dom->find('div > h1')->prop('tagName'));
        $this->assertEquals('h1', $dom->find('div')->children('h1')->prop('tagName'));
        $this->assertEquals('<h1 class="title">Hello</h1>', (string) $dom->find('div > h1'));
        $this->assertEquals('h1', $dom->find('div')->children('h1')->prop('tagName'));
        $this->assertCount(2, $dom->find('div, h1'));
    }

    /*
     * Test simple links lookup
     */
    public function testGetLink()
    {
        $dom = new DomQuery;

        $dom->loadContent('<!DOCTYPE html> <html> <head></head> <body> <p><a href="test.html"></a></p>
        <a href="test2.html">X</a> </body> </html>');

        $this->assertEquals('html', $dom->nodeName);

        $link_found = $dom->find('p > a[href^="test"]');
        $this->assertCount(1, $link_found, 'finding link'); // 1 link is inside p
        $this->assertEquals(1, $link_found->length, 'finding link');
        $this->assertTrue(isset($link_found->href), 'finding link');
        $this->assertEquals('test.html', $link_found->href, 'finding link');
        $this->assertEquals('test.html', $link_found->attr('href'), 'finding link');
        $this->assertEquals('a', $link_found->nodeName);
        $this->assertEquals('a', $link_found->prop('tagName'));
        $this->assertInstanceOf(DomQuery::class, $link_found);
        $this->assertInstanceOf(DomQuery::class, $link_found[0]);

        $link_found = $dom->find('body > a');
        $this->assertCount(1, $link_found, 'finding link');
        $this->assertEquals('X', $link_found->text());
    }

    /*
     * Test loading utf8 html
     */
    public function testLoadingUf8AndGettingSameContent()
    {
        $html = '<div><h1>Iñtërnâtiônàlizætiøn</h1></div><a>k</a>';
        $dom = new DomQuery($html);

        $this->assertEquals($html, (string) $dom); // same result
        $this->assertEquals('<h1>Iñtërnâtiônàlizætiøn</h1>', (string) $dom->find('h1')); // same header
        $this->assertEquals('Iñtërnâtiônàlizætiøn', $dom->find('h1')->text()); // same text
        $this->assertEquals('Iñtërnâtiônàlizætiøn', $dom->text());
    }

    /*
     * Test loading html with new lines
     */
    public function testLoadingWithNewLines()
    {
        $dom = new DomQuery("<div>\n<h1>X</h1>\n</div>");
        $this->assertEquals("<div>\n<h1>X</h1>\n</div>", (string) $dom);
    }

    /*
     * Test preserve attribute without value
     */
    public function testPreserverAttributeWithoutValue()
    {
        $dom = new DomQuery('<div selected>a</div>');
        $this->assertEquals('<div selected>a</div>', (string) $dom);
    }

    /*
     * Test get data
     */
    public function testGetData()
    {
        $dom = new DomQuery('<div data-role="page"></div>');
        $this->assertEquals('page', $dom->data('role'));
        $this->assertNull($dom->data('nope'));
        $this->assertEquals((object) array('role' => 'page'), $dom->data());
    }

    /*
     * Test get object data
     */
    public function testGetObjectData()
    {
        $dom = new DomQuery('<div data-options=\'{"name":"John"}\'></div>');
        $this->assertEquals((object) array('name' => 'John'), $dom->data('options'));
        $this->assertEquals((object) array('options' => (object) array('name' => 'John')), $dom->data());
    }

    /*
     * Test set string as data, surviving wrap
     */
    public function testSetStringData()
    {
        $dom = new DomQuery('<div> <a data-role=""></a> </div>');
        $dom->find('a')->data('role', 'page');
        $this->assertEquals('page', $dom->find('a')->data('role'));
        $dom->find('a')->wrap('<div>');
        $this->assertEquals('page', $dom->find('a')->data('role'));
        $this->assertEquals((object) array('role' => 'page'), $dom->find('a')->data());
    }

    /*
     * Test set instance as data, surviving wrap
     */
    public function testSetInstanceData()
    {
        $dom = new DomQuery('<div> <a data-role=""></a> </div>');
        $dom->find('a')->data('role', $this);
        $this->assertEquals($this, $dom->find('a')->data('role'));
        $dom->find('a')->wrap('<div>');
        $this->assertEquals($this, $dom->find('a')->data('role'));
    }

    /*
     * Test remove data
     */
    public function testRemoveData()
    {
        $dom = new DomQuery('<div> <a></a> </div>');
        $dom->find('a')->data('role', 'page');
        $dom->find('a')->data('other', 'still-there');
        $this->assertEquals('page', $dom->find('a')->data('role'));
        $this->assertEquals('still-there', $dom->find('a')->data('other'));
        $dom->find('a')->removeData('role');
        $this->assertEquals(null, $dom->find('a')->data('role'));
        $this->assertEquals('still-there', $dom->find('a')->data('other'));
    }

    /*
     * Test remove all data
     */
    public function testRemoveAllData()
    {
        $dom = new DomQuery('<div> <a data-stay="x"></a> </div>');
        $dom->find('a')->data('role', 'page');
        $dom->find('a')->data('other', 'also-gone');
        $this->assertEquals('page', $dom->find('a')->data('role'));
        $dom->find('a')->removeData();
        $this->assertEquals(null, $dom->find('a')->data('role'));
        $this->assertEquals(null, $dom->find('a')->data('other'));
        $this->assertEquals('<div> <a data-stay="x"></a> </div>', (string) $dom);
    }

    /*
     * Test get index of position in result that matches selector
     */
    public function testIndexWithSelector()
    {
        $dom = new DomQuery('<div> <a class="a">1</a><a class="b">2</a><a class="c">3</a><a class="d">4</a> </div>');
        $this->assertEquals(-1, $dom->find('a')->index('.nope'));
        $this->assertEquals(0, $dom->find('a')->index('.a'));
        $this->assertEquals(1, $dom->find('a')->index($dom->find('.b')));
        $this->assertEquals(2, $dom->find('a')->index($dom->find('a')->get(2)));
        $this->assertEquals(3, $dom->find('a')->index('.d'));
    }

    /*
     * Test get index of position in dom
     */
    public function testIndex()
    {
        $dom = new DomQuery('<div> <a class="a">1</a><a class="b">2</a><a class="c">3</a><a class="d">4</a> </div>');
        $this->assertEquals(-1, $dom->find('nope')->index());
        $this->assertEquals(0, $dom->find('div')->index());
        $this->assertEquals(0, $dom->find('.a')->index());
        $this->assertEquals(3, $dom->find('.d')->index());
    }

    /*
     * Test change attribute without value in xml write mode
     */
    public function testChangeAttributeWithoutValueInXmlWriteMode()
    {
        $dom = new DomQuery('<div selected>a</div>');
        $dom->xml_mode = true;
        $this->assertEquals("<div selected=\"selected\">a</div>", (string) $dom);
    }

    /*
     * Test change attribute without value in xml read+write mode
     */
    public function testChangeAttributeWithoutValueInXmlReadWriteModeWithDeclaration()
    {
        $dom = new DomQuery("<?xml version=\"1.0\" encoding=\"iso-8859-1\"?>\n\n<div selected>a</div>");
        $this->assertEquals("<?xml version=\"1.0\" encoding=\"iso-8859-1\"?>\n\n<div>a</div>", (string) $dom);
    }

    /*
     * Test each iteration
     */
    public function testEachIteration()
    {
        $dom = new DomQuery('<p> <a>1</a> <a>2</a> <span></span> </p>');

        $result = array();

        $dom->find('a')->each(function ($elm, $i) use (&$result) {
            $result[$i] = $elm;
        });

        $this->assertCount(2, $result);
        $this->assertInstanceOf(\DOMNode::class, $result[0]);
    }

    /*
     * Test instance without nodes
     */
    public function testDomQueryNoDocument()
    {
        $dom = new DomQuery;
        $this->assertCount(0, $dom);
        $this->assertFalse(isset($dom[0]));
        $this->assertCount(0, $dom->find('*'));
        $this->assertCount(0, $dom->children());

        $this->assertNull($dom->get(0));

        $this->assertInstanceOf(DomQuery::class, $dom[0]);
        $this->assertInstanceOf(DomQuery::class, $dom->children('a')->first('b')->last('c')->find('d'));
        $this->assertInstanceOf(DomQuery::class, $dom->parent('a')->next('b')->prev('c'));
        $this->assertInstanceOf(DomQuery::class, $dom->not('a')->filter('b'));

        $this->assertNull($dom->getDocument());
        $this->assertNull($dom->getXpathQuery());
        $this->assertNull($dom->getCssQuery());
        $this->assertNull($dom->getCssQuery());

        $num = 0;
        foreach ($dom as $node) {
            $num++;
        }

        $this->assertEquals(0, $num);
    }

    /*
     * Test constructor with selector and html context
     */
    public function testConstuctorWithSelectorAndHtmlContext()
    {
        $dom = new DomQuery('div', '<div>X</div><p>Nope</p>');
        $this->assertEquals('<div>X</div>', (string) $dom);
    }

    /*
     * Test constructor with selector and self as context
     */
    public function testConstuctorWithSelectorAndSelfContext()
    {
        $dom = new DomQuery('div', new DomQuery('<div>X</div><p>Nope</p>'));
        $this->assertEquals('<div>X</div>', (string) $dom);
    }

    /*
     * Test to array and array as constructor argument
     */
    public function testConstructorWithNodesArray()
    {
        $nodes_array = DomQuery::create('<div>X</div><p>Nope</p>')->toArray();
        $this->assertContainsOnlyInstancesOf(\DOMNode::class, $nodes_array);
        $this->assertCount(2, $nodes_array);
        $dom = new DomQuery($nodes_array);
        $this->assertEquals('<div>X</div><p>Nope</p>', (string) $dom);
    }

    /*
     * Test constructor exception by giving unknown object argument
     */
    public function testConstructorUnknownObjectArgument()
    {
        $this->expectException(\InvalidArgumentException::class);
        $dom = new DomQuery(new \stdClass);
    }

    /*
     * Test constructor exception by giving unknown argument
     */
    public function testConstructorUnknownArgument()
    {
        $this->expectException(\InvalidArgumentException::class);
        $dom = new DomQuery(null);
    }

    /*
     * Test constructor exception by giving double document
     */
    public function testConstructorDoubleDocument()
    {
        $this->expectException(\Exception::class);
        $dom = new DomQuery(new \DOMDocument(), new \DOMDocument());
    }

    /*
     * Test constructor exception caused by giving empty node list
     */
    public function testConstructorEmptyNodeList()
    {
        $this->expectException(\Exception::class);
        $empty_node_list = (new \DOMDocument())->getElementsByTagName('nope');
        $dom = new DomQuery($empty_node_list);
    }

    /*
     * Test exception caused by array access with invalid offset key
     */
    public function testArrayAccessGetWithInvalidOffsetKey()
    {
        $this->expectException(\BadMethodCallException::class);
        $dom = (new DomQuery())['invalid'];
    }

    /*
     * Test exception caused by array access set value
     */
    public function testArrayAccessSet()
    {
        $this->expectException(\BadMethodCallException::class);
        $dom = new DomQuery();
        $dom['invalid'] = null;
    }

    /*
     * Test exception caused by array access unset
     */
    public function testArrayAccessUnset()
    {
        $this->expectException(\BadMethodCallException::class);
        $dom = new DomQuery();
        unset($dom['invalid']);
    }

    /*
     * Test exception caused by non existing method call
     */
    public function testNonExistingMethodCall()
    {
        $this->expectException(\Exception::class);
        $dom = (new DomQuery())->nope();
    }

    /*
     * Test invalid xpath expression
     */
    public function testInvalidXpath()
    {
        $this->expectException(\Exception::class);
        $dom = new DomQuery('<div>');
        $dom->xpathQuery("\n[]");
    }
}
