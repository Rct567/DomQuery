<?php

namespace Rct567\DomQuery;

/**
 * Class DomQuery
 *
 * @package Rct567\DomQuery
 */
class DomQuery extends DomQueryNodes implements \IteratorAggregate, \ArrayAccess
{

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
            return new self(...\func_get_args());
        }
    }

    /**
     * Create new instance of self with some properties of its parents
     *
     * @return self
     */
    private function createChildInstance()
    {
        $instance = new self(...\func_get_args());

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

        $xpath_expression = self::cssToXpath($css_expression);
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
     * Get the descendants of each element in the current set of matched elements, filtered by a selector.
     * If no results are found a exception is thrown.
     *
     * @param string|self|\DOMNodeList|\DOMNode $selector A string containing a selector expression,
     * or DomQuery|DOMNodeList|DOMNode instance to match against
     *
     * @return DomQuery
     * @throws \Exception if no results are found
     */
    public function findOrFail($selector)
    {
        $result = $this->find($selector);
        if ($result->length === 0) {
            if (\is_string($selector)) {
                throw new \Exception('Find with selector "'.$selector.'" failed!');
            } else {
                throw new \Exception('Find with node (collection) as selector failed!');
            }
        }
        return $result;
    }

    /**
     * Get the combined text contents of each element in the set of matched elements, including their descendants,
     * or set the text contents of the matched elements.
     *
     * @param string|null $val
     *
     * @return $this|string|null
     */
    public function text($val=null)
    {
        if ($val !== null) { // set node value for all nodes
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
     * @return $this|string
     */
    public function html($html_string=null)
    {
        if ($html_string !== null) { // set html for all nodes
            foreach ($this as $node) {
                $node->get(0)->nodeValue = '';
                $node->append($html_string);
            }

            if (strpos($html_string, "\n") !== false) {
                $this->preserve_no_newlines = false;
            }

            return $this;
        } else { // get html for first node
            return $this->getInnerHtml();
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
        if ($val !== null) { // set attribute for all nodes
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
     * @param string|string[] $name
     *
     * @return $this
     */
    public function removeAttr($name)
    {
        $remove_names = \is_array($name) ? $name : explode(' ', $name);

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
     * @param string|string[] $class_name class name(s)
     *
     * @return $this
     */
    public function addClass($class_name)
    {
        $add_names = \is_array($class_name) ? $class_name : explode(' ', $class_name);

        foreach ($this->nodes as $node) {
            if ($node instanceof \DOMElement) {
                $node_classes = array();
                if ($node_class_attr = $node->getAttribute('class')) {
                    $node_classes = explode(' ', $node_class_attr);
                }
                foreach ($add_names as $add_name) {
                    if (!in_array($add_name, $node_classes, true)) {
                        $node_classes[] = $add_name;
                    }
                }
                if (\count($node_classes) > 0) {
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
            if ($node instanceof \DOMElement && $node_class_attr = $node->getAttribute('class')) {
                $node_classes = explode(' ', $node_class_attr);
                if (in_array($class_name, $node_classes, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Remove a single class, multiple classes, or all classes from each element in the set of matched elements.
     *
     * @param string|string[] $class_name
     *
     * @return $this
     */
    public function removeClass($class_name='')
    {
        $remove_names = \is_array($class_name) ? $class_name : explode(' ', $class_name);

        foreach ($this->nodes as $node) {
            if ($node instanceof \DOMElement && $node->hasAttribute('class')) {
                $node_classes = preg_split('#\s+#s', $node->getAttribute('class'));
                $class_removed = false;

                if ($class_name === '') { // remove all
                    $node_classes = array();
                    $class_removed = true;
                } else {
                    foreach ($remove_names as $remove_name) {
                        $key = array_search($remove_name, $node_classes, true);
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
     * @return $this|mixed|null
     */
    public function prop(string $name, $val=null)
    {
        if ($val !== null) { // set attribute for all nodes
            foreach ($this->nodes as $node) {
                $node->$name = $val;
            }

            return $this;
        } else { // get property value for first element
            if ($name === 'outerHTML') {
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
        return $this->children(false);
    }

    /**
     * Get the children of each element in the set of matched elements, optionally filtered by a selector.
     *
     * @param string|self|callable|\DOMNodeList|\DOMNode|false|null $selector expression that filters the set of matched elements
     *
     * @return self
     */
    public function children($selector=null)
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

            if ($selector !== false) { // filter out text nodes
                $filtered_nodes = array();
                foreach ($result->nodes as $result_node) {
                    if ($result_node instanceof \DOMElement) {
                        $filtered_nodes[] = $result_node;
                    }
                }
                $result->nodes = $filtered_nodes;
                $result->length = \count($result->nodes);
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
     * @param string|self|callable|\DOMNodeList|\DOMNode|null $selector expression that filters the set of matched elements
     *
     * @return self
     */
    public function siblings($selector=null)
    {
        $result = $this->createChildInstance();

        if (isset($this->document) && $this->length > 0) {
            foreach ($this->nodes as $node) {
                if ($node->parentNode) {
                    foreach ($node->parentNode->childNodes as $sibling) {
                        if ($sibling instanceof \DOMElement && !$sibling->isSameNode($node)) {
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
     * @param string|self|callable|\DOMNodeList|\DOMNode|null $selector expression that filters the set of matched elements
     *
     * @return self
     */
    public function parent($selector=null)
    {
        $result = $this->createChildInstance();

        if (isset($this->document) && $this->length > 0) {
            foreach ($this->nodes as $node) {
                if ($node->parentNode) {
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
     * @param string|self|callable|\DOMNodeList|\DOMNode $selector selector expression to match elements against
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
     * @param string|self|callable|\DOMNodeList|\DOMNode $selector
     *
     * @return self
     */
    public function not($selector)
    {
        $result = $this->createChildInstance();

        if ($this->length > 0) {
            if (\is_callable($selector)) {
                foreach ($this->nodes as $index => $node) {
                    if (!$selector($node, $index)) {
                        $result->addDomNode($node);
                    }
                }
            } else {
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
        }

        return $result;
    }

    /**
     * Reduce the set of matched elements to those that match the selector
     *
     * @param string|self|callable|\DOMNodeList|\DOMNode $selector
     *
     * @return self
     */
    public function filter($selector)
    {
        $result = $this->createChildInstance();

        if ($this->length > 0) {
            if (\is_callable($selector)) {
                foreach ($this->nodes as $index => $node) {
                    if ($selector($node, $index)) {
                        $result->addDomNode($node);
                    }
                }
            } else {
                $selection = self::create($this->document)->find($selector);
                $result->xpath_query = $selection->xpath_query;

                foreach ($selection as $result_node) {
                    foreach ($this->nodes as $node) {
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
     * @param string|self|callable|\DOMNodeList|\DOMNode $selector
     *
     * @return boolean
     */
    public function is($selector)
    {
        if ($this->length > 0) {
            if (\is_callable($selector)) {
                foreach ($this->nodes as $index => $node) {
                    if ($selector($node, $index)) {
                        return true;
                    }
                }
            } else {
                $selection = self::create($this->document)->find($selector);

                foreach ($selection->nodes as $result_node) {
                    foreach ($this->nodes as $node) {
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
     * Reduce the set of matched elements to those that have a descendant that matches the selector
     *
     * @param string|self|callable|\DOMNodeList|\DOMNode $selector
     *
     * @return self
     */
    public function has($selector)
    {
        $result = $this->createChildInstance();

        if ($this->length > 0) {
            foreach ($this as $node) {
                if ($node->find($selector)->length > 0) {
                    $result->addDomNode($node->get(0));
                }
            }
        }

        return $result;
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
        $result->nodes = \array_slice($this->nodes, $offset, $length);
        $result->length = \count($result->nodes);
        return $result;
    }

    /**
     * Returns DomQuery with first node
     *
     * @param string|self|callable|\DOMNodeList|\DOMNode|null $selector expression that filters the set of matched elements
     *
     * @return self
     */
    public function first($selector=null)
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
     * @param string|self|callable|\DOMNodeList|\DOMNode|null $selector expression that filters the set of matched elements
     *
     * @return self
     */
    public function last($selector=null)
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
     * @param string|self|callable|\DOMNodeList|\DOMNode|null $selector expression that filters the set of matched elements
     *
     * @return self
     */
    public function next($selector=null)
    {
        $result = $this->createChildInstance();

        $get_next_elm = function ($node) {
            while ($node && ($node = $node->nextSibling)) {
                if ($node instanceof \DOMElement || ($node instanceof \DOMCharacterData && trim($node->data) !== '')) {
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
     * @param string|self|callable|\DOMNodeList|\DOMNode|null $selector expression that filters the set of matched elements
     *
     * @return self
     */
    public function prev($selector=null)
    {
        $result = $this->createChildInstance();

        $get_prev_elm = function ($node) {
            while ($node && ($node = $node->previousSibling)) {
                if ($node instanceof \DOMElement || ($node instanceof \DOMCharacterData && trim($node->data) !== '')) {
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
     * Remove the set of matched elements
     *
     * @param string|self|callable|\DOMNodeList|\DOMNode|null $selector expression that
     * filters the set of matched elements to be removed
     *
     * @return self
     */
    public function remove($selector=null)
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
        if (\is_array($content)) {
            foreach ($content as $item) {
                $this->importNodes($item, $import_function);
            }
        } else {
            if (\is_string($content) && strpos($content, "\n") !== false) {
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
        $this->importNodes(\func_get_args(), function ($node, $imported_node) {
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
        $this->importNodes(\func_get_args(), function ($node, $imported_node) {
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
        $this->importNodes(\func_get_args(), function ($node, $imported_node) {
            if ($node->parentNode instanceof \DOMDocument) {
                throw new \Exception('Can not set before root element '.$node->tagName.' of document');
            }

            $node->parentNode->insertBefore($imported_node, $node);
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
        $this->importNodes(\func_get_args(), function ($node, $imported_node) {
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
        $this->importNodes(\func_get_args(), function ($node, $imported_node) {
            if ($node->parentNode instanceof \DOMDocument) {
                throw new \Exception('Can not wrap inside root element '.$node->tagName.' of document');
            }

            // replace node with imported wrapper
            $old = $node->parentNode->replaceChild($imported_node, $node);
            // old node goes inside the most inner child of wrapper
            $target = $imported_node;
            while ($target->hasChildNodes()) {
                $target = $target->childNodes[0];
            }
            $target->appendChild($old);
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

        $this->importNodes(\func_get_args(), function ($node, $imported_node) use (&$wrapper_node, &$wrap_target_node) {
            if ($node->parentNode instanceof \DOMDocument) {
                throw new \Exception('Can not wrap inside root element '.$node->tagName.' of document');
            }
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
            self::create($node->childNodes)->wrapAll(\func_get_args());
        }

        return $this;
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
        return $this->__get($name) !== null;
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
     * Transform CSS selector expression to XPath
     *
     * @param string $path css selector expression
     *
     * @return string xpath expression
     */
    public static function cssToXpath(string $path)
    {
        return CssToXpath::transform($path);
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
     * @param int $key
     *
     * @return bool
     */
    public function offsetExists($key)
    {
        return ($this->length > 0 && in_array($key, range(0, $this->length - 1), true));
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
