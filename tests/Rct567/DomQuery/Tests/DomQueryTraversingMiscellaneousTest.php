<?php

namespace Rct567\DomQuery\Tests;

use Rct567\DomQuery\DomQuery;

class DomQueryTraversingMiscellaneousTest extends \PHPUnit\Framework\TestCase
{
    /*
     * Test add using selector
     */
    public function testAddWithSelector()
    {
        $dom = new DomQuery('<a>1</a><span>2</span>');
        $dom->find('a')->add('span')->addClass('yep');
        $this->assertEquals('<a class="yep">1</a><span class="yep">2</span>', (string) $dom);
    }

    /*
     * Test add using html fragment
     */
    public function testAddWithHtml()
    {
        $dom = new DomQuery('<a>1</a>');
        $new_dom = $dom->find('a')->add('<span>2</span>');
        $this->assertEquals('<a>1</a>', (string) $dom);
        $this->assertEquals('<a>1</a><span>2</span>', (string) $new_dom);
    }

    /*
     * Test add using selector with html fragment as context
     */
    public function testAddWithHtmlContext()
    {
        $dom = new DomQuery('<a>1</a><p>nope</p>');
        $new_dom = $dom->find('a')->add('span', '<span>2</span><p>nope</p>');
        $this->assertEquals('<a>1</a><p>nope</p>', (string) $dom);
        $this->assertEquals('<a>1</a><span>2</span>', (string) $new_dom);
    }

    /*
     * Test addBack().
     */
    public function testAddBack()
    {
        $dom = new DomQuery('
            <div>
                <span id="first">1</span>
                <span>2</span>
                <span>3</span>
            </div>');

        $result =  $dom->find('#first')->next();

        $this->assertEquals('<span>2</span>', (string) $result); // only next to the first
        $this->assertEquals('<span id="first">1</span><span>2</span>', (string) $result->addBack()); // prepend the first with addBack()
    }
}
