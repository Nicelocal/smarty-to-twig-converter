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
 * @author sankara <sankar.suda@gmail.com>
 */
class MiscConverter extends ConverterAbstract
{
    protected string $name = 'misc';
    protected string $description = 'Convert smarty general tags like {ldelim} {rdelim} {literal} {strip}';
    protected int $priority = 52;

    // Lookup tables for performing some token
    // replacements not addressed in the grammar.
    private const OPEN_REPLACE = [
        'ldelim' => '',
        'rdelim' => '',
        'literal' => '{# literal #}',
        'strip' => '{% spaceless %}',
    ];
    private const CLOSE_REPLACE = [
        'literal' => '{# /literal #}',
        'strip' => '{% endspaceless %}',
    ];

    public function convert(TokenTag $content): TokenTag
    {
        foreach (self::OPEN_REPLACE as $in => $out) {
            $content = $content->replaceOpenTag($in, fn () => $out);
        }
        foreach (self::CLOSE_REPLACE as $in => $out) {
            $content = $content->replaceCloseTag($in, $out);
        }

        return $content;
    }
}
