<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\PhpcsTokenStream;
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Internal\Spacing;

use function strlen;

/**
 * Ports `Universal.Classes.RequireAnonClassParentheses` (`Missing`): `new class { }` needs
 * parentheses, `new class() { }`.
 */
final class RequireAnonClassParenthesesRule implements Rule
{
    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/require-anon-class-parentheses',
            name: 'Require anonymous class parentheses',
            description: 'Requires parentheses after `new class`, even without arguments.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $tokens = Spacing::tokens($context);
        foreach ($tokens as $i => $token) {
            // phpcs keeps `new class;` (no body) a plain T_CLASS.
            if ($token->code !== 'T_ANON_CLASS' || $token->scopeOpener === null) {
                continue;
            }

            $next = PhpcsTokenStream::next($tokens, $i);
            if ($next !== null && $tokens[$next]->code === 'T_OPEN_PARENTHESIS') {
                continue;
            }

            Spacing::report(
                $this->report,
                $context,
                $token,
                'Parenthesis required when creating a new anonymous class.',
                ['Universal.Classes.RequireAnonClassParentheses.Missing'],
                [TextEdit::insert($token->pos + strlen($token->text), '()')],
            );
        }
    }
}
