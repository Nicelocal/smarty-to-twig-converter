<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace toTwig\Converter;

use toTwig\SourceConverter\Token;
use toTwig\SourceConverter\Token\TokenTag;


/**
 * Class BlockConverter
 */
class BlockConverter extends ConverterAbstract
{
    protected string $name = 'block';
    protected string $description = 'Convert block to twig';
    protected int $priority = 50;

    public function convert(TokenTag $content): TokenTag
    {
        $content = $this->replaceBlock($content);
        $content = $this->replaceEndBlock($content);
        $content = $this->replaceExtends($content);
        $content = $this->replaceParent($content);

        return $content;
    }

    private function replaceEndBlock(TokenTag $content): Token
    {
        return $content->replaceCloseTag('block', 'endblock');
    }

    private function replaceBlock(TokenTag $content): Token
    {
        return $content->replaceOpenTag('block', function ($matches) {
            $attr = $this->extractAttributes($matches);

            if (isset($attr['name'])) {
                $name = $attr['name'];
            } else {
                $name = array_shift($attr);
            }

            $block = "block ".trim($name, '"');
            if (isset($attr['prepend'])) {
                $block .= "{{ parent() }}";
            }

            return "{% $block %}";
        });
    }

    private function replaceExtends(TokenTag $content): Token
    {
        return $content->replaceOpenTag(
            'extends',
            function ($matches) {
                $attr = $this->extractAttributes($matches);
                $file = reset($attr);
                $file = $this->convertFileExtension($file);

                return "{% extends $file %}";
            }
        );
    }

    private function replaceParent(TokenTag $content): Token
    {
        return $content->replaceOpenTag(
            '$smarty.block.parent',
            function () {
                return "{{ parent() }}";
            }
        );
    }
}
