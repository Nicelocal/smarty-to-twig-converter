<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace toTwig\Converter;

/**
 * Class MathConverter
 */
class MathConverter extends ConverterAbstract
{
    protected string $name = 'math';
    protected string $description = "Convert smarty math to twig";
    protected int $priority = 1000;

    public function convert(string $content): string
    {
        $pattern = $this->getOpeningTagPattern('math');

        return preg_replace_callback(
            $pattern,
            function ($matches) {
                /**
                 * $matches contains an array of strings.
                 *
                 * $matches[0] contains a string with full matched tag i.e.'[{math equation="x + y" x=1 y=2}]'
                 * $matches[1] should contain a string with all attributes passed to a tag i.e.
                 * 'equation="x + y" x=1 y=2}'
                 */
                $attr = $this->getAttributes($matches);
                $vars = $attr;
                unset($vars['equation']);
                unset($vars['format']);
                if (isset($attr['format'])) {
                    $vars['format_string'] = $attr['format'];
                }
                $replace['assign'] = $this->getAssignAttribute($attr);
                $string = $this->getStringForPregReplace($replace);
                $equationTemplate = $this->translateEquationTemplate($attr, $vars);
                $formattedEquation = $this->mathEquationSprintf($equationTemplate, $vars);
                $replace['equation'] = $formattedEquation;
                $string = $this->replaceNamedArguments($string, $replace);

                return str_replace($matches[0], $string, $matches[0]);
            },
            $content
        );
    }

    /**
     * Get string with pattern to be replaced by preg_replace.
     */
    private function getStringForPregReplace(array $replace): string
    {
        if ($replace['assign']) {
            $string = '{% set :assign = :equation %}';
        } else {
            $string = '{{ :equation }}';
        }

        return $string;
    }

    private function translateEquationTemplate(array $attr, array $vars): string
    {
        $equationTemplate = $this->sanitizeVariableName($attr['equation']);
        $equationTemplate = $this->mathAllVariationsOfRoundSprintf($equationTemplate);
        $equationTemplate = $this->mathAbsSprintf($equationTemplate);
        $equationTemplate = $this->mathPowSprintf($equationTemplate);
        $equationTemplate = $this->mathRandSprintf($equationTemplate);
        if (isset($attr['format'])) {
            $equationTemplate = $this->mathFormatSprintf($equationTemplate, $vars);
        }

        return $equationTemplate;
    }

    /**
     * Replace named args in string
     */
    protected function mathEquationSprintf(string $string, array $args): string
    {
        $pattern = '/([a-zA-Z0-9]+)/';

        return preg_replace_callback(
            $pattern,
            function ($matches) use ($args) {
                if (isset($args[$matches[1]])) {
                    $replace = $this->sanitizeExpression($args[$matches[1]]);

                    return str_replace($matches[0], $replace, $matches[0]);
                } else {
                    return $matches[0];
                }
            },
            $string
        );
    }

    /**
     * Replace variations of round. Order in which functions are called is important.
     */
    protected function mathAllVariationsOfRoundSprintf(string $equationTemplate): string
    {
        $equationTemplate = $this->mathRoundSprintf($equationTemplate);
        $equationTemplate = $this->mathCeilSprintf($equationTemplate);
        $equationTemplate = $this->mathFloorSprintf($equationTemplate);

        return $equationTemplate;
    }

    /**
     * Replace abs function in equation
     */
    protected function mathAbsSprintf(string $string): string
    {
        // [{math equation="abs(x)" x=-1}]
        $pattern = '/abs\(\b\s*([^{}]+)?\)/';

        return preg_replace_callback(
            $pattern,
            function ($matches) {
                if (isset($matches[1])) {
                    return str_replace($matches[0], '(' . $matches[1] . ') | abs', $matches[0]);
                }
            },
            $string
        );
    }

    /**
     * Replace ceil function in equation
     */
    protected function mathCeilSprintf(string $string): string
    {
        // [{math equation="ceil(x)" x=3.4}]
        $pattern = '/ceil\(\b\s*([^{}]+)?\)/';

        return preg_replace_callback(
            $pattern,
            function ($matches) {
                if (isset($matches[1])) {
                    return str_replace($matches[0], '(' . $matches[1] . ') | round(0, \'ceil\')', $matches[0]);
                }
            },
            $string
        );
    }

    /**
     * Replace floor function in equation
     */
    protected function mathFloorSprintf(string $string): string
    {
        // [{math equation="floor(x)" x=3.4}]
        $pattern = '/floor\(\b\s*([^{}]+)?\)/';

        return preg_replace_callback(
            $pattern,
            function ($matches) {
                if (isset($matches[1])) {
                    return str_replace($matches[0], '(' . $matches[1] . ') | round(0, \'floor\')', $matches[0]);
                }
            },
            $string
        );
    }

    /**
     * Replace pow function in equation
     */
    protected function mathPowSprintf(string $string): string
    {
        // [{math equation="pow(x,y)" x=2 y=3}]
        $pattern = '/pow\(\b\s*([^{)}]+)?\)/';

        return preg_replace_callback(
            $pattern,
            function ($matches) {
                if (isset($matches[1])) {
                    return str_replace($matches[0], 'pow(' . $matches[1] . ')', $matches[0]);
                }
            },
            $string
        );
    }

    /**
     * Replace rand function in equation
     */
    protected function mathRandSprintf(string $string): string
    {
        //[{math equation="rand(x, y)" x=2 y=3}]
        $pattern = '/rand\(\b\s*([^{)}]+)?\)/';

        return preg_replace_callback(
            $pattern,
            function ($matches) {
                if (isset($matches[1])) {
                    return str_replace($matches[0], 'random([' . $matches[1] . '])', $matches[0]);
                }
            },
            $string
        );
    }

    /**
     * Replace round function in equation
     */
    protected function mathRoundSprintf(string $string): string
    {
        //[{math equation="round(x)" x=3.4}]
        $pattern = '/round\(\b\s*([^{}]+)?\)/';

        return preg_replace_callback(
            $pattern,
            function ($matches) {
                if (isset($matches[1])) {
                    return str_replace($matches[0], '(' . $matches[1] . ') | round', $matches[0]);
                }
            },
            $string
        );
    }

    /**
     * Replace format function in equation
     */
    protected function mathFormatSprintf(string $string, array $args): string
    {
        // [{math equation="x + y" x=1 y=2 format="%.2f"}]
        $pattern = '/format\(\b\s*([^{}]+)?\)/';
        $string .= ' | format(format_string)';

        return preg_replace_callback(
            $pattern,
            function ($matches) use ($args) {
                if (isset($matches[1])) {
                    return str_replace($matches[1], $args[$matches[1]], $matches[0]);
                }
            },
            $string
        );
    }

    private function getAssignAttribute(array $attr): string
    {
        $assign = '';
        if (isset($attr['assign'])) {
            $assign = $this->sanitizeVariableName($attr['assign']);
        }

        return $assign;
    }
}
