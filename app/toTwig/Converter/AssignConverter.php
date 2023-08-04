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

use toTwig\SourceConverter\Token;
use toTwig\SourceConverter\Token\TokenTag;


/**
 * @author sankara <sankar.suda@gmail.com>
 */
class AssignConverter extends ConverterAbstract
{
    protected string $name = 'assign';
    protected string $description = "Convert smarty {assign} to twig {% set foo = 'foo' %}";
    protected int $priority = 100;

    public function convert(TokenTag $content): TokenTag
    {
        return $content->replaceOpenTag($this->name, function ($attributes) {
                $attr = $this->extractAttributes($attributes);

                if (isset($attr['var'])) {
                    $key = $attr['var'];
                }

                if (isset($attr['value'])) {
                    $value = $attr['value'];
                }

                // Short-hand {assign "name" "Bob"}
                if (!isset($key)) {
                    $key = reset($attr);
                }

                if (!isset($value)) {
                    $value = next($attr);
                }

                $key = $this->sanitizeVariableName($key);
                return $this->replaceNamedArguments('{% set :key = :value %}', ['key' => $key, 'value' => $value]);
            }
        );
    }
}
