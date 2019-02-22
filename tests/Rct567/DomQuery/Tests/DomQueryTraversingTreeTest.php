<?php

namespace Rct567\DomQuery\Tests;

use Rct567\DomQuery\DomQuery;

class DomQueryTraversingTreeTest extends \PHPUnit\Framework\TestCase
{
    /*
     * Test find
     */
    public function testFindWithSelection()
    {
        $dom = new DomQuery('<a>1</a><a>2</a><a id="last">3</a>');
        $this->assertEquals('<a>1</a><a>2</a><a id="last">3</a>', (string) $dom->find($dom->find('a')));
        $this->assertEquals('<a id="last">3</a>', (string) $dom->find($dom->getDocument()->getElementById('last')));
        $this->assertEquals('<a id="last">3</a>', (string) $dom->find($dom->find('a:contains(3)')));
    }

    /*
     * Test findOrFail
     */
    public function testFindOrFailSuccess()
    {
        $dom = new DomQuery('<a>1</a><a>2</a><a id="last">3</a>');
        $this->assertEquals(1, $dom->findOrFail('a#last')->length);
    }

    /*
     * Test findOrFail exception
     */
    public function testFindOrFailException()
    {
        $this->expectException(\Exception::class);
        $dom = new DomQuery('<a>1</a><a>2</a><a id="last">3</a>');
        $dom->findOrFail('span');
    }

    /*
     * Test findOrFail exception using collection
     */
    public function testFindOrFailExceptionUsingCollection()
    {
        $this->expectException(\Exception::class);
        $dom = new DomQuery('<a>1</a><a>2</a><a id="last">3</a>');
        $dom->find('#last')->findOrFail($dom->find('a:first-child'));
    }

    /*
     * Test next
     */
    public function testNext()
    {
        $dom = new DomQuery('<ul> <li id="a">1</li> nope <li id="b">2</li> </ul>');

        $this->assertEquals('<li id="b">2</li>', (string) $dom->find('#a')->next());
        $this->assertEquals('', (string) $dom->find('#b')->next());
    }

    /*
     * Test next all
     */
    public function testNextAll()
    {
        $dom = new DomQuery('<ul> <li id="a">1</li> nope <li id="b">2</li> <li id="c">3</li> </ul>');

        $this->assertEquals('<li id="b">2</li><li id="c">3</li>', (string) $dom->find('#a')->nextAll());
        $this->assertEquals('<li id="c">3</li>', (string) $dom->find('#b')->nextAll());
        $this->assertEquals('', (string) $dom->find('#c')->nextAll());
    }

    /*
     * Test next
     */
    public function testPrev()
    {
        $dom = new DomQuery('<ul> <li id="a">1</li> nope <li id="b">2</li> </ul>');

        $this->assertEquals('<li id="a">1</li>', (string) $dom->find('#b')->prev());
        $this->assertEquals('', (string) $dom->find('#a')->prev());
    }

    /*
     * Test previous all
     */
    public function testPrevAll()
    {
        $dom = new DomQuery('<ul> <li id="a">1</li> nope <li id="b">2</li> <li id="c">3</li> </ul>');

        $this->assertEquals('<li id="a">1</li><li id="b">2</li>', (string) $dom->find('#c')->prevAll());
        $this->assertEquals('<li id="a">1</li>', (string) $dom->find('#b')->prevAll());
        $this->assertEquals('', (string) $dom->find('#a')->prevAll());
    }

    /*
     * Test first last, with and without filter selector
     */
    public function testSiblings()
    {
        $dom = new DomQuery('<div><a>1</a> <a id="target">2</a> <a>3</a></div>');
        $siblings = $dom->find('#target')->siblings();

        $this->assertEquals(2, $siblings->length);
        $this->assertEquals('1', $siblings->first()->text());
        $this->assertEquals('3', $siblings->last()->text());
    }

    /*
     * Test children (spaces should be ignored by default)
     */
    public function testChildren()
    {
        $dom = new DomQuery('<div><a>1</a> <a>2</a> <a>3</a></div>');
        $children = $dom->find('div')->children();

        $this->assertEquals(3, $children->length);
        $this->assertEquals('<a>1</a>', (string) $children->first());
        $this->assertEquals('<a>3</a>', (string) $children->last());
    }

    /*
     * Test contents (children including text nodes)
     */
    public function testContents()
    {
        $dom = new DomQuery('<div> <a>1</a> <a>2</a> <a>3</a> </div>');
        $children = $dom->find('div')->contents();

        $this->assertEquals(7, $children->length); // 3 elements plus 4 spaces
        $this->assertEquals(' ', (string) $children[0]);
        $this->assertEquals('<a>1</a>', (string) $children[1]);
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
     *  Test get closest
     */
    public function testClosest()
    {
        $dom = new DomQuery('<a></a><a></a><a class="link">
            <b><span></span></b>
        </a>');

        $this->assertEquals('a', $dom->find('span')->closest('.link')->tagName);
        $this->assertEquals('<b><span></span></b>', (string) $dom->find('span')->closest('b'));
        $this->assertEquals(0, $dom->find('span')->closest('nope')->length);
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
        $this->assertTrue($dom->is($dom->getDocument()->getElementById('main')));

        // main selection
        $main_selection = $dom->find('.level-a');
        $this->assertCount(3, $main_selection);
        $this->assertTrue($main_selection->is($dom->getDocument()->getElementsByTagName('div')));
        $this->assertFalse($main_selection->is($dom->getDocument()->getElementById('main')));

        // make sub selection
        $sub_selection = $main_selection->find('> div'); // child divs from main selection
        $this->assertCount(3, $sub_selection);
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
        $this->assertCount(3, $sub_selection);
        $this->assertEquals('level-b', $sub_selection->class);

        // dual selection
        $dual_selection = $dom->find('p:first-child, div.level-b:last-child');
        $this->assertCount(2, $dual_selection);

        $this->assertTrue($dual_selection[0]->is('p'));
        $this->assertTrue($dual_selection[0]->is(':first-child'));
        $this->assertFalse($dual_selection[0]->is(':last-child'));

        $this->assertTrue($dual_selection[1]->is('div'));
        $this->assertTrue($dual_selection[1]->is(':last-child'));
        $this->assertFalse($dual_selection[1]->is(':first-child'));
    }
}
