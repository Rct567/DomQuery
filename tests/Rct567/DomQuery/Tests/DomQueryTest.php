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
            <div class="level-a" id="first-child-a"></div>
            <div class="level-a" id="second-child-a">
                <p>Hai</p>
                <div class="level-b"></div>
                <div class="level-b"></div>
                <div class="level-b">
                    <div class="level-c"></div>
                </div>
            </div>
            <div class="level-a" id="last-child-a"></div>
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
        $this->assertEquals('first-child-a', $dom->find('.root div')->get(0)->getAttribute('id'));
        $this->assertEquals('last-child-a', $dom->find('.root div')->get(-1)->getAttribute('id'));
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
        $this->assertEquals(2, count($dom->find('div, h1')));
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
        $this->assertEquals(1, count($link_found), 'finding link'); // 1 link is inside p
        $this->assertEquals(1, $link_found->length, 'finding link');
        $this->assertTrue(isset($link_found->href), 'finding link');
        $this->assertEquals('test.html', $link_found->href, 'finding link');
        $this->assertEquals('test.html', $link_found->attr('href'), 'finding link');
        $this->assertEquals('a', $link_found->nodeName);
        $this->assertEquals('a', $link_found->prop('tagName'));
        $this->assertInstanceOf(DomQuery::class, $link_found);
        $this->assertInstanceOf(DomQuery::class, $link_found[0]);

        $link_found = $dom->find('body > a');
        $this->assertEquals(1, count($link_found), 'finding link');
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
     * Test each iteration
     */
    public function testEachIteration()
    {
        $dom = new DomQuery('<p> <a>1</a> <a>2</a> <span></span> </p>');

        $result = array();

        $dom->find('a')->each(function ($i, $elm) use (&$result) {
            $result[$i] = $elm;
        });

        $this->assertEquals(2, count($result));
        $this->assertInstanceOf(\DOMNode::class, $result[0]);
    }

    /*
     * Test instance without nodes
     */
    public function testDomQueryNoDocument()
    {
        $dom = new DomQuery;
        $this->assertEquals(0, count($dom));
        $this->assertFalse(isset($dom[0]));
        $this->assertEquals(0, count($dom->find('*')));
        $this->assertEquals(0, count($dom->children()));
        $this->assertNull($dom->get(0));

        $num = 0;
        foreach ($dom as $node) {
            $num++;
        }

        $this->assertEquals(0, $num);
    }

    /*
     * Test constructor exception 
     */
    public function testConstructorException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $dom = new DomQuery(new \stdClass);
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
