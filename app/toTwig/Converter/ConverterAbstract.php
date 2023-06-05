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
use toTwig\SourceConverter\Token;
use toTwig\SourceConverter\Token\TokenTag;


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

    public function convert(TokenTag $content): TokenTag
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
                [$value] = $this->parseValue($string, $x, [' ']);
                $pairs[trim($key)] = trim($value);
                $key = '';
                $is_key = true;
            }
        }
        $key = trim($key);
        [$value] = $this->parseValue($string, $x, [' ']);
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
        '!==',
        '!=',
        '==',
        '!',
        '&&',
        '||',
        '-',
        '=>',
        '>=',
        '<=',
        '>',
        '<',
        '?',
        ':',
        '.',
        '->'
    ];

    private const DELIM_MAP = [
        '!' => 'not',
        '&&' => 'and',
        '||' => 'or',
        'mod' => '%',

        'gt' => '>',
        'lt' => '<',
        'eq' => '==',
        'ne' => '!=',
        'neq' => '!=',
        'gte' => '>=',
        'ge' => '>=',
        'lte' => '<=',
        'le' => '<=',

        '->' => '.',
    ];
    private function splitSanitize(string $string): string {
        $final = [];
        $prevDelim = '';
        $prevValue = '';
        for ($x = 0; $x < strlen($string); $x++) {
            [$value, $delim] = $this->parseValue($string, $x, self::TOKENS);
            $delimNew = trim($delim);
            $delimNew = self::DELIM_MAP[$delim] ?? $delim;
            if (in_array($value[0] ?? '', ['"', "'"], true)) {
                if ($delimNew === '.') {
                    $delimNew = '~';
                } elseif ($prevDelim === '.') {
                    array_pop($final);
                    $final []= ' ~ ';
                    $prevDelim = ' ~ ';
                }
            } elseif (in_array($prevValue[0] ?? '', ['"', "'"], true)) {
                if ($delimNew === '.') {
                    $delimNew = '~';
                }
            }
            if ($prevDelim === ' not ' && $value[0] !== '$') {
                $value = '$'.$value;
            }
            if ($delimNew !== ' ' && $delimNew !== '' && $delimNew !== '.') {
                $delimNew = " $delimNew ";
            }
            if ($prevDelim === '.') {
                array_pop($final);
                $value = array_pop($final).'.'.$value;
            }
            $final []= $this->sanitizeValue($value);
            $final []= $delimNew;
            $prevDelim = $delimNew;
            $prevValue = $value;
        }
        return implode('', $final);
    }
    protected function splitParsing(string $string, string $delim): array {
        $final = [];
        for ($x = 0; $x < strlen($string); $x++) {
            $final []= $this->parseValue($string, $x, [$delim])[0];
            if ($x === strlen($string)-1) {
                $final []= '';
            }
        }
        return $final;
    }
    protected function parseValue(string $string, int &$x, array $delim): array {
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
                } elseif ($cur === '{' && !$has_delim) {
                    $value .= $cur;
                    $stack []= '}';
                } elseif ($cur === '(' && !$has_delim) {
                    $value .= $cur;
                    $stack []= ')';
                } elseif ($has_delim && !$stack && !(trim($d) === '' && trim($value) === '')) {
                    $x += strlen($d)-1;
                    return [trim($value), $d];
                } else {
                    $value .= $cur;
                }
            }
        }
        return [trim($value), ''];
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
        $string = self::DELIM_MAP[$string] ?? $string;

        if (empty($string)) {
            return $string;
        }

        // Handle non-quoted strings
        if (preg_match("/^[a-zA-Z]\w+$/", $string)) {
            if (!in_array($string, ["true", "false", "and", "or", "not", "null", "TRUE", "FALSE", "NULL"])) {
                return "\"" . $string . "\"";
            }
            $string = strtolower($string);
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
        [$final] = $this->parseValue($string, $x, ['(']);
        if ($is_array = trim($final) === 'array') {
            $final = '[';
            $x++;
        } else {
            $final .= $string[$x++] ?? '';
        }
        while ($x < strlen($string)) {
            [$temp] = $this->parseValue($string, $x, [',', ')']);
            if (!$is_array) {
                $temp = $this->sanitizeExpression($temp);
            }
            $final .= $temp;
            $final .= $string[$x++] ?? '';
        }
        if ($is_array) {
            $final = rtrim($final, ')');
        }
        return $final;
    }


    private function convertArrayKey(string $string): string
    {
        $x = 0;

        $chunks = [];

        [$prefix] = $this->parseValue($string, $x, ['[']);
        $prefix .= $string[$x++] ?? '';

        while ($x < strlen($string)) {
            [$chunk] = $this->parseValue($string, $x, [']']);

            $chunks []= [
                $prefix,
                $chunk
            ];

            $prefix = $this->sanitizeExpression($this->parseValue($string, $x, ['['])[0]);
            $prefix .= $string[$x++] ?? '';

            $postfix = trim($prefix, '[]');
        }

        if (count($chunks) === 0) {
            return $prefix;
        }
        if (count($chunks) > 1 || $chunks[0][0][0] !== '[') {
            $final = '';
            foreach ($chunks as [$prefix, $chunk]) {
                $prefix = trim($prefix, '[]');
                $chunk = $this->sanitizeExpression($chunk);
                $final .= $prefix."[$chunk]";
            }
            return $final.$postfix;
        }
        return $this->parseArrayKeyValue($chunks[0][1]).$postfix;
    }

    private function parseArrayKeyValue(string $string): string
    {
        $x = 0;
        $k = 0;
        $arr = [];
        while ($x < strlen($string)) {
            $key = $this->sanitizeExpression($this->parseValue($string, $x, ['=>', ',', ']'])[0]);
            $cur = $string[$x++] ?? '';
            if ($cur === '>') {
                $value = $this->sanitizeExpression($this->parseValue($string, $x, [',', ']'])[0]);
                $x++;
            } elseif ($cur === ',' || $cur === ']' || $cur === '') {
                $value = $key;
                $key = $k++;
            } else {
                throw new AssertionError('Unreachable');
            }
            $arr []= "$key: $value";
        }
        if (!$arr) {
            return '{}';
        }
        return '{'.implode(', ', $arr).'}';
    }

    private const STATE_FILTER = 0;
    private const STATE_ARGS = 1;
    /**
     * Handle translation of filters
     * For example:
     *   smarty: [{ "foo"|smarty_bar) }]
     *   twig:   {{ "foo"|twig_bar }}
     */
    private function convertFilters(string $string, bool $root = false): string
    {
        $x = 0;
        $final = '';
        while (1) {
            $final .= $this->parseValue($string, $x, ['|'])[0];
            $x++;
            if (($string[$x] ?? '') === '|') {
                $final .= '||';
                $x++;
            } else {
                break;
            }
        }
        $final = [$final];
        
        $last_filter = '';
        $append_filter = function (string &$filter_name, array &$filter_args) use (&$final, &$last_filter) {
            $filter_name = FilterNameMap::getConvertedFilterName(ltrim($filter_name, '@'));
            $last_filter = $filter_name.($filter_args ? '('.implode(', ', $filter_args).')' : '');
            $final []= '|'.$last_filter;
            $filter_name = '';
            $filter_args = [];
        };

        $state = self::STATE_FILTER;
        $filter_name = '';
        $filter_args = [];
        for (; $x < strlen($string); $x++) {
            $cur = $string[$x];
            if ($state === self::STATE_ARGS) {
                $filter_args []= $this->sanitizeExpression($this->parseValue($string, $x, [',', ':', ')', '|'])[0]);
                if (($string[$x] ?? '') === ')') {
                    $x++;
                }
                if (($string[$x] ?? '') === '|') {
                    $append_filter($filter_name, $filter_args);
                    $state = self::STATE_FILTER;
                }
            } elseif ($state === self::STATE_FILTER) {
                if ($cur === ':' || $cur === '(') {
                    $state = self::STATE_ARGS;
                } elseif ($cur === '|') {
                    $append_filter($filter_name, $filter_args);
                    $state = self::STATE_FILTER;
                } else {
                    $filter_name .= $cur;
                }
            } else {
                throw new AssertionError("Unreachable!");
            }
        }
        if (trim($filter_name) !== '') {
            $append_filter($filter_name, $filter_args);
        }
        if ($root) {
            if ($last_filter === 'esc' || $last_filter === 'escape("html")' || $last_filter === 'htmlspecialchars') {
                array_pop($final);
            } elseif ($last_filter !== 'raw' && $last_filter !== 'json_encode') {
                return '('.implode('', $final).')|raw';
            }
        }
        //var_dump($string, $final);
        return implode('', $final);
    }
    private function convertIdentical(string $expression): string
    {
        $final = [];
        $prev = '';
        for ($x = 0; $x < strlen($expression); $x++) {
            [$cur] = $this->parseValue($expression, $x, [' ']);
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
    protected function sanitizeExpression(string $expression, bool $root = false): string
    {
        $expression = $this->convertFilters($expression, $root);

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
        return str_replace('.tpl', '.twig', $templateName);
    }

    protected function getAttributes(string $attributes): array
    {
        return $this->extractAttributes($attributes);
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
