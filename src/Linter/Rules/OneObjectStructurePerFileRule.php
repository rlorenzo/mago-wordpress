<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\Report;

use function array_merge;
use function in_array;
use function strtolower;
use function usort;

/**
 * Ports `Generic.Files.OneObjectStructurePerFile`: a class, interface, trait or enum
 * declared after another one has ended, anywhere in the file, so including the
 * `if ( ... ) { class A {} } else { class A {} }` shape that Mago's single-class-per-file
 * accepts. Reported at the later declaration's keyword, as the sniff does.
 */
final class OneObjectStructurePerFileRule implements Rule
{
    private const CODE = 'Generic.Files.OneObjectStructurePerFile.MultipleFound';

    private const KINDS = [NodeKind::Class_, NodeKind::Interface, NodeKind::Trait, NodeKind::Enum];

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/one-object-structure-per-file',
            name: 'One object structure per file',
            description: 'Reports a second class, interface, trait or enum declared in the same file.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $declarations = [];
        foreach (self::KINDS as $kind) {
            $declarations = array_merge($declarations, $file->getNodes($kind));
        }

        usort($declarations, static fn(Node $a, Node $b): int => $a->span->start <=> $b->span->start);
        // Each declaration reports the first one that starts at or after its end (a nested one
        // never qualifies); the starts are sorted, so a binary search finds it.
        $starts = array_map(static fn(Node $declaration): int => $declaration->span->start, $declarations);
        $reported = [];
        foreach ($declarations as $declaration) {
            $low = 0;
            $high = count($starts);
            while ($low < $high) {
                $middle = intdiv($low + $high, 2);
                if ($starts[$middle] < $declaration->span->end) {
                    $low = $middle + 1;
                } else {
                    $high = $middle;
                }
            }

            $next = $declarations[$low] ?? null;
            if ($next === null || isset($reported[$next->id])) {
                continue;
            }

            $reported[$next->id] = true;
            $this->report->issue(
                $context,
                Issue::new(
                    'Only one object structure is allowed in a file',
                    self::keyword($context, $next)->span,
                    'another declaration',
                )->withHelp('Move it to its own file.'),
                [self::CODE],
            );
        }
    }

    private static function keyword(LintContext $context, Node $declaration): Node
    {
        foreach ($context->file->getChildren($declaration) as $child) {
            if (
                $child->kind === NodeKind::Keyword
                && in_array(
                    strtolower($context->file->getText($child)),
                    ['class', 'interface', 'trait', 'enum'],
                    strict: true,
                )
            ) {
                return $child;
            }
        }

        return $declaration;
    }
}
