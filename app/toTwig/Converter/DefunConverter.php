<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace toTwig\Converter;

use toTwig\SourceConverter\Token\TokenTag;

/**
 * Class DefunConverter
 *
 * @package toTwig\Converter
 */
class DefunConverter extends ConverterAbstract
{
    protected string $name = 'defun';
    protected string $description = "Convert Smarty2 defun to macro";
    protected int $priority = 50;

    //list of arguments passed to macro
    private ?string $arguments = null;

    //string to replace smarty call to macro
    private string $twigCallToMacro = '{% import _self as self %}{{ self.:macroName(:arguments) }}';
    private ?string $macroName = null;


    public function convert(TokenTag $content): TokenTag
    {
        $content = $this->replaceOpeningTag($content);
        $content = $this->replaceCallToMacro($content);
        $content = $this->replaceClosingTag($content);

        return $content;
    }

    private function replaceOpeningTag(TokenTag $content): TokenTag
    {
        $string = '{% macro :macroName(:parameters) %}';

        return $content->replaceOpenTag(
            'defun',
            function ($matches) use ($string) {
                $attr = $this->getAttributes($matches);
                $this->macroName = $this->sanitizeVariableName($attr['name']);
                $parameters = $this->getParameters($attr);
                $this->setArguments($attr);
                return $this->replaceNamedArguments(
                    $string,
                    ['macroName' => $this->macroName, 'parameters' => $parameters]
                );
            },
            $content
        );
    }

    private function replaceCallToMacro(TokenTag $content): TokenTag
    {
        return $content->replaceOpenTag(
            'fun',
            function ($matches) {
                $attr = $this->getAttributes($matches);
                $macroName = $this->sanitizeVariableName($attr['name']);

                // we have to use local macro variables as arguments passed to the nested macro
                $parameters = $this->getParameters($attr);
                return $this->replaceNamedArguments(
                    $this->twigCallToMacro,
                    ['macroName' => $macroName, 'arguments' => $parameters]
                );
            },
            $content
        );
    }

    private function replaceClosingTag(TokenTag $content): TokenTag
    {
        return $content->replaceCloseTag(
            'defun',
            $this->replaceNamedArguments(
                "{% endmacro %}" . $this->twigCallToMacro,
                ['macroName' => $this->macroName, 'arguments' => $this->arguments]
            ),
            true
        );
    }

    /**
     * Extract parameters from attributes. Defun uses all attributes, except name as parameters.
     */
    private function getParameters(array $attr): string
    {
        $parameters = '';
        foreach ($attr as $parameterName => $variableName) {
            if ($parameterName == 'name') {
                continue;
            }
            $parameters = $this->concatVariablesNames($parameters, $parameterName);
        }

        return $parameters;
    }

    /**
     * Similar to parameters, but we use variables defined outside macros
     */
    private function setArguments(array $attr): void
    {
        $arguments = '';
        foreach ($attr as $parameterName => $variableName) {
            if ($parameterName == 'name' || $parameterName == 'defun') {
                continue;
            }
            $arguments = $this->concatVariablesNames($arguments, $variableName);
        }

        $this->arguments = $arguments;
    }

    private function concatVariablesNames(string $variable, string $variableName): string
    {
        if ($variable == '') {
            $variable .= $variableName;
        } else {
            $variable .= ', ' . $variableName;
        }

        return $variable;
    }
}
