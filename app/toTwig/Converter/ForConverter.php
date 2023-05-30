<?php

/**
 * This file is part of the PHP ST utility.
 *
 * (c) Sankar suda <sankar.suda@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace toTwig\Converter;

use toTwig\SourceConverter\Token\TokenTag;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
class ForConverter extends ConverterAbstract
{
    protected string $name = 'for';
    protected string $description = 'Convert foreach/foreachelse to twig';
    protected int $priority = 50;

    public function convert(TokenTag $content): TokenTag
    {
        $content = $content->replaceCloseTag('foreach', 'endfor')
            ->replaceCloseTag('for', 'endfor')
            ->replaceOpenTag('foreachelse', fn () => '{% else %}');
        $content = $this->replaceForEach($content);
        $content = $this->replaceFor($content);

        $contentStr = $content->content;
        foreach ([
            'smarty\.foreach.*\.index' => 'loop.index0',
            'smarty\.foreach.*\.iteration' => 'loop.index',
            'smarty\.foreach.*\.first' => 'loop.first',
            'smarty\.foreach.*\.last' => 'loop.last',
        ] as $k => $v) {
            $contentStr = preg_replace('/' . $k . '/', $v, $contentStr);
        }

        return $content->replace($contentStr);
    }

    private function replaceForEach(TokenTag $content): TokenTag
    {
        return $content->replaceOpenTag(
            'foreach',
            function ($match) {
                if (preg_match("/(.*)(?:\bas\b)(.*)/i", $match, $mcs)) {
                    $replace = $this->getReplaceArgumentsForSmarty3($mcs);
                } else {
                    $replace = $this->getReplaceArgumentsForSmarty2($match);
                }
                $replace['from'] = $this->sanitizeExpression($replace['from']);
                return $this->replaceNamedArguments('{% for :key :item in :from %}', $replace);
            }
        );
    }

    private function replaceFor(TokenTag $content): TokenTag
    {
        return $content->replaceOpenTag(
            'for',
            function ($attrs) {
                $args = $this->extractAttributes($attrs);

                $args['value'] = $this->sanitizeVariableName($args['value']);
                $args['limit'] = $args['loop']-1;
                $args['step'] ??= 1;
                $args['start'] ??= 0;
                return $this->replaceNamedArguments(
                    '{% for :value in range(:start, :limit, :step) %}',
                    $args
                );
            }
        );
    }

    /**
     * Returns array of replace arguments for foreach function in smarty 3
     * For example:
     * {foreach $arrayVar as $itemVar}
     * or
     * {foreach $arrayVar as $keyVar=>$itemVar}
     */
    private function getReplaceArgumentsForSmarty3(array $mcs): array
    {
        $replace = [];
        /**
         * $pattern is supposed to detect key variable and value variable in structure like this:
         * [{foreach $arrayVar as $keyVar=>$itemVar}]
         **/
        if (preg_match("/(.*)\=\>(.*)/", $mcs[2], $match)) {
            if (!isset($replace['key'])) {
                $replace['key'] = '';
            }
            $replace['key'] .= $this->sanitizeVariableName($match[1]) . ',';
            $mcs[2] = $match[2];
        }
        $replace['item'] = $this->sanitizeVariableName($mcs[2]);
        $replace['from'] = $mcs[1];

        return $replace;
    }

    /**
     * Returns array of replace arguments for foreach function in smarty 2
     * For example:
     * {foreach from=$myArray key="myKey" item="myItem"}
     */
    private function getReplaceArgumentsForSmarty2(string $match): array
    {
        $attr = $this->getAttributes($match);

        if (isset($attr['key'])) {
            $replace['key'] = $this->sanitizeVariableName($attr['key']) . ',';
        }

        $replace['item'] = $this->sanitizeVariableName($attr['item'] ?? $attr['value']);
        $replace['from'] = $attr['from'];

        return $replace;
    }
}
