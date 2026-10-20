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
use Rlorenzo\MagoWordPress\Internal\Report;

/**
 * Ports `Universal.Namespaces.DisallowCurlyBraceSyntax`: a `namespace Foo { ... }` declaration, at
 * its `namespace` keyword (`Forbidden`).
 */
final class DisallowNamespaceCurlyBraceSyntaxRule implements Rule
{
    private const SNIFF = 'Universal.Namespaces.DisallowCurlyBraceSyntax';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/disallow-namespace-curly-brace-syntax',
            name: 'Disallow namespace curly brace syntax',
            description: 'Reports a namespace declaration that uses the curly brace syntax.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Namespace],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $namespace = $context->node;
        foreach ($file->getChildren($namespace) as $child) {
            if (
                $child->kind !== NodeKind::NamespaceBody
                || $file->getFirstDescendant($child, NodeKind::Block) === null
            ) {
                continue;
            }

            $start = $namespace->span->start;
            $this->report->issue(
                $context,
                Issue::new(
                    'Namespace declarations using the curly brace syntax are not allowed.',
                    new Span($start, $start + 9),
                    'curly brace syntax',
                ),
                [self::SNIFF . '.Forbidden'],
            );

            return;
        }
    }
}
