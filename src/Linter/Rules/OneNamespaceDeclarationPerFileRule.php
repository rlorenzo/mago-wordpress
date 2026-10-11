<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\NodeIndex;
use Rlorenzo\MagoWordPress\Internal\Report;

use function sprintf;
use function substr_count;

/**
 * Ports `Universal.Namespaces.OneDeclarationPerFile`: every namespace declaration after the first
 * in a file (`MultipleFound`).
 */
final class OneNamespaceDeclarationPerFileRule implements Rule
{
    private const SNIFF = 'Universal.Namespaces.OneDeclarationPerFile';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/one-namespace-declaration-per-file',
            name: 'One namespace declaration per file',
            description: 'Reports a file with more than one namespace declaration; the second and later ones are reported.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $first = null;
        foreach (NodeIndex::ofKind($file, $context->node, NodeKind::Namespace) as $namespace) {
            // `namespace;` is a parse error that Mago reports itself.
            if (
                $file->getFirstDescendant($namespace, NodeKind::Identifier) === null
                && $file->getFirstDescendant($namespace, NodeKind::Block) === null
            ) {
                continue;
            }

            $keyword = $file->getFirstDescendant($namespace, NodeKind::Keyword);
            $span = $keyword->span ?? new Span($namespace->span->start, $namespace->span->start + 9);
            if ($first === null) {
                $first = 1 + substr_count($file->contents, needle: "\n", offset: 0, length: $namespace->span->start);

                continue;
            }

            $this->report->issue(
                $context,
                Issue::new(
                    sprintf(
                        'There should be only one namespace declaration per file. The first declaration was found on line %d',
                        $first,
                    ),
                    $span,
                    'another namespace declaration',
                ),
                [self::SNIFF . '.MultipleFound'],
            );
        }
    }
}
