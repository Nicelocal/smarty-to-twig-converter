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

/**
 * @author sankara <sankar.suda@gmail.com>
 */
class AssignConverter extends ConverterAbstract
{
    protected string $name = 'assign';
    protected string $description = "Convert smarty {assign} to twig {% set foo = 'foo' %}";
    protected int $priority = 100;

    public function convert(string $content): string
    {
        $pattern = $this->getOpeningTagPattern('assign');
        $string = '{% set :key = :value %}';

        return preg_replace_callback(
            $pattern,
            function ($matches) use ($string) {
                $attr = $this->getAttributes($matches);

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
                $value = $this->sanitizeExpression($value);
                $string = $this->replaceNamedArguments($string, ['key' => $key, 'value' => $value]);

                return str_replace($matches[0], $string, $matches[0]);
            },
            $content
        );
    }
}
