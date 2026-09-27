<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\CallExpression;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Internal\WordPress\Lists;
use Rlorenzo\MagoWordPress\Linter\CallRule;

use function array_intersect;
use function array_keys;
use function array_values;
use function count;

/**
 * Ports `WordPress.CodeAnalysis.EscapedNotTranslated`.
 *
 * `esc_html()` and `esc_attr()` take a single `$text` argument; a call with
 * more than one argument looks like it was meant to call the "translate and
 * escape" sister function instead, which also takes `$domain`/context
 * arguments.
 */
final class EscapedNotTranslatedRule extends CallRule
{
    private const SNIFF = 'WordPress.CodeAnalysis.EscapedNotTranslated';

    /**
     * Key is the function actually called; value is the sister function
     * the sniff suggests instead.
     *
     * @var array<string, string>
     */
    private const ALTERNATIVES = [
        'esc_html' => 'esc_html__',
        'esc_attr' => 'esc_attr__',
    ];

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/escaped-not-translated',
            name: 'Escaped but not translated',
            description: 'Reports a call to esc_html() or esc_attr() with more than one argument, which likely should have been esc_html__() or esc_attr__().',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::FunctionCall],
        );
    }

    protected function names(): array
    {
        // Reuses the shared escaping-function list rather than a list of its own.
        return array_values(array_intersect(Lists::ESCAPING_FUNCTIONS, array_keys(self::ALTERNATIVES)));
    }

    protected function inspect(LintContext $context, CallExpression $call, string $name): void
    {
        if (count($call->arguments) <= 1) {
            return;
        }

        $alternative = self::ALTERNATIVES[$name];

        Report::issue(
            $context,
            Issue::new(
                "{$name}() expects only a \$text parameter. Did you mean to use {$alternative}()?",
                $context->node->span,
            )->withHelp("Use {$alternative}() to translate and escape the text in one call."),
            [self::SNIFF . '.Found'],
        );
    }
}
