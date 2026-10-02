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

use function in_array;
use function strtolower;

/**
 * Ports `Squiz.Scope.MethodScope`: a method in a class, interface, trait or enum without a
 * `public`, `protected` or `private` modifier. Reported at the `function` keyword, as the sniff.
 */
final class MethodScopeRule implements Rule
{
    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/method-scope',
            name: 'Method scope',
            description: 'Reports a method declared without a visibility modifier.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Method],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $keyword = null;
        foreach ($file->getChildren($context->node) as $child) {
            if (
                $child->kind === NodeKind::Modifier
                && in_array(strtolower($file->getText($child)), ['public', 'protected', 'private'], strict: true)
            ) {
                return;
            }

            if ($child->kind === NodeKind::Keyword) {
                $keyword = $child;
            }

            if ($child->kind === NodeKind::LocalIdentifier && $keyword !== null) {
                $name = $file->getText($child);
                $this->report->issue(
                    $context,
                    Issue::new("Visibility must be declared on method \"{$name}\"", $keyword->span)->withHelp(
                        'Add `public`, `protected` or `private`.',
                    ),
                    ['Squiz.Scope.MethodScope.Missing'],
                );

                return;
            }
        }
    }
}
