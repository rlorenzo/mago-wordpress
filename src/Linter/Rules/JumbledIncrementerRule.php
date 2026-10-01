<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;
use Rlorenzo\MagoWordPress\Internal\LoopHeader;
use Rlorenzo\MagoWordPress\Internal\Report;

use function array_intersect;
use function array_unique;
use function implode;
use function strlen;

/**
 * Ports `Generic.CodeAnalysis.JumbledIncrementer`: a nested `for` loop that increments a
 * variable the outer loop also increments. Like the sniff, only an outer loop with a
 * braced or colon body is checked, and every variable in the increment part counts.
 */
final class JumbledIncrementerRule implements Rule
{
    private const CODE = 'Generic.CodeAnalysis.JumbledIncrementer.Found';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/jumbled-incrementer',
            name: 'Jumbled incrementer',
            description: 'Reports a `for` loop whose nested `for` loop increments the same variable, which usually means the inner loop increments the wrong variable.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::For],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $for = $context->node;
        $body = $this->scopedBody($file, $for);
        if ($body === null) {
            return;
        }

        $outer = self::incrementers($file, $for);
        if ($outer === []) {
            return;
        }

        foreach ($file->getDescendants($body, NodeKind::For) as $inner) {
            $shared = array_unique(array_intersect($outer, self::incrementers($file, $inner)));
            if ($shared === []) {
                continue;
            }

            $this->report->issue(
                $context,
                Issue::new(
                    'Loop incrementer (' . implode(', ', $shared) . ') jumbling with inner loop',
                    new Span($for->span->start, $for->span->start + strlen('for')),
                    'outer loop',
                )->withSecondaryAnnotation($inner->span, 'inner loop increments the same variable')->withHelp(
                    'Increment the inner loop\'s own variable.',
                ),
                [self::CODE],
            );
        }
    }

    /** The loop's braced or colon-delimited body; the sniff skips a loop with neither. */
    private function scopedBody(SourceFile $file, Node $for): ?Node
    {
        foreach ($file->getChildren($for) as $child) {
            if ($child->kind !== NodeKind::ForBody) {
                continue;
            }

            $body = $file->getChildren($child)[0] ?? null;
            if ($body?->kind === NodeKind::ForColonDelimitedBody) {
                return $child;
            }

            // A Statement holding a Block; `for (...);` and an unbraced body have no scope.
            $block = $body === null ? null : $file->getChildren($body)[0] ?? null;

            return $block?->kind === NodeKind::Block ? $child : null;
        }

        return null;
    }

    /**
     * Every variable after the header's second semicolon.
     *
     * @return list<string>
     */
    private static function incrementers(SourceFile $file, Node $for): array
    {
        $semicolons = 0;
        $variables = [];
        foreach (LoopHeader::tokens($file, $for) as $token) {
            $semicolons += $token->text === ';' ? 1 : 0;
            if ($semicolons === 2 && $token->id === T_VARIABLE) {
                $variables[] = $token->text;
            }
        }

        return $variables;
    }
}
