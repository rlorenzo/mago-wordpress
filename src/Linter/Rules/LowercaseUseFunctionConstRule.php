<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\Report;

use function strtolower;

/**
 * Ports `Universal.UseStatements.LowercaseFunctionConst`: the `function` and `const` keywords of
 * an import (`use FUNCTION foo;`, or inside a group) that are not lowercase (`NotLowercase`).
 */
final class LowercaseUseFunctionConstRule implements Rule
{
    private const SNIFF = 'Universal.UseStatements.LowercaseFunctionConst';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/lowercase-use-function-const',
            name: 'Lowercase use function/const',
            description: 'Reports a `function` or `const` keyword in an import `use` statement that is not lowercase.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Use],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        foreach ($file->getDescendants($context->node, NodeKind::UseType) as $type) {
            $text = $file->getText($type);
            $lower = strtolower($text);
            if ($text === $lower) {
                continue;
            }

            $this->report->issue(
                $context,
                Issue::new(
                    "The \"{$lower}\" keyword when used in an import use statements must be lowercase.",
                    $type->span,
                    "`{$text}` is not lowercase",
                )->withEdit(TextEdit::replace($type->span, $lower)),
                [self::SNIFF . '.NotLowercase'],
            );
        }
    }
}
