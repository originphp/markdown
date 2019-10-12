<?php
declare(strict_types = 1);
/**
 * OriginPHP Framework
 * Copyright 2018 - 2019 Jamiel Sharief.
 *
 * Licensed under The MIT License
 * The above copyright notice and this permission notice shall be included in all copies or substantial
 * portions of the Software.
 *
 * @copyright   Copyright (c) Jamiel Sharief
 * @link        https://www.originphp.com
 * @license     https://opensource.org/licenses/mit-license.php MIT License
 */

namespace Origin\Markdown;

use DOMNode;
use DOMDocument;
use Origin\Html\Html;

class Markdown
{

    /**
     * Characters to escape
     *
     * @var string
     */
    protected static $characters = '`~*_\'"';

    /**
     * Prepares Markdown for parsing
     *
     * @param string $Markdown
     * @return string
     */
    protected static function prepareMarkdown(string $Markdown) : string
    {
        # Don't remove spaces from start of line  //preg_replace('/^ +/m')
        $Markdown = str_replace(["\r\n", "\n"], PHP_EOL, $Markdown); // Standardize line endings
       
        // Remove preceeding spaces on headings and blockquotes
        $Markdown = preg_replace('/^(\s*)#/', '#', $Markdown);
        $Markdown = preg_replace('/^(\s*)> /', '> ', $Markdown);

        return preg_replace('/^(\s*)&gt; /', '> ', $Markdown);
    }

    /**
     * Converts Markdown to text
     *
     * @param string $Markdown
     * @return string
     */
    public static function toText(string $Markdown): string
    {
        # Don't remove spaces from start of line  //preg_replace('/^ +/m')
        $Markdown = static::prepareMarkdown($Markdown);

        $Markdown = preg_replace('/^#{0,6} (.*)$/m', '$1', $Markdown);
        $Markdown = preg_replace('/^> (.*)$/m', '"$1"', $Markdown);
        $Markdown = preg_replace("/```?\n([^\n```].*)```/ms", '$1', $Markdown);

        $Markdown = preg_replace('/`([^`].*)`/', '$1', $Markdown);
        ;
        $Markdown = preg_replace("/!\[([^\]].*)\]\((.*)\)/", '[image: $1 $2]', $Markdown); # order
        $Markdown = preg_replace("/!\[]\((.*)\)/", '[image: $1]', $Markdown); # order
        # Links stay the same
        $lines = explode("\n", $Markdown);
        foreach ($lines as $index => $line) {
            if ($line and $line[0] === '|') {
                if (strpos($line, '---') !== false) {
                    unset($lines[$index]);
                    continue;
                }
                $cells = explode('|', substr($line, 1, -1));
                $cells = array_map('trim', $cells);
                $lines[$index] = implode(' ', $cells);
            }
        }
        $Markdown = implode(PHP_EOL, $lines);

        // This could easily be done in one line, but then you run into issues like **asterisks and _underscores_**
        $Markdown = preg_replace('/(\*\*|__)(.*)\1/', '\2', $Markdown);
        $Markdown = preg_replace('/(\*|_)(.*)\1/', '\2', $Markdown);
        $Markdown = preg_replace('/~~(.*?)~~/', '$1', $Markdown);

        return $Markdown;
    }
    /**
     * Converts HTML to Markdown
     *
     * @param string $html
     * @return string
     */
    public static function fromHtml(string $html): string
    {
        $html = Html::stripTags($html, ['script', 'style', 'iframe']);
        $html = Html::minify($html);

        $doc = new DOMDocument();
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = false;

        $html = str_replace(["\r\n", "\n"], PHP_EOL, $html); // Standardize line endings

        @$doc->loadHTML($html, LIBXML_HTML_NODEFDTD);
        /**
         * Do not sort. The order is important. Certain elements need to be adjusted first including links, images
         */
        $process = ['a', 'img', 'br', 'code', 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'table','li', 'ul', 'ol', 'blockquote'];
        foreach ($process as $needle) {
            $nodes = $doc->getElementsByTagName($needle);
            foreach ($nodes as $node) {
                static::processTag($node, $doc);
            }
        }
       
        return trim($doc->textContent);
    }

    /**
     * Converts Markdown to HTML
     *
     * @param string $Markdown
     * @param array $options The following option keys are supported:
     *   -escape: default is true. Escapes all text before passing. Code blocks are always escaped
     * @return string
     */
    public static function toHtml(string $Markdown, array $options = []): string
    {
        $options += ['escape' => true];
        if ($options['escape']) {
            $Markdown = htmlentities($Markdown, ENT_NOQUOTES); // this will
        }
        # Don't remove spaces from start of line  //preg_replace('/^ +/m')
        $Markdown = static::prepareMarkdown($Markdown);

        $Markdown = static::parseHeadings($Markdown);

        $Markdown = preg_replace_callback('/!\[(.*)\]\((.*)\)/', function ($matches) {
            $alt = static::escape($matches[1]);

            // Remove invalid urls
            $src = '';
            if (filter_var($matches[2], FILTER_VALIDATE_URL)) {
                $src = static::escape($matches[2]);
            }

            return '<img src="'.$src.'" alt="'.$alt.'">';
        }, $Markdown);

        $Markdown = preg_replace_callback('/\[(.*)\]\((.*)\)/', function ($matches) {
            $Markdown = static::escape($matches[1]);
            
            // Remove invalid urls
            $href = '';
            if (filter_var($matches[2], FILTER_VALIDATE_URL)) {
                $href = static::escape($matches[2]);
            }

            return '<a href="'.$href.'">' . $Markdown . '</a>';
        }, $Markdown);

        $Markdown = preg_replace('/^> (.*)$/m', "<blockquote>$1</blockquote>\n", $Markdown);
        $Markdown = preg_replace('/^&gt; (.*)$/m', "<blockquote>$1</blockquote>\n", $Markdown); // work with escaping

        $Markdown = static::parseLists($Markdown);

        # Work with Code Blocks
        $Markdown = preg_replace_callback('/```([^```].*)```/ms', function ($matches) use ($options) {
            $needle = trim($matches[1]);
            if ($options['escape'] === false) {
                $needle = htmlentities($needle, ENT_NOQUOTES); // Codeblocks need to be escaped, but not twice
            }
            $needle = static::escape($needle); // Escape Markdown characters

            // trim for correct spacing and add line spacing around pre to help with p detection
            return "\n" . '<pre><code>' . $needle . '</code></pre>' . "\n" ; // add extra \n so that its not stropped
        }, $Markdown);
  
        $Markdown = preg_replace_callback('#\`(.*?)`#', function ($matches) use ($options) {
            $needle = trim($matches[1]);
            if ($options['escape'] === false) {
                $needle = htmlentities($needle, ENT_NOQUOTES);
            }
            $needle = static::escape($needle); // Escape Markdown characters
            return '<code>' . $needle . '</code>';
        }, $Markdown);
      
        $Markdown = static::parseTables($Markdown);
        $Markdown = static::parseParagraphs($Markdown);
        $Markdown = static::unescape($Markdown);
  
        /**
        * Sanitize HTML to ensure nobody slipped in HTML.
        * On look out for XSS stuff like IMG onerror and javascript alert encode.
        * This is fallback if escape is disabled.
        */
       
        $Markdown = Html::sanitize($Markdown, [
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'p',
            'i', 'em', 'strong', 'b', 'blockquote', 'del',
            'a' => ['href'],
            'ul', 'li', 'ol', 'br',
            'code', 'pre',
            'img' => ['src','alt'],
        ]);
    
        return trim($Markdown);
    }

    /**
     * Parses heading tags
     *
     * @param string $data
     * @return string
     */
    protected static function parseHeadings(string $data): string
    {
        $text = preg_replace('/^# (.*)$/m', "<h1>$1</h1>\n", $data);
        $text = preg_replace('/^## (.*)$/m', "<h2>$1</h2>\n", $text);
        $text = preg_replace('/^### (.*)$/m', "<h3>$1</h3>\n", $text);
        $text = preg_replace('/^#### (.*)$/m', "<h3>$1</h4>\n", $text);
        $text = preg_replace('/^##### (.*)$/m', "<h4>$1</h5>\n", $text);

        return  preg_replace('/^###### (.*)$/m', "<h5>$1</h6>\n", $text);
    }

    /**
     * Parses unordered and numbered lists
     *
     * @param string $data
     * @return string
     */
    protected static function parseLists(string $data): string
    {
        $text = preg_replace_callback(
            '/^[*\-] (.*)$/m',
            function ($matches) {
                $item = static::convertEmphasis($matches[1]);

                return "<ul><li>{$item}</li></ul>";
            },
            $data
        );

        $text = preg_replace("/^[*\-] (.*)$/m", '<ul><li>$1</li></ul>', $text);
        $text = str_replace("</ul>\n<ul><li>", "\n<li>", $text);

        $text = preg_replace("/^[0-9]\. (.*)$/m", "\n<ol><li>$1</li></ol>", $text);

        return str_replace("</ol>\n\n<ol><li>", "\n<li>", $text);
    }

    /**
     * Parses, you guessed it paragaphs and deals with some other stuff
     *
     * @param string $data
     * @return string
     */
    protected static function parseParagraphs(string $data): string
    {
        $lines = explode("\n\n", $data);
        /**
         * Paragraph lines
         */
        $skip = false;
      
        foreach ($lines as $index => $line) {
            $line = trim($line);
            if (strpos($line, '<pre>') !== false) {
                $skip = true;
            }
            if (strpos($line, '</pre>') !== false) {
                $skip = false;
                continue; // jump to next line
            }

            if ($skip === true) {
                continue;
            }
            if (! preg_match('/<\/(h1|h2|h3|h4|h5|h6|ul|ol|blockquote|table|tr|th|td)>/', $line)) {
                $line = static::convertEmphasis($line);
                $line = sprintf('<p>%s</p>', $line);
                $line = str_replace("\n", '<br>', $line);
            }
            if ($line) {
                $lines[$index] = $line;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Parses Markdown tables
     *
     * @param string $data
     * @return string
     */
    protected static function parseTables(string $data): string
    {
        # Parse Tables
        $lines = explode("\n", $data);

        $isTable = false;
        $out = [];
        foreach ($lines as $i => $line) {
            if (substr($line, 0, 1) === '|') {
                if ($isTable === false) {
                    $out[] = '<table>';
                    $isTable = true;
                }
            } elseif ($isTable) {
                $isTable = false;
                $out[] = '</table>'; // if its last line
            }

            if ($isTable) {
                $cells = explode('|', substr($line, 1, -1));
                $cells = array_map('trim', $cells);
                if (isset($lines[$i + 1]) and strpos($lines[$i + 1], '---') !== false) {
                    $line = '<tr><th>' . implode('</th><th>', $cells) . '</th></tr>';
                } else {
                    if (strpos($line, '---') === false) {
                        $line = '<tr><td>' . implode('</td><td>', $cells) . '</td></tr>';
                    } else {
                        continue;
                    }
                }
                $line = static::convertEmphasis($line);
            }
            $out[] = $line;
        }

        return implode("\n", $out);
    }

    /**
     * Handles the emphasis formatting (used in paragraphs,tds and lis only).
     * Why? Cause converting to text. So it wont be spans etc
     *
     * @param string $data
     * @return string
     */
    protected static function convertEmphasis(string $data): string
    {
        $data = preg_replace('/(?<!\\\)(\*\*|__)(.*)\1/', '<strong>\2</strong>', $data);
        $data = preg_replace('/(?<!\\\)(\*|_)(.*)\1/', '<em>\2</em>', $data);

        return preg_replace('/(?<!\\\)~~(.*?)~~/', '<del>$1</del>', $data);
    }

    /**
     * Processes a tag from a DOMDocument
     *
     * @internal Attempting to modify the dom causes strange issues and even recursion
     * @param \DOMNode $tag
     * @param \DOMDocument $doc
     * @return void
     */
    protected static function processTag(DOMNode $tag, DOMDocument $doc): void
    {
        $value = static::htmlspecialchars($tag->nodeValue);

        switch ($tag->tagName) {
            case 'a':
                $tag->nodeValue = "[{$value}](" . static::htmlspecialchars($tag->getAttribute('href')) . ')';
                break;
            case 'br':
                $tag->nodeValue = PHP_EOL;
                break;
            case 'code':

                if (strpos($tag->nodeValue, PHP_EOL) !== false) {
                    // multiline
                    $tag->nodeValue = PHP_EOL . '```' . PHP_EOL . $value . PHP_EOL . '```'  . PHP_EOL;
                } else {
                    $tag->nodeValue = '`' .  $value . '`';
                }

                break;
            case 'blockquote':
                $tag->nodeValue = PHP_EOL . '> ' . $value . PHP_EOL;
                break;
            case 'h1':
                $tag->nodeValue = PHP_EOL . '# ' .  $value . PHP_EOL;
                break;
            case 'h2':
                $tag->nodeValue = PHP_EOL . '## ' . $value . PHP_EOL;
                break;
            case 'h3':
                $tag->nodeValue = PHP_EOL . '### ' . $value . PHP_EOL;
                break;
            case 'h4':
                $tag->nodeValue = PHP_EOL . '#### ' . $value . PHP_EOL;
                break;
            case 'h5':
                $tag->nodeValue = PHP_EOL . '##### ' . $value . PHP_EOL;
                break;
            case 'h6':
                $tag->nodeValue = PHP_EOL . '###### ' . $value . PHP_EOL;
                break;
            case 'img':
                $alt = '';
                if ($tag->hasAttribute('alt')) {
                    $alt = $tag->getAttribute('alt') . ' ';
                }
                $tag->nodeValue = "![{$alt}](" . static::htmlspecialchars($tag->getAttribute('src')) . ')';
                break;
            case 'li':
                if ($tag->hasChildNodes()) {
                    foreach ($tag->childNodes as $child) {
                        if (in_array($child->nodeName, ['ul', 'ol'])) {
                            static::processTag($child, $doc);
                        }
                    }
                }
                break;
            case 'ol':
                $count = 1;
                $lineBreak = PHP_EOL;
                $indent = static::getIndentLevel($tag);
                $pre = str_repeat(' ', $indent);
                foreach ($tag->childNodes as $child) {
                    if (isset($child->tagName) and $child->tagName === 'li') {
                        $child->nodeValue = $lineBreak . $pre .  $count . '. ' . static::htmlspecialchars($child->nodeValue);
                        $child->nodeValue = rtrim($child->nodeValue) . PHP_EOL; // friendly with nested lists
                        $count++;
                        $lineBreak = null;
                    }
                }
                break;
            case 'p':
                $tag->nodeValue = PHP_EOL . $value . PHP_EOL;
                break;
            case 'table':

                $data = [];
                $headers = false;
                foreach ($tag->getElementsByTagName('tr') as $node) {
                    $row = [];
                    foreach ($node->childNodes as $child) {
                        if (isset($child->tagName) and ($child->tagName === 'td' or $child->tagName === 'th')) {
                            if ($child->tagName === 'th') {
                                $headers = true;
                            }
                            $row[] = static::htmlspecialchars($child->nodeValue);
                        }
                    }
                    $data[] = $row;
                }
                if ($data) {
                    $data = static::arrayToTable($data, $headers);
                }
               
                // Replacing can cause issues
                $div = $doc->createElement('div', PHP_EOL . implode(PHP_EOL, $data) . PHP_EOL);
                $tag->parentNode->insertBefore($div, $tag);
                $tag->nodeValue = null;

                break;
            case 'ul':
                $lineBreak = PHP_EOL;
                $indent = static::getIndentLevel($tag);
                $pre = str_repeat(' ', $indent);

                foreach ($tag->childNodes as $child) {
                    if (isset($child->tagName) and $child->tagName === 'li') {
                        $child->nodeValue = $lineBreak . $pre . '* ' .   static::htmlspecialchars($child->nodeValue);
                        $child->nodeValue = rtrim($child->nodeValue) . PHP_EOL; // friendly with nested lists
                        $lineBreak = null;
                    }
                }
                break;
        }
        // Remove all attributes
        foreach ($tag->attributes as $attr) {
            $tag->removeAttribute($attr->nodeName);
        }
    }

    /**
     * Internal for creating table
     *
     * @param array $array
     * @param boolean $headers
     * @return array
     */
    protected static function arrayToTable(array $array, bool $headers = true): array
    {
        // Calculate width of each column
        $widths = [];
        foreach ($array as $row) {
            foreach ($row as $columnIndex => $cell) {
                if (! isset($widths[$columnIndex])) {
                    $widths[$columnIndex] = 0;
                }
                $width = strlen($cell) + 4;
                if ($width > $widths[$columnIndex]) {
                    $widths[$columnIndex] = $width;
                }
            }
        }

        $out = [];
        $seperator = '';

        foreach ($array[0] as $i => $cell) {
            $seperator .= str_pad('|', $widths[$i], '-', STR_PAD_RIGHT);
        }
        $seperator .= '|';

        if ($headers) {
            $headers = '|';
            foreach ($array[0] as $i => $cell) {
                $headers .= ' ' . str_pad($cell, $widths[$i] - 2, ' ', STR_PAD_RIGHT) . '|';
            }
            $out[] = $headers;
            $out[] = $seperator;
            array_shift($array);
        }

        foreach ($array as $row) {
            $cells = '|';
            foreach ($row as $i => $cell) {
                $cells .= ' ' . str_pad($cell, $widths[$i] - 2, ' ', STR_PAD_RIGHT) . '|';
            }
            $out[] = $cells;
        }

        return $out;
    }

    /**
     * Check if value needs converting and convert
     *
     * @param string $value
     * @return string
     */
    protected static function htmlspecialchars(string $value) : string
    {
        if (strpos($value, '&') !== false) {
            $value = htmlspecialchars($value);
        }

        return $value;
    }

    /**
     * Gets the indent level for ul/ol
     *
     * @param \DOMNode $node
     * @return integer
     */
    protected static function getIndentLevel(DOMNode $node): int
    {
        $indent = 0;
        $checkLevelUp = true;
        $current = $node;

        while ($checkLevelUp) {
            if ($current->parentNode->nodeName === 'li') {
                $current = $current->parentNode;
                $indent = $indent + 3;
            } else {
                $checkLevelUp = false;
            }
        }

        return $indent;
    }

    /**
     * Escape Markdown characters
     *
     * @param string $Markdown
     * @return string
     */
    protected static function escape(string $Markdown) : string
    {
        return preg_replace('#([' . preg_quote(self::$characters, '#') . '])#', '\\\$1', $Markdown);
    }
  
    /**
     * UnEscape Markdown characters
     *
     * @param string $Markdown
     * @return string
     */
    protected static function unescape(string $Markdown) : string
    {
        return preg_replace('#\\\\([' . preg_quote(self::$characters, '#') . '])#', '$1', $Markdown);
    }
}
