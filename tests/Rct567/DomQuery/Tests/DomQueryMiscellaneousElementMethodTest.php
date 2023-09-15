<?php

namespace Rct567\DomQuery\Tests;

use Rct567\DomQuery\DomQuery;

class DomQueryMiscellaneousElementMethodTest extends \PHPUnit\Framework\TestCase
{
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
     * Test clone
     */
    public function testClone()
    {
        $dom = new DomQuery('<div><p>My words</p></div>');
        $elm = $dom->find('p');
        $cloned_elm = $elm->clone();
        $this->assertEquals('<p>My words</p>', (string) $elm);
        $this->assertEquals('<p>My words</p>', (string) $cloned_elm);
        $this->assertFalse($elm->get(0)->isSameNode($cloned_elm->get(0)));

        $dom_clone = $dom->clone();
        $this->assertEquals('<div><p>My words</p></div>', $dom_clone);
        $this->assertEquals('<p>My words</p>', $dom_clone->find('p')->first());
        $this->assertTrue($dom_clone->find('p')->first()->is('p'));
    }
}
