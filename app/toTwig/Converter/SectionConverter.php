<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace toTwig\Converter;

use toTwig\SourceConverter\Token\TokenTag;

/**
 * Class SectionConverter
 */
class SectionConverter extends ConverterAbstract
{
    protected string $name = 'section';
    protected string $description = 'Convert smarty {section} to twig {for}';
    protected int $priority = 20;

    // Lookup tables for performing some token
    // replacements not addressed in the grammar.
    private array $replacements = [
        '\$smarty\.section.*\.index' => 'loop.index0',
        '\$smarty\.section.*\.iteration' => 'loop.index',
        '\$smarty\.section.*\.first' => 'loop.first',
        '\$smarty\.section.*\.last' => 'loop.last',
    ];

    /**
     * Function converts smarty {section} tags to twig {for}
     */
    public function convert(TokenTag $content): TokenTag
    {
        $contentReplacedOpeningTag = $this->replaceSectionOpeningTag($content);
        $content = $this->replaceSectionClosingTag($contentReplacedOpeningTag);

        $contentStr = $content->content;
        foreach ($this->replacements as $k => $v) {
            $contentStr = preg_replace('/' . $k . '/', $v, $contentStr);
        }

        return new TokenTag($contentStr, $content->converted);
    }

    /**
     * Function converts opening tag of smarty {section} to twig {for}
     */
    private function replaceSectionOpeningTag(TokenTag $content): TokenTag
    {
        return $content->replaceOpenTag(
            'section',
            function ($matches) {
                $replacement = $this->getAttributes($matches);
                $replacement['start'] = isset($replacement['start']) ? $replacement['start'] : 0;
                $replacement['name'] = $this->sanitizeVariableName($replacement['name']);
                return $this->replaceNamedArguments('{% for :name in :start..:loop %}', $replacement);
            },
            $content
        );
    }

    /**
     * Function converts closing tag of smarty {section} to twig {for}
     */
    private function replaceSectionClosingTag(TokenTag $content): TokenTag
    {
        return $content->replaceCloseTag('section', 'endfor');
    }
}
