<?php

declare(strict_types=1);

namespace Coduo\PHPMatcher\Factory;

use Coduo\PHPMatcher\Backtrace;
use Coduo\PHPMatcher\Factory;
use Coduo\PHPMatcher\Lexer;
use Coduo\PHPMatcher\Matcher;
use Coduo\PHPMatcher\Parser;
use function class_exists;

final class MatcherFactory implements Factory
{
    public function createMatcher() : Matcher
    {
        $matcherBacktrace = new Backtrace();

        return new Matcher($this->buildMatchers($this->buildParser($matcherBacktrace), $matcherBacktrace), $matcherBacktrace);
    }

    protected function buildMatchers(Parser $parser, Backtrace $backtrace) : Matcher\ChainMatcher
    {
        $scalarMatchers = $this->buildScalarMatchers($parser, $backtrace);
        $arrayMatcher = $this->buildArrayMatcher($scalarMatchers, $parser, $backtrace);

        // Matchers are registered in order of matching
        // 1) all scalars
        // 2) json/xml
        // 3) array
        // 4) or "||"
        // 5) full text

        $matchers = [$scalarMatchers];
        $matchers[] = new Matcher\JsonMatcher($arrayMatcher, $backtrace);

        if (class_exists('LSS\XML2Array')) {
            $matchers[] = new Matcher\XmlMatcher($arrayMatcher, $backtrace);
        }

        $matchers[] = $arrayMatcher;
        $matchers[] = new Matcher\OrMatcher($backtrace, $scalarMatchers);
        $matchers[] = new Matcher\TextMatcher($scalarMatchers, $backtrace, $parser);

        $chainMatcher = new Matcher\ChainMatcher(
            'all',
            $backtrace,
            $matchers
        );

        return $chainMatcher;
    }

    protected function buildArrayMatcher(Matcher\ChainMatcher $scalarMatchers, Parser $parser, Backtrace $backtrace) : Matcher\ArrayMatcher
    {
        $orMatcher = new Matcher\OrMatcher($backtrace, $scalarMatchers);

        return new Matcher\ArrayMatcher(
            new Matcher\ChainMatcher(
                'array',
                $backtrace,
                [
                    $orMatcher,
                    $scalarMatchers,
                    new Matcher\TextMatcher($scalarMatchers, $backtrace, $parser)
                ]
            ),
            $backtrace,
            $parser
        );
    }

    protected function buildScalarMatchers(Parser $parser, Backtrace $backtrace) : Matcher\ChainMatcher
    {
        return new Matcher\ChainMatcher(
            'scalars',
            $backtrace,
            [
                new Matcher\CallbackMatcher($backtrace),
                new Matcher\ExpressionMatcher($backtrace),
                new Matcher\NullMatcher($backtrace),
                new Matcher\StringMatcher($backtrace, $parser),
                new Matcher\IntegerMatcher($backtrace, $parser),
                new Matcher\BooleanMatcher($backtrace, $parser),
                new Matcher\DoubleMatcher($backtrace, $parser),
                new Matcher\NumberMatcher($backtrace, $parser),
                new Matcher\ScalarMatcher($backtrace),
                new Matcher\WildcardMatcher($backtrace),
                new Matcher\UuidMatcher($backtrace, $parser),
                new Matcher\JsonObjectMatcher($backtrace, $parser)
            ]
        );
    }

    protected function buildParser(Backtrace $backtrace) : Parser
    {
        return new Parser(new Lexer(), new Parser\ExpanderInitializer($backtrace));
    }
}
