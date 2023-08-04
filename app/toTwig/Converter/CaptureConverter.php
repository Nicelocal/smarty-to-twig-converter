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
        $content = $content->replaceOpenTag(
            'capture',
            function ($matches) {
                $attr = $this->extractAttributes($matches);
                if (isset($attr['name'])) {
                    $attr['name'] = $this->sanitizeVariableName($attr['name']);
                    $string = '{% set __capture_:name %}';
                } elseif (isset($attr['append'])) {
                    $attr['append'] = $this->sanitizeVariableName($attr['append']);
                    throw new AssertionError("Can't append!");
                } elseif (isset($attr['assign'])) {
                    $attr['assign'] = $this->sanitizeVariableName($attr['assign']);
                    $string = '{% set :assign %}';
                } else {
                    throw new AssertionError("Unreachable!");
                }

                return $this->replaceNamedArguments($string, $attr);
            }
        )->replaceCloseTag(
            'capture',
            'endset'
        );
        return $content->replace(str_replace('quicky.capture.', '__capture_', $content->content));
    }
}
