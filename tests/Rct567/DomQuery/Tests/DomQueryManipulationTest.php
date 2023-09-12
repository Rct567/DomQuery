<?php

namespace Rct567\DomQuery\Tests;

use Rct567\DomQuery\DomQuery;

class DomQueryManipulationTest extends \PHPUnit\Framework\TestCase
{

    /*
     * Test wrap all
     */
    public function testWrapAll()
    {
        $dom = DomQuery::create('<html><div class="container">'.
            '<div class="inner">Hello</div> <div class="inner">Goodbye</div>'.
        '</div></html>');

        $dom->find('.inner')->wrapAll('<div class="new" />');

        $this->assertEquals('<html><div class="container">'.
            '<div class="new"><div class="inner">Hello</div><div class="inner">Goodbye</div></div>'.
        ' </div></html>', (string) $dom);
    }

    /*
     * Test wrap all with non wrapping sibling
     */
    public function testWrapAllWithNonWrappingSibling()
    {
        $dom = DomQuery::create('<html><div class="container">'.
            '<div class="inner">Hello</div> <div class="nope">Goodbye</div>'.
        '</div></html>');

        $dom->find('.inner')->wrapAll('<div class="new" />');

        $this->assertEquals('<html><div class="container">'.
            '<div class="new"><div class="inner">Hello</div></div> <div class="nope">Goodbye</div>'.
        '</div></html>', (string) $dom);
    }

    /*
     * Test wrap all with multiple elements wrapper
     */
    public function testWrapAllWithMultipleElementsWrapper()
    {
        $dom = DomQuery::create('<html><div class="container">'.
            '<div class="inner">Hello</div> <div class="inner">Goodbye</div>'.
        '</div></html>');

        $dom->find('.inner')->wrapAll('<div class="new"><x></x></div>');

        $this->assertEquals('<html><div class="container">'.
            '<div class="new"><x><div class="inner">Hello</div><div class="inner">Goodbye</div></x></div>'.
        ' </div></html>', (string) $dom);
    }

    /*
     * Test wrap inner
     */
    public function testInnerWrap()
    {
        $dom = DomQuery::create('<p><a>Hello</a></p>');
        $dom->find('a')->wrapInner('<div></div>');
        $this->assertEquals("<p><a><div>Hello</div></a></p>", (string) $dom);
    }

    /*
     * Test wrap
     */
    public function testWrap()
    {
        $dom = DomQuery::create('<p><a>Hello</a></p>');
        $dom->find('a')->wrap('<div></div>');
        $this->assertEquals("<p><div><a>Hello</a></div></p>", (string) $dom);
    }

    /*
     * Test wrap on element with sibling
     */
    public function testWrapOnElementWithSibling()
    {
        $dom = DomQuery::create('<p> <span>Hi</span> <a>Hello</a> </p>');
        $dom->find('a')->wrap('<x></x>');
        $this->assertEquals("<p> <span>Hi</span> <x><a>Hello</a></x> </p>", (string) $dom);
    }

    /*
     * Test wrap on multiple elements
     */
    public function testWrapOnMultipleElements()
    {
        $dom = DomQuery::create('<p> <span>Hi</span> <a>Hello</a> </p>');
        $dom->find('a, span')->wrap('<x></x>');
        $this->assertEquals("<p> <x><span>Hi</span></x> <x><a>Hello</a></x> </p>", (string) $dom);
    }

    /*
     * Test wrap where wrapper has multiple elements
     */
    public function testWrapWithMultipleElementsWrapper()
    {
        $dom = DomQuery::create('<p><a>Hello</a></p>');
        $dom->find('a')->wrap('<div><x></x></div>');
        $this->assertEquals("<p><div><x><a>Hello</a></x></div></p>", (string) $dom);
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
    public function testMultipleNodesTextChange()
    {
        $dom = DomQuery::create('<div><a title="hello">Some text</a><a>B</a><span>C</span></div>');
        $dom->find('a')->text('Changed text');
        $this->assertEquals("<div><a title=\"hello\">Changed text</a><a>Changed text</a><span>C</span></div>", (string) $dom);
    }

    /*
     * Test append html string
     */
    public function testAppend()
    {
        $dom = new DomQuery('<p id="target">1</p> <p></p> <b></b> <a>2</a>');
        $dom->find('p#target')->append('<span></span>');
        $this->assertEquals('<p id="target">1<span></span></p><p></p><b></b><a>2</a>', (string) $dom);
    }

    /*
     * Test append multiple content
     */
    public function testAppendMulti()
    {
        $dom = new DomQuery('<p id="target"><a></a></p>');
        $dom->find('p#target')->append('<b></b> ', '<c></c>');
        $this->assertEquals('<p id="target"><a></a><b></b><c></c></p>', (string) $dom);
    }

    /*
     * Test append DomQuery instance with multiple targets
     */
    public function testAppendDomQueryInstance()
    {
        $dom = new DomQuery('<p class="target"></p><p></p><p class="target"></p>');
        $dom->find('p.target')->append(DomQuery::create('<i>X</i>'));
        $this->assertEquals('<p class="target"><i>X</i></p><p></p><p class="target"><i>X</i></p>', (string) $dom);
        $this->assertEquals('X', $dom->find('i')->text());
    }

    /*
     * Test append, content getting moved
     */
    public function testAppendClone()
    {
        $dom = new DomQuery('<div> <p id="target"></p><p><b id="content">XX</b></p> </div>');
        $dom->find('p#target')->append($dom->find('b#content'));
        $this->assertEquals('<div> <p id="target"><b id="content">XX</b></p><p></p> </div>', (string) $dom);
    }

    /*
     * Test append with multiple targets, first content should be cloned, second/last should move it
     */
    public function testAppendCloneMultipleTargets()
    {
        $dom = new DomQuery('<div> <p class="target"></p><p class="target"></p><p><b id="content">XX</b></p> </div>');
        $dom->find('p.target')->append($dom->find('b#content'));
        $this->assertEquals('<div> <p class="target"><b id="content">XX</b></p><p class="target"><b id="content">XX</b></p><p></p> </div>', (string) $dom);
    }

    /*
     * Test append to
     */
    public function testAppendTo()
    {
        $dom = new DomQuery('<div> <span>A</span><span>B</span> <p id="target"></p> </div>');
        $dom->find('span')->appendTo('p#target');
        $this->assertEquals('<div>  <p id="target"><span>A</span><span>B</span></p> </div>', (string) $dom);
    }

    /*
     * Test append to (other DomQuery)
     */
    public function testAppendToOther()
    {
        $dom = DomQuery::create('<span>Hello</span>')->appendTo(DomQuery::create('<div>P</div>'));
        $this->assertEquals('<div>P<span>Hello</span></div>', (string) $dom);
    }

    /*
     * Test append to moves content,
     */
    public function testAppendToMoved()
    {
        $dom = new DomQuery('<div> <span>X</span> <p id="target"></p> </div>');
        $dom->find('span')->appendTo('p#target');
        $this->assertEquals('<div>  <p id="target"><span>X</span></p> </div>', (string) $dom);
    }

    /*
     * Test append to with clones
     */
    public function testAppendToCloned()
    {
        $dom = new DomQuery('<div> <span>X</span> <p id="target"></p> </div>');
        $dom->find('span')->clone()->appendTo('p#target');
        $this->assertEquals('<div> <span>X</span> <p id="target"><span>X</span></p> </div>', (string) $dom);
    }

    /*
     * Test prepend html
     */
    public function testPrepend()
    {
        $dom = new DomQuery('<a>X</a>');
        $dom->find('a')->prepend('<span></span>', '<i></i>');
        $this->assertEquals('<a><i></i><span></span>X</a>', (string) $dom);
        $this->assertEquals(1, $dom->find('span')->length);
    }

    /*
     * Test prepend multiple content
     */
    public function testPrependMulti()
    {
        $dom = new DomQuery('<p><c></c></p>');
        $dom->find('p:last')->prepend('<b></b>', '<a></a>');
        $this->assertEquals('<p><a></a><b></b><c></c></p>', (string) $dom);
    }

    /*
     * Test prepend to
     */
    public function testPrependTo()
    {
        $dom = new DomQuery('<div> <span>X</span> <div id="target"></div> </div>');
        $dom->find('span')->prependTo('#target');
        $this->assertEquals('<div>  <div id="target"><span>X</span></div> </div>', (string) $dom);
    }

    /*
     * Test prepend to (other DomQuery)
     */
    public function testPrependToOther()
    {
        $dom = DomQuery::create('<span>Hello</span>')->prependTo(DomQuery::create('<div>P</div>'));
        $this->assertEquals('<div><span>Hello</span>P</div>', (string) $dom);
    }

    /*
     * Test insert before
     */
    public function testBefore()
    {
        $dom = new DomQuery('<div> <a>X</a> </div>');
        $dom->find('a')->before('<span></span>');
        $this->assertEquals('<div> <span></span><a>X</a> </div>', (string) $dom);
        $this->assertEquals(1, $dom->find('span')->length);
    }

    /*
     * Test insert after
     */
    public function testAfter()
    {
        $dom = new DomQuery('<div> <a>X</a> </div>');
        $dom->find('a')->after('<span></span>');
        $this->assertEquals('<div> <a>X</a><span></span> </div>', (string) $dom);
        $this->assertEquals(1, $dom->find('span')->length);
    }

    /*
     * Test insert after with nl
     * @note nl is detected in child instance and set back to root
     */
    public function testAfterWithWhiteSpaces()
    {
        $dom = new DomQuery("<div> <a>X</a> </div>");
        $dom->find('div')->find('a')->after("<span>\n</span>");
        $this->assertEquals("<div> <a>X</a><span>\n</span> </div>", (string) $dom);
        $this->assertFalse($dom->preserve_no_newlines);
    }

    /*
     * Test get html
     */
    public function testGetHtml()
    {
        $dom = new DomQuery('<p> <a>M<i selected>A</i></a> <span></span></p>');
        $this->assertEquals('M<i selected>A</i>', $dom->find('a')->html()); // inner
        $this->assertEquals('<a>M<i selected>A</i></a>', $dom->find('a')->prop('outerHTML')); // outer
        $this->assertEquals('<a>M<i selected>A</i></a>', $dom->find('a')->outerHTML); // outer
        $this->assertEquals('A', $dom->find('a i')->html());
    }

    /*
     * Test get html in xml mode
     */
    public function testGetHtmlInXmlMode()
    {
        $dom = new DomQuery('<p> <a>M<i selected>A</i></a> <span></span> </p>');
        $dom->xml_mode = true;
        $this->assertEquals('M<i selected="selected">A</i>', $dom->find('a')->html()); // inner
        $this->assertEquals('<a>M<i selected="selected">A</i></a>', $dom->find('a')->prop('outerHTML')); // outer
        $this->assertEquals('<a>M<i selected="selected">A</i></a>', $dom->find('a')->outerHTML); // outer
        $this->assertEquals('A', $dom->find('a i')->html());
    }

    /*
     * Test set html
     */
    public function testSetHtml()
    {
        $dom = new DomQuery('<p> <a>M<i>A</i></a> <span></span> </p>');
        $dom->find('a')->html('<i>x</i>');
        $this->assertEquals('<p> <a><i>x</i></a> <span></span> </p>', (string) $dom);
    }

    /*
     * Test set html with new line content
     */
    public function testSetHtmlWithNewline()
    {
        $dom = new DomQuery('<p> <a>M<i>A</i></a> <span></span> </p>');
        $dom->find('a')->html("<i>x\n</i>");
        $this->assertEquals("<p> <a><i>x\n</i></a> <span></span> </p>", (string) $dom);
    }

    /*
     * Test get css
     */
    public function testGetCss()
    {
        $dom = new DomQuery('<p><a style="color: green;"></a></p>');
        $this->assertEquals('green', $dom->find('a')->css('color'));
        $this->assertEquals(null, $dom->find('a')->css('margin-top'));
    }

    /*
     * Test get css from element with multiple css values
     */
    public function testGetCssFromMulti()
    {
        $dom = new DomQuery('<p><a style="color: green;margin-top:2px"></a></p>');
        $this->assertEquals('green', $dom->find('a')->css('color'));
        $this->assertEquals('2px', $dom->find('a')->css('margin-top'));
        $this->assertEquals(null, $dom->find('p')->css('margin-top'));
    }

    /*
     * Test test css
     */
    public function testSetNewCss()
    {
        $dom = new DomQuery('<p><a></a></p>');
        $dom->find('a')->css('color', 'green');
        $this->assertEquals('<p><a style="color: green;"></a></p>', (string) $dom);
    }

    /*
     * Test add css to existing inline css
     */
    public function testSetExistingCss()
    {
        $dom = new DomQuery('<p><a style="margin-top: 5px;"></a></p>');
        $dom->find('a')->css('color', 'green');
        $this->assertEquals('<p><a style="margin-top: 5px;color: green;"></a></p>', (string) $dom);
    }

    /*
     * Test overwrite existing inline css property
     */
    public function testOverwriteCss()
    {
        $dom = new DomQuery('<p><a style="color: red;"></a></p>');
        $dom->find('a')->css('color', 'green');
        $this->assertEquals('<p><a style="color: green;"></a></p>', (string) $dom);
    }

    /*
     * Test replace with
     */
    public function testReplaceWith()
    {
        $dom = new DomQuery('<div> <a class="a"></a> <a class="b"></a> <a class="c"></a> </div>');
        $removed = $dom->find('.b')->replaceWith('<h2>Hello</h2>');
        $this->assertEquals('<a class="b"></a>', (string) $removed);
        $this->assertEquals('<div> <a class="a"></a> <h2>Hello</h2> <a class="c"></a> </div>', (string) $dom);
    }

    /*
     * Test replace with, using DomQuery as new content
     */
    public function testReplaceWithSelection()
    {
        $dom = new DomQuery('<div> <a class="a"></a> <a class="b"></a> <a class="c"></a> </div>');
        $removed = $dom->find('.c')->replaceWith($dom->find('.a'));
        $this->assertEquals('<a class="c"></a>', (string) $removed);
        $this->assertEquals('<div>  <a class="b"></a> <a class="a"></a> </div>', (string) $dom); // moved, not cloned
    }
}
