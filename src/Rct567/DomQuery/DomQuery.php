<?php

namespace Rct567\DomQuery;

/**
 * Class DomQuery
 *
 * @property \DOMXPath $dom_xpath
 * @property string $tagName
 * @property string $nodeName
 * @property string $nodeValue
 * @property string $outerHTML
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
    public $length = 0;

    /**
     * Load and return as xml
     *
     * @var boolean
     */
    public $xml_mode;

    /**
     * Return xml with pi node (xml declaration) if in xml mode
     *
     * @var boolean
     */
    public $xml_print_pi = false;

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
     * Css selector to xpath cache
     *
     * @var array
     */
    private static $xpath_cache = array();

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
     *
     * @throws \InvalidArgumentException
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
                $this->loadContent($arg);
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
     * @throws \InvalidArgumentException
     */
    public static function create()
    {
        if (func_get_arg(0) instanceof self) {
            return func_get_arg(0);
        } else {
            return new self(...func_get_args());
        }
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
        if (!isset($this->document) && $dom_node_list->length == 0) {
            throw new \Exception('DOMDocument is missing!');
        } elseif ($dom_node_list->length > 0) {
            $this->setDomDocument($dom_node_list->item(0)->ownerDocument);
        }

        foreach ($dom_node_list as $node) {
            $this->addDomNode($node);
        }
    }

    /**
     * Add node to result set
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
     * Load html or xml content
     *
     * @param string $content
     * @param string $encoding
     *
     * @return void
     */
    public function loadContent(string $content, $encoding='UTF-8')
    {
        $this->preserve_no_newlines = (strpos($content, '<') !== false && strpos($content, "\n") === false);
        
        if (!is_bool($this->xml_mode)) {
            $this->xml_mode = (stripos($content, '<?xml') === 0);
        }

        $this->xml_print_pi = (stripos($content, '<?xml') === 0);

        $xml_pi_node_added = false;
        if (!$this->xml_mode && $encoding && stripos($content, '<?xml') === false) {
            $content = '<?xml encoding="'.$encoding.'">'.$content; // add pi node to make libxml use the correct encoding
            $xml_pi_node_added = true;
        }

        libxml_disable_entity_loader(true);
        libxml_use_internal_errors(true);

        $dom_document = new \DOMDocument('1.0', $encoding);
        $dom_document->strictErrorChecking = false;
        $dom_document->validateOnParse = false;
        $dom_document->recover = true;

        if ($this->xml_mode) {
            $dom_document->loadXML($content, $this->libxml_options);
        } else {
            $dom_document->loadHTML($content, $this->libxml_options);
        }

        $this->setDomDocument($dom_document);

        if ($xml_pi_node_added) { // pi node added, now remove it
            foreach ($dom_document->childNodes as $node) {
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
     * Create new instance of self with some properties of its parents
     *
     * @return self
     */
    private function createChildInstance()
    {
        $instance = new self(...func_get_args());

        if (isset($this->document)) {
            $instance->setDomDocument($this->document);
        }

        if (isset($this->root_instance)) {
            $instance->root_instance = $this->root_instance;
        } else {
            $instance->root_instance = $this;
        }

        if (is_bool($this->xml_mode)) {
            $instance->xml_mode = $this->xml_mode;
        }

        if (isset($this->document) && $this->dom_xpath instanceof \DOMXPath) {
            $instance->dom_xpath = $this->dom_xpath;
        }

        return $instance;
    }

    /**
     * Use xpath and return new DomQuery with resulting nodes
     *
     * @param string $xpath_query
     *
     * @return self
     */
    public function xpath(string $xpath_query)
    {
        $result = $this->createChildInstance();

        if (isset($this->document)) {
            $result->xpath_query = $xpath_query;

            if (isset($this->root_instance) || isset($this->xpath_query)) {  // all nodes as context
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
        }

        return $result;
    }

    /**
     * Get the descendants of each element in the current set of matched elements, filtered by a selector
     *
     * @param string|self|\DOMNodeList|\DOMNode $selector A string containing a selector expression,
     *  or DomQuery|DOMNodeList|DOMNode instance to match against
     *
     * @return self
     */
    public function find($selector)
    {
        if (is_string($selector)) {
            $css_expression = $selector;
        } else {
            $selector_tag_names = array();
            $selector_result = self::create($selector);
            foreach ($selector_result as $node) {
                $selector_tag_names[] = $node->tagName;
            }
            $css_expression = implode(',', $selector_tag_names);
        }

        $xpath_expression = self::cssToXpath($css_expression);
        $result = $this->xpath($xpath_expression);

        if (is_string($selector)) {
            $result->css_query = $css_expression;
            $result->selector = $css_expression; // jquery style
        }

        if (isset($selector_result)) {
            $new_result_nodes = array();
            foreach ($result->nodes as $result_node) {
                foreach ($selector_result->nodes as $selector_result_node) {
                    if ($result_node->isSameNode($selector_result_node)) {
                        $new_result_nodes[] = $result_node;
                    }
                }
            }
            $result->nodes = $new_result_nodes;
        }

        return $result;
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
                    if ($this->xml_mode) {
                        $html .= $document->saveXML($node);
                    } else {
                        $html .= $document->saveHTML($node);
                    }
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
                return $node->getAttribute($name);
            }
        }
    }

    /**
     * Remove an attribute from each element in the set of matched elements.
     * Name can be a space-separated list of attributes.
     *
     * @param string $name
     *
     * @return $this
     */
    public function removeAttr($name)
    {
        $remove_names = explode(' ', $name);

        foreach ($this->nodes as $node) {
            if ($node instanceof \DOMElement) {
                foreach ($remove_names as $remove_name) {
                    $node->removeAttribute($remove_name);
                }
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
     * Get the children of each element in the set of matched elements, including text and comment nodes.
     *
     * @return self
     */
    public function contents()
    {
        return $this->children(null);
    }

    /**
     * Get the children of each element in the set of matched elements, optionally filtered by a selector.
     *
     * @param string|self|\DOMNodeList|\DOMNode|null $selector expression that filters the set of matched elements
     *
     * @return self
     */
    public function children($selector='*')
    {
        $result = $this->createChildInstance();

        if (isset($this->document) && $this->length > 0) {
            if (isset($this->root_instance) || isset($this->xpath_query)) {
                foreach ($this->nodes as $node) {
                    if ($node->hasChildNodes()) {
                        $result->loadDomNodeList($node->childNodes);
                    }
                }
            } else {
                $result->loadDomNodeList($this->document->childNodes);
            }

            if ($selector) {
                $result = $result->filter($selector);
            }
        }

        return $result;
    }

    /**
     * Get the siblings of each element in the set of matched elements, optionally filtered by a selector.
     *
     * @param string|self|\DOMNodeList|\DOMNode|null $selector expression that filters the set of matched elements
     *
     * @return self
     */
    public function siblings($selector=null)
    {
        $result = $this->createChildInstance();

        if (isset($this->document) && $this->length > 0) {
            foreach ($this->nodes as $node) {
                if (!is_null($node->parentNode)) {
                    foreach ($node->parentNode->childNodes as $sibling) {
                        if (!$sibling->isSameNode($node) && $sibling instanceof \DOMElement) {
                            $result->addDomNode($sibling);
                        }
                    }
                }
            }

            if ($selector) {
                $result = $result->filter($selector);
            }
        }

        return $result;
    }

    /**
     * Get the parent of each element in the current set of matched elements, optionally filtered by a selector
     *
     * @param string|self|\DOMNodeList|\DOMNode|null $selector expression that filters the set of matched elements
     *
     * @return self
     */
    public function parent($selector=null)
    {
        $result = $this->createChildInstance();

        if (isset($this->document) && $this->length > 0) {
            foreach ($this->nodes as $node) {
                if (!is_null($node->parentNode)) {
                    $result->addDomNode($node->parentNode);
                }
            }

            if ($selector) {
                $result = $result->filter($selector);
            }
        }

        return $result;
    }

    /**
     * For each element in the set, get the first element that matches the selector
     * by testing the element itself and traversing up through its ancestors in the DOM tree.
     *
     * @param string|self|\DOMNodeList|\DOMNode $selector selector expression to match elements against
     *
     * @return self
     */
    public function closest($selector)
    {
        $result = $this->createChildInstance();

        if (isset($this->document) && $this->length > 0) {
            foreach ($this->nodes as $node) {
                $current = $node;

                while ($current instanceof \DOMElement) {
                    if (self::create($current)->is($selector)) {
                        $result->addDomNode($current);
                        break;
                    }
                    $current = $current->parentNode;
                }
            }
        }

        return $result;
    }

    /**
     * Remove elements from the set of matched elements.
     *
     * @param string|self|\DOMNodeList|\DOMNode $selector
     *
     * @return self
     */
    public function not($selector)
    {
        $result = $this->createChildInstance();

        if ($this->length > 0) {
            $selection = self::create($this->document)->find($selector);

            if ($selection->length > 0) {
                foreach ($this->nodes as $node) {
                    $matched = false;
                    foreach ($selection as $result_node) {
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
     * @param string|self|\DOMNodeList|\DOMNode $selector
     *
     * @return self
     */
    public function filter($selector)
    {
        $result = $this->createChildInstance();

        if ($this->length > 0) {
            $selection = self::create($this->document)->find($selector);
            $result->xpath_query = $selection->xpath_query;

            if ($selection->length > 0) {
                foreach ($this->nodes as $node) {
                    foreach ($selection as $result_node) {
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
     * @param string|self|\DOMNodeList|\DOMNode $selector
     *
     * @return boolean
     */
    public function is($selector)
    {
        if ($this->length > 0) {
            $selection = self::create($this->document)->find($selector);

            if ($selection->length > 0) {
                foreach ($this->nodes as $node) {
                    foreach ($selection->nodes as $result_node) {
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
     * Reduce the set of matched elements to a subset specified by the offset and length (php like)
     *
     * @param integer $offset
     * @param integer $length
     *
     * @return self
     */
    public function slice($offset=0, $length=null)
    {
        $result = $this->createChildInstance();
        $result->nodes = array_slice($this->nodes, $offset, $length);
        $result->length = count($result->nodes);
        return $result;
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
            return null; // return null if no result for key
        }
    }

    /**
     * Returns DomQuery with first node
     *
     * @param string|null $selector expression that filters the set of matched elements
     *
     * @return self
     */
    public function first(string $selector=null)
    {
        $result = $this[0];
        if ($selector) {
            $result = $result->filter($selector);
        }
        return $result;
    }

    /**
     * Returns DomQuery with last node
     *
     * @param string|null $selector expression that filters the set of matched elements
     *
     * @return self
     */
    public function last(string $selector=null)
    {
        $result = $this[$this->length-1];
        if ($selector) {
            $result = $result->filter($selector);
        }
        return $result;
    }

    /**
     * Returns DomQuery with immediately following sibling of all nodes
     *
     * @param string|null $selector expression that filters the set of matched elements
     *
     * @return self
     */
    public function next(string $selector=null)
    {
        $result = $this->createChildInstance();

        $get_next_elm = function ($node) {
            while ($node && ($node = $node->nextSibling)) {
                if ($node instanceof \DOMElement || ($node instanceof DOMCharacterData && trim($this->$node->data) !== '')) {
                    break;
                }
            }
            return $node;
        };

        if (isset($this->document) && $this->length > 0) {
            foreach ($this->nodes as $node) {
                if ($next = $get_next_elm($node)) {
                    $result->addDomNode($next);
                }
            }

            if ($selector) {
                $result = $result->filter($selector);
            }
        }

        return $result;
    }

    /**
     * Returns DomQuery with immediately preceding sibling of all nodes
     *
     * @param string|null $selector expression that filters the set of matched elements
     *
     * @return self
     */
    public function prev(string $selector=null)
    {
        $result = $this->createChildInstance();

        $get_prev_elm = function ($node) {
            while ($node && ($node = $node->previousSibling)) {
                if ($node instanceof \DOMElement || ($node instanceof DOMCharacterData && trim($this->$node->data) !== '')) {
                    break;
                }
            }
            return $node;
        };

        if (isset($this->document) && $this->length > 0) {
            foreach ($this->nodes as $node) { // get all previous sibling of all nodes
                if ($prev = $get_prev_elm($node)) {
                    $result->addDomNode($prev);
                }
            }

            if ($selector) {
                $result = $result->filter($selector);
            }
        }

        return $result;
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
            $return_value = call_user_func($callback, $node, $index);

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
     * Import nodes and insert or append them via callback function
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

            if (!($content instanceof self)) {
                $content = new self($content);
            }

            foreach ($this->nodes as $node) {
                foreach ($content->getNodes() as $content_node) {
                    if ($content_node->ownerDocument === $node->ownerDocument) {
                        $imported_node = $content_node->cloneNode(true);
                    } else {
                        $imported_node = $this->document->importNode($content_node, true);
                    }
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
     * Wrap an HTML structure around each element in the set of matched elements
     *
     * @param string|self $content,...
     *
     * @return $this
     */
    public function wrap()
    {
        $this->importNodes(func_get_args(), function ($node, $imported_node) {
            if ($node->parentNode instanceof \DOMDocument) {
                throw new \Exception('Can not wrap inside root element '.$node->tagName.' of document');
            } else {
                // replace node with imported wrapper
                $old = $node->parentNode->replaceChild($imported_node, $node);
                // old node goes inside the most inner child of wrapper
                $target = $imported_node;
                while ($target->hasChildNodes()) {
                    $target = $target->childNodes[0];
                }
                $target->appendChild($old);
            }
        });

        return $this;
    }

    /**
     * Wrap an HTML structure around all elements in the set of matched elements
     *
     * @param string|self $content,...
     *
     * @return $this
     */
    public function wrapAll()
    {
        $wrapper_node = null; // node given as wrapper
        $wrap_target_node = null; // node that wil be parent of content to be wrapped

        $this->importNodes(func_get_args(), function ($node, $imported_node) use (&$wrapper_node, &$wrap_target_node) {
            if ($node->parentNode instanceof \DOMDocument) {
                throw new \Exception('Can not wrap inside root element '.$node->tagName.' of document');
            } else {
                if ($wrapper_node && $wrap_target_node) { // already wrapped before
                    $old = $node->parentNode->removeChild($node);
                    $wrap_target_node->appendChild($old);
                } else {
                    $wrapper_node = $imported_node;
                    // replace node with (imported) wrapper
                    $old = $node->parentNode->replaceChild($imported_node, $node);
                    // old node goes inside the most inner child of wrapper
                    $target = $imported_node;

                    while ($target->hasChildNodes()) {
                        $target = $target->childNodes[0];
                    }
                    $target->appendChild($old);
                    $wrap_target_node = $target; // save for next round
                }
            }
        });

        return $this;
    }


    /**
     * Wrap an HTML structure around the content of each element in the set of matched elements
     *
     * @param string|self $content,...
     *
     * @return $this
     */
    public function wrapInner()
    {
        foreach ($this->nodes as $node) {
            self::create($node->childNodes)->wrapAll(func_get_args());
        }

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
     * Create dom xpath instance
     *
     * @return \DOMXPath
     */
    private function createDomXpath()
    {
        $xpath = new \DOMXPath($this->document);

        if ($this->xml_mode) { // register all name spaces
            foreach ($xpath->query('namespace::*') as $node) {
                if ($node->prefix !== 'xml') {
                    $xpath->registerNamespace($node->prefix, $node->namespaceURI);
                }
            }
        }

        return $xpath;
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
            return $this->createDomXpath();
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

        if ($this->xml_mode && $this->xml_print_pi) {
            $outer_html .= '<?xml version="'.$this->document->xmlVersion.'" encoding="'.$this->document->xmlEncoding.'"?>';
            $outer_html .= "\n\n";
        }

        foreach ($this->nodes as $node) {
            if (isset($this->document)) {
                if ($this->xml_mode) {
                    $outer_html .= $this->document->saveXML($node);
                } else {
                    $outer_html .= $this->document->saveHTML($node);
                }
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
        if ($str == '' || strpos($str, $search_char) === false || strpos($str, $enclosure_open) === false) {
            return $str;
        }

        for ($i = 0; $i < strlen($str); $i++) {
            if ($str[$i] === $search_char && $i > 0) {
                // check if enclosure is open by counting char before position
                $enclosure_is_open = substr_count($str, $enclosure_open, 0, $i) != substr_count($str, $enclosure_close, 0, $i);
                if ($enclosure_is_open) {
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
        if (isset(self::$xpath_cache[$path])) {
            return self::$xpath_cache[$path];
        }

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

        // replace spaces inside (), to correctly create tokens (restore later)

        $path_escaped = self::replaceCharInsideEnclosure($path, ' ');

        // create tokens and analyze to create segments

        $tokens = preg_split('/\s+/', $path_escaped);

        $segments = array();

        foreach ($tokens as $key => $token) {
            $token = str_replace("\0", ' ', $token); // restore spaces
                
            if ($segment = self::getSegmentFromToken($token, $key, $tokens)) {
                $segments[] = $segment;
            }
        }

        // use segments to create array with transformed tokens

        $new_path_tokens = self::transformCssSegments($segments);

        // result tokens to xpath

        $xpath_result = implode('', $new_path_tokens);

        self::$xpath_cache[$path] = $xpath_result;

        return $xpath_result;
    }

    /**
     * Get segment data from token (css selector delimited by space and commas)
     *
     * @param string $token
     * @param integer $key
     * @param array $tokens
     *
     * @return object|boolean $segment
     */
    private static function getSegmentFromToken($token, $key, array $tokens)
    {
        $relation_tokens = array('>', '~', '+');

        if (in_array($token, $relation_tokens)) { // not a segment
            return false;
        }

        $segment = (object) array(
            'selector' => '',
            'relation_token' => false,
            'attribute_filters' => array(),
            'pseudo_filters' => array()
        );

        if (isset($tokens[$key-1]) && in_array($tokens[$key-1], $relation_tokens)) { // get relationship token
            $segment->relation_token = $tokens[$key-1];
        }

        if (ctype_alpha($token)) { // simple element selector
            $segment->selector = $token;
            return $segment;
        }

        $char_tmp_replaced = false;
        if (strpos($token, '\\') !== false) {
            $token = preg_replace_callback( // temporary replace escaped characters
                '#(\\\\)(.{1})#',
                function ($matches) {
                    return 'ESCAPED'.ord($matches[2]);
                },
                $token
            );
            $char_tmp_replaced = true;
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

        while (preg_match('/(.*)\.([a-z]+[a-z0-9\-\_]*)$/i', $segment->selector, $matches)) { // class selector
            $segment->selector = $matches[1];
            $segment->attribute_filters[] = 'class~="'.$matches[2].'"';
        }

        while (preg_match('/(.*)\#([a-z]+[a-z0-9\-\_]*)$/i', $segment->selector, $matches)) { // id selector
            $segment->selector = $matches[1];
            $segment->attribute_filters[] = 'id="'.$matches[2].'"';
        }

        if ($char_tmp_replaced) { // restore temporary replaced characters
            $set_escape_back = function (string $str) {
                return preg_replace_callback(
                    '#(ESCAPED)([0-9]{1,3})#',
                    function ($matches) {
                        return chr($matches[2]);
                    },
                    $str
                );
            };

            $segment->selector = $set_escape_back($segment->selector);

            foreach ($segment->attribute_filters as &$attr_filter) {
                $attr_filter = $set_escape_back($attr_filter);
            }
        }

        return $segment;
    }

    /**
     * Transform css segments to xpath
     *
     * @param array $segments
     *
     * @return array $new_path_tokens
     */
    private static function transformCssSegments(array $segments)
    {
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

            if ($segment->selector != '') {
                $new_path_tokens[] = $segment->selector; // specific tagname
            } elseif (substr(array_slice($new_path_tokens, -1)[0], -2) !== '::') {
                $new_path_tokens[] = '*'; // any tagname
            }

            if ($segment->relation_token === '+') {
                $new_path_tokens[] = '[1]';
            }

            foreach (array_reverse($segment->attribute_filters) as $attr) {
                $new_path_tokens[] = self::transformAttrSelector($attr);
            }

            foreach (array_reverse($segment->pseudo_filters) as $attr) {
                $new_path_tokens[] = self::transformCssPseudoSelector($attr, $new_path_tokens);
            }
        }

        return $new_path_tokens;
    }

    /**
     * Transform 'css pseudo selector' expression to xpath expression
     *
     * @param string $expression
     * @param array $new_path_tokens
     *
     * @return string transformed expression (xpath)
     * @throws \Exception
     */
    private static function transformCssPseudoSelector($expression, array &$new_path_tokens)
    {
        if (preg_match('|not\((.+)\)|i', $expression, $matches)) {
            $parts = explode(',', $matches[1]);
            foreach ($parts as &$part) {
                $part = trim($part);
                $part = 'self::'.ltrim(self::cssToXpath($part), '/');
            }
            $not_selector = implode(' or ', $parts);
            return '[not('.$not_selector.')]';
        } elseif (preg_match('|contains\((.+)\)|i', $expression, $matches)) {
            return '[text()[contains(.,\''.$matches[1].'\')]]'; // contain the specified text
        } elseif (preg_match('|has\((.+)\)|i', $expression, $matches)) {
            if (substr($matches[1], 0, 2) === '> ') {
                return '[child::' . ltrim(self::cssToXpath($matches[1]), '/') .']';
            } else {
                return '[descendant::' . ltrim(self::cssToXpath($matches[1]), '/') .']';
            }
        } elseif ($expression === 'first' || $expression === 'last') { // new path inside selection
            array_unshift($new_path_tokens, '(');
            $new_path_tokens[] = ')';
        }

        //  static replacement

        $pseudo_class_selectors = array(
            'disabled' => '[@disabled]',
            'first-child' => '[not(preceding-sibling::*)]',
            'last-child' => '[not(following-sibling::*)]',
            'only-child' => '[not(preceding-sibling::*) and not(following-sibling::*)]',
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

        if (!isset($pseudo_class_selectors[$expression])) {
            throw new \Exception('Pseudo class '.$expression.' unknown');
        }

        return $pseudo_class_selectors[$expression];
    }

    /**
     * Transform 'css attribute selector' expression to xpath expression
     *
     * @param string $expression
     *
     * @return string transformed expression (xpath)
     */
    private static function transformAttrSelector($expression)
    {
        if (preg_match('|@?([a-z0-9_-]+)(([\!\*\^\$\~]{0,1})=)[\'"]([^\'"]+)[\'"]|i', $expression, $matches)) {
            if ($matches[3] === '') { // arbitrary attribute strict value equality
                return '[@' . strtolower($matches[1]) . "='" . $matches[4] . "']";
            } elseif ($matches[3] === '!') { // arbitrary attribute negation strict value
                return '[@' . strtolower($matches[1]) . "!='" . $matches[4] . "']";
            } elseif ($matches[3] === '~') { // arbitrary attribute value contains full word
                return "[contains(concat(' ', normalize-space(@" . strtolower($matches[1]) . "), ' '), ' ". $matches[4] . " ')]";
            } elseif ($matches[3] === '*') {  // arbitrary attribute value contains specified content
                return "[contains(@" . strtolower($matches[1]) . ", '" . $matches[4] . "')]";
            } elseif ($matches[3] === '^') { // attribute value starts with specified content
                return "[starts-with(@" . strtolower($matches[1]) . ", '" . $matches[4] . "')]";
            } elseif ($matches[3] === '$') { // attribute value ends with specified content
                return "[@".$matches[1]." and substring(@".$matches[1].", string-length(@".$matches[1].")-".
                (strlen($matches[4])-1).") = '".$matches[4]."']";
            }
        }

        // attribute without value
        if (preg_match('|([a-z0-9]+)([a-z0-9_-]*)|i', $expression, $matches)) {
            return "[@" . $matches[1].$matches[2] . "]";
        }

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
                $iteration_result[] = $this->createChildInstance($node);
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
     * @return self
     */
    public function offsetGet($key)
    {
        if (isset($this->nodes[$key])) {
            return $this->createChildInstance($this->nodes[$key]);
        } else {
            return $this->createChildInstance();
        }
    }

    /**
     * ArrayAccess: set offset
     *
     * @param mixed $key
     * @param mixed $value
     *
     * @throws \BadMethodCallException when attempting to write to a read-only item
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
     * @throws \BadMethodCallException when attempting to unset a read-only item
     */
    public function offsetUnset($key)
    {
        throw new \BadMethodCallException('Attempting to unset on a read-only list');
    }
}
