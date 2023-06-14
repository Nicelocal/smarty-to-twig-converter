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
class StripConverter extends ConverterAbstract
{
    protected string $name = 'strip';
    protected string $description = 'Converts Smarty strip into Twig';
    protected int $priority = 100;

    public function convert(TokenTag $content): TokenTag
    {
        return $content->replaceOpenTag(
            'strip',
            fn () => '{% apply spaceless %}'
        )->replaceCloseTag(
            'strip',
            'endapply'
        );
    }
}
