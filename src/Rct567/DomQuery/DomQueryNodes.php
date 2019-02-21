<?php

namespace Rct567\DomQuery;

/**
 * Class DomQueryNodes
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
class DomQueryNodes implements \Countable, \IteratorAggregate, \ArrayAccess
{

    /**
     * Instance of DOMDocument
     *
     * @var \DOMDocument
     */
    protected $document;

    /**
     * All nodes as instances of DOMNode
     *
     * @var \DOMNode[]
     */
    protected $nodes = array();

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
     * Preserve no newlines (prevent creating newlines in html result)
     *
     * @var boolean
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
     * Root instance who began the chain
     *
     * @var static
     */
    protected $root_instance;

    /**
     * Xpath expression used to create the result of this instance
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
     * Constructor
     *
     * @throws \InvalidArgumentException
     */
    public function __construct()
    {
        if (\func_num_args() === 2 && \is_string(\func_get_arg(0)) && strpos(func_get_arg(0), '<') === false) {
            $result = self::create(func_get_arg(1))->find(func_get_arg(0));
            $this->addNodes($result->nodes);
            return;
        }

        foreach (\func_get_args() as $arg) {
            if ($arg instanceof \DOMDocument) {
                $this->setDomDocument($arg);
            } elseif ($arg instanceof \DOMNodeList) {
                $this->loadDomNodeList($arg);
            } elseif ($arg instanceof \DOMNode) {
                $this->addDomNode($arg);
            } elseif (\is_array($arg) && $arg[0] instanceof \DOMNode) {
                $this->addNodes($arg);
            } elseif ($arg instanceof \DOMXPath) {
                $this->dom_xpath = $arg;
            } elseif (\is_string($arg) && strpos($arg, '<') !== false) {
                $this->loadContent($arg);
            } elseif (\is_object($arg)) {
                throw new \InvalidArgumentException('Unknown object '. \get_class($arg).' given as argument');
            } else {
                throw new \InvalidArgumentException('Unknown argument '. \gettype($arg));
            }
        }
    }

     /**
     * Create new instance of self with some properties of its parents
     *
     * @return static
     */
    protected function createChildInstance()
    {
        $instance = new static(...\func_get_args());

        if (isset($this->document)) {
            $instance->setDomDocument($this->document);
        }

        if (isset($this->root_instance)) {
            $instance->root_instance = $this->root_instance;
        } else {
            $instance->root_instance = $this;
        }

        if (\is_bool($this->xml_mode)) {
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
     * @return static
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
     * Retrieve last created XPath query
     *
     * @return string
     */
    public function getXpathQuery()
    {
        return $this->xpath_query;
    }

    /**
     * Create new instance
     *
     * @return static
     * @throws \InvalidArgumentException
     */
    public static function create()
    {
        if (func_get_arg(0) instanceof static) {
            return func_get_arg(0);
        }

        return new static(...\func_get_args());
    }

    /**
     * Set dom document
     *
     * @param \DOMDocument $document
     *
     * @return void
     * @throws \Exception if other document is already set
     */
    public function setDomDocument(\DOMDocument $document)
    {
        if (isset($this->document) && $this->document !== $document) {
            throw new \Exception('Other DOMDocument already set!');
        }

        $this->document = $document;
    }

    /**
     * Add nodes from dom node list to result set
     *
     * @param \DOMNodeList $dom_node_list
     *
     * @return void
     * @throws \Exception if no document is set and list is empty
     */
    public function loadDomNodeList(\DOMNodeList $dom_node_list)
    {
        if (!isset($this->document) && $dom_node_list->length === 0) {
            throw new \Exception('DOMDocument is missing!');
        }

        if ($dom_node_list->length > 0) {
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
     * @param bool $prepend
     *
     * @return void
     */
    public function addDomNode(\DOMNode $dom_node, $prepend=false)
    {
        if ($prepend) {
            array_unshift($this->nodes, $dom_node);
        } else {
            $this->nodes[] = $dom_node;
        }

        $this->length = \count($this->nodes);
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

        if (!\is_bool($this->xml_mode)) {
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
                if ($node->nodeType === XML_PI_NODE) {
                    $dom_document->removeChild($node);
                    break;
                }
            }
        }

        foreach ($dom_document->childNodes as $node) {
            $this->nodes[] = $node;
        }

        $this->length = \count($this->nodes);
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
        $result = \array_slice($this->nodes, $index, 1); // note: index can be negative
        if (\count($result) > 0) {
            return $result[0];
        }

        return null; // return null if no result for key
    }

    /**
     * Get the descendants of each element in the current set of matched elements, filtered by a selector
     *
     * @param string|static|\DOMNodeList|\DOMNode $selector A string containing a selector expression,
     *  or DomQuery|DOMNodeList|DOMNode instance to match against
     *
     * @return static
     */
    public function find($selector)
    {
        if (\is_string($selector)) {
            $css_expression = $selector;
        } else {
            $selector_tag_names = array();
            $selector_result = self::create($selector);
            foreach ($selector_result as $node) {
                $selector_tag_names[] = $node->tagName;
            }
            $css_expression = implode(',', $selector_tag_names);
        }

        $xpath_expression = CssToXpath::transform($css_expression);
        $result = $this->xpath($xpath_expression);

        if (\is_string($selector)) {
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
     * Get next element from node
     *
     * @param \DOMNode $node
     *
     * @return \DOMNode
     */
    protected static function getNextElement(\DOMNode $node)
    {
        while ($node && ($node = $node->nextSibling)) {
            if ($node instanceof \DOMElement) {
                break;
            }
        }

        return $node;
    }

    /**
     * Get next element from node
     *
     * @param \DOMNode $node
     *
     * @return \DOMNode
     */
    protected static function getPreviousElement(\DOMNode $node)
    {
        while ($node && ($node = $node->previousSibling)) {
            if ($node instanceof \DOMElement) {
                break;
            }
        }

        return $node;
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
     * Get the descendants of each element in the current set of matched elements, filtered by a selector.
     * If no results are found a exception is thrown.
     *
     * @param string|static|\DOMNodeList|\DOMNode $selector A string containing a selector expression,
     * or DomQuery|DOMNodeList|DOMNode instance to match against
     *
     * @return static
     * @throws \Exception if no results are found
     */
    public function findOrFail($selector)
    {
        $result = $this->find($selector);
        if ($result->length === 0) {
            if (\is_string($selector)) {
                throw new \Exception('Find with selector "'.$selector.'" failed!');
            }
            throw new \Exception('Find with node (collection) as selector failed!');
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
            $return_value = \call_user_func($callback, $node, $index);

            if ($return_value === false) {
                break;
            }
        }

        return $this;
    }

    /**
     * Pass each element in the current matched set through a function,
     * producing an array containing the return values
     *
     * @param callable $callback
     *
     * @return array
     */
    public function map(callable $callback)
    {
        $result = array();

        foreach ($this->nodes as $index => $node) {
            $return_value = \call_user_func($callback, $node, $index);

            if ($return_value === null) {
                continue;
            } elseif (\is_array($return_value)) {
                $result = \array_merge($result, $return_value);
            } else {
                $result[] = $return_value;
            }
        }

        return $result;
    }

    /**
     * Returns dom elements
     *
     * @return \Generator
     */
    protected function getElements()
    {
        foreach ($this->nodes as $node) {
            if ($node instanceof \DOMElement) {
                yield $node;
            }
        }
    }

    /**
     * Return array with nodes
     *
     * @return \DOMNode[]
     */
    protected function getNodes()
    {
        return $this->nodes;
    }

    /**
     * Return array with cloned nodes
     *
     * @return \DOMNode[]
     */
    protected function getClonedNodes()
    {
        $cloned_nodes = array();

        foreach ($this->nodes as $node) {
            $cloned_node = $node->cloneNode(true);

            if ($cloned_node instanceof \DOMElement && $cloned_node->hasAttribute('dqn_tmp_id')) {
                $cloned_node->removeAttribute('dqn_tmp_id');
            }

            $cloned_nodes[] = $cloned_node;
        }

        return $cloned_nodes;
    }

    /**
     * Return array with nodes
     *
     * @return \DOMNode[]
     */
    public function toArray()
    {
        return $this->nodes;
    }

    /**
     * Add nodes to result set
     *
     * @param \DOMNode[] $node_list
     *
     * @return void
     */
    public function addNodes(array $node_list)
    {
        foreach ($node_list as $node) {
            $this->addDomNode($node);
        }
    }

    /**
     * Return first DOMElement
     *
     * @return \DOMElement|null
     */
    protected function getFirstElmNode()
    {
        foreach ($this->nodes as $node) {
            if ($node instanceof \DOMElement) {
                return $node;
            }
        }
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
        }
        if ($name === 'outerHTML') {
            return $this->getOuterHtml();
        }

        if ($node = $this->getFirstElmNode()) {
            if (isset($node->$name)) {
                return $node->{$name};
            }
            if ($node instanceof \DOMElement && $node->hasAttribute($name)) {
                return $node->getAttribute($name);
            }
        }

        return null;
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
            return \call_user_func_array(array($this->getFirstElmNode(), $name), $arguments);
        }

        throw new \Exception('Unknown call '.$name);
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
            } elseif ($node_list === false && $context_node) {
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
     * Countable: get count
     *
     * @return int
     */
    public function count()
    {
        return \count($this->nodes);
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

        $outer_html = $this->handleHtmlResult($outer_html);

        return $outer_html;
    }

    /**
     * Get id for node
     *
     * @param \DOMElement $node
     *
     * @return string $node_id
     */
    public static function getElementId(\DOMElement $node)
    {
        if ($node->hasAttribute('dqn_tmp_id')) {
            return $node->getAttribute('dqn_tmp_id');
        }

        $node_id = md5(mt_rand());
        $node->setAttribute('dqn_tmp_id', $node_id);
        return $node_id;
    }

    /**
     * Handle html when resulting html is requested
     *
     * @param string $html
     *
     * @return string
     */
    private function handleHtmlResult($html)
    {
        if ($this->preserve_no_newlines) {
            $html = str_replace("\n", '', $html);
        }
        if (stripos($html, 'dqn_tmp_id=') !== false) {
            $html = preg_replace('/ dqn_tmp_id="([a-z0-9]+)"/', '', $html);
        }

        return $html;
    }

    /**
     * Get the HTML contents of the first element in the set of matched elements.
     *
     * @return string
     */
    public function getInnerHtml()
    {
        $html = '';
        if ($content_node = $this->getFirstElmNode()) {
            $document = $content_node->ownerDocument;

            foreach ($content_node->childNodes as $node) {
                if ($this->xml_mode) {
                    $html .= $document->saveXML($node);
                } else {
                    $html .= $document->saveHTML($node);
                }
            }

            $html = $this->handleHtmlResult($html);
        }

        return $html;
    }

    /**
     * Return html of all nodes
     *
     * @return string
     */
    public function __toString()
    {
        return $this->getOuterHtml();
    }

    /**
     * IteratorAggregate (note: using Iterator conflicts with next method in jquery)
     *
     * @return \ArrayIterator containing nodes as instances of DomQuery
     */
    public function getIterator()
    {
        $iteration_result = array();
        if (\is_array($this->nodes)) {
            foreach ($this->nodes as $node) {
                $iteration_result[] = $this->createChildInstance($node);
            }
        }

        return new \ArrayIterator($iteration_result);
    }

    /**
     * ArrayAccess: offset exists
     *
     * @param mixed $key
     *
     * @return bool
     */
    public function offsetExists($key)
    {
        return isset($this->nodes[$key]);
    }

    /**
     * ArrayAccess: get offset
     *
     * @param mixed $key
     *
     * @return static
     */
    public function offsetGet($key)
    {
        if (!\is_int($key)) {
            throw new \BadMethodCallException('Attempting to access node list with non-integer');
        }

        if (isset($this->nodes[$key])) {
            return $this->createChildInstance($this->nodes[$key]);
        }

        return $this->createChildInstance();
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
        throw new \BadMethodCallException('Attempting to write to a read-only node list');
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
        throw new \BadMethodCallException('Attempting to unset on a read-only node list');
    }
}
