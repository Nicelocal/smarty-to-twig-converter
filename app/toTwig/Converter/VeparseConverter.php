<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace toTwig\Converter;

use toTwig\SourceConverter\Token\TokenTag;

/**
 * Class VeparseConverter
 */
class VeparseConverter extends ConverterAbstract
{
    protected string $name = 'veparse';
    protected string $description = 'Convert veparse to twig';
    protected int $priority = 20;

    public function convert(TokenTag $content): TokenTag
    {
        $content = $this->replaceVeparse($content);
        $content = $this->replaceEndVeparse($content);

        return $content;
    }

    private function replaceVeparse(TokenTag $content): TokenTag
    {
        return $content->replaceOpenTag(
            'veparse',
            function ($matches) {
                $attr = $this->extractAttributes($matches);

                $vars = [];
                foreach ($attr as $key => $value) {
                    $vars[$key] = $key . '=' . $value;
                }

                $replace['vars'] = implode(' ', $vars);
                $string = $this->replaceNamedArguments('veparse :vars', $replace);

                return $string;
            },
        );
    }

    private function replaceEndVeparse(TokenTag $content): TokenTag
    {
        return $content->replaceCloseTag('verpase', 'endveparse');
    }

}
