<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace toTwig\Converter;

use AssertionError;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\Print_;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Echo_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use PhpParser\PrettyPrinterAbstract;
use toTwig\SourceConverter\Token\TokenTag;

class PhpConverter extends ConverterAbstract
{
    protected string $name = 'php';
    protected string $description = "Convert smarty {php} to twig function {{ mailto() }}";
    protected int $priority = 1000;

    private Parser $parser;
    private PrettyPrinterAbstract $printer;
    public function __construct()
    {
        $this->parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
        $this->printer = new Standard();
    }

    public function convert(TokenTag $content): TokenTag
    {
        if (trim($content->content) === 'php') {
            $code = $content->next->content;
            $content->next = $content->next->next->next;

            $parsed = $this->parser->parse("<?php $code");
            if (count($parsed) !== 1) {
                throw new AssertionError("Only one statement is expected: $code");
            }
            $parsed = $parsed[0];
            if ($parsed instanceof Echo_ || $parsed instanceof Print_) {
                $parsed = $parsed->exprs;
                if (count($parsed) !== 1) {
                    throw new AssertionError("Only one expr is expected: $code");
                }
                $parsed = $parsed[0];
                if (!$parsed instanceof New_) {
                    throw new AssertionError("A new is expected: $code");
                }
                if (!$parsed->class instanceof Name) {
                    throw new AssertionError("A name is expected: $code");
                }
                $parsed = new FuncCall(
                    new Name('print_class'),
                    [
                        new Arg(new String_('\\'.$parsed->class->toString())),
                        ...$parsed->args
                    ]
                );
            } else {
                if (!$parsed instanceof Expression) {
                    throw new AssertionError("An expr is expected: $code");
                }
                $parsed = $parsed->expr;
                if (!$parsed instanceof FuncCall) {
                    throw new AssertionError("A function call is expected: $code");
                }
                if ($parsed->name->toString() !== 'print_r') {
                    throw new AssertionError("A print_r is expected: $code");
                }
                $parsed->name = new Name('dump');
            }
            $code = $this->printer->prettyPrintExpr($parsed);
            return $content->replaceOpenTag(
                'php',
                function () use ($code) {
                    return $code;
                },
                false
            );
        }
        return $content;
    }
}
