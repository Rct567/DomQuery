<?php

namespace Rct567\DomQuery;

/**
 * Class DomQuery
 *
 * @property \DOMXPath $dom_xpath
 * @property string $tagName
 * @property string $nodeName
 * @property string $nodeValue
 *
 * @method string getAttribute(string $name)
 *
 * @package Rct567\DomQuery
 */
class DomQuery implements \IteratorAggregate, \Countable, \ArrayAccess
{
    /**
     * Instance of DOMDocument
     *
     * @var \DOMDocument
     */
    private $document;

    /**
     * All nodes as instances of DOMNode
     *
     * @var \DOMNode[]
     */
    private $nodes = array();

    /**
     * Number of nodes
     *
     * @var integer
     */
    public $length = null;

    /**
     * Xpath expression used to create the result of this instance
     *
     * @var string
     */
    private $xpath_query;

    /**
     * Root instance who began the chain
     *
     * @var self
     */
    private $root_instance;

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
     * Preserve no newlines (prevent creating newlines in html result)
     */
    public $preserve_no_newlines;

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
    public function __construct()
    {
        foreach (func_get_args() as $arg) {
            if ($arg instanceof \DOMDocument) {
                $this->setDomDocument($arg);
            } elseif ($arg instanceof \DOMNodeList) {
                $this->loadDomNodeList($arg);
            } elseif ($arg instanceof \DOMNode) {
                $this->addDomNode($arg);
            } elseif ($arg instanceof \DOMXPath) {
                $this->dom_xpath = $arg;
            } elseif (is_string($arg) && strpos($arg, '>') !== false) {
                $this->loadHtmlContent($arg);
            } elseif (is_object($arg)) {
                throw new \InvalidArgumentException('Unknown object '.get_class($arg).' given as argument');
            } else {
                throw new \InvalidArgumentException('Unknown argument '.gettype($arg));
            }
        }
    }

    /**
     * Create new instance
     *
     * @return self
     */
    public static function create()
    {
        return new self(...func_get_args());
    }

    /**
     * Set dom document
     *
     * @param \DOMDocument $document
     *
     * @return void
     */
    public function setDomDocument(\DOMDocument $document)
    {
        if (isset($this->document) && $this->document !== $document) {
            throw new \Exception('Other DOMDocument already set!');
        }

        $this->document = $document;
    }

    /**
     * Load nodes from dom list
     *
     * @param \DOMNodeList $dom_node_list
     *
     * @return void
     */
    public function loadDomNodeList(\DOMNodeList $dom_node_list)
    {
        if (!isset($this->document)) {
            throw new \Exception('DOMDocument is missing!');
        }

        foreach ($dom_node_list as $node) {
            $this->addDomNode($node);
        }
    }

    /**
     * Add node
     *
     * @param \DOMNode $dom_node
     *
     * @return void
     */
    public function addDomNode(\DOMNode $dom_node)
    {
        $this->nodes[] = $dom_node;
        $this->length = count($this->nodes);
        $this->setDomDocument($dom_node->ownerDocument);
    }

    /**
     * Load html content
     *
     * @param string $html
     * @param string $encoding
     *
     * @return void
     */
    public function loadHtmlContent(string $html, $encoding='UTF-8')
    {
        $this->preserve_no_newlines = (strpos($html, '<') !== false && strpos($html, "\n") === false);

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

        foreach ($dom_document->childNodes as $node) {
            if ($xml_pi_node_added) { // pi nod added, now remove it
                if ($node->nodeType == XML_PI_NODE) {
                    $dom_document->removeChild($node);
                    break;
                }
            }
        }

        foreach ($dom_document->childNodes as $node) {
            $this->nodes[] = $node;
        }

        $this->length = count($this->nodes);
    }

    /**
     * Use xpath and return new DomQuery with resulting nodes
     *
     * @param string $xpath_query
     *
     * @return self|void
     */
    public function xpath(string $xpath_query)
    {
        if (isset($this->document)) {
            $result = new self($this->document, $this->dom_xpath);
            if (isset($this->root_instance)) {
                $result->root_instance = $this->root_instance;
            } else {
                $result->root_instance = $this;
            }
            $result->xpath_query = $xpath_query;

            if ($this->length > 0 && isset($this->xpath_query)) {  // all nodes as context
                foreach ($this->nodes as $node) {
                    if ($result_node_list = $this->xpathQuery('.'.$xpath_query, $node)) {
                        $result->loadDomNodeList($result_node_list);
                    }
                }
            } else { // whole document
                if ($result_node_list = $this->xpathQuery($xpath_query)) {
                    $result->loadDomNodeList($result_node_list);
                }
            }

            return $result;
        }
    }

    /**
     * Use css expression and return new DomQuery with results
     *
     * @param string $css_expression
     *
     * @return self|void
     */
    public function find(string $css_expression)
    {
        if (isset($this->document)) {
            $xpath_expression = self::cssToXpath($css_expression);
            $result = $this->xpath($xpath_expression);

            $result->css_query = $css_expression;
            $result->selector = $css_expression; // jquery style

            return $result;
        }
    }

    /**
     * Get the combined text contents of each element in the set of matched elements, including their descendants,
     * or set the text contents of the matched elements.
     *
     * @param string|null $val
     *
     * @return $this|string|void
     */
    public function text($val=null)
    {
        if (!is_null($val)) { // set node value for all nodes
            foreach ($this->nodes as $node) {
                $node->nodeValue = $val;
            }

            return $this;
        } else { // get value for first node
            if ($node = $this->getFirstElmNode()) {
                return $node->nodeValue;
            }
        }
    }

    /**
     * Get the HTML contents of the first element in the set of matched elements
     *
     * @param string|null $html_string
     *
     * @return $this|string|void
     */
    public function html($html_string=null)
    {
        if (!is_null($html_string)) { // set html for all nodes
            foreach ($this as $node) {
                $node->get(0)->nodeValue = '';
                $node->append($html_string);
            }

            if (strpos($html_string, "\n") !== false) {
                $this->preserve_no_newlines = false;
            }

            return $this;
        } else { // get html for first node
            if ($node = $this->getFirstElmNode()) {
                $html = '';
                $document = $node->ownerDocument;

                foreach ($node->childNodes as $node) {
                    $html .= $document->saveHTML($node);
                }

                if ($this->preserve_no_newlines) {
                    $html = str_replace("\n", '', $html);
                }

                return $html;
            }
        }
    }

    /**
     * Get the value of an attribute for the first element in the set of matched elements
     * or set one or more attributes for every matched element.
     *
     * @param string $name
     * @param string $val
     *
     * @return $this|string
     */
    public function attr(string $name, $val=null)
    {
        if (!is_null($val)) { // set attribute for all nodes
            foreach ($this->nodes as $node) {
                if ($node instanceof \DOMElement) {
                    $node->setAttribute($name, $val);
                }
            }
            return $this;
        } else { // get attribute value for first element
            if ($node = $this->getFirstElmNode()) {
                if ($node instanceof \DOMElement) {
                    return $node->getAttribute($name);
                }
            }
        }
    }

    /**
     * Remove an attribute from each element in the set of matched elements
     *
     * @param string $name
     *
     * @return $this
     */
    public function removeAttr($name)
    {
        foreach ($this->nodes as $node) {
            if ($node instanceof \DOMElement) {
                $node->removeAttribute($name);
            }
        }

        return $this;
    }

    /**
     * Adds the specified class(es) to each element in the set of matched elements.
     *
     * @param string $class_name class name(s)
     *
     * @return $this
     */
    public function addClass($class_name)
    {
        $add_names = preg_split('#\s+#s', $class_name);

        foreach ($this->nodes as $node) {
            if ($node instanceof \DOMElement) {
                $node_classes = array();
                if ($node_class_attr = $node->getAttribute('class')) {
                    $node_classes = preg_split('#\s+#s', $node_class_attr);
                }
                foreach ($add_names as $add_name) {
                    if (!in_array($add_name, $node_classes)) {
                        $node_classes[] = $add_name;
                    }
                }
                if (count($node_classes) > 0) {
                    $node->setAttribute('class', implode(' ', $node_classes));
                }
            }
        }

        return $this;
    }

    /**
     * Determine whether any of the matched elements are assigned the given class.
     *
     * @param string $class_name
     *
     * @return boolean
     */
    public function hasClass($class_name)
    {
        foreach ($this->nodes as $node) {
            if ($node instanceof \DOMElement) {
                if ($node_class_attr = $node->getAttribute('class')) {
                    $node_classes = preg_split('#\s+#s', $node_class_attr);
                    if (in_array($class_name, $node_classes)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Remove a single class, multiple classes, or all classes from each element in the set of matched elements.
     *
     * @param string $class_name
     *
     * @return $this
     */
    public function removeClass($class_name='')
    {
        $remove_names = preg_split('#\s+#s', $class_name);
        
        foreach ($this->nodes as $node) {
            if ($node instanceof \DOMElement && $node->hasAttribute('class')) {
                $node_classes = preg_split('#\s+#s', $node->getAttribute('class'));
                $class_removed = false;
            
                if ($class_name === '') { // remove all
                    $node_classes = array();
                    $class_removed = true;
                } else {
                    foreach ($remove_names as $remove_name) {
                        $key = array_search($remove_name, $node_classes);
                        if ($key !== false) {
                            unset($node_classes[$key]);
                            $class_removed = true;
                        }
                    }
                }
                if ($class_removed) {
                    $node->setAttribute('class', implode(' ', $node_classes));
                }
            }
        }

        return $this;
    }

    /**
     * Get the value of a property for the first element in the set of matched elements
     * or set one or more properties for every matched element.
     *
     * @param string $name
     * @param string $val
     *
     * @return $this|mixed
     */
    public function prop(string $name, $val=null)
    {
        if (!is_null($val)) { // set attribute for all nodes
            foreach ($this->nodes as $node) {
                $node->$name = $val;
            }

            return $this;
        } else { // get property value for first element
            if ($name == 'outerHTML') {
                return $this->getOuterHtml();
            } elseif ($node = $this->getFirstElmNode()) {
                if (isset($node->$name)) {
                    return $node->$name;
                }
            }
        }
    }

    /**
     * Get the children of each element in the set of matched elements, optionally filtered by a selector.
     *
     * @param string $selector
     *
     * @return self|void
     */
    public function children(string $selector='*')
    {
        if (strpos($selector, ',') !== false) {
            $selectors = explode(',', $selector);
        } else {
            $selectors = array($selector);
        }

        // make all selectors for direct children
        foreach ($selectors as &$single_selector) {
            $single_selector = '> '.ltrim($single_selector, ' >');
        }

        $direct_children_selector = implode(', ', $selectors);

        return $this->find($direct_children_selector);
    }

    /**
     * Get the parent of each element in the current set of matched elements, optionally filtered by a selector
     *
     * @param string|null $selector expression that filters the set of matched elements
     *
     * @return self|void
     */
    public function parent(string $selector=null)
    {
        if (isset($this->document) && $this->length > 0) {
            $result = new self($this->document);

            foreach ($this->nodes as $node) {
                if (!is_null($node->parentNode)) {
                    $result->addDomNode($node->parentNode);
                }
            }

            if ($selector) {
                $result = $result->filter($selector);
            }

            return $result;
        }
    }

    /**
     * Reduce the set of matched elements to those that match the selector
     *
     * @param string $selector
     *
     * @return self
     */
    public function not(string $selector)
    {
        $result = new self($this->document, $this->dom_xpath);

        if ($this->length > 0) {
            $xpath_query = self::cssToXpath($selector);
            $selector_result_node_list = $this->xpathQuery($xpath_query);

            $result->xpath_query = $xpath_query;

            if ($selector_result_node_list->length > 0) {
                foreach ($this->nodes as $node) {
                    $matched = false;
                    foreach ($selector_result_node_list as $result_node) {
                        if ($result_node->isSameNode($node)) {
                            $matched = true;
                            break 1;
                        }
                    }
                    if (!$matched) {
                        $result->addDomNode($node);
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Reduce the set of matched elements to those that match the selector
     *
     * @param string $selector
     *
     * @return self
     */
    public function filter(string $selector)
    {
        $result = new self($this->document, $this->dom_xpath);

        if ($this->length > 0) {
            $xpath_query = self::cssToXpath($selector);
            $selector_result_node_list = $this->xpathQuery($xpath_query);

            $result->xpath_query = $xpath_query;

            if ($selector_result_node_list->length > 0) {
                foreach ($this->nodes as $node) {
                    foreach ($selector_result_node_list as $result_node) {
                        if ($result_node->isSameNode($node)) {
                            $result->addDomNode($node);
                            break 1;
                        }
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Check if any node matches the selector
     *
     * @param string $selector
     *
     * @return boolean
     */
    public function is(string $selector)
    {
        if ($this->length > 0) {
            $xpath_query = self::cssToXpath($selector);
            $result_node_list = $this->xpathQuery($xpath_query);

            if ($result_node_list->length > 0) {
                foreach ($this->nodes as $node) {
                    foreach ($result_node_list as $result_node) {
                        if ($result_node->isSameNode($node)) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * Grants access to the DOM nodes of this instance
     *
     * @param int $index
     *
     * @return \DOMNode|null
     */
    public function get($index)
    {
        $result = array_slice($this->nodes, $index, 1); // note: index can be negative
        if (count($result) > 0) {
            return $result[0];
        } else {
            return null; // return null if nu result for key
        }
    }

    /**
     * Returns DomQuery with first node
     *
     * @param string|null $selector expression that filters the set of matched elements
     *
     * @return self|void
     */
    public function first(string $selector=null)
    {
        if (isset($this[0])) {
            $result = $this[0];
            if ($selector) {
                $result = $result->filter($selector);
            }
            return $result;
        }
    }

    /**
     * Returns DomQuery with last node
     *
     * @param string|null $selector expression that filters the set of matched elements
     *
     * @return self|void
     */
    public function last(string $selector=null)
    {
        if ($this->length > 0 && isset($this[$this->length-1])) {
            $result = $this[$this->length-1];
            if ($selector) {
                $result = $result->filter($selector);
            }
            return $result;
        }
    }

    /**
     * Returns DomQuery with immediately following sibling of all nodes
     *
     * @param string|null $selector expression that filters the set of matched elements
     *
     * @return self|void
     */
    public function next(string $selector=null)
    {
        if (isset($this->document) && $this->length > 0) {
            $result = new self($this->document);

            foreach ($this->nodes as $node) {
                if (!is_null($node->nextSibling)) {
                    $result->addDomNode($node->nextSibling);
                }
            }

            if ($selector) {
                $result = $result->filter($selector);
            }

            return $result;
        }
    }

    /**
     * Returns DomQuery with immediately preceding sibling of all nodes
     *
     * @param string|null $selector expression that filters the set of matched elements
     *
     * @return self|void
     */
    public function prev(string $selector=null)
    {
        if (isset($this->document) && $this->length > 0) {
            $result = new self($this->document);

            foreach ($this->nodes as $node) { // get all previous sibling of all nodes
                if (!is_null($node->previousSibling)) {
                    $result->addDomNode($node->previousSibling);
                }
            }

            if ($selector) {
                $result = $result->filter($selector);
            }

            return $result;
        }
    }

    /**
     * Iterate over result set and executing a callback for each node
     *
     * @param callable $callback
     *
     * @return $this
     */
    public function each(callable $callback)
    {
        foreach ($this->nodes as $index => $node) {
            $return_value = call_user_func($callback, $index, $node);

            if ($return_value === false) {
                break;
            }
        }

        return $this;
    }

    /**
     * Remove the set of matched elements
     *
     * @param string|null $selector expression that filters the set of matched elements to be removed
     *
     * @return self
     */
    public function remove(string $selector=null)
    {
        $result = $this;
        if ($selector) {
            $result = $result->filter($selector);
        }
        foreach ($result->nodes as $node) {
            if ($node->parentNode) {
                $node->parentNode->removeChild($node);
            }
        }

        $result->nodes = array();
        $result->length = 0;

        return $result;
    }

    /**
     * Import nodes and do something with them
     *
     * @param string|self|array $content
     * @param callable $import_function
     *
     * @return void
     */
    private function importNodes($content, callable $import_function)
    {
        if (is_array($content)) {
            foreach ($content as $item) {
                $this->importNodes($item, $import_function);
            }
        } else {
            if (is_string($content) && strpos($content, "\n") !== false) {
                $this->preserve_no_newlines = false;
                if (isset($this->root_instance)) {
                    $this->root_instance->preserve_no_newlines = false;
                }
            }

            if (!($content instanceof DomQuery)) {
                $content = new self($content);
            }

            foreach ($this->nodes as $node) {
                foreach ($content->getNodes() as $content_node) {
                    $imported_node = $this->document->importNode($content_node, true);
                    $import_function($node, $imported_node);
                }
            }
        }
    }

    /**
     * Insert content to the end of each element in the set of matched elements.
     *
     * @param string|self $content,...
     *
     * @return $this
     */
    public function append()
    {
        $this->importNodes(func_get_args(), function ($node, $imported_node) {
            $node->appendChild($imported_node);
        });

        return $this;
    }

    /**
     * Insert content to the beginning of each element in the set of matched elements
     *
     * @param string|self $content,...
     *
     * @return $this
     */
    public function prepend()
    {
        $this->importNodes(func_get_args(), function ($node, $imported_node) {
            $node->insertBefore($imported_node, $node->childNodes->item(0));
        });

        return $this;
    }

    /**
     * Insert content before each element in the set of matched elements.
     *
     * @param string|self $content,...
     *
     * @return $this
     */
    public function before()
    {
        $this->importNodes(func_get_args(), function ($node, $imported_node) {
            if ($node->parentNode instanceof \DOMDocument) {
                throw new \Exception('Can not set before root element '.$node->tagName.' of document');
            } else {
                $node->parentNode->insertBefore($imported_node, $node);
            }
        });

        return $this;
    }

    /**
     * Insert content after each element in the set of matched elements.
     *
     * @param string|self $content,...
     *
     * @return $this
     */
    public function after()
    {
        $this->importNodes(func_get_args(), function ($node, $imported_node) {
            if ($node->nextSibling) {
                $node->parentNode->insertBefore($imported_node, $node->nextSibling);
            } else { // node is last, so there is no next sibling to insert before
                $node->parentNode->appendChild($imported_node);
            }
        });

        return $this;
    }

    /**
     * Return array with nodes
     *
     * @return \DOMNode[]
     */
    private function getNodes()
    {
        return $this->nodes;
    }

    /**
     * Return first DOMElement
     *
     * @return \DOMElement|void
     */
    private function getFirstElmNode()
    {
        foreach ($this->nodes as $node) {
            if ($node instanceof \DOMElement) {
                return $node;
            }
        }
    }

    /**
     * Call method on first DOMElement
     *
     * @param string $name
     * @param $arguments
     *
     * @return mixed
     * @throws \Exception
     */
    public function __call($name, $arguments)
    {
        if (method_exists($this->getFirstElmNode(), $name)) {
            return call_user_func_array(array($this->getFirstElmNode(), $name), $arguments);
        } else {
            throw new \Exception('Unknown call '.$name);
        }
    }

    /**
     * Perform query via xpath expression (using DOMXPath::query)
     *
     * @param string $expression
     * @param \DOMNode|null $context_node
     *
     * @return \DOMNodeList|false
     * @throws \Exception
     */
    public function xpathQuery(string $expression, \DOMNode $context_node=null)
    {
        if ($this->dom_xpath) {
            $node_list = $this->dom_xpath->query($expression, $context_node);

            if ($node_list instanceof \DOMNodeList) {
                return $node_list;
            } elseif ($node_list === false && !is_null($context_node)) {
                throw new \Exception('Expression '.$expression.' is malformed or contextnode is invalid.');
            } elseif ($node_list === false) {
                throw new \Exception('Expression '.$expression.' is malformed.');
            }
        }

        return false;
    }

    /**
     * Access xpath or ... DOMNode properties (nodeName, parentNode etc) or get attribute value of first node
     *
     * @param string $name
     *
     * @return \DOMXPath|\DOMNode|string|null
     */
    public function __get($name)
    {
        if ($name === 'dom_xpath') {
            return new \DOMXPath($this->document);
        } elseif ($name === 'outerHTML') {
            return $this->getOuterHtml();
        }

        if ($node = $this->getFirstElmNode()) {
            if (isset($node->$name)) {
                return $node->{$name};
            } elseif ($node instanceof \DOMElement && $node->hasAttribute($name)) {
                return $node->getAttribute($name);
            }
        }

        return null;
    }

    /**
     * Check if property exist for this instance
     *
     * @param string $name
     *
     * @return boolean
     */
    public function __isset($name)
    {
        return $this->__get($name) != null;
    }

    /**
     * Return html of all nodes (HTML fragment describing all the elements, including their descendants)
     *
     * @return string
     */
    public function getOuterHtml()
    {
        $outer_html = '';

        foreach ($this->nodes as $node) {
            if (isset($this->document)) {
                $outer_html .= $this->document->saveHTML($node);
            }
        }

        if ($this->preserve_no_newlines) {
            $outer_html = str_replace("\n", '', $outer_html);
        }

        return $outer_html;
    }

    /**
     * Return html of first domnode
     *
     * @return string
     */
    public function __toString()
    {
        return $this->getOuterHtml();
    }

    /**
     * Replace char with null bytes inside (optionally specified) enclosure
     *
     * @param string $str
     * @param string $search_char
     * @param string $enclosure_open
     * @param string $enclosure_close
     *
     * @return string $str
     */
    private static function replaceCharInsideEnclosure($str, $search_char, $enclosure_open='(', $enclosure_close=')')
    {
        if ($str == '') {
            return $str;
        }

        for ($i = 0; $i < strlen($str); $i++) {
            if ($str[$i] === $search_char && $i > 0) {
                if (substr_count($str, $enclosure_open, 0, $i) != substr_count($str, $enclosure_close, 0, $i)) { // count char before position
                    $str[$i] = "\0";
                }
            }
        }

        return $str;
    }

    /**
     * Transform CSS selector expression to XPath
     *
     * @param string $path css selector expression
     *
     * @return string xpath expression
     */
    public static function cssToXpath(string $path)
    {
        $tmp_path = self::replaceCharInsideEnclosure($path, ',');
        if (strpos($tmp_path, ',') !== false) {
            $paths = explode(',', $tmp_path);
            $expressions = array();

            foreach ($paths as $path) {
                $path = str_replace("\0", ',', $path); // restore commas
                $xpath = static::cssToXpath(trim($path));
                $expressions[] = $xpath;
            }

            return implode('|', $expressions);
        }

        // replace spaces inside (), to correctly create tokens

        $path = self::replaceCharInsideEnclosure($path, ' ');

        // create and analyze tokens and create segments

        $tokens = preg_split('/\s+/', $path);

        $segments = array();

        $relation_tokens = array('>', '~', '+');

        foreach ($tokens as $key => $token) {
            $token = str_replace("\0", ' ', $token); // restore spaces

            if (!in_array($token, $relation_tokens)) {
                $segment = (object) array(
                    'selector' => '',
                    'relation_token' => false,
                    'attribute_filters' => array(),
                    'pseudo_filters' => array()
                );

                if (isset($tokens[$key-1]) && in_array($tokens[$key-1], $relation_tokens)) { // get relationship token
                    $segment->relation_token = $tokens[$key-1];
                }

                $segment->selector = $token;

                while (preg_match('/(.*)\:(not|contains|has)\((.+)\)$/i', $segment->selector, $matches)) { // pseudo selector
                    $segment->selector = $matches[1];
                    $segment->pseudo_filters[] = $matches[2].'('.$matches[3].')';
                }

                while (preg_match('/(.*)\:([a-z][a-z\-]+)$/i', $segment->selector, $matches)) { // pseudo selector
                    $segment->selector = $matches[1];
                    $segment->pseudo_filters[] = $matches[2];
                }

                while (preg_match('/(.*)\[([^]]+)\]$/', $segment->selector, $matches)) { // attribute selector
                    $segment->selector = $matches[1];
                    $segment->attribute_filters[] = $matches[2];
                }

                while (preg_match('/(.*)\.([a-z][a-z0-9\-\_]+)$/i', $segment->selector, $matches)) { // class selector
                    $segment->selector = $matches[1];
                    $segment->attribute_filters[] = 'class~="'.$matches[2].'"';
                }

                while (preg_match('/(.*)\#([a-z][a-z0-9\-\_]+)$/i', $segment->selector, $matches)) { // id selector
                    $segment->selector = $matches[1];
                    $segment->attribute_filters[] = 'id="'.$matches[2].'"';
                }

                $segments[] = $segment;
            }
        }

        // use segments to create array with transformed tokens

        $new_path_tokens = array();

        foreach ($segments as $segment) {
            if ($segment->relation_token === '>') {
                $new_path_tokens[] = '/';
            } elseif ($segment->relation_token === '~') {
                $new_path_tokens[] = '/following-sibling::';
            } elseif ($segment->relation_token === '+') {
                $new_path_tokens[] = '/following-sibling::';
            } else {
                $new_path_tokens[] = '//';
            }

            if ($segment->relation_token === '+') {
                $segment->pseudo_filters[] = 'first-child';
            }

            if ($segment->selector != '') {
                $new_path_tokens[] = $segment->selector; // specific tagname
            } elseif (substr(array_slice($new_path_tokens, -1)[0], -2) != '::') {
                $new_path_tokens[] = '*'; // any tagname
            }

            foreach (array_reverse($segment->attribute_filters) as $attr) {
                $new_path_tokens[] = self::transformAttrSelection($attr);
            }

            foreach (array_reverse($segment->pseudo_filters) as $attr) {
                $new_path_tokens[] = self::transformCssPseudoSelector($attr, $new_path_tokens);
            }
        }

        return implode('', $new_path_tokens);
    }

    /**
     * Transform 'css pseudo selector' expression to xpath expression
     *
     * @param string $expression
     * @param array $new_path_tokens
     *
     * @return string transformed expression (xpath)
     */
    private static function transformCssPseudoSelector($expression, array &$new_path_tokens)
    {
        if (strpos($expression, 'not(') === 0) {
            $expression = preg_replace_callback(
                '|not\((.+)\)|i',
                function ($matches) {
                    $parts = explode(',', $matches[1]);
                    foreach ($parts as &$part) {
                        $part = trim($part);
                        $part = 'self::'.ltrim(self::cssToXpath($part), '/');
                    }
                    $not_selector = implode(' or ', $parts);
                    return '[not('.$not_selector.')]';
                },
                $expression
            );
            return $expression;
        } elseif (strpos($expression, 'contains(') === 0) {
            $expression = preg_replace_callback(
                '|contains\((.+)\)|i',
                function ($matches) {
                    return '[text()[contains(.,\''.$matches[1].'\')]]'; // contain the specified text
                },
                $expression
            );
            return $expression;
        } elseif (strpos($expression, 'has(') === 0) {
            $expression = preg_replace_callback(
                '|has\((.+)\)|i',
                function ($matches) {
                    if (substr($matches[1], 0, 2) == '> ') {
                        return '[child::' . ltrim(self::cssToXpath($matches[1]), '/') .']';
                    } else {
                        return '[descendant::' . ltrim(self::cssToXpath($matches[1]), '/') .']';
                    }
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
            'odd' => '[position() mod 2 = 0]',
            'even' => '[position() mod 2 = 1]',
            'first' => '[1]',
            'last' => '[last()]',
            'root' => '[not(parent::*)]'
        );

        if (isset($pseudo_class_selectors[$expression])) {
            return $pseudo_class_selectors[$expression];
        } else {
            throw new \Exception('Pseudo class '.$expression.' unknown');
        }

        return $expression;
    }

    /**
     * Transform 'css attribute selector' expression to xpath expression
     *
     * @param string $expression
     *
     * @return string transformed expression (xpath)
     */
    private static function transformAttrSelection($expression)
    {
        $expression = '['.$expression.']';

        // arbitrary attribute strict value equality
        $expression = preg_replace_callback(
            '|\[@?([a-z0-9_-]+)=[\'"]([^\'"]+)[\'"]\]|i',
            function ($matches) {
                return '[@' . strtolower($matches[1]) . "='" . $matches[2] . "']";
            },
            $expression
        );

        // arbitrary attribute Negation strict value
        $expression = preg_replace_callback(
            '|\[@?([a-z0-9_-]+)!=[\'"]([^\'"]+)[\'"]\]|i',
            function ($matches) {
                return '[@' . strtolower($matches[1]) . "!='" . $matches[2] . "']";
            },
            $expression
        );

        // arbitrary attribute value contains full word
        $expression = preg_replace_callback(
            '|\[([a-z0-9_-]+)~=[\'"]([^\'"]+)[\'"]\]|i',
            function ($matches) {
                return "[contains(concat(' ', normalize-space(@" . strtolower($matches[1]) . "), ' '), ' ". $matches[2] . " ')]";
            },
            $expression
        );

        // arbitrary attribute value contains specified content
        $expression = preg_replace_callback(
            '|\[([a-z0-9_-]+)\*=[\'"]([^\'"]+)[\'"]\]|i',
            function ($matches) {
                return "[contains(@" . strtolower($matches[1]) . ", '" . $matches[2] . "')]";
            },
            $expression
        );

        // attribute value starts with specified content
        $expression = preg_replace_callback(
            '|\[([a-z0-9_-]+)\^=[\'"]([^\'"]+)[\'"]\]|i',
            function ($matches) {
                return "[starts-with(@" . strtolower($matches[1]) . ", '" . $matches[2] . "')]";
            },
            $expression
        );

        // attribute value ends with specified content
        $expression = preg_replace_callback(
            '|\[([a-z0-9_-]+)\$=[\'"]([^\'"]+)[\'"]\]|i',
            function ($matches) {
                return "[@".$matches[1]." and substring(@".$matches[1].", string-length(@".$matches[1].")-".
                (strlen($matches[2])-1).") = '".$matches[2]."']";
            },
            $expression
        );

        // attribute no value
        $expression = preg_replace_callback(
            '|\[([a-z0-9]+)([a-z0-9_-]*)\]|i',
            function ($matches) {
                return "[@" . $matches[1].$matches[2] . "]";
            },
            $expression
        );

        return $expression;
    }

    /**
     * Retrieve last used CSS Query
     *
     * @return string
     */
    public function getCssQuery()
    {
        return $this->css_query;
    }

    /**
     * Retrieve last created XPath query
     *
     * @return string
     */
    public function getXpathQuery()
    {
        return $this->xpath_query;
    }

    /**
     * Retrieve DOMDocument
     *
     * @return \DOMDocument
     */
    public function getDocument()
    {
        return $this->document;
    }

    /**
     * IteratorAggregate (note: using Iterator conflicts with next method in jquery)
     *
     * @return \ArrayIterator containing nodes as instances of DomQuery
     */
    public function getIterator()
    {
        $iteration_result = array();
        if (is_array($this->nodes)) {
            foreach ($this->nodes as $node) {
                $iteration_result[] = new self($node);
            }
        }

        return new \ArrayIterator($iteration_result);
    }

    /**
     * Countable: get count
     *
     * @return int
     */
    public function count()
    {
        if (isset($this->nodes) && is_array($this->nodes)) {
            return count($this->nodes);
        } else {
            return 0;
        }
    }

    /**
     * ArrayAccess: offset exists
     *
     * @param int $key
     *
     * @return bool
     */
    public function offsetExists($key)
    {
        if (in_array($key, range(0, $this->length - 1)) && $this->length > 0) {
            return true;
        }

        return false;
    }

    /**
     * ArrayAccess: get offset
     *
     * @param int $key
     *
     * @return mixed
     */
    public function offsetGet($key)
    {
        if (isset($this->nodes[$key])) {
            return new self($this->nodes[$key]);
        } else {
            return null;
        }
    }

    /**
     * ArrayAccess: set offset
     *
     * @param mixed $key
     * @param mixed $value
     *
     * @throws Exception\BadMethodCallException when attempting to write to a read-only item
     */
    public function offsetSet($key, $value)
    {
        throw new \BadMethodCallException('Attempting to write to a read-only list');
    }

    /**
     * ArrayAccess: unset offset
     *
     * @param mixed $key
     *
     * @throws Exception\BadMethodCallException when attempting to unset a read-only item
     */
    public function offsetUnset($key)
    {
        throw new \BadMethodCallException('Attempting to unset on a read-only list');
    }
}
