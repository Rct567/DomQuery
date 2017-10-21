<?php

namespace Rct567\DomQuery\Tests;

use Rct567\DomQuery\DomQuery;

class DomQueryTest extends \PHPUnit\Framework\TestCase
{
    /*
     * Test filters, first and last
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

        // first and last via psuedo selector, check id
        $this->assertEquals('first-child-a', $dom->find('.root div:first')->attr('id')); // id of first div inside .root
        $this->assertEquals('last-child-a', $dom->find('.root div:last')->attr('id')); // id of last div inside .root

        // first and last via get method, check id
        $this->assertEquals('first-child-a', $dom->find('.root div')->get(0)->getAttribute('id'));
        $this->assertEquals('last-child-a', $dom->find('.root div')->get(-1)->getAttribute('id'));
    }

    /*
     * Test first last, with and without selector
     */
    public function testFirstLast()
    {
        $dom = new DomQuery('<a>1</a> <a>2</a> <a>3</a>');
        $links = $dom->children('a');

        $this->assertEquals(null, $links->first()->next('p')->text());
        $this->assertEquals(null, $links->last()->prev('p')->text());

        $this->assertEquals('2', $links->first()->next('a')->text());
        $this->assertEquals('2', $links->last()->prev('a')->text());

        $this->assertEquals(0, $links->first('p')->length);
        $this->assertEquals(0, $links->last('p')->length);

        $this->assertEquals(1, $links->first('a')->length);
        $this->assertEquals(1, $links->last('a')->length);
    }

    /*
     *  Test get parent
     */
    public function testParent()
    {
        $dom = new DomQuery('<a></a><a></a><a class="link">
            <b><span></span></b>
        </a>');

        $this->assertEquals('b', $dom->find('span')->parent()->tagName);
        $this->assertEquals('link', $dom->find('span')->parent('b')->parent('a')->class);
        $this->assertEquals(0, $dom->find('span')->parent('div')->length);
    }

    /*
     * Test traversing nodes from readme
     */
    public function testTraversingNodesReadmeExamples()
    {
        $dom = new DomQuery('<a>1</a> <a>2</a> <a>3</a>');
        $links = $dom->children('a');

        $result = '';
        foreach ($links as $elm) {
            $result .= $elm->text();
        }
        $this->assertEquals('123', $result);

        $this->assertEquals('1', $links[0]->text());
        $this->assertEquals('3', $links->last()->text());

        $this->assertEquals('2', $links->first()->next()->text());
        $this->assertEquals('2', $links->last()->prev()->text());

        $this->assertEquals('1', $links->get(0)->textContent);
        $this->assertEquals('3', $links->get(-1)->textContent);
    }

    /*
     * Test basic use examples from readme
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
    public function testLoadHtmlAndGetLink()
    {
        $dom = new DomQuery;

        $dom->loadHtmlContent('<!DOCTYPE html> <html> <head></head> <body> <p><a href="test.html"></a></p> 
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
        $html = '<div><h1>ßüöä</h1></div><a>k</a>';
        $dom = new DomQuery($html);

        $this->assertEquals($html, (string) $dom); // same result
        $this->assertEquals('<h1>ßüöä</h1>', (string) $dom->find('h1')); // same header
        $this->assertEquals('ßüöä', $dom->find('h1')->text()); // same text
        $this->assertEquals('ßüöä', $dom->text());
    }

    /*
     * Make selection and subselection
     */
    public function testSubSelection()
    {
        $html = '<div id="main" class="root">
            <div class="level-a"></div>
            <div class="level-a">
                <p>Hai</p>
                <div class="level-b"></div>
                <div class="level-b"></div>
                <div class="level-b">
                    <div class="level-c"></div>
                </div>
            </div>
            <div class="level-a"></div>
        </div>';

        $dom = new DomQuery($html);
        $this->assertEquals('main', $dom->id);
        $this->assertEquals('root', $dom->class);

        // main selection
        $main_selection = $dom->find('.level-a');
        $this->assertEquals(3, count($main_selection));

        // make subselection
        $sub_selection = $main_selection->find('> div'); // child divs from main selection
        $this->assertEquals(3, count($sub_selection));
        $this->assertEquals('level-b', $sub_selection->class);

        // check what it is
        $this->assertTrue($sub_selection->is('.level-b'));
        $this->assertTrue($sub_selection->is('.root .level-b'));
        $this->assertTrue($sub_selection->is('div:not-empty')); // last has child (div with class level-c)
        $this->assertTrue($sub_selection->is('div:has(.level-c)')); // last has child (div with class level-c)
        $this->assertFalse($sub_selection->is('div.level-a'));
        $this->assertFalse($sub_selection->is('div.level-c'));
        $this->assertFalse($sub_selection->is('p'));
        $this->assertFalse($sub_selection->is('#main'));

        // same thing again
        $sub_selection = $main_selection->children('div, span');
        $this->assertEquals(3, count($sub_selection));
        $this->assertEquals('level-b', $sub_selection->class);

        // dual selection
        $dual_selection = $dom->find('p:first-child, div.level-b:last-child');
        $this->assertEquals(2, count($dual_selection));

        $this->assertTrue($dual_selection[0]->is('p'));
        $this->assertTrue($dual_selection[0]->is(':first-child'));
        $this->assertFalse($dual_selection[0]->is(':last-child'));

        $this->assertTrue($dual_selection[1]->is('div'));
        $this->assertTrue($dual_selection[1]->is(':last-child'));
        $this->assertFalse($dual_selection[1]->is(':first-child'));
    }

    /*
     * Test create single node
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
     * Test change text
     */
    public function testSingleNodeTextChange()
    {
        $dom = DomQuery::create('<a title="hello">Some text</a>');
        $dom->text('Changed text');
        $this->assertEquals('<a title="hello">Changed text</a>', (string) $dom);
        $this->assertEquals('Changed text', $dom->text());
    }

    /*
     * Test remove
     */
    public function testRemove()
    {
        $dom = DomQuery::create('<div><a title="hello">Some text</a><a>B</a><span>C</span></div>');
        $dom->find('a')->remove();
        $this->assertEquals("<div><span>C</span></div>", (string) $dom);
    }

     /*
     * Test remove with selector filter
     */
    public function testRemoveWithSelectorFilter()
    {
        $dom = DomQuery::create('<div><a title="hello">Some text</a><a>B</a><span>C</span></div>');
        $dom->find('a')->remove('[title]');
        $this->assertEquals("<div><a>B</a><span>C</span></div>", (string) $dom);
    }

    /*
     * Test change text
     */
    public function testMultibleNodesTextChange()
    {
        $dom = DomQuery::create('<div><a title="hello">Some text</a><a>B</a><span>C</span></div>');
        $dom->find('a')->text('Changed text');
        $this->assertEquals("<div><a title=\"hello\">Changed text</a><a>Changed text</a><span>C</span></div>", (string) $dom);
    }

    /*
     * Test append html
     */
    public function testAppend()
    {
        // append string with html
        $dom = new Domquery('<a></a><p></p><b></b><a></a>');
        $dom->find('a')->append('<span></span>');
        $this->assertEquals('<a><span></span></a><p></p><b></b><a><span></span></a>', (string) $dom);

        // append DomQuery instance
        $dom = new Domquery('<a></a><p></p><b></b><a></a>');
        $dom->find('a')->append(DomQuery::create('<i>X</i>'));
        $this->assertEquals('<a><i>X</i></a><p></p><b></b><a><i>X</i></a>', (string) $dom);
        $this->assertEquals('X', $dom->find('i')->text());
    }

    /*
     * Test prepend html
     */
    public function testPrepend()
    {
        $dom = new Domquery('<a>X</a>');
        $dom->find('a')->prepend('<span></span>', '<i></i>');
        $this->assertEquals('<a><i></i><span></span>X</a>', (string) $dom);
        $this->assertEquals(1, $dom->find('span')->length);
    }

    /*
     * Test before
     */
    public function testBefore()
    {
        $dom = new Domquery('<div> <a>X</a> </div>');
        $dom->find('a')->before('<span></span>');
        $this->assertEquals('<div> <span></span><a>X</a> </div>', (string) $dom);
        $this->assertEquals(1, $dom->find('span')->length);
    }

    /*
     * Test after
     */
    public function testAfter()
    {
        $dom = new Domquery('<div> <a>X</a> </div>');
        $dom->find('a')->after('<span></span>');
        $this->assertEquals('<div> <a>X</a><span></span> </div>', (string) $dom);
        $this->assertEquals(1, $dom->find('span')->length);
    }

    /*
     * Test filter
     */
    public function testFilter()
    {
        $dom = new Domquery('<a>hai</a> <a></a> <a id="mmm"></a> <a class="x"></a> <a class="xpp"></a>');
        $selection = $dom->find('a');
        $this->assertEquals(5, $selection->length);
        $this->assertEquals(5, $selection->filter('a')->length);
        $this->assertEquals(1, $selection->filter('#mmm')->length);
        $this->assertEquals(1, $selection->filter('a')->filter('.xpp')->length);
        $this->assertEquals(3, $selection->filter('a[class], #mmm')->length);
        $this->assertEquals(3, $selection->filter(':even')->length);
    }

    /*
     * Test not 
     */
    public function testNot()
    {
        $dom = new Domquery('<a>hai</a> <a></a> <a id="mmm"></a> <a class="x"></a> <a class="xpp"></a>');
        $selection = $dom->find('a');
        $this->assertEquals(5, $selection->length);
        $this->assertEquals(0, $selection->not('a')->length);
        $this->assertEquals(4, $selection->not('#mmm')->length);
        $this->assertEquals(3, $selection->not('#mmm')->not('.xpp')->length);
        $this->assertEquals(2, $selection->not('a[class], #mmm')->length);
        $this->assertEquals(2, $selection->not(':even')->length);
    }

    /*
     * Test get html
     */
    public function testGetHtml()
    {
        $dom = new Domquery('<p> <a>M<i>A</i></a> <span></span></p>');
        $this->assertEquals('M<i>A</i>', $dom->find('a')->html()); // inner
        $this->assertEquals('<a>M<i>A</i></a>', $dom->find('a')->prop('outerHTML')); // outer
        $this->assertEquals('A', $dom->find('a i')->html());
    }

    /*
     * Test set html
     */
    public function testSetHtml()
    {
        $dom = new Domquery('<p> <a>M<i>A</i></a> <span></span> </p>');
        $dom->find('a')->html('<i>x</i>');
        $this->assertEquals('<p> <a><i>x</i></a> <span></span> </p>', (string) $dom);
    }

    /*
     * Test each iteration
     */
    public function testEachIteration()
    {
        $dom = new Domquery('<p> <a>1</a> <a>2</a> <span></span> </p>');

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
}
