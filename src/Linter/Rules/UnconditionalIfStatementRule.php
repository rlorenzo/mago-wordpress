<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;
use PhpToken;
use Rlorenzo\MagoWordPress\Internal\Report;

use function in_array;
use function ltrim;
use function strtolower;

/**
 * Ports `Generic.CodeAnalysis.UnconditionalIfStatement`: an `if` or `elseif` whose condition
 * is the bare literal `true` or `false` (`\true` and comments included), as the sniff checks
 * it. Anything else in the parentheses, `(true)` or `true || $a` included, passes.
 */
final class UnconditionalIfStatementRule implements Rule
{
    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/unconditional-if-statement',
            name: 'Unconditional if statement',
            description: 'Reports an `if` or `elseif` whose condition is the literal `true` or `false`.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [
                NodeKind::If,
                NodeKind::IfStatementBodyElseIfClause,
                NodeKind::IfColonDelimitedBodyElseIfClause,
            ],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $keyword = null;
        foreach ($file->getChildren($context->node) as $child) {
            if ($child->kind === NodeKind::Keyword) {
                $keyword ??= $child;
                continue;
            }

            if ($keyword === null || $child->kind !== NodeKind::Expression) {
                return;
            }

            $words = [];
            foreach (PhpToken::tokenize('<?php ' . $file->getText($child)) as $token) {
                if (!$token->isIgnorable() && $token->id !== T_NS_SEPARATOR) {
                    $words[] = strtolower(ltrim($token->text, characters: "\\"));
                }
            }

            if (in_array($words, [['true'], ['false']], strict: true)) {
                $this->report->issue(
                    $context,
                    Issue::new('Avoid IF statements that are always true or false', $keyword->span),
                    ['Generic.CodeAnalysis.UnconditionalIfStatement.Found'],
                );
            }

            return;
        }
    }
}
