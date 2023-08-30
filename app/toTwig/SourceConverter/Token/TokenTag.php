<?php

/**
 * This file is part of the PHP ST utility.
 *
 * (c) Sankar suda <sankar.suda@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace toTwig\SourceConverter\Token;

use SebastianBergmann\Diff\Differ;
use toTwig\ConversionResult;
use toTwig\Converter\ConverterAbstract;
use toTwig\SourceConverter\Token;

final class TokenTag extends Token {
    public function __construct(public readonly string $content, public readonly bool $converted)
    {
    }
    public function replaceOpenTag(string $openTag, callable $replacer, bool $converted = true): self {
        if ($this->converted) {
            return $this;
        }
        $pos = strpos($this->content, $openTag);
        if ($pos === false
            || trim(substr($this->content, 0, $pos)) !== ''
            || !in_array(substr($this->content, $pos+strlen($openTag), 1), ['', ' ', "\t", "\r", "\n"], true)
        ) {
            return $this;
        }
        $pos = strpos($this->content, ' ', $pos) ?: ($pos + strlen($openTag));
        $args = substr($this->content, $pos);
        $result = $replacer($args);
        if ($args === $result) {
            return $this;
        }
        $token = new self($result, $converted);
        $token->prev = $this->prev;
        $token->next = $this->next;
        return $token;
    }
    public function replaceCloseTag(string $closeTag, string $replacement, bool $custom = false): self {
        if ($this->converted) {
            return $this;
        }

        $pos = strpos($this->content, $closeTag);
        if ($pos === false
            || trim(substr($this->content, 0, $pos)) !== '/'
            || trim(substr($this->content, $pos+strlen($closeTag))) !== ''
        ) {
            return $this;
        }
        if (!$custom) {
            $replacement = '{% '.$replacement.' %}';
        }
        $token = new self($replacement, true);
        $token->prev = $this->prev;
        $token->next = $this->next;
        return $token;
    }
    public function replace(string $replacement, ?bool $converted = null): self {
        if ($this->converted || $replacement === $this->content) {
            return $this;
        }

        $token = new self($replacement, $converted ?? $this->converted);
        $token->prev = $this->prev;
        $token->next = $this->next;
        return $token;
    }
    public function __toString(): string
    {
        if (!$this->converted) {
            throw new \RuntimeException("Can't serialize smarty tag!");
        }
        return $this->content;
    }
}