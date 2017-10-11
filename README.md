# DomQuery


DomQuery is a PHP library that allows you to easily traverse and modify the DOM (HTML/XML). As a libery it aims to
provide 'jQuery like' access to the PHP DOMDocument class (http://php.net/manual/en/book.dom.php).  

## Installation

Install the latest version with

```bash
$ composer require rct567/dom-query
```

Note: DomQuery can also be used as a standalone class (no external dependencies). The class itself can be found in `src\Rct567\DomQuery\DomQuery.php`. 

## Basic Usage

### Read attributes and properties:

``` php
$dom = new DomQuery('<div><h1 class="title">Hello</h1></div>');

echo $dom->find('h1')->text(); // output: Hello
echo $dom->find('div')->prop('outerHTML'); // output: <div><h1 class="title">Hello</h1></div>
echo $dom->find('div')->html(); // output: <h1 class="title">Hello</h1>
echo $dom->find('div > h1')->class; // ouput: title
echo $dom->find('div > h1')->attr('class'); // ouput: title
echo $dom->find('div > h1')->prop('tagName'); // ouput: h1
echo $dom->find('div')->children('h1')->prop('tagName'); // ouput: h1
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

### Jquery methods available

#### Traversing > Tree Traversal

- `.find( selector )`
- `.children( [selector] )`
- `.parent( [selector] )`
- `.next( [selector] )`
- `.prev( [selector] )`

 #### Traversing > Filtering

- `.is( selector )`
- `.filter ( selector )`
- `.first( [selector] )`
- `.last( [selector] )`

 #### Manipulation > DOM Insertion, Inside

- `.text( [text] )`
- `.html( [html_string] )`
- `.append( [content], )`
- `.prepend( [content], )`

 #### Attributes | Manipulation > General Attributes

- `.attr( [name, [val]] )`
- `.prop( [name, [val]] )`

 #### Miscellaneous > DOM Element Methods

- `.get( index )`

 #### Manipulation > DOM Removal

- `.remove( [selector] )`

## About

### Requirements

- Works with PHP 7.0 or above 
- Requires libxml PHP extension (enabled by default)
