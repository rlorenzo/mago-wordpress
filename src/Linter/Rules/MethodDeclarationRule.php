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
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\Report;

use function ctype_space;
use function strlen;
use function strtolower;

/**
 * Ports `PSR2.Methods.MethodDeclaration`: `final` and `abstract` before the visibility,
 * `static` after it (fixed as phpcbf does), and a method name with a single leading
 * underscore (`Underscore`, a warning `SplitRule` routes to the `-warning` companion;
 * WordPress-Core silences it, WordPress-Extra restores it).
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class MethodDeclarationRule implements Rule
{
    private const SNIFF = 'PSR2.Methods.MethodDeclaration';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/method-declaration',
            name: 'Method declaration',
            description: 'Reports `final` or `abstract` after a method\'s visibility, and `static` before it.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Method],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        /** @var array<string, Node> $modifiers */
        $modifiers = [];
        foreach ($file->getChildren($context->node) as $child) {
            if ($child->kind === NodeKind::Modifier) {
                $modifiers[strtolower($file->getText($child))] = $child;
            }

            if ($child->kind !== NodeKind::LocalIdentifier) {
                continue;
            }

            $name = $file->getText($child);
            if (($name[0] ?? '') === '_' && ($name[1] ?? '_') !== '_') {
                $this->report->issue(
                    $context,
                    Issue::new(
                        "Method name \"{$name}\" should not be prefixed with an underscore to indicate visibility",
                        $child->span,
                    ),
                    [self::SNIFF . '.Underscore'],
                );
            }

            break;
        }

        $visibility = $modifiers['public'] ?? $modifiers['protected'] ?? $modifiers['private'] ?? null;
        if ($visibility === null) {
            return;
        }

        foreach (['final' => 'FinalAfterVisibility', 'abstract' => 'AbstractAfterVisibility'] as $keyword => $code) {
            $modifier = $modifiers[$keyword] ?? null;
            if ($modifier !== null && $modifier->span->start > $visibility->span->start) {
                $this->move(
                    $context,
                    $modifier,
                    "The {$keyword} declaration must precede the visibility declaration",
                    $code,
                    TextEdit::insert($visibility->span->start, $context->file->getText($modifier) . ' '),
                );
            }
        }

        $static = $modifiers['static'] ?? null;
        if ($static !== null && $static->span->start < $visibility->span->start) {
            $this->move(
                $context,
                $static,
                'The static declaration must come after the visibility declaration',
                'StaticBeforeVisibility',
                TextEdit::insert($visibility->span->end, ' ' . $context->file->getText($static)),
            );
        }
    }

    /** Reports a misplaced modifier, fixed by deleting it with the whitespace after it and re-inserting it. */
    private function move(LintContext $context, Node $modifier, string $message, string $code, TextEdit $insert): void
    {
        $contents = $context->file->contents;
        $end = $modifier->span->end;
        while ($end < strlen($contents) && ctype_space($contents[$end])) {
            $end++;
        }

        $this->report->issue(
            $context,
            Issue::new($message, $modifier->span)->withEdit(TextEdit::delete(
                new Span($modifier->span->start, $end),
            ))->withEdit($insert),
            [self::SNIFF . '.' . $code],
        );
    }
}
