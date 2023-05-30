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

use toTwig\SourceConverter\Token\TokenTag;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
class UseConverter extends ConverterAbstract
{
    protected string $name = 'use';
    protected string $description = 'Convert smarty use/useif to twig';
    protected int $priority = 100;

    public function convert(TokenTag $content): TokenTag
    {
        $content = $this->convertTag($content, false);
        $content = $this->convertTag($content, true);
        return $content->replaceCloseTag('useif', 'endif')
            ->replaceCloseTag('use', '', true);
    }

    private function convertTag(TokenTag $content, bool $addIf): TokenTag {
        return $content->replaceOpenTag(
            $addIf ? 'useif' : 'use',
            function ($matches) use ($addIf) {
                [$value, $key] = $this->splitParsing($matches, 'as');
                $value = $this->sanitizeExpression($value);

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
            }
        );
    }
}
