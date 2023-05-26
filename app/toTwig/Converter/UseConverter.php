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

/**
 * @author sankar <sankar.suda@gmail.com>
 */
class UseConverter extends ConverterAbstract
{
    protected string $name = 'use';
    protected string $description = 'Convert smarty use/useif to twig';
    protected int $priority = 50;

    public function convert(string $content): string
    {
        $content = $this->replaceUse($content);
        $content = $this->replaceUseIf($content);
        // Replace smarty {/if} to its twig analogue
        $content = preg_replace($this->getClosingTagPattern('useif'), "{% endif %}", $content);
        // Remove smarty {/use}
        $content = preg_replace($this->getOpeningTagPattern('use'), '', $content);

        return $content;
    }

    private function generateAssignment(string $pattern, string $content, bool $addIf): string {
        return preg_replace_callback(
            $pattern,
            function ($matches) use ($addIf) {
                [$value, $key] = $this->splitParsing($matches[1], 'as');
                $value = $this->sanitizeValue($value);

                $keys = explode(',', $key);
                if (count($keys) === 1) {
                    $key = $this->sanitizeVariableName($key);
                    $final = "{% set $key = $value %}";
                    if ($addIf) {
                        $final .= "{% if $key %}";
                    }
                    return $final;
                }
                $key_temp = 'temp_'.bin2hex($key);
                $final = "{% set $key_temp = $value %}";
                if ($addIf) {
                    $final .= "{% if $key_temp %}";
                }
                foreach ($keys as $k => $key) {
                    $key = $this->sanitizeVariableName($key);
                    $final .= "{% set {$key} = {$key_temp}[{$k}] %}";
                }
                return $final;
            },
            $content
        );
    }
    /**
     * Replace smarty "use" tag to its twig analogue
     */
    private function replaceUse(string $content): string
    {
        return $this->generateAssignment($this->getOpeningTagPattern('use'), $content, false);
    }

    /**
     * Replace smarty "useif" tag to its twig analogue
     */
    private function replaceUseIf(string $content): string
    {
        return $this->generateAssignment($this->getOpeningTagPattern('use'), $content, true);
    }
}
