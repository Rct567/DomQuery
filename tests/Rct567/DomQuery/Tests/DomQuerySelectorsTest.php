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
            'p a' => '//p//a',
            'div, span' => '//div|//span',
            'p a[href]' => '//p//a[@href]',
            'a[href="html"]' => '//a[@href=\'html\']',
            'a[href!="html"]' => '//a[@href!=\'html\']',
            'a[href*=\'html\']' => '//a[contains(@href, \'html\')]',
            '[href*=\'html\']' => '//*[contains(@href, \'html\')]',
            '[href^=\'html\']' => '//*[starts-with(@href, \'html\')]',
            '[href$=\'html\']' => '//*[@href = substring(@href, string-length(@href) - string-length(@href) +1)]',
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
            'a:not(b[co-ol])' => '//a[not(b[@co-ol])]',
            'a:not(.cool)' => '//a[not(*[contains(concat(\' \', normalize-space(@class), \' \'), \' cool \')])]',
            'a:contains(txt)' => '//a[text()[contains(.,\'txt\')]]',
            'h1 ~ ul' => '//h1/following-sibling::ul',
            'h1 + ul' => '//h1/following-sibling::ul[1]',
            'h1 ~ #id' => '//h1/following-sibling::[@id=\'id\']',
            'p > a:has(> a)' => '//p/a[descendant::a]',
            'p > a:has(b > a)' => '//p/a[b/a]',
            'p > a:has(a)' => '//p/a[a]',
            'a:first-child:first' => '(//a[1])[1]',
            'div > a:first' => '(//div/a)[1]',
            ':first' => '(//*)[1]'
        );

        foreach ($css_to_xpath as $css => $expected_xpath) {
                $this->assertEquals($expected_xpath, DomQuery::cssToXpath($css, true), $css);
        }
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
}
