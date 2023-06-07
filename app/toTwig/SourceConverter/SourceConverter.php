<?php

/**
 * This file is part of the PHP ST utility.
 *
 * (c) Sankar suda <sankar.suda@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace toTwig\SourceConverter;

use SebastianBergmann\Diff\Differ;
use SebastianBergmann\Diff\Output\UnifiedDiffOutputBuilder;
use toTwig\ConversionResult;
use toTwig\Converter\ConverterAbstract;
use toTwig\SourceConverter\Token\TokenComment;
use toTwig\SourceConverter\Token\TokenHtml;
use toTwig\SourceConverter\Token\TokenTag;

/**
 * Class SourceConverter
 */
class SourceConverter
{
    private Differ $diff;

    /**
     * SourceConverter constructor.
     */
    public function __construct()
    {
        $this->diff = new Differ(new UnifiedDiffOutputBuilder());
    }

    /**
     * @param string              $templateToConvert
     * @param bool                $diff
     * @param ConverterAbstract[] $converters
     *
     * @return ConversionResult
     */
    public function convertTemplate(string $templateToConvert, bool $diff, array $converters): ConversionResult
    {
        $result = new ConversionResult();
        $result->setOriginalTemplate($templateToConvert);

        $final = $this->convertInner($templateToConvert, $converters);
        $result->setConvertedTemplate($final);

        if ($templateToConvert !== $final) {
            $result->setDiff($this->stringDiff($templateToConvert, $final));
        }

        return $result;
    }
    private function convertInner(string $in, array $converters): string {
        $token = Tokenizer::chainTokenize($in);
        $final = '';
        do {
            if ($token instanceof TokenHtml) {
                $final .= (string) $token;
            } elseif ($token instanceof TokenComment) {
                $final .= (string) $token;
            } elseif ($token instanceof TokenTag) {
                foreach ($converters as $converter) {
                    $token = $converter->convert($token);
                }
                $final .= (string) $token;
            }
        } while ($token = $token->next);
        return $final;
    }

    protected function stringDiff(string $old, string $new): string
    {
        $diff = $this->diff->diff($old, $new);

        return implode(
            PHP_EOL,
            array_map(
                function ($string) {
                    $string = preg_replace('/^(\+){3}/', '<info>+++</info>', $string);
                    $string = preg_replace('/^(\+){1}/', '<info>+</info>', $string);

                    $string = preg_replace('/^(\-){3}/', '<error>---</error>', $string);
                    $string = preg_replace('/^(\-){1}/', '<error>-</error>', $string);

                    $string = str_repeat(' ', 6) . $string;

                    return $string;
                },
                explode(PHP_EOL, $diff)
            )
        );
    }
}
