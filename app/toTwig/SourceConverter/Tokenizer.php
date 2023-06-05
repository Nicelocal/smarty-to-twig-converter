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
use toTwig\ConversionResult;
use toTwig\Converter\ConverterAbstract;
use toTwig\SourceConverter\Token\TokenComment;
use toTwig\SourceConverter\Token\TokenHtml;
use toTwig\SourceConverter\Token\TokenTag;

final class Tokenizer extends ConverterAbstract
{
    private const STATE_HTML = 0;
    private const STATE_COMMENT = 1;
    private const STATE_TAG = 2;
    private int $state = self::STATE_HTML;
    private int $offset = 0;
    public function __construct(private readonly string $content) {
    }
    public static function chainTokenize(string $content): Token {
        if ($content === '') {
            $token = new TokenHtml('');
            $token->next = null;
            $token->prev = null;
            return $token;
        }
        $me = new self($content);
        $first = $token = $me->next();
        $prev = null;
        do {
            if ($prev) {
                $token->prev = $prev;
                $prev->next = $token;
            } else {
                $token->prev = null;
            }
            $prev = $token;
        } while ($token = $me->next());
        $prev->next = null;
        return $first;
    }
    public function next(): ?Token {
        if ($this->offset >= strlen($this->content)) {
            return null;
        }
        if ($this->state === self::STATE_HTML) {
            $next_tag = strpos($this->content, '{{', $this->offset);
            $token = new TokenHtml(substr($this->content, $this->offset, $next_tag === false ? null : $next_tag - $this->offset));
            if ($next_tag !== false) {
                $this->offset = $next_tag+2;
                if ($this->content[$this->offset] === '*') {
                    $this->state = self::STATE_COMMENT;
                    $this->offset++;
                } else {
                    $this->state = self::STATE_TAG;
                }
            } else {
                $this->offset = strlen($this->content);
            }
            if ($token->content !== '' || $next_tag === false) {
                return $token;
            }
        }
        if ($this->state === self::STATE_COMMENT) {
            $content = '';
            $stack = 1;
            while ($stack && $this->offset < strlen($this->content)) {
                $next_tag = min(
                    strpos($this->content, '{{*', $this->offset) ?: PHP_INT_MAX,
                    strpos($this->content, '*}}', $this->offset) ?: PHP_INT_MAX
                );
                if ($next_tag === PHP_INT_MAX) {
                    $next_tag = false;
                    $content .= substr($this->content, $this->offset);
                } else {
                    $content .= substr($this->content, $this->offset, $next_tag - $this->offset);
                }
                if ($next_tag !== false) {
                    $this->offset = $next_tag+2;
                    if ($this->content[$this->offset++] === '*') {
                        $content .= ' ';
                        // Nested comment opener
                        $stack++;
                    } else {
                        $stack--;
                    }
                } else {
                    $this->offset = strlen($this->content);
                }
            }
            $this->state = self::STATE_HTML;
            return new TokenComment($content);
        }
        [$content] = $this->parseValue($this->content, $this->offset, ['}}']);
        $this->offset++;
        $this->state = self::STATE_HTML;
        return new TokenTag($content, false);
    }
}