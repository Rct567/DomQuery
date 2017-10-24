<?php

namespace Rct567\DomQuery\Tests;

use Rct567\DomQuery\DomQuery;

class DomQuerySelectorsTest extends \PHPUnit\Framework\TestCase
{
    /*
     * Test css to xpath conversion
     */
    public function testCssToXpath()
    {
        $css_to_xpath = array(
            'a' => '//a',
            '*' => '//*',
            '* > a' => '//*/a',
            '#someid' => '//*[@id=\'someid\']',
            'p#someid' => '//p[@id=\'someid\']',
            '#some\\.id' => '//*[@id=\'some.id\']',
            'p a' => '//p//a',
            'div, span' => '//div|//span',
            'a[href]' => '//a[@href]',
            'a[href="html"]' => '//a[@href=\'html\']',
            'a[href!="html"]' => '//a[@href!=\'html\']',
            'a[href*=\'html\']' => '//a[contains(@href, \'html\')]',
            '[href*=\'html\']' => '//*[contains(@href, \'html\')]',
            '[href^=\'html\']' => '//*[starts-with(@href, \'html\')]',
            '[href$=\'html\']' => '//*[@href and substring(@href, string-length(@href)-3) = \'html\']',
            '[href~=\'html\']' => '//*[contains(concat(\' \', normalize-space(@href), \' \'), \' html \')]',
            '> a' => '/a',
            'p > a' => '//p/a',
            'p > a[href]' => '//p/a[@href]',
            'p a[href]' => '//p//a[@href]',
            ':disabled' => '//*[@disabled]',
            'div :header' => '//div//*[self::h1 or self::h2 or self::h3 or self::h5 or self::h5 or self::h6]',
            ':odd' => '//*[position() mod 2 = 0]',
            '.hidden' => '//*[contains(concat(\' \', normalize-space(@class), \' \'), \' hidden \')]',
            '.hidden-something' => '//*[contains(concat(\' \', normalize-space(@class), \' \'), \' hidden-something \')]',
            'a.hidden[href]' => '//a[contains(concat(\' \', normalize-space(@class), \' \'), \' hidden \')][@href]',
            'a[href] > .hidden' => '//a[@href]/*[contains(concat(\' \', normalize-space(@class), \' \'), \' hidden \')]',
            'a:not(b[co-ol])' => '//a[not(self::b[@co-ol])]',
            'a:not(b,c)' => '//a[not(self::b or self::c)]',
            'a:not(.cool)' => '//a[not(self::*[contains(concat(\' \', normalize-space(@class), \' \'), \' cool \')])]',
            'a:contains(txt)' => '//a[text()[contains(.,\'txt\')]]',
            'h1 ~ ul' => '//h1/following-sibling::ul',
            'h1 + ul' => '//h1/following-sibling::ul[1]',
            'h1 ~ #id' => '//h1/following-sibling::[@id=\'id\']',
            'p > a:has(> a)' => '//p/a[child::a]',
            'p > a:has(b > a)' => '//p/a[descendant::b/a]',
            'p > a:has(a)' => '//p/a[descendant::a]',
            'a:has(b)' => '//a[descendant::b]',
            'a:first-child:first' => '(//a[1])[1]',
            'div > a:first' => '(//div/a)[1]',
            ':first' => '(//*)[1]'
        );

        foreach ($css_to_xpath as $css => $expected_xpath) {
            $this->assertEquals($expected_xpath, DomQuery::cssToXpath($css), $css);
        }
    }

    /*
     * Test select by tagname selector
     */
    public function testElementTagnameSelector()
    {
        $dom = new DomQuery('<a>1</a><b>2</b><i>3</i>');
        $this->assertEquals('2', $dom->find('b')->text());
    }

    /*
     * Test wildcard / all selector
     */
    public function testWildcardSelector()
    {
        $dom = new DomQuery('<a>1</a><b>2</b>');
        $this->assertEquals(2, $dom->find('*')->length);
    }

    /*
     * Test children selector
     */
    public function testSelectChildrenSelector()
    {
        $dom = new DomQuery('<div><a>1</a><b>2</b></div><a>3</a>');
        $this->assertEquals('1', $dom->find('div > a')->text());
        $this->assertEquals(1, $dom->find('div > a')->length);
    }

    /*
     * Test id selector
     */
    public function testIdSelector()
    {
        $dom = new DomQuery('<div><a>1</a><b>2</b></div><a id="here">3</a>');
        $this->assertEquals('3', $dom->find('#here')->text());
        $this->assertEquals(1, $dom->find('#here')->length);
    }

    /*
     * Test id selector with meta character
     */
    public function testIdSelectorWithMetaCharacter()
    {
        $dom = new DomQuery('<div><a>1</a><b>2</b></div><a id="here.here">3</a>');
        $this->assertEquals('3', $dom->find('#here\\.here')->text());
    }

    /*
     * Test descendant selector
     */
    public function testDescendantSelector()
    {
        $dom = new DomQuery('<div><a>1</a><b>2</b></div><a id="here">3</a><p><a>4</a></p>');
        $this->assertEquals('2', $dom->find('div > b')->text());
        $this->assertEquals(1, $dom->find('div > b')->length);
        $this->assertEquals(0, $dom->find('a > b')->length);
        $this->assertEquals(1, $dom->find('> a')->length);
        $this->assertEquals(2, $dom->find('* > a')->length);
    }

    /*
     * Test next adjacent selector
     */
    public function testNextAdjacentSelector()
    {
        $dom = new DomQuery('<b>a</b><a>1</a><a>2</a><a>3</a>');
        $this->assertEquals(1, $dom->find('b + a')->length);
        $this->assertEquals('1', $dom->find('b + a')->text());
        $this->assertEquals(0, $dom->find('a + b')->length);
    }

    /*
     * Test next siblings selector
     */
    public function testNextSiblingsSelector()
    {
        $dom = new DomQuery('<b>a</b><a>1</a><a>2</a><a>3</a>');
        $this->assertEquals(3, $dom->find('b ~ a')->length);
        $this->assertEquals('3', $dom->find('b ~ a')->last()->text());
        $this->assertEquals(0, $dom->find('a ~ b')->length);
    }

    /*
     * Test multiple selectors
     */
    public function testMultipleSelectors()
    {
        $dom = new DomQuery('<div><a>1</a><b>2</b></div><a id="here">3</a><p><a>4</a></p>');
        $this->assertEquals(2, $dom->find('#here, div > b')->length);
    }

    /*
     * Test class selector
     */
    public function testClassSelector()
    {
        $dom = new DomQuery('<div><a class="monkey moon">1</a><b>2</b></div><a class="monkey">3</a>');
        $this->assertEquals(2, $dom->find('a.monkey')->length);
        $this->assertEquals(1, $dom->find('.moon')->length);
        $this->assertEquals(1, $dom->find('a.moon')->length);
    }

    /*
     * Test class selector with uppercase
     */
    public function testClassSelectorWithUppercase()
    {
        $dom = new DomQuery('<div><a class="monkey">1</a><b>2</b></div><a class="Monkey">3</a>');
        $this->assertEquals('3', $dom->find('.Monkey')->text());
        $this->assertEquals(1, $dom->find('.Monkey')->length);
        $this->assertEquals(1, $dom->find('a.Monkey')->length);
    }

    /*
     * Test class selector with underscore
     */
    public function testClassSelectorWithUnderscore()
    {
        $dom = new DomQuery('<div><a class="monkey_moon">1</a><b>2</b></div><a class="monkey-moon">3</a>');
        $this->assertEquals('1', $dom->find('.monkey_moon')->text());
        $this->assertEquals('3', $dom->find('.monkey-moon')->text());
    }

    /*
     * Test not filter selector
     */
    public function testNotFilterSelector()
    {
        $dom = new DomQuery('<a>1</a><a class="monkey">2</a><a id="some-monkey">3</a>');

        $this->assertEquals(2, $dom->find('a:not(.monkey)')->length);
        $this->assertEquals(2, $dom->find('a:not([id])')->length);
        $this->assertEquals(1, $dom->find('a:not([id],[class])')->length);
        $this->assertEquals(2, $dom->find('a:not(#some-monkey)')->length);
        $this->assertEquals(1, $dom->find('a:not(#some-monkey, .monkey)')->length);
        $this->assertEquals(1, $dom->find('a:not(.monkey,#some-monkey)')->length);
        $this->assertEquals(3, $dom->find('a:not(b)')->length);
    }

    /*
     * Test has filter selector
     */
    public function testHasFilterSelector()
    {
        $dom = new DomQuery('<ul>
            <li id="item2">list item 1<a></a></li>
            <li id="item2">list item 2</li>
            <li id="item3"><b></b>list item 3</li>
            <li >list item 4</li>
            <li class="item-item"><span id="anx">x</span></li>
            <li class="item item6">list item 6</li>
        </ul>');

        $this->assertEquals('item2', $dom->find('li:has(a)')->id);
        $this->assertEquals(1, $dom->find('li:has(b)')->length);
        $this->assertEquals('item-item', $dom->find('li:has(span#anx)')->class);
        $this->assertEquals('item-item', $dom->find('li:has(*#anx)')->class);
        $this->assertEquals(1, $dom->find('ul:has(li.item)')->length);
        $this->assertEquals(1, $dom->find('ul:has(span)')->length);
        $this->assertEquals(1, $dom->find('ul:has(li span)')->length);
        $this->assertEquals(0, $dom->find('ul:has(> span)')->length);
    }

    /*
     *
     */
    public function testAttributeSelector()
    {
        $dom = new DomQuery('<ul>
            <li>list item 1</li>
            <li id="item2">list item 2</li>
            <li id="item3">list item 3</li>
            <li>list item 4</li>
            <li class="item-item">list item 5</li>
            <li class="item item6">list item 6</li>
        </ul>');

        $this->assertEquals(2, $dom->find('li[id]')->length);
        $this->assertEquals(2, $dom->find('li[id^=\'item\']')->length);
        $this->assertEquals(2, $dom->find('li[id*=\'tem\']')->length);
        $this->assertEquals('list item 3', $dom->find('li[id$=\'tem3\']')->text());
        $this->assertEquals(0, $dom->find('li[id$=\'item\']')->length);
        $this->assertEquals('list item 3', $dom->find('li[id=\'item3\']')->text());
        $this->assertEquals('list item 6', $dom->find('li[class~=\'item6\']')->text());
        $this->assertEquals(1, $dom->find('li[class~=\'item\']')->length);
        $this->assertEquals(1, $dom->find('li[class~=\'item\'][class~=\'item6\']')->length);
        $this->assertEquals(0, $dom->find('li[class~=\'item\'][id~=\'item\']')->length);
        $this->assertEquals(2, $dom->find('li[class*=\'item\']')->length);
    }

    /*
     * Test odd even pseudo selector
     * @note :even and :odd use 0-based indexing, so even (0, 2, 4) becomes item (1, 3, 5)
     */
    public function testOddEvenPseudoSelector()
    {
        $dom = new DomQuery('<ul>
            <li>list item 1</li>
            <li>list item 2</li>
            <li>list item 3</li>
            <li>list item 4</li>
            <li>list item 5</li>
            <li>list item 6</li>
        </ul>');

        $this->assertEquals(3, $dom->find('li')->filter(':even')->length); // 1 3 5
        $this->assertEquals('list item 5', $dom->find('li')->filter(':even')->last()->text());

        $this->assertEquals(3, $dom->find('li')->filter(':odd')->length); // 2 4 6
        $this->assertEquals('list item 6', $dom->find('li')->filter(':odd')->last()->text());
    }

    /*
     * Test invalid xpath expression
     */
    public function testInvalidPseudoSelector()
    {
        $this->expectException(\Exception::class);
        DomQuery::cssToXpath('a:not-a-selector');
    }
}
