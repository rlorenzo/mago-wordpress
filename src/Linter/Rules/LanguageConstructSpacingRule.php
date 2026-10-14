<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\ConstructSpacing;
use Rlorenzo\MagoWordPress\Internal\JoinedStatement;
use Rlorenzo\MagoWordPress\Internal\PhpcsTokens;
use Rlorenzo\MagoWordPress\Internal\Report;

use function in_array;
use function strlen;

/**
 * Ports `Generic.WhiteSpace.LanguageConstructSpacing` (`IncorrectSingle`, `Incorrect`,
 * `IncorrectYieldFrom`): `echo`, `print`, `return`, `include`, `require`, `new`, `yield`, `throw`,
 * `namespace`, `use` and `goto` are followed by exactly one space, unless a `(` or `;` follows
 * directly. `mago format` breaks the line after `echo` or `return` when the argument is long, so
 * this runs after it, as `mago lint --fix --only wordpress/language-construct-spacing` (a step of
 * `mago-wordpress format`); it is off by default. The fix joins the argument to the keyword and, for
 * `echo`, `print`, `return` and `throw`, takes the extra indent level off the rest of the statement, as
 * phpcbf's later passes would.
 */
final class LanguageConstructSpacingRule implements Rule
{
    private const SNIFF = 'Generic.WhiteSpace.LanguageConstructSpacing';

    private const CONSTRUCTS = [
        'T_ECHO',
        'T_PRINT',
        'T_RETURN',
        'T_INCLUDE',
        'T_INCLUDE_ONCE',
        'T_REQUIRE',
        'T_REQUIRE_ONCE',
        'T_NEW',
        'T_YIELD',
        'T_YIELD_FROM',
        'T_THROW',
        'T_NAMESPACE',
        'T_USE',
        'T_GOTO',
    ];

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/language-construct-spacing',
            name: 'Language construct spacing',
            description: 'Reports a language construct (`echo`, `return`, `new`, `use`, ...) not followed by exactly one space.',
            defaultLevel: Level::Error,
            defaultEnabled: false,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $tokens = PhpcsTokens::of($context->file);
        $text = $context->file->contents;
        foreach ($tokens->codes as $index => $code) {
            if (!in_array($code, self::CONSTRUCTS, strict: true)) {
                continue;
            }

            if ($code === 'T_NAMESPACE' && $tokens->code($index + 1) === 'T_NS_SEPARATOR') {
                continue;
            }

            $at = $tokens->pos($index);
            $length = $code === 'T_YIELD_FROM'
                ? ConstructSpacing::yieldFromLength($text, $at)
                : strlen($tokens->content($index));
            $found = $code === 'T_YIELD_FROM' ? ConstructSpacing::yieldFrom($text, $at) : null;
            if ($found !== null) {
                $this->add($context, $at, $found);
            }

            $found = ConstructSpacing::after($text, $at + $length, $tokens->content($index));
            if ($found !== null) {
                $joined = $found[1] === 'IncorrectSingle'
                    ? JoinedStatement::dedent($tokens, $text, $code, $at, $found[2]->end)
                    : [];
                $this->add($context, $at, $found, $joined);
            }
        }
    }

    /**
     * @param array{string, string, Span, string} $found message, sniff code, replaced span, replacement
     * @param list<TextEdit> $more
     */
    private function add(LintContext $context, int $at, array $found, array $more = []): void
    {
        $issue = Issue::new($found[0], new Span($at, $at + 1))->withEdit(TextEdit::replace($found[2], $found[3]));
        foreach ($more as $edit) {
            $issue = $issue->withEdit($edit);
        }

        $this->report->issue($context, $issue, [self::SNIFF . '.' . $found[1]]);
    }
}
