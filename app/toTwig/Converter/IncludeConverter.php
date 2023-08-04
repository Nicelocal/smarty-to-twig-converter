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
class IncludeConverter extends ConverterAbstract
{
    protected string $name = 'include';
    protected string $description = 'Convert smarty include to twig include';
    protected int $priority = 100;

    protected string $pattern;
    protected string $string = '{% include :template :with :vars %}';
    protected string $attrName = 'file';


    private function getOptionalReplaceVariables(array $attr): string
    {
        $vars = [];
        foreach ($attr as $key => $value) {
            $vars[] = $this->sanitizeVariableName($key) . ": " . $value;
        }

        return '{' . implode(', ', $vars) . '}';
    }
    public function convert(TokenTag $content): TokenTag
    {
        return $content->replaceOpenTag(
            $this->name,
            function ($matches) use ($content) {
                $attr = $this->extractAttributes($matches);
                $replace = [];
                $replace['template'] = $this->convertFileExtension($attr[$this->attrName]);
                if (isset($attr['insert'])) {
                    unset($attr['insert']);
                }

                // If we have any other variables
                if (count($attr) > 1) {
                    $replace['with'] = 'with';
                    unset($attr[$this->attrName]); // We won't need in vars

                    $replace['vars'] = $this->getOptionalReplaceVariables($attr);
                }
                return $this->replaceNamedArguments($this->string, $replace);
            }
        );
    }
}
