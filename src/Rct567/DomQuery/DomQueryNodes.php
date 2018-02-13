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
class DomQueryNodes implements \Countable
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
     *
     * @throws \InvalidArgumentException
     */
    public function __construct()
    {
        foreach (\func_get_args() as $arg) {
            if ($arg instanceof \DOMDocument) {
                $this->setDomDocument($arg);
            } elseif ($arg instanceof \DOMNodeList) {
                $this->loadDomNodeList($arg);
            } elseif ($arg instanceof \DOMNode) {
                $this->addDomNode($arg);
            } elseif ($arg instanceof \DOMXPath) {
                $this->dom_xpath = $arg;
            } elseif (\is_string($arg) && strpos($arg, '>') !== false) {
                $this->loadContent($arg);
            } elseif (\is_object($arg)) {
                throw new \InvalidArgumentException('Unknown object '. \get_class($arg).' given as argument');
            } else {
                throw new \InvalidArgumentException('Unknown argument '. \gettype($arg));
            }
        }
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
        } else {
            return null; // return null if no result for key
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
     * Return array with nodes
     *
     * @return \DOMNode[]
     */
    protected function getNodes()
    {
        return $this->nodes;
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
    protected function createDomXpath()
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
        if (isset($this->nodes) && \is_array($this->nodes)) {
            return \count($this->nodes);
        } else {
            return 0;
        }
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

        if ($this->preserve_no_newlines) {
            $outer_html = str_replace("\n", '', $outer_html);
        }

        return $outer_html;
    }
}
