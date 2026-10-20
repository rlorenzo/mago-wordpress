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
use PhpToken;
use Rlorenzo\MagoWordPress\Internal\FileTokens;
use Rlorenzo\MagoWordPress\Internal\Layout;
use Rlorenzo\MagoWordPress\Internal\Report;

use function array_pop;
use function count;
use function strlen;
use function strpos;

use const T_COMMENT;
use const T_DOC_COMMENT;
use const T_FOR;
use const T_OPEN_TAG;
use const T_OPEN_TAG_WITH_ECHO;
use const T_WHITESPACE;

/**
 * Ports `Generic.Formatting.DisallowMultipleStatements`: a second statement on the line of the
 * previous one (`SameLine`), by the `;` that ends it. The `;` of a `for` header are left alone. The
 * fix puts the new statement on the next line, as phpcbf does, and is not offered when a
 * `phpcs:ignore` comment sits between the two.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:excessive-parameter-list
 */
final class DisallowMultipleStatementsRule implements Rule
{
    private const SNIFF = 'Generic.Formatting.DisallowMultipleStatements';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/disallow-multiple-statements',
            name: 'Disallow multiple statements',
            description: 'Reports a statement that starts on the line where the previous statement ends.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $contents = $context->file->contents;
        /** @var list<PhpToken> $tokens */
        $tokens = FileTokens::raw($context->file);
        $newline = strpos($contents, "\n");
        $eol = $newline !== false && $newline > 0 && $contents[$newline - 1] === "\r" ? "\r\n" : "\n";
        $count = count($tokens);
        $forDepth = 0;
        /** @var list<bool> $parens */
        $parens = [];
        $previousSignificant = null;
        $last = null;
        $lastInFor = false;
        $ignored = false;
        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            $id = $token->id;
            if ($id === T_OPEN_TAG || $id === T_OPEN_TAG_WITH_ECHO) {
                $last = null;
                $ignored = false;
            } elseif ($id === T_COMMENT && Layout::ignoresLine($token->text)) {
                $ignored = true;
            } elseif ($token->id === 40) {
                $isFor = $previousSignificant === T_FOR;
                $parens[] = $isFor;
                $forDepth += $isFor ? 1 : 0;
            } elseif ($token->id === 41) {
                $forDepth -= array_pop($parens) === true ? 1 : 0;
            } elseif ($token->id === 59) {
                $inFor = $forDepth > 0;
                if ($last !== null && !$inFor && !$lastInFor && $tokens[$last]->line === $token->line) {
                    $this->flag($context, $tokens, $last, $i, $eol, !$ignored);
                }

                $last = $i;
                $lastInFor = $inFor;
                $ignored = false;
            }

            if ($id !== T_WHITESPACE && $id !== T_COMMENT && $id !== T_DOC_COMMENT) {
                $previousSignificant = $id;
            }
        }
    }

    /**
     * @param list<PhpToken> $tokens
     */
    private function flag(
        LintContext $context,
        array $tokens,
        int $prev,
        int $semicolon,
        string $eol,
        bool $fixable,
    ): void {
        $issue = Issue::new(
            'Each PHP statement must be on a line by itself',
            new Span($tokens[$semicolon]->pos, $tokens[$semicolon]->pos + 1),
        );
        if ($fixable) {
            $end = $tokens[$prev]->pos + 1;
            if (($tokens[$prev + 1]->id ?? null) === T_WHITESPACE) {
                $end += strlen($tokens[$prev + 1]->text);
            }

            $issue = $issue->withEdit(TextEdit::replace(new Span($tokens[$prev]->pos, $end), ';' . $eol));
        }

        $this->report->issue($context, $issue, [self::SNIFF . '.SameLine']);
    }
}
