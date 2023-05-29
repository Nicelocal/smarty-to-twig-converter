<?php

/**
 * This file is part of the PHP ST utility.
 *
 * (c) Sankar <sankar.suda@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace toTwig\Converter;

use AssertionError;
use Exception;
use SplFileInfo;
use toTwig\FilterNameMap;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
abstract class ConverterAbstract
{
    /**
     * @var string Name of the converter.
     *
     * The name must be all lowercase and without any spaces.
     */
    protected string $name;

    /**
     * @var string Description of the converter.
     *
     * A short one-line description of what the converter does.
     */
    protected string $description;

    /**
     * @var int Priority of the converter.
     *
     * The default priority is 0 and higher priorities are executed first.
     */
    protected int $priority = 0;

    /**
     * Fixes a file.
     *
     * @param string $content The file content
     *
     * @return string The fixed file content
     */
    public function convert(string $content): string
    {
        return $content;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Returns true if the file is supported by this converter.
     *
     * @param SplFileInfo $file
     *
     * @return Boolean true if the file is supported by this converter, false otherwise
     */
    public function supports(SplFileInfo $file): bool
    {
        return true;
    }

    /**
     * Get opening tag patterns like:
     *   [{tagName other stuff}]
     *   [{foreach $myColors as $color}]
     *
     * Matching this pattern will give these results:
     *   $matches[0] contains a string with full matched tag i.e.'[{tagName foo="bar" something="somevalue"}]'
     *   $matches[1] should contain a string with all other configuration coming with a tag i.e.
     *   'foo = "bar" something="somevalue"'
     */
    protected function getOpeningTagPattern(string $tagName): string
    {
        return sprintf("#\{\{\s*%s\b\s*((?:(?!\{\{|\}\}).(?<!\{\{)(?<!\}\}))+)?\}\}#is", preg_quote($tagName, '#'));
    }

    /**
     * Get closing tag pattern: [{/tagName}]
     */
    protected function getClosingTagPattern(string $tagName): string
    {
        return sprintf("#\{\{\s*/%s\s*\}\}#i", preg_quote($tagName, '#'));
    }

    /**
     * Method to extract key/value pairs out of a string with xml style attributes
     *
     * @param string $string String containing xml style attributes
     *
     * @return  array   Key/Value pairs for the attributes
     */
    protected function extractAttributes(string $string): array
    {
        $pairs = [];
        $is_key = true;
        $key = '';
        for ($x = 0; $x < strlen($string); $x++) {
            $cur = $string[$x];
            if ($is_key) {
                if ($cur === '=') {
                    $is_key = false;
                } else {
                    $key .= $cur;
                }
            } else {
                $value = $this->parseValue($string, $x, [' ']);
                $pairs[trim($key)] = trim($value);
                $key = '';
                $is_key = true;
            }
        }
        $key = trim($key);
        $value = $this->parseValue($string, $x, [' ']);
        if ($key !== '') {
            $pairs[trim($key)] = trim($value);
        }
        //var_dump($string, $pairs);

        return $pairs;
    }

    private const TOKENS = [
        ' ',
        '+',
        '?:',
        '*',
        '/',
        '%',
        '===',
        '==',
        '&&',
        '||',
        '-',
        '=>',
        '>=',
        '<=',
        '>',
        '<',
        '!==',
        '!=',
        '?',
        ':',
        '.',
        '->'
    ];

    private function splitSanitize(string $string, int $idx = 0): string {
        if ($idx === count(self::TOKENS)) {
            return $this->sanitizeValue($string);
        }
        $delim = self::TOKENS[$idx];
        if ($string === '===' && $delim === '==') {
            return $string;
        }
        if ($string === '!==' && $delim === '==') {
            return $string;
        }
        if ($string === '!==' && $delim === '!=') {
            return $string;
        }
        if ($string === '->') {
            return $string;
        }
        if ($string === '=>') {
            return $string;
        }
        if ($string === '?:') {
            return $string;
        }
        $delimNew = $this->sanitizeValue($delim) ?: $delim;
        if ($delimNew !== ' ') {
            $delimNew = " $delimNew ";
        }
        if (!str_contains($string, $delim)) {
            return $this->splitSanitize($string, $idx+1);
        }
        $split = $this->splitParsing($string, $delim);
        if ($delim === '.' && count($split) > 1) {
            $has_string = false;
            foreach ($split as $v) {
                if (in_array(trim($v)[0] ?? '', ['"', "'"], true)) {
                    $has_string = true;
                    break;
                }
            }
            if ($has_string) {
                $delimNew = ' ~ ';
            } else {
                return $this->splitSanitize(implode('.', $split), $idx+1);
            }
        }
        if ($delim === '->') {
            return $this->splitSanitize(implode('.', $split), $idx+1);
        }
        return implode($delimNew, array_map(fn ($v) => $this->splitSanitize($v, $idx+1), $split));
    }
    protected function splitParsing(string $string, string $delim): array {
        $final = [];
        for ($x = 0; $x < strlen($string); $x++) {
            $final []= $this->parseValue($string, $x, [$delim]);
            if ($x === strlen($string)-1) {
                $final []= '';
            }
        }
        return $final;
    }
    private function parseValue(string $string, int &$x, array $delim): string {
        $stack = [];
        $value = '';
        for (; $x < strlen($string); $x++) {
            $cur = $string[$x];
            if (end($stack) === $cur) {
                $value .= $cur;
                array_pop($stack);
            } else {
                $has_delim = false;
                foreach ($delim as $d) {
                    if (substr($string, $x, strlen($d)) === $d
                        && !($d === '-' && substr($string, $x, 2) === '->')
                        && !($d === '>' && substr($string, $x-1, 2) === '->')
                    ) {
                        $has_delim = true;
                        break;
                    }
                }
                if (in_array($cur, ['"', "'"])) {
                    $start = $x;
                    $x++;
                    for (; $x < strlen($string); $x++) {
                        if ($string[$x] === $cur && $string[$x-1] !== '\\') {
                            break;
                        }
                    }
                    $value .= substr($string, $start, ($x-$start)+1);
                } elseif ($cur === '[' && !$has_delim) {
                    $value .= $cur;
                    $stack []= ']';
                } elseif ($cur === '(' && !$has_delim) {
                    $value .= $cur;
                    $stack []= ')';
                } elseif ($has_delim && !$stack && (trim($value) !== '' || !$x)) {
                    $x += strlen($d)-1;
                    return trim($value);
                } else {
                    $value .= $cur;
                }
            }
        }
        return trim($value);
    }

    /**
     * Sanitize variable, remove $,' or " from string
     */
    protected function sanitizeVariableName(string $string): string
    {
        return trim(trim($string), '$"\'');
    }

    private function sanitizeValue(string $string): string
    {
        $string = trim($string);

        if (empty($string)) {
            return $string;
        }

        // Handle "..." and '...'
        if (preg_match("/^\"[^\"]*\"$/", $string) || preg_match("/^'[^']*'$/", $string)) {
            return $string;
        }

        // Handle operators
        switch ($string) {
            case '&&':
                return 'and';
            case '||':
                return 'or';
            case 'mod':
                return '%';
            case 'gt':
                return '>';
            case 'lt':
                return '<';

            case 'eq':
                return '==';

            case 'ne':
            case 'neq':
                return '!=';

            case 'gte':
            case 'ge':
                return '>=';

            case 'lte':
            case 'le':
                return '<=';

            case '->':
                return '.';
        }

        // Handle non-quoted strings
        if (preg_match("/^[a-zA-Z]\w+$/", $string)) {
            if (!in_array($string, ["true", "false", "and", "or", "not"])) {
                return "\"" . $string . "\"";
            }
        }

        // Handle "($var"
        if ($string[0] == "(") {
            return "(" . $this->sanitizeExpression(ltrim($string, "("));
        }

        // Handle "!$var"
        if (preg_match("/(?<=[(\\s]|^)!(?!=)(?=[$]?\\w*)/", $string)) {
            return "not " . $this->sanitizeExpression(ltrim($string, "!"));
        }

        $string = ltrim($string, '$');

        $string = $this->convertFunctionArguments($string);
        $string = $this->convertArrayKey($string);
        $string = $this->convertFilters($string);
        $string = $this->convertIdentical($string);

        return $string;
    }

    /**
     * Handles a case when smarty variable is passed to a function as a parameter
     * For example:
     *   smarty: [{ foo($bar)}]
     *   twig:   {{ foo(bar) }]
     */
    private function convertFunctionArguments(string $string): string
    {
        $x = 0;
        $final = $this->parseValue($string, $x, ['(']);
        $final .= $string[$x++] ?? '';
        while ($x < strlen($string)) {
            $final .= $this->sanitizeExpression($this->parseValue($string, $x, [',', ')']));
            $final .= $string[$x++] ?? '';
        }
        return $final;
    }


    private function convertArrayKey(string $string): string
    {
        $x = 0;
        $final = $this->parseValue($string, $x, ['[']);
        $final .= $string[$x++] ?? '';
        while ($x < strlen($string)) {
            $final .= $this->sanitizeExpression($this->parseValue($string, $x, [']']));
            $final .= $string[$x++] ?? '';
        }
        return $final;
    }

    private const STATE_FILTER = 0;
    private const STATE_ARGS = 1;
    /**
     * Handle translation of filters
     * For example:
     *   smarty: [{ "foo"|smarty_bar) }]
     *   twig:   {{ "foo"|twig_bar }}
     */
    private function convertFilters(string $string): string
    {
        $x = 0;
        $final = '';
        while (1) {
            $final .= $this->parseValue($string, $x, ['|']);
            $x++;
            if (($string[$x] ?? '') === '|') {
                $final .= '||';
                $x++;
            } else {
                break;
            }
        }
        

        $state = self::STATE_FILTER;
        $filter_name = '';
        $filter_args = [];
        for (; $x < strlen($string); $x++) {
            $cur = $string[$x];
            if ($state === self::STATE_ARGS) {
                $filter_args []= $this->sanitizeExpression($this->parseValue($string, $x, [',', ':', ')', '|']));
                if (($string[$x] ?? '') === ')') {
                    $x++;
                }
                if (($string[$x] ?? '') === '|') {
                    $final .= '|'.$filter_name.($filter_args ? '('.implode(', ', $filter_args).')' : '');
                    $filter_name = '';
                    $filter_args = [];
                    $state = self::STATE_FILTER;
                }
            } elseif ($state === self::STATE_FILTER) {
                if ($cur === ':' || $cur === '(') {
                    $state = self::STATE_ARGS;
                } elseif ($cur === '|') {
                    $final .= '|'.$filter_name.($filter_args ? '('.implode(', ', $filter_args).')' : '');
                    $filter_name = '';
                    $filter_args = [];
                    $state = self::STATE_FILTER;
                } else {
                    $filter_name .= $cur;
                }
            } else {
                throw new AssertionError("Unreachable!");
            }
        }
        if (trim($filter_name) !== '') {
            $final .= '|'.$filter_name.($filter_args ? '('.implode(', ', $filter_args).')' : '');
        }
        //var_dump($string, $final);
        return $final;
    }
    private function convertIdentical(string $expression): string
    {
        $final = [];
        $prev = '';
        for ($x = 0; $x < strlen($expression); $x++) {
            $cur = $this->parseValue($expression, $x, [' ']);
            if ($prev === '===') {
                $final []= "is same as($cur)";
            } elseif ($prev === '!==') {
                $final []= "is not same as($cur)";
            } elseif ($cur !== '===' && $cur !== '!==') {
                $final []= $cur;
            }

            $prev = $cur;
        }
        return implode(' ', $final);
    }

    /**
     * Explodes expression to parts to converts them separately
     * For example:
     *  input:  ($a+$b)
     *  output: ($a + $b)
     *
     * Matching input pattern will give these results:
     *   $matches[0] contains a string with full matched tag i.e.'[{($a+$b)}]'
     *   $matches[1] should contain a string with first part of an expression i.e. $a
     *   $matches[2] should contain a string with one of following characters: +, -, >, <, *, /, %, &&, ||
     *   $matches[3] should contain a string with second part of an expression i.e. $b
     */
    protected function sanitizeExpression(string $expression): string
    {
        $expression = $this->convertFilters($expression);

        $expression = $this->splitSanitize($expression);

        return $this->convertIdentical($expression);
    }

    /**
     * Replace named args in string
     * For example:
     *   $string = '{% set :key = :value %}'
     *   $args = ['key' => 'foo', 'value' => 'bar']
     *
     * return '{% set 'foo' = 'bar' %}'
     */
    protected function replaceNamedArguments(string $string, array $args): string
    {
        $string = preg_replace_callback(
            '/:([a-zA-Z0-9_-]+)/',
            function ($matches) use ($args) {
                if (isset($args[$matches[1]])) {
                    return str_replace($matches[0], $args[$matches[1]], $matches[0]);
                }
            },
            $string
        );

        return $this->removeMultipleSpaces($string);
    }

    /**
     * Replace multiple spaces in string with single space
     */
    protected function removeMultipleSpaces(string $string): string
    {
        return preg_replace('! +!', ' ', $string);
    }

    /**
     * Converts associative php array to twig array
     * ['a' => 1, 'b' => 2]  ==>>  "{ a: 1, b: 2 }"
     */
    protected function convertArrayToAssocTwigArray(array $array, array $skippedKeys): string
    {
        $pairs = [];
        foreach ($array as $key => $value) {
            if (in_array($key, $skippedKeys)) {
                continue;
            }
            $pairs[] = $this->sanitizeVariableName($key) . ": " . $this->sanitizeExpression($value);
        }

        // If array is empty, return nothing
        if (empty($pairs)) {
            return "";
        }

        return sprintf("{ %s }", implode(", ", $pairs));
    }

    protected function rawString(string $string): string
    {
        return trim($string, '\'"');
    }

    protected function convertFileExtension(string $templateName): string
    {
        return preg_replace('/\.tpl/', '.html.twig', $templateName);
    }

    protected function getAttributes(array $matches): array
    {
        $match = $this->getPregReplaceCallbackMatch($matches);
        return $this->extractAttributes($match);
    }

    protected function getPregReplaceCallbackMatch(array $matches): string
    {
        if (!isset($matches[1]) && $matches[0]) {
            $match = $matches[0];
        } else {
            $match = $matches[1];
        }

        return $match;
    }

    /**
     * Used in InsertTrackerConverter nad IncludeConverter
     */
    protected function getOptionalReplaceVariables(array $attr): string
    {
        $vars = [];
        foreach ($attr as $key => $value) {
            $vars[] = $this->sanitizeVariableName($key) . ": " . $this->sanitizeExpression($value);
        }

        return '{' . implode(', ', $vars) . '}';
    }
}
