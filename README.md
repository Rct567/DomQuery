# DomQuery

DomQuery is a PHP library that allows you to easily traverse and modify the DOM (HTML/XML). As a library it aims to
provide 'jQuery like' access to the PHP DOMDocument class (http://php.net/manual/en/book.dom.php).

## Installation

Install the latest version with

```bash
$ composer require rct567/dom-query
```

## Basic Usage

### Read attributes and properties:

``` php
$dom = new DomQuery('<div><h1 class="title">Hello</h1></div>');

echo $dom->find('h1')->text(); // output: Hello
echo $dom->find('div')->prop('outerHTML'); // output: <div><h1 class="title">Hello</h1></div>
echo $dom->find('div')->html(); // output: <h1 class="title">Hello</h1>
echo $dom->find('div > h1')->class; // output: title
echo $dom->find('div > h1')->attr('class'); // output: title
echo $dom->find('div > h1')->prop('tagName'); // output: h1
echo $dom->find('div')->children('h1')->prop('tagName'); // output: h1
echo (string) $dom->find('div > h1'); // output: <h1 class="title">Hello</h1>
echo count($dom->find('div, h1')); // output: 2
```

### Traversing nodes (result set):

``` php
$dom = new DomQuery('<a>1</a> <a>2</a> <a>3</a>');
$links = $dom->children('a');

foreach($links as $elm) {
    echo $elm->text(); // output 123
}

echo $links[0]->text(); // output 1
echo $links->last()->text(); // output 3
echo $links->first()->next()->text(); // output 2
echo $links->last()->prev()->text(); // output 2
echo $links->get(0)->textContent; // output 1
echo $links->get(-1)->textContent; // output 3
```

### Factory method (create instance alternative):

```php
DomQuery::create('<a title="hello"></a>')->attr('title') // hello
```

## Jquery methods available

#### Traversing > Tree Traversal

- `.find( selector )`
- `.children( [selector] )`
- `.parent( [selector] )`
- `.closest( [selector] )`
- `.next( [selector] )`
- `.prev( [selector] )`
- `.nextAll( [selector] )`
- `.prevAll( [selector] )`
- `.siblings( [selector] )`

#### Traversing > Miscellaneous Traversing

- `.contents()` get children including text nodes
- `.add( selector, [context] )` new result with added elements that match selector

 #### Traversing > Filtering

- `.is( selector )`
- `.filter ( selector )` reduce to those that match the selector
- `.not( selector )` remove elements from the set of matched elements
- `.has( selector )` reduce to those that have a descendant that matches the selector
- `.first( [selector] )`
- `.last( [selector] )`
- `.slice( [offset] [, length])` like [array_slice in php](http://php.net/manual/en/function.array-slice.php), not js/jquery
- `.eq( index )`
- `.map( callable(elm,i) )`

<sub>\* __[selector]__ can be a css selector or an instance of DomQuery|DOMNodeList|DOMNode </sub>

 #### Manipulation > DOM Insertion & removal

- `.text( [text] )`
- `.html( [html_string] )`
- `.append( [content],... )`
- `.prepend( [content],... )`
- `.after( [content],... )`
- `.before( [content],... )`
- `.appendTo( [target] )`
- `.prependTo( [target] )`
- `.replaceWith( [content] )`
- `.wrap( [content] )`
- `.wrapAll( [content] )`
- `.wrapInner( [content] )`
- `.remove( [selector] )`

<sub>\* __[content]__ can be html or an instance of DomQuery|DOMNodeList|DOMNode</sub>

 #### Attributes | Manipulation

- `.attr( name [, val] )`
- `.prop( name [, val] )`
- `.css( name [, val] )`
- `.removeAttr( name )`
- `.addClass( name )`
- `.hasClass( name )`
- `.toggleClass ( name )`
- `.removeClass( [name] )`

<sub>\* addClass, removeClass, toggleClass and removeAttr also accepts an array or space-separated __names__</sub>

 #### Miscellaneous > DOM Element Methods | Traversing | Storage

- `.get( index )`
- `.each ( callable(elm,i) )`
- `.data ( key [, val] )`
- `.removeData ( [name] )`
- `.index ( [selector] )`
- `.toArray()`
- `.clone()`

## Supported selectors

- `.class`
- `#foo`
- `parent > child`
- `foo, bar` multiple selectors
- `prev + next` elements matching "next" that are immediately preceded by a sibling "prev"
- `prev ~ siblings` elements matching "siblings" that are preceded by "prev"
- `*` all selector
- `[name="foo"]` attribute value equal foo
- `[name*="foo"]` attribute value contains foo
- `[name~="foo"]` attribute value contains word foo
- `[name^="foo"]` attribute value starts with foo
- `[name$="foo"]` attribute value ends with foo
- `[name|="foo"]` attribute value equal to foo, or starting foo followed by a hyphen (-)

### Pseudo selectors

- `:empty`
- `:even`
- `:odd`
- `:first-child`
- `:last-child`
- `:only-child`
- `:parent` elements that have at least one child node
- `:first`
- `:last`
- `:header` selects h1, h2, h3 etc.
- `:not(foo)` elements that do not match selector foo
- `:has(foo)` elements containing at least one element that matches foo selector
- `:contains(foo)` elements that contain text foo
- `:root` element that is the root of the document

## Other (non jQuery) methods

- `findOrFail( selector )` find descendants of each element in the current set of matched elements, or throw an exception
- `loadContent(content, encoding='UTF-8')` load html/xml content
- `xpath(xpath_query)` Use xpath to find descendants of each element in the current set of matched elements
- `getOuterHtml()` get resulting html describing all the elements (same as `(string) $dom`, or `$elm->prop('outerHTML')`)

## XML support

- XML content will automatically be loaded '[as XML](http://php.net/manual/en/domdocument.loadxml.php)' if a [XML declaration](http://xmlwriter.net/xml_guide/xml_declaration.shtml) is found (property `xml_mode` will be set to true)
- This in turn will also make saving (rendering) happen '[as XML](http://php.net/manual/en/domdocument.savexml.php)'. You can set property `xml_mode` to false to prevent this.
- To prevent content with a XML declaration loading 'as XML' you can set property `xml_mode` to false and then use the `loadContent($content)` method.
- Namespaces are automatically registered (no need to do it [manually](http://php.net/manual/en/domxpath.registernamespace.php))

Escaping meta chars in selector to find elements with namespace:

```php
$dom->find('namespace\\:h1')->text();
```

## About

### Requirements

- Works with PHP 7.0 or above
- Requires libxml PHP extension (enabled by default)

### Inspiration/acknowledgements

- https://github.com/wasinger/htmlpagedom
- https://github.com/symfony/dom-crawler
- https://github.com/ARTACK/DOMQuery
- https://github.com/zendframework/zend-dom
- http://simplehtmldom.sourceforge.net
