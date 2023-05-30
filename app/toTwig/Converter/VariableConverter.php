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
class VariableConverter extends ConverterAbstract
{
    protected string $name = 'variable';
    protected string $description = 'Convert smarty variable {$var.name} to twig {{ var.name }}';
    protected int $priority = 10;

    public function convert(TokenTag $content): TokenTag
    {
        return $content->replaceOpenTag(
            '',
            function ($args) {
                $match = $this->sanitizeExpression($args);
                return '{{ ' . $match . ' }}';
            },
            $content
        );
    }
}
