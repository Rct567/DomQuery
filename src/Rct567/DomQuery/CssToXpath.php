<?php

namespace Rct567\DomQuery;

/**
 * Class CssToXpath
 */
class CssToXpath
{

    /**
     * Css selector to xpath cache
     *
     * @var array
     */
    private static $xpath_cache = array();

    /**
     * Transform CSS selector expression to XPath
     *
     * @param string $path css selector expression
     *
     * @return string xpath expression
     */
    public static function transform(string $path)
    {
        if (isset(self::$xpath_cache[$path])) {
            return self::$xpath_cache[$path];
        }

        $tmp_path = self::replaceCharInsideEnclosure($path, ',');
        if (strpos($tmp_path, ',') !== false) {
            $paths = explode(',', $tmp_path);
            $expressions = array();

            foreach ($paths as $single_path) {
                $single_path = str_replace("\0", ',', $single_path); // restore commas
                $xpath = self::transform(trim($single_path));
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
        if ($str === '' || strpos($str, $search_char) === false || strpos($str, $enclosure_open) === false) {
            return $str;
        }

        for ($i = 0, $str_length = \strlen($str); $i < $str_length; $i++) {
            if ($i > 0 && $str[$i] === $search_char) {
                // check if enclosure is open by counting char before position
                $enclosure_is_open = substr_count($str, $enclosure_open, 0, $i) !== substr_count($str, $enclosure_close, 0, $i);
                if ($enclosure_is_open) {
                    $str[$i] = "\0";
                }
            }
        }

        return $str;
    }

    /**
     * Get segment data from token (css selector delimited by space and commas)
     *
     * @param string $token
     * @param integer $key
     * @param string[] $tokens
     *
     * @return object|false $segment
     */
    private static function getSegmentFromToken($token, $key, array $tokens)
    {
        $relation_tokens = array('>', '~', '+');

        if (\in_array($token, $relation_tokens, true)) { // not a segment
            return false;
        }

        $segment = (object) array(
            'selector' => '',
            'relation_token' => false,
            'attribute_filters' => array(),
            'pseudo_filters' => array()
        );

        if (isset($tokens[$key-1]) && \in_array($tokens[$key-1], $relation_tokens, true)) { // get relationship token
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
                    return 'ESCAPED'. \ord($matches[2]);
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

        while (preg_match('/(.*)\.([^\.\#]+)$/i', $segment->selector, $matches)) { // class selector
            $segment->selector = $matches[1];
            $segment->attribute_filters[] = 'class~="'.$matches[2].'"';
        }

        while (preg_match('/(.*)\#([^\.\#]+)$/i', $segment->selector, $matches)) { // id selector
            $segment->selector = $matches[1];
            $segment->attribute_filters[] = 'id="'.$matches[2].'"';
        }

        if ($char_tmp_replaced) { // restore temporary replaced characters
            $set_escape_back = function (string $str) {
                return preg_replace_callback(
                    '#(ESCAPED)([0-9]{1,3})#',
                    function ($matches) {
                        return \chr($matches[2]);
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
     * @param object[] $segments
     *
     * @return string[] $new_path_tokens
     */
    private static function transformCssSegments(array $segments)
    {
        $new_path_tokens = array();

        foreach ($segments as $num => $segment) {
            if ($segment->relation_token === '>') {
                $new_path_tokens[] = '/';
            } elseif ($segment->relation_token === '~' || $segment->relation_token === '+') {
                $new_path_tokens[] = '/following-sibling::';
            } else {
                $new_path_tokens[] = '//';
            }

            if ($segment->selector !== '') {
                $new_path_tokens[] = $segment->selector; // specific tagname
            } else {
                $new_path_tokens[] = '*'; // any tagname
            }

            if ($segment->relation_token === '+' && isset($segments[$num-1])) { // add adjacent filter
                $prev_selector = implode('', self::transformCssSegments([$segments[$num-1]]));
                $new_path_tokens[] = '[preceding-sibling::*[1][self::'.ltrim($prev_selector, '/').']]';
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
     * @param string[] $new_path_tokens
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
                $part = 'self::'.ltrim(self::transform($part), '/');
            }
            $not_selector = implode(' or ', $parts);
            return '[not('.$not_selector.')]';
        } elseif (preg_match('|contains\((.+)\)|i', $expression, $matches)) {
            return '[text()[contains(.,\''.$matches[1].'\')]]'; // contain the specified text
        } elseif (preg_match('|has\((.+)\)|i', $expression, $matches)) {
            if (strpos($matches[1], '> ') === 0) {
                return '[child::' . ltrim(self::transform($matches[1]), '/') .']';
            } else {
                return '[descendant::' . ltrim(self::transform($matches[1]), '/') .']';
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
        if (preg_match('|^([a-z0-9_]{1}[a-z0-9_-]*)(([\!\*\^\$\~\|]{0,1})=)?'.
        '(?:[\'"]*)?([^\'"]+)?(?:[\'"]*)?$|i', $expression, $matches)) {
            if (!isset($matches[3])) { // attribute without value
                return "[@" . $matches[1] . "]";
            } elseif ($matches[3] === '') { // arbitrary attribute strict value equality
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
                (\strlen($matches[4])-1).") = '".$matches[4]."']";
            } elseif ($matches[3] === '|') { // attribute has prefix selector
                return '[@' . strtolower($matches[1]) . "='" . $matches[4] . "' or starts-with(@".
                strtolower($matches[1]) . ", '" . $matches[4].'-' . "')]";
            }
        }

        throw new \Exception('Attribute selector is malformed or contains unsupported characters.');
    }
}
