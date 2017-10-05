<?php 

	namespace Rct567\DomQuery\Tests;

	use Rct567\DomQuery\DomQuery;

	class DomQueryTest extends \PHPUnit\Framework\TestCase {
		
		/*
		 * Test css to xpath conversion
		 */
		public function testCssToXpath() {
			
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
				':odd' => '//*[position() mod 2 = 1]',
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
			
			foreach($css_to_xpath as $css => $expected_xpath) {
				
				$this->assertEquals($expected_xpath, DomQuery::cssToXpath($css, true), $css);
				
			}	
			
		}

		/*
		 * Test filters, first and last
		 */
		public function testFirstAndLastFilters() {
			
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
		 * Test traversing nodes from readme
		 */
		public function testTraversingNodesReadmeExamples() {
			
			$dom = new DomQuery('<a>1</a> <a>2</a> <a>3</a>');
			$links = $dom->children('a');

			$result = '';
			foreach($links as $elm) $result .= $elm->text(); 
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
		public function testBasicUsageReadmeExamples() {
			
			$dom = new DomQuery('<div><h1 class="title">Hello</h1></div>');

			$this->assertEquals('Hello', $dom->find('div')->text()); 
			$this->assertEquals('<div><h1 class="title">Hello</h1></div>', $dom->find('div')->prop('outerHTML')); 
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
		public function testLoadHtmlAndGetLink() {
			
			$dom = new DomQuery;
			$dom->LoadHtmlContent('<!DOCTYPE html> <html> <head></head> <body> <p><a href="test.html"></a></p> <a href="test2.html">X</a> </body> </html>');
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
		public function testLoadingUf8AndGettingSameContent() {
			
			$html = '<div><h1>ßüöä</h1></div>';
			$dom = new DomQuery($html);

			$this->assertEquals($html, (string) $dom); // same result
			$this->assertEquals('<h1>ßüöä</h1>', (string) $dom->find('h1')); // same header
			$this->assertEquals('ßüöä', $dom->find('h1')->text()); // same text
			$this->assertEquals('ßüöä', $dom->text()); 
		}
		
		/*
		 * Make selection and subselection
		 */
		public function testSubSelection() {
			
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
			
			// main selection
			$main_selection = $dom->find('.level-a');
			$this->assertEquals(3, count($main_selection));
			
			// make subselection
			$sub_selection = $main_selection->find('> div'); // child divs from main selection
			$this->assertEquals(3, count($sub_selection));
			$this->assertEquals('level-b', $sub_selection->class);	
			
			// check what it is
			$this->assertTrue($sub_selection->is('div.level-b'));
			$this->assertTrue($sub_selection->is('div:not-empty'));	// last has child (div with class level-c)
			$this->assertTrue($sub_selection->is('div div.level-c'));	// last has child (div with class level-c)
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
		public function testSingleNodeAttr() {
			
			$this->assertEquals('hello', DomQuery::create('<a title="hello"></a>')->attr('title'));

		}

		/*
		 * Test create single node
		 */
		public function testSingleNodeAttrChange() {
			
			$this->assertEquals('oke', DomQuery::create('<a title="hello"></a>')->attr('title', 'oke')->attr('title'));

		}

		/*
		 * Test change text
		 */
		public function testSingleNodeTextChange() {
			
			$dom = DomQuery::create('<a title="hello">Some text</a>');
			$new_html = (string) $dom->text('Changed text');
			$this->assertEquals('<a title="hello">Changed text</a>', $new_html);
			$this->assertEquals('Changed text', $dom->text());

		}

		/*
		* Test instance without nodes
		*/
		public function testDomQueryNoDocument() {
			
			$dom = new DomQuery;
			$this->assertEquals(0, count($dom));
			$this->assertFalse(isset($dom[0]));
			$this->assertEquals(0, count($dom->find('*')));
			$this->assertEquals(0, count($dom->children()));
			
			$num = 0;
			foreach($dom as $node) $num++;
			$this->assertEquals(0, $num);
			
		}

		/* 
		* Test constructor exception 
		*/
		public function testConstructorException() {

			$this->expectException(\InvalidArgumentException::class);
			$dom = new DomQuery(new \stdClass);

		}
		
	}
	
?>