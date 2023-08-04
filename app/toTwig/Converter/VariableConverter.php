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

use AssertionError;
use toTwig\NeedAttributeExtraction;
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
        if ($content->converted) {
            return $content;
        }
        if (ltrim($content->content)[0] === '/') {
            throw new AssertionError("Unrecognized close tag ".$content->content);
        }
        try {
            $sanitized = $this->sanitizeExpression($content->content, true);
        } catch (NeedAttributeExtraction) {
            [$tag, $args] = explode(' ', trim($content->content), 2);
            $final = [];
            foreach ($this->extractAttributes($args) as $key => $value) {
                $final []= '"'.$key.'": '.$value;
            }
            $sanitized = $tag.'({'.implode(', ', $final).'})';
        }
        if (str_starts_with($sanitized, 'set ')) {
            $sanitized = '{% '.$sanitized.' %}';
        } else {
            $sanitized = '{{ '.$sanitized.' }}';
        }
        return $content->replace(
            $sanitized,
            true
        );
    }
}
