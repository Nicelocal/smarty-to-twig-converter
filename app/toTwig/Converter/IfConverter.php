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
class IfConverter extends ConverterAbstract
{
    protected string $name = 'if';
    protected string $description = 'Convert smarty if/else/elseif to twig';
    protected int $priority = 50;

    public function convert(TokenTag $content): TokenTag
    {
        $content = $this->replace('if', $content);
        $content = $this->replace('elseif', $content);
        return $content->replaceCloseTag('if', 'endif')
            ->replaceOpenTag('else', fn () => '{% else %}')
            ->replaceCloseTag('else', '{% else %}', true);
    }
    /**
     * Helper for replacing starting tag patterns with additional checks and
     * converting of the arguments coming with those tags
     */
    private function replace(string $pattern, TokenTag $content): TokenTag
    {
        return $content->replaceOpenTag(
            $pattern,
            function ($arg) use ($pattern) {
                $arg = $this->sanitizeExpression($arg);
                return "{% $pattern $arg %}";
            }
        );
    }
}
