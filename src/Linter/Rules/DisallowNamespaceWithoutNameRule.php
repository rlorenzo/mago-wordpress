<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\Report;

/**
 * Ports `Universal.Namespaces.DisallowDeclarationWithoutName`: `namespace { ... }`, which declares
 * the global namespace (`Forbidden`).
 */
final class DisallowNamespaceWithoutNameRule implements Rule
{
    private const SNIFF = 'Universal.Namespaces.DisallowDeclarationWithoutName';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/disallow-namespace-without-name',
            name: 'Disallow namespace without a name',
            description: 'Reports a namespace declaration without a name (`namespace { ... }`).',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Namespace],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $braced = false;
        foreach ($file->getChildren($context->node) as $child) {
            if ($child->kind === NodeKind::Identifier) {
                return;
            }

            $braced =
                $braced
                || $child->kind === NodeKind::NamespaceBody
                && $file->getFirstDescendant($child, NodeKind::Block) !== null;
        }

        // `namespace;` is a parse error that Mago reports itself.
        if (!$braced) {
            return;
        }

        $keyword = $file->getFirstDescendant($context->node, NodeKind::Keyword);
        $this->report->issue(
            $context,
            Issue::new(
                'Namespace declarations without a namespace name are not allowed.',
                $keyword->span ?? $context->node->span,
                'declares the global namespace',
            )->withHelp('Name the namespace, or drop the declaration.'),
            [self::SNIFF . '.Forbidden'],
        );
    }
}
