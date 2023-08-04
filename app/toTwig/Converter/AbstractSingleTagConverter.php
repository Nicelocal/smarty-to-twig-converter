<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace toTwig\Converter;

use toTwig\SourceConverter\Token;
use toTwig\SourceConverter\Token\TokenTag;


/**
 * Class AbstractSingleTagConverter
 */
abstract class AbstractSingleTagConverter extends ConverterAbstract
{
    protected array $mandatoryFields = [];
    protected ?string $convertedName = null;

    /**
     * AbstractSingleTagConverter constructor.
     */
    public function __construct()
    {
        if (!$this->convertedName) {
            $this->convertedName = $this->name;
        }
    }

    public function convert(TokenTag $content): TokenTag
    {
        return $content->replaceOpenTag($this->name, function ($attributes) {
            $attributes = $this->extractAttributes($attributes);

            $arguments = [];
            foreach ($this->mandatoryFields as $mandatoryField) {
                $arguments[] = $attributes[$mandatoryField];
            }

            if ($this->convertArrayToAssocTwigArray($attributes, $this->mandatoryFields)) {
                $arguments[] = $this->convertArrayToAssocTwigArray($attributes, $this->mandatoryFields);
            }

            return sprintf("{{ %s(%s) }}", $this->convertedName, implode(", ", $arguments));
        });
    }
}
