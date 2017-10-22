<?php

namespace Rct567\DomQuery\Tests;

use Rct567\DomQuery\DomQuery;

class DomQueryTraversalTest extends \PHPUnit\Framework\TestCase
{
    /*
     * Test first last, with and without filter selector
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
     * Make selection and sub selection
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
     * Test filter on selection result
     */
    public function testFilter()
    {
        $dom = new DomQuery('<a>hai</a> <a></a> <a id="mmm"></a> <a class="x"></a> <a class="xpp"></a>');
        $selection = $dom->find('a');
        $this->assertEquals(5, $selection->length);
        $this->assertEquals(5, $selection->filter('a')->length);
        $this->assertEquals(1, $selection->filter('#mmm')->length);
        $this->assertEquals(1, $selection->filter('a')->filter('.xpp')->length);
        $this->assertEquals(3, $selection->filter('a[class], #mmm')->length);
        $this->assertEquals(3, $selection->filter(':even')->length);
    }

    /*
     * Test not filter on selection result 
     */
    public function testNot()
    {
        $dom = new DomQuery('<a>hai</a> <a></a> <a id="mmm"></a> <a class="x"></a> <a class="xpp"></a>');
        $selection = $dom->find('a');
        $this->assertEquals(5, $selection->length);
        $this->assertEquals(0, $selection->not('a')->length);
        $this->assertEquals(4, $selection->not('#mmm')->length);
        $this->assertEquals(3, $selection->not('#mmm')->not('.xpp')->length);
        $this->assertEquals(2, $selection->not('a[class], #mmm')->length);
        $this->assertEquals(2, $selection->not(':even')->length);
    }
}
