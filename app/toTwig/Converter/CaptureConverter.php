<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace toTwig\Converter;

use AssertionError;
use toTwig\SourceConverter\Token\TokenTag;

/**
 * Class CaptureConverter
 */
class CaptureConverter extends ConverterAbstract
{
    protected string $name = 'capture';
    protected string $description = 'Converts Smarty Capture into Twig';
    protected int $priority = 100;

    public function convert(TokenTag $content): TokenTag
    {
        return $content->replaceOpenTag('capture',
            function ($matches) {
                $attr = $this->getAttributes($matches);
                if (isset($attr['name'])) {
                    $attr['name'] = $this->sanitizeVariableName($attr['name']);
                    $string = '{% capture name = ":name" %}';
                } elseif (isset($attr['append'])) {
                    $attr['append'] = $this->sanitizeVariableName($attr['append']);
                    $string = '{% capture append = ":append" %}';
                } elseif (isset($attr['assign'])) {
                    $attr['assign'] = $this->sanitizeVariableName($attr['assign']);
                    $string = '{% capture assign = ":assign" %}';
                } else {
                    throw new AssertionError("Unreachable!");
                }

                return $this->replaceNamedArguments($string, $attr);
            }
        )->replaceCloseTag(
            'capture',
            'endcapture'
        );
    }
}
