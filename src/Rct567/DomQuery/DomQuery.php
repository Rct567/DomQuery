<?php 

	namespace Rct567\DomQuery;

	/**
	 * Class DomQuery
	 * @package Rct567\DomQuery
	 */
	class DomQuery implements \IteratorAggregate, \Countable, \ArrayAccess {
		
		/**
		 * 
		 * @var \DOMDocument
		 */
		private $document; 

		/**
		 * @var \DOMNodeList[]
		 */
		private $nodes = array();
		
		/**
		 * Number of nodes
		 *
		 * @var integer
		 */
		public $length = null;
		
		/**
		 * Xpath used to creates result of this instance
		 *
		 * @var string
		 */
		private $xpath_query;

		/**
		 * Css selector given to create result of this instance
		 *
		 * @var string
		 */
		private $css_query;

		/**
		 * Jquery style property; css selector given to create result of this instance
		 *  
		 * @var string
		 */
		public $selector;
		
		/**
		 * LibXMl options used to load html for DOMDocument
		 *
		 * @var mixed
		 */
		public $libxml_options =
		  LIBXML_HTML_NOIMPLIED // turns off the automatic adding of implied html/body
		| LIBXML_HTML_NODEFDTD; // prevents a default doctype being added when one is not found
		
		/**
		 * Constructor
		 */
		public function __construct() {
			
			foreach(func_get_args() as $arg) {
			
				if ($arg instanceof \DOMDocument) $this->setDomDocument($arg);
				elseif ($arg instanceof \DOMNodeList) $this->loadDomNodeList($arg);
				elseif ($arg instanceof \DOMNode) $this->addDomNode($arg);
				elseif ($arg instanceof \DOMXPath) $this->dom_xpath = $arg;
				elseif (is_string($arg) && substr(ltrim($arg), 0, 1) == '<') $this->LoadHtmlContent($arg);
				elseif (is_object($arg)) throw new \InvalidArgumentException('Unknown object '.get_class($arg).' given as argument');
				else throw new \InvalidArgumentException('Unknown argument '.gettype($arg));
				
			}

		}

		/**
		 * Create new instance
		 * @return self
		 */
		public static function create() {

			return new DomQuery(...func_get_args());

		}
		
		/**
		 * Set dom document
		 *
		 * @param DOMDocument $document
		 * @return void
		 */
		public function setDomDocument(\DOMDocument $document) {
			
			if (isset($this->document) && $this->document != $document) throw new \Exception('Other DOMDocument already set!');
			
			$this->document = $document;
			
		}
		
		/**
		 * Load nodes from dom list
		 *
		 * @param DOMNodeList $dom_node_list
		 * @return void
		 */
		public function loadDomNodeList(\DOMNodeList $dom_node_list) {
			
			if (!isset($this->document)) throw new \Exception('DOMDocument is missing!');
			
			foreach($dom_node_list as $node) $this->addDomNode($node);
			
		}
		
		/**
		 * Add node
		 *
		 * @param DOMNode $dom_node
		 * @return void
		 */
		public function addDomNode(\DOMNode $dom_node) {
			
			$this->nodes[] = $dom_node;
			$this->length = count($this->nodes);
			$this->setDomDocument($dom_node->ownerDocument);
			
		}
		
		/**
		 * Load html content
		 *
		 * @param string $html
		 * @param string $encoding
		 * @return void
		 */
		public function LoadHtmlContent(string $html, $encoding='UTF-8') {
			
			$xml_pi_node_added = false;
			if ($encoding && stripos($html, '<?xml') === false) { // add pi nod to make libxml use the correct encoding
				$html = '<?xml encoding="'.$encoding.'">'.$html;
				$xml_pi_node_added = true;
			}
			
			libxml_disable_entity_loader(true);
			libxml_use_internal_errors(true);

			$dom_document = new \DOMDocument('1.0', $encoding);
			$dom_document->strictErrorChecking = false;
			$dom_document->validateOnParse = false;
			$dom_document->recover = true;
			$dom_document->loadHTML($html, $this->libxml_options);
			
			$this->setDomDocument($dom_document);
			
			if ($xml_pi_node_added) { // pi nod added, now remove it
				foreach($this->childNodes as $node) {
					if ($node->nodeType == XML_PI_NODE) {
						$doc->removeChild($node); 
						break;
					}
				}
			}
			
		}
		
		/**
		 * Use xpath and return new DomQuery with resulting nodes
		 * 
		 * @param string $xpath_query
		 * @return self|void
		 */
		public function xpath(string $xpath_query) {
			
			if (isset($this->document)) {
			
				$result = new DomQuery($this->document, $this->dom_xpath);
				$result->xpath_query = $xpath_query;
				
				if ($this->length > 0) {  // nodes as context
				
					foreach($this->nodes as $node) {
						
						$result_node_list = $this->dom_xpath->query('.'.$xpath_query, $node);
						if ($result_node_list === false) throw new \Exception('Expression '.$xpath_query.' is malformed or the first node of node_list as contextnode is invalid.');
						else $result->loadDomNodeList($result_node_list);
						
					}
					
					
				} else { // whole document
				
					$result_node_list = $this->dom_xpath->query($xpath_query);
					if ($result_node_list === false) throw new \Exception('Expression '.$xpath_query.' is malformed.');
					else $result->loadDomNodeList($result_node_list);
					
				}
				
				return $result;
			
			}
			
		}
		
		/**
		 * Use css expression and return new DomQuery with results
		 * 
		 * @param string $css_expression
		 * @return self|void
		 */
		public function find(string $css_expression) {
			
			if (isset($this->document)) {
			
				$xpath_expression = self::cssToXpath($css_expression);
				
				$result = $this->xpath($xpath_expression);
				
				$result->css_query = $css_expression;
				$result->selector = $css_expression; // jquery style
				
				return $result;
			
			}
			
		}
		
		/**
		 * jQuery: Get the combined text contents of each element in the set of matched elements, including their descendants, 
		 * or set the text contents of the matched elements.
		 * 
		 * @param string $val
		 * @return self|string|void
		 */
		public function text($val=null) {
			
			if (!is_null($val)) { // set node value for all nodes
				
				foreach($this[0] as $node) $node->nodeValue = $val;
				
				return $this;
				
			} else { // get value for first node
				
				if ($node = $this->getFirstDomNode()) {
					return $node->nodeValue;
				}
				
			}
			
		}
		
		/**
		 * jQuery: Get the value of an attribute for the first element in the set of matched elements 
		 * or set one or more attributes for every matched element.
		 *
		 * @param string $name
		 * @param string $val
		 * @return self|string
		 */
		public function attr($name, $val=null) {
			
			if (!is_null($val)) { // set attribute for all nodes
				
				foreach($this as $node) {
					if ($node instanceof \DOMElement) {
						$node->setAttribute($name, $val);
					}
				}
				
				return $this;
				
			} else { // get attribute value for first element
				
				if ($node = $this->getFirstDomNode()) {
					if ($node instanceof \DOMElement) {
						return $node->getAttribute($name);
					}
				}
				
			}
			
		}
		
		/**
		 * jQuery: Get the value of a property for the first element in the set of matched elements 
		 * or set one or more properties for every matched element.
		 *
		 * @param string $name
		 * @param string $val
		 * @return mixed
		 */
		public function prop($name, $val=null) {
			
			if (!is_null($val)) { // set attribute for all nodes
				
				foreach($this as $node) {
					$node->$name = $val;
				}
				
				return $this;
				
			} else { // get propertie value for first element

				if ($name == 'outerHTML') {
					return $this->getOuterHtml();
				} elseif ($node = $this->getFirstDomNode()) {
					if (isset($node->$name)) return $node->$name;
				}
				
			}
			
		}
		
		/**
		 * Get children
		 * jQuery: Get the children of each element in the set of matched elements, optionally filtered by a selector.
		 * 
		 * @param string $selector
		 * @return self 
		 */
		public function children($selector='*') {
			
			if (strpos($selector, ',') !== false) $selectors = explode(',', $selector);
			else $selectors = array($selector);
			
			// make all selectors for direct children
			foreach($selectors as &$single_selector) $single_selector = '> '.ltrim($single_selector, ' >');
			
			$direct_children_selector = implode(', ', $selectors);
			
			return $this->find($direct_children_selector);
			
		}
		
		/**
		 * Check if any node matches the selector
		 * Jquery: Check the current matched set of elements against a selector, element, or jQuery object 
		 * and return true if at least one of these elements matches the given arguments.
		 *
		 * @param string $selector
		 * @return boolean
		 */
		public function is($selector) {
			
			if ($this->length > 0) {
			
				$xpath_query = self::cssToXpath('> '.ltrim($selector, ' >'));
				
				foreach($this->nodes as $node) {
					
					$result_node_list = $this->dom_xpath->query('.'.$xpath_query, $node->parentNode);
					
					if ($result_node_list === false) throw new \Exception('Expression '.$xpath_query.' is malformed or the first node of node_list as contextnode is invalid.');
					
					if ($result_node_list->length > 0)  {
						
						if (strpos($selector, ' ') !== false) { // any child matches
							
							return true;
							
						} else { // self only
						
							foreach($result_node_list as $result_node) {
								if ($result_node->isSameNode($node)) return true;
							}
						
						}
						
					}
					
				}
				
			}
			
			return false;
			
		}
		
		/**
		 * Grants access to the DOM nodes of this instance
		 * jQuery: Retrieve the DOM elements matched by the jQuery object.
		 *
		 * @param int $index
		 * @return DOMNode|null
		 */
		public function get($index) {
			
			$result = array_slice($this->nodes, $index, 1); // note: index can be negative
			if (count($result) > 0) return $result[0];
			else return null; // return null if nu result for key
			
		}
		
		/**
		 * Returns DomQuery with first node
		 * jQuery: Reduce the set of matched elements to the first in the set.
		 * @return self|void
		 */
		public function first() {
			
			if (isset($this[0])) return $this[0];
			
		}
		
		/**
		 * Returns DomQuery with last node
		 * jQuery: Reduce the set of matched elements to the final one in the set.
		 * @return self|void
		 */
		public function last() {
			
			if ($this->length > 0) {
				if (isset($this[$this->length-1])) return $this[$this->length-1];
			}
			
		}
		
		/**
		 * Returns DomQuery with immediately following sibling of all nodes
		 * jQuery: Get the immediately following sibling of each element in the set of matched elements.
		 * @todo: If a selector is provided, it retrieves the previous sibling only if it matches that selector.
		 * @return self|void
		 */
		public function next() {
			
			if (isset($this->document) && $this->length > 0) {
			
				$result = new DomQuery($this->document);
				
				foreach($this->nodes as $node) {
					
					if (!is_null($node->nextSibling)) {
						$result->addDomNode($node->nextSibling);
					}
					
				}
				
				return $result;
			
			}
			
		}
		
		/**
		 * Returns DomQuery with immediately preceding sibling of all nodes
		 * jQuery: Get the immediately preceding sibling of each element in the set of matched elements. 
		 * @todo If a selector is provided, it retrieves the previous sibling only if it matches that selector.
		 * @return self|void
		 */
		public function prev() {
			
			if (isset($this->document) && $this->length > 0) {
			
				$result = new DomQuery($this->document);
				
				foreach($this->nodes as $node) { // get all previous sibling of all nodes
					
					if (!is_null($node->previousSibling)) {
						$result->addDomNode($node->previousSibling);
					}
					
				}
				
				return $result;
			
			}
			
		}
		
		/**
		 * Return first DOMNode
		 * @return DOMNode|void
		 */
		private function getFirstDomNode() {
			
			if (isset($this->nodes[0])) $first_dom_elm = $this->nodes[0];
			elseif (isset($this->document)) $first_dom_elm = $this->document->documentElement; // root element node 
			
			if ($first_dom_elm instanceof \DOMNode) return $first_dom_elm;

		}
		
		/**
		 * Call method of first DOMNode
		 * @return mixed
		 */
		public function __call($name, $arguments) {
			
			if (method_exists($this->getFirstDomNode(), $name)) return call_user_func_array(array($this->getFirstDomNode(), $name), $arguments);
			else throw new \Exception('Unknown call '.$name);
			
		}
		
		/**
		 * Access xpath or ... DOMNode properties (nodeName, parentNode etc) or get attribute value of first node
		 *
		 * @param string $name
		 * @return DOMXPath|DOMNode|string
		 */
		public function __get($name) {
			
			if ($name === 'dom_xpath') {
				return new \DOMXPath($this->document);
			} elseif ($name === 'outerHTML') {
				return $this->getOuterHtml();
			}
			
			if ($node = $this->getFirstDomNode()) {
				if (isset($node->$name)) return $node->{$name};
				elseif ($node->hasAttribute($name)) return $node->getAttribute($name);
			}
			
			return null;
			
		}
		
		/**
		 * Check if propertie exist for this instance
		 *
		 * @param string $name
		 * @return boolean
		 */
		public function __isset($name) {
			
			return $this->__get($name) != null;
			
		}

		/**
		 * Return html of first domnode
		 * @return string
		 */
		public function getOuterHtml() {

			if ($node = $this->getFirstDomNode()) {
				if (isset($this->document)) {
					return $this->document->saveHTML($node);
				}
			}

		}
		
		/**
		 * Return html of first domnode
		 * @return string
		 */
		public function __toString() {
			
			return $this->getOuterHtml();
			
		}
		
		/**
		 * Transform CSS selector expression to XPath
		 *
		 * @param string $path css selector expression
		 * @return string xpath expression
		 */
		public static function cssToXpath(string $path) {
			
			if (strstr($path, ',')) {
				
				$paths = explode(',', $path);
				$expressions = array();
				
				foreach ($paths as $path) {
					
					$xpath = static::cssToXpath(trim($path));
					
					if (is_string($xpath)) {
						$expressions[] = $xpath;
					} elseif (is_array($xpath)) {
						$expressions = array_merge($expressions, $xpath);
					}
					
				}
				
				return implode('|', $expressions);
				
			}
			
			// replace spaces inside (), to correcly create tokens
			
			for($i = 0; $i < strlen($path); $i++) {
				if ($path[$i] === ' ') {
					if (substr_count($path, '(', 0, $i) != substr_count($path, ')', 0, $i)) $path[$i] = "\0";
				}
				
			}
			
			// create and analyze tokens and create segments
			
			$tokens = preg_split('/\s+/', $path);
			
			$segments = array();
			
			$relation_tokens = array('>', '~', '+');
			
			foreach($tokens as $key => $token) {
				
				$token = str_replace("\0", ' ', $token); // restore spaces
				
				if (!in_array($token, $relation_tokens)) {
				
					$segment = (object) array('selector' => '', 'relation_filter' => false, 'attribute_filters' => array(), 'pseudo_filters' => array());
					
					if (isset($tokens[$key-1]) && in_array($tokens[$key-1], $relation_tokens)) { // get relationship selector
						$segment->relation_filter = $tokens[$key-1];
					}
					
					$segment->selector = $token;

					while(preg_match('/(.*)\:(not|contains|has)\((.+)\)$/', $segment->selector, $matches)) { // pseudo selector
						$segment->selector = $matches[1];
						$segment->pseudo_filters[] = $matches[2].'('.$matches[3].')'; 
					}
					
					while(preg_match('/(.*)\:([a-z][a-z\-]*)$/', $segment->selector, $matches)) { // pseudo selector
						$segment->selector = $matches[1];
						$segment->pseudo_filters[] = $matches[2]; 
					}
					
					while(preg_match('/(.*)\[([^]]+)\]$/', $segment->selector, $matches)) { // attribute selector
						$segment->selector = $matches[1];
						$segment->attribute_filters[] = $matches[2];
					}
					
					while(preg_match('/(.*)\.([a-z][a-z0-9\-]+)$/', $segment->selector, $matches)) { // class selector
						$segment->selector = $matches[1];
						$segment->attribute_filters[] = 'class~="'.$matches[2].'"'; 
					}
					
					while(preg_match('/(.*)\#([a-z][a-z0-9\-]+)$/', $segment->selector, $matches)) { // id selector
						$segment->selector = $matches[1];
						$segment->attribute_filters[] = 'id="'.$matches[2].'"'; 
					}
					
					$segments[] = $segment;
				
				}
				
			}
			
			// use segments to create array with transformed tokens
			
			$new_path_tokens = array();
			
			foreach($segments as $segment) {
				
				if ($segment->relation_filter === '>') $new_path_tokens[] = '/';
				elseif ($segment->relation_filter === '~') $new_path_tokens[] = '/following-sibling::';
				elseif ($segment->relation_filter === '+') $new_path_tokens[] = '/following-sibling::';
				else $new_path_tokens[] = '//';
				
				if ($segment->relation_filter === '+') $segment->pseudo_filters[] = 'first-child';
				
				if ($segment->selector != '') $new_path_tokens[] = $segment->selector; // specific tagname
				elseif (substr(array_slice($new_path_tokens, -1)[0], -2) != '::') $new_path_tokens[] = '*'; // any tagname
				
				foreach(array_reverse($segment->attribute_filters) as $attr) {
					$new_path_tokens[] = self::tranformAttrSelection($attr);
				}
				
				foreach(array_reverse($segment->pseudo_filters) as $attr) {
					$new_path_tokens[] = self::transformCssPseudoSelector($attr, $new_path_tokens);
				}
				
			}
			
			return implode('', $new_path_tokens);
			
		}
		
		/**
		 * Tranform 'css pseudo selector' expression to xpath expression
		 *
		 * @param string $expression
		 * @param array $new_path_tokens
		 * @return string trandformed expression (xpath)
		 */
		private static function transformCssPseudoSelector($expression, array &$new_path_tokens) {
			
			// not selector
			
			if (strpos($expression, 'not(') === 0) {
				
				$expression = preg_replace_callback(
					'|not\((.+)\)|i', function ($matches) {
						return '[not(' . ltrim(self::cssToXpath($matches[1]), '/') .')]';
					},
					$expression
				);
				
				return $expression;
			
			} elseif (strpos($expression, 'contains(') === 0) {
				
				$expression = preg_replace_callback(
					'|contains\((.+)\)|i', function ($matches) {
						return '[text()[contains(.,\''.$matches[1].'\')]]'; // contain the specified text
					},
					$expression
				);
				
				return $expression;
			
			} elseif (strpos($expression, 'has(') === 0) {
				
				$expression = preg_replace_callback(
					'|has\((.+)\)|i', function ($matches) {
						if (substr($matches[1], 0, 2) == '> ') return '[descendant::' . ltrim(self::cssToXpath($matches[1]), '/') .']';
						else return '[' . ltrim(self::cssToXpath($matches[1]), '/') .']';
					},
					$expression
				);
				
				return $expression;
			
			} elseif ($expression === 'first' || $expression === 'last') { // new path inside selection
				
				array_unshift($new_path_tokens, '(');
				$new_path_tokens[] = ')';
				
			}
			
			//  static replacement
			
			$pseudo_class_selectors = array(
				'disabled' => '[@disabled]',
				'first-child' => '[1]',
				'last-child' => '[last()]',
				'only-child' => '[count(*)=1]',
				'empty' => '[count(*) = 0 and string-length() = 0]',
				'not-empty' => '[count(*) > 0 or string-length() > 0]',
				'parent' => '[count(*) > 0]',
				'header' => '[self::h1 or self::h2 or self::h3 or self::h5 or self::h5 or self::h6]',
				'odd' => '[position() mod 2 = 1]',
				'even' => 'not([position() mod 2 = 1])',
				'first' => '[1]',
				'last' => '[last()]',
				'root' => '[not(parent::*)]'
			);
			
			if (isset($pseudo_class_selectors[$expression])) return $pseudo_class_selectors[$expression];
			else throw new \Exception('Pseudo class '.$expression.' unknown');
			
			return $expression;
			
		}
		
		/**
		 * Tranform 'css attribute selector' expression to xpath expression
		 *
		 * @param string $expression
		 * @return string trandformed expression (xpath)
		 */
		private static function tranformAttrSelection($expression) {
			
			$expression = '['.$expression.']';
			
			// arbitrary attribute strict value equality
			$expression = preg_replace_callback(
				'|\[@?([a-z0-9_-]+)=[\'"]([^\'"]+)[\'"]\]|i', function ($matches) {
					return '[@' . strtolower($matches[1]) . "='" . $matches[2] . "']";
				},
				$expression
			);
			
			// arbitrary attribute Negation strict value 
			$expression = preg_replace_callback(
				'|\[@?([a-z0-9_-]+)!=[\'"]([^\'"]+)[\'"]\]|i', function ($matches) {
					return '[@' . strtolower($matches[1]) . "!='" . $matches[2] . "']";
				},
				$expression
			);

			// arbitrary attribute value contains full word
			$expression = preg_replace_callback(
				'|\[([a-z0-9_-]+)~=[\'"]([^\'"]+)[\'"]\]|i', function ($matches) {
					return "[contains(concat(' ', normalize-space(@" . strtolower($matches[1]) . "), ' '), ' ". $matches[2] . " ')]";
				},
				$expression
			);

			// arbitrary attribute value contains specified content
			$expression = preg_replace_callback(
				'|\[([a-z0-9_-]+)\*=[\'"]([^\'"]+)[\'"]\]|i', function ($matches) {
					return "[contains(@" . strtolower($matches[1]) . ", '" . $matches[2] . "')]";
				},
				$expression
			);
			
			// attribute value starts with specified content
			$expression = preg_replace_callback(
				'|\[([a-z0-9_-]+)\^=[\'"]([^\'"]+)[\'"]\]|i', function ($matches) {
					return "[starts-with(@" . strtolower($matches[1]) . ", '" . $matches[2] . "')]";
				},
				$expression
			);
			
			// attribute value ends with specified content
			$expression = preg_replace_callback(
				'|\[([a-z0-9_-]+)\$=[\'"]([^\'"]+)[\'"]\]|i', function ($matches) {
					return "[@" . strtolower($matches[1]) . " = substring(@" . strtolower($matches[1]) . ", string-length(@" . strtolower($matches[1]) . ") - string-length(@" . strtolower($matches[1]) . ") +1)]";
				},
				$expression
			);
			
			// attribute no value
			$expression = preg_replace_callback(
				'|\[([a-z0-9]+)([a-z0-9_-]*)\]|i', function ($matches) {
					return "[@" . $matches[1].$matches[2] . "]";
				},
				$expression
			);
			
			return $expression;
			
		}

		/**
		 * Retrieve last used CSS Query
		 * @return string
		 */
		public function getCssQuery() {

			return $this->css_query;

		}

		/**
		 * Retrieve last created XPath query
		 * @return string
		 */
		public function getXpathQuery() {

			return $this->xpath_query;

		}

		/**
		 * Retrieve DOMDocument
		 * @return DOMDocument
		 */
		public function getDocument() {

			return $this->document;
			
		}
		
		/**
		 * IteratorAggregate
		 * note: using Iterator conflicts with next method in jquery
		 */
		public function getIterator() {
			
			$iteration_result = array();
			if (is_array($this->nodes)) {
				foreach($this->nodes as $node) $iteration_result[] = new DomQuery($node);
			}
			
			return new \ArrayIterator($iteration_result);
			
		}

		/**
		 * Countable: get count
		 * @return int
		 */
		public function count() {

			if (isset($this->nodes) && is_array($this->nodes)) return count($this->nodes);
			else return 0;

		}

		/**
		 * ArrayAccess: offset exists
		 * @param int $key
		 * @return bool
		 */
		public function offsetExists($key) {
			
			if (in_array($key, range(0, $this->length - 1)) && $this->length > 0) {
				return true;
			}
			
			return false;
			
		}

		/*
		 * ArrayAccess: get offset
		 * @param int $key
		 * @return mixed
		 */
		public function offsetGet($key) {
			
			if (isset($this->nodes[$key])) return new DomQuery($this->nodes[$key]);
			else return null;
			
		}

		/*
		 * ArrayAccess: set offset
		 * @param  mixed $key
		 * @param  mixed $value
		 * @throws Exception\BadMethodCallException when attempting to write to a read-only item
		 */
		public function offsetSet($key, $value) {

			throw new \BadMethodCallException('Attempting to write to a read-only list');

		}

		/*
		 * ArrayAccess: unset offset
		 * @param  mixed $key
		 * @throws Exception\BadMethodCallException when attempting to unset a read-only item
		 */
		public function offsetUnset($key) {

			throw new \BadMethodCallException('Attempting to unset on a read-only list');
			
		}
		
	}