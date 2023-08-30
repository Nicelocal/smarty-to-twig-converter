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
use toTwig\NeedAttributeExtraction;
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
                $value = $this->sanitizeExpression($string, x: $x, terminators: [' ', "\n", "\t", '"', "'"]);
                $pairs[trim($key)] = trim($value);
                $key = '';
                $is_key = true;
            }
        }
        $key = trim($key);
        $value = $this->sanitizeExpression($string, x: $x, terminators: [' ', "\n", "\t"]);
        if ($key !== '') {
            $pairs[trim($key)] = trim($value);
        }
        //var_dump($string, $pairs);

        return $pairs;
    }


    private const TOKENS = [
        ' ',
        '++',
        '--',
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
        'AND',
        'OR',
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
        '->',
        '~',
        '=',
    ];

    private const DELIM_MAP = [
        '!' => 'not',
        '&&' => 'and',
        '||' => 'or',
        'AND' => 'and',
        'OR' => 'or',
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
    private static function isString(string $string): bool {
        return in_array($string[0] ?? '', ['"', "'"], true);
    }
    private static function isVar(string $string): bool {
        return ($string[0] ?? '') === '$';
    }
    private const STATE_SPLIT_NONE = 0;
    private const STATE_SPLIT_IS = 1;
    private const STATE_SPLIT_NOT = 2;
    private const STATE_SPLIT_WAITING_BY = 4;
    private function splitSanitize(string $string): string {
        $assign_var = null;
        $assign_opt = false;

        $pairs = [];
        $prevValue = '';
        $prevValueUntrimmed = '';
        $prevDelim = '';
        for ($x = 0; $x < strlen($string); $x++) {
            [$value, $delim, $valueUntrimmed] = $this->parseValue($string, $x, self::TOKENS);
            if ($prevDelim === '.' && ($prevValueUntrimmed[0] ?? '') === '$' && ($valueUntrimmed[0] ?? '') === '$') {
                $last = array_pop($pairs);
                $last[1] = $prevDelim = '->';
                $pairs []= $last;
            }
            if (trim($value) === '' && $prevDelim === ' ') {
                array_pop($pairs);
                $value = $prevValue;
            } elseif ($prevDelim === ' ' && $value[0] === '(') {
                array_pop($pairs);
                $value = $prevValue.$value;
            }
            $pairs []= [$value, $delim];
            $prevValue = $value;
            $prevValueUntrimmed = $valueUntrimmed;
            $prevDelim = $delim;
        }

        $final = [];
        $prevDelim = '';
        $prevValue = '';
        $state = self::STATE_SPLIT_NONE;
        $keyword = null;
        $op = null;
        $by = null;
        $finalDelim = null;
        foreach ($pairs as [$value, $delim]) {
            $delimNew = self::DELIM_MAP[trim($delim)] ?? $delim;

            if ($value === 'is' && $state === self::STATE_SPLIT_NONE && in_array($prevValue, ['index', 'iteration', 'first', 'last'], true)) {
                $keyword = $final[count($final)-2];
                array_pop($final);
                array_pop($final);
                $state = self::STATE_SPLIT_IS;
                continue;
            } elseif ($value === 'not' && $state === self::STATE_SPLIT_IS) {
                $state |= self::STATE_SPLIT_NOT;
                continue;
            } elseif ($state !== 0 && $op === null && in_array($value, ['even', 'odd', 'div'], true)) {
                $op = $value;
                $finalDelim = $delimNew;
                continue;
            } elseif ($state !== 0 && $op !== null && $value === 'by') {
                $state |= self::STATE_SPLIT_WAITING_BY;
                continue;
            } elseif ($state & self::STATE_SPLIT_WAITING_BY) {
                $by = $this->sanitizeValue($value);
                $finalDelim = $delimNew;
                continue;
            } elseif ($state !== 0) {
                $operator = $state & self::STATE_SPLIT_NOT ? "is not $op" : "is $op";
    
                $outBy = $by ? "($keyword / $by)" : $keyword;
                $out = match ($operator) {
                    'is not odd' => "($outBy % 2) is not same as(0)",
                    'is not even' => "($outBy % 2) is same as(0)",
                    'is odd' => "($outBy % 2) is same as(0)",
                    'is even' => "($outBy % 2) is not same as (0)",
    
                    'is not div' => "($keyword % $by) is not same as(0)",
                    'is div' => "($keyword % $by) is same as(0)",
                };
                $final []= $out;
                $final []= $finalDelim;
                $prevValue = $out; 
                $prevDelim = $finalDelim;
                $state = self::STATE_SPLIT_NONE;
                $keyword = null;
                $op = null;
                $by = null;
            }

            $delimCheck = trim($delim);
            if ($prevDelim === '->') {
                array_pop($final);
                array_pop($final);
                if ($value[0] === '$') {
                    $value = "attribute($prevValue, $value)";
                } else {
                    $value = $prevValue.'.'.$value;
                }
            }
            if ($prevDelim === '.') {
                array_pop($final);
                array_pop($final);

                if ((
                    self::isString($prevValue)
                    || self::isString($value)
                ) || (
                    self::isVar($prevValue)
                    && self::isVar($value)
                )) {
                    $final []= $this->sanitizeValue($prevValue);
                    $final []= ' ~ ';
                } else {
                    if (is_numeric($value[0]) && !is_numeric($prevValue)) {
                        $value = "attribute($prevValue, \"$value\")";
                    } else {
                        $value = $prevValue.'.'.$value;
                    }
                }
            }

            if ($delimNew === '=' || $delimNew === '++' || $delimNew === '--') {
                if (($final[0]??'') === '' && ($final[1]??'') === ' ? ') {
                    $assign_opt = true;
                    array_shift($final);
                    array_shift($final);
                } elseif ($delimNew === '=' && count($final) === 2 && trim($final[1]) === '') {
                    throw new NeedAttributeExtraction();
                }
                $assign_var = trim(implode('', $final).$this->sanitizeValue($value));
                if ($delimNew !== '=') {
                    if ($assign_opt) {
                        $assign_opt = false;
                        $tmp = "($assign_var|default(0))";
                    } else {
                        $tmp = $assign_var;
                    }
                }
                $final = match ($delimNew) {
                    '=' => [],
                    '++' => ["$tmp + 1"],
                    '--' => ["$tmp - 1"],
                };
                continue;
            }
            if ($delimNew !== ' ' && $delimNew !== '' && $delimNew !== '.' && $delimNew !== '++' && $delimNew !== '--') {
                $delimNew = " $delimNew ";
            }
            $final []= $this->sanitizeValue($value);
            $final []= $delimNew;
            $prevDelim = $delimCheck;
            $prevValue = $value;
        }
        if ($state !== 0) {
            $operator = $state & self::STATE_SPLIT_NOT ? "is not $op" : "is $op";

            $outBy = $by ? "($keyword / $by)" : $keyword;
            $out = match ($operator) {
                'is not odd' => "($outBy % 2) is not same as(0)",
                'is not even' => "($outBy % 2) is same as(0)",
                'is odd' => "($outBy % 2) is same as(0)",
                'is even' => "($outBy % 2) is not same as (0)",

                'is not div' => "($keyword % $by) is not same as(0)",
                'is div' => "($keyword % $by) is same as(0)",
            };
            $final []= $out;
            $final []= $finalDelim;
            $state = self::STATE_SPLIT_NONE;
        }
        if ($assign_var) {
            if ($assign_opt) {
                return "set $assign_var = $assign_var|default(".implode('', $final).')';
            } else {
                return "set $assign_var = ".implode('', $final);
            }
        }
        return implode('', $final);
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
                    if ($has_delim && !$stack && !(trim($d) === '' && trim($value) === '')) {
                        $x += strlen($d)-1;
                        return [trim($value), $d, $value];
                    }
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
                    return [trim($value), $d, $value];
                } else {
                    $value .= $cur;
                }
            }
        }
        return [trim($value), '', $value];
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
            if ($string === 'index') {
                return 'loop.index0';
            }
            if ($string === 'iteration') {
                return 'loop.index';
            }
            if ($string === 'first') {
                return 'loop.first';
            }
            if ($string === 'last') {
                return 'loop.last';
            }
            if (!in_array($string, ["true", "false", "and", "or", "not", "null", "TRUE", "FALSE", "NULL"])) {
                return "\"" . $string . "\"";
            }
            $string = strtolower($string);
        }

        $string = $this->convertFunctionArguments($string);
        $string = $this->convertArrayKey($string);
        $string = $this->convertIdentical($string);

        $string = ltrim($string, '$');

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
        $final = '';

        [$function_name] = $this->parseValue($string, $x, ['(']);
        if ($x++ === strlen($string)) {
            return $string;
        }
        
        $function_name = trim($function_name);
        
        if (($function_name[0] ?? '') === '$' && !str_contains($function_name, '|')
            && !str_contains($function_name, '>')
            && !str_contains($function_name, '.')
            && substr($function_name, 0, -1) !== '$gmdate') {
            $string = "call_user_func($function_name, ".substr($string, $x);
            return $this->convertFunctionArguments($string);
        }
        if ($function_name === '') {
            [$final] = $this->parseValue($string, $x, [')']);
            $trailer = trim(substr($string, $x+1));
            if ($trailer !== '') {
                throw new AssertionError("Trailer is not empty: $trailer");
            }
            return '('.$this->sanitizeExpression($final).')';
        }

        do {
            $args = [];
            while ($x < strlen($string)) {
                [$temp, $delim] = $this->parseValue($string, $x, [',', ')']);
                if ($function_name !== 'array') {
                    $temp = $this->sanitizeExpression($temp);
                }
                $args []= $temp;

                $x++;
                if ($delim === ',') {
                    // OK
                } elseif ($delim === ')') {
                    if ($function_name === 'array') {
                        $final .= '['.implode(',', $args).']';
                    } elseif ($function_name === 'date') {
                        if (count($args) > 2) {
                            throw new AssertionError();
                        }
                        $timestamp = $args[1] ?? '"now"';
                        $final .= $timestamp."|date({$args[0]})";
                    } elseif ($function_name === 'defined') {
                        if (count($args) > 1) {
                            throw new AssertionError();
                        }
                        $final .= trim($args[0], '"')." is defined";
                    } elseif ($function_name === 'dump') {
                        foreach ($args as &$arg) {
                            $arg = trim($arg);
                            if (preg_match("/^var\[['\"](\w+)['\"]\]$/", $arg, $matches)) {
                                $arg = $matches[1];
                            }
                        }
                        $final .= $function_name.'('.implode(',', $args).')';
                    } else {
                        $final .= $function_name.'('.implode(',', $args).')';
                    }
                    $args = [];
                    break;
                } else {
                    throw new AssertionError("Unreachable!");
                }
            }

            [$function_name] = $this->parseValue($string, $x, ['(']);
            $function_name = trim($function_name);
            if ($x++ === strlen($string)) {
                $final .= $function_name;
            }
        } while ($x < strlen($string));
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
        $is_list = true;
        $arr = [];
        $list = [];
        while ($x < strlen($string)) {
            $key = $this->sanitizeExpression($this->parseValue($string, $x, ['=>', ',', ']'])[0]);
            $cur = $string[$x++] ?? '';
            if ($cur === '>') {
                $value = $this->sanitizeExpression($this->parseValue($string, $x, [',', ']'])[0]);
                $x++;
                $is_list = false;
            } elseif ($cur === ',' || $cur === ']' || $cur === '') {
                $value = $key;
                $key = $k++;
            } else {
                throw new AssertionError('Unreachable');
            }
            if (!preg_match('/^\d+|((\'|")[_\w0]+(\'|"))$/', $key)) {
                $key = "($key)";
            }
            $arr []= "$key: $value";
            $list []= "$value";
        }
        if (!$arr) {
            return '[]';
        }
        if ($is_list) {
            return '['.implode(', ', $list).']';
        }
        return '{'.implode(', ', $arr).'}';
    }

    private const STATE_FILTER = 0;
    private const STATE_ARGS = 1;
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
    protected function sanitizeExpression(string $string, bool $root = false, int &$x = 0, array $terminators = []): string
    {
        $final = '';
        while (1) {
            $final .= $this->parseValue($string, $x, ['|', ...$terminators])[0];
            $x++;
            if (($string[$x] ?? '') === '|') {
                $final .= '||';
                $x++;
            } else {
                break;
            }
        }
        $final = $this->splitSanitize($final);
        $final = [$final];
        
        $trailer = '';
        $last_filter = '';
        if (($string[$x-1] ?? '') === '|') {
            $append_filter = function (string &$filter_name, array &$filter_args) use (&$final, &$last_filter) {
                $filter_name = FilterNameMap::getConvertedFilterName(ltrim($filter_name, '@'));
                if ($filter_name === 'replace') {
                    $last_filter = $filter_name.($filter_args ? '({'.implode(':', $filter_args).'})' : '');
                } else {
                    $last_filter = $filter_name.($filter_args ? '('.implode(', ', $filter_args).')' : '');
                }
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
                    } elseif ($cur === ' ' || $cur === "\n" || $cur === "\r") {
                        $append_filter($filter_name, $filter_args);
                        break;
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
            if (!$terminators && trim(substr($string, $x)) !== '') {
                $trailer = ' '.$this->sanitizeExpression($string, $root, $x);
            }
        } else {
            if (!$terminators) {
                $trailer = $this->splitSanitize(substr($string, $x));
            } else {
                $x--;
            }
        }
        $needs_raw = false;
        if ($root) {
            if (count($final) === 1) {
                $x = 0;
                [$parsed] = $this->parseValue($final[0], $x, ['|', ...self::TOKENS, ...$terminators]);
                if ($x === strlen($final[0]) && $parsed[0] === '"') {
                    throw new \RuntimeException("Only one string expression: {$string}");
                }
            }
            if ($last_filter === 'esc' || $last_filter === 'escape' || $last_filter === 'escape("html")' || $last_filter === 'htmlspecialchars') {
                array_pop($final);
            } elseif ($last_filter !== 'raw') {
                $needs_raw = true;
            }
        }
        $expression = implode('', $final).$trailer;

        $expression = $this->convertIdentical($expression);
        
        if (!str_starts_with(trim($expression), 'set ')) {
            if ($needs_raw) {
                $expression = "($expression)|raw";
            }
        }
        return $expression;
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
    
    protected function getPregReplaceCallbackMatch(array $matches): string
    {
        if (!isset($matches[1]) && $matches[0]) {
            $match = $matches[0];
        } else {
            $match = $matches[1];
        }

        return $match;
    }
}
