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

use function str_starts_with;

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
            // A `namespace Foo;` body holds the file's statements: only the braced form starts with `{`.
            if ($child->kind !== NodeKind::NamespaceBody || !str_starts_with($file->getText($child), '{')) {
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
