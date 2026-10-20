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
use Rlorenzo\MagoWordPress\Internal\PhpcsTokens;
use Rlorenzo\MagoWordPress\Internal\Report;

use function str_starts_with;
use function strlen;

/**
 * Ports `Squiz.Functions.FunctionDuplicateArgument`: a variable that appears twice between the
 * parentheses of a `function` declaration or closure, reported at the second one.
 */
final class FunctionDuplicateArgumentRule implements Rule
{
    private const CODE = 'Squiz.Functions.FunctionDuplicateArgument.Found';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/function-duplicate-argument',
            name: 'Function duplicate argument',
            description: 'Reports a parameter name that appears more than once in a function declaration.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $tokens = PhpcsTokens::of($context->file);
        foreach ($tokens->codes as $index => $code) {
            if ($code !== 'T_FUNCTION') {
                continue;
            }

            $open = $index + 1;
            if (str_starts_with((string) $tokens->code($open), 'T_AMPERSAND')) {
                ++$open;
            }

            if ($tokens->code($open) === 'T_STRING') {
                ++$open;
            }

            $close = $tokens->code($open) === '(' ? $tokens->closer($open) : null;
            if ($close === null) {
                continue;
            }

            $seen = [];
            for ($i = $open + 1; $i < $close; $i++) {
                if ($tokens->code($i) !== 'T_VARIABLE') {
                    continue;
                }

                $name = $tokens->content($i);
                if (isset($seen[$name])) {
                    $at = $tokens->pos($i);
                    $this->report->issue(
                        $context,
                        Issue::new(
                            "Variable \"{$name}\" appears more than once in function declaration",
                            new Span($at, $at + strlen($name)),
                            'duplicate parameter',
                        ),
                        [self::CODE],
                    );
                }

                $seen[$name] = true;
            }
        }
    }
}
