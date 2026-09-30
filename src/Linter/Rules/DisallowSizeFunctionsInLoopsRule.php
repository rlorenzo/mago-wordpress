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
use Rlorenzo\MagoWordPress\Internal\LoopHeader;
use Rlorenzo\MagoWordPress\Internal\Report;

use function array_filter;
use function array_keys;
use function array_slice;
use function count;
use function in_array;
use function strlen;
use function strrpos;
use function substr;

/**
 * Ports `Squiz.PHP.DisallowSizeFunctionsInLoops`: `count()`, `sizeof()` or `strlen()` in a
 * `while`/do-`while` condition or a `for` loop's test part. Like the sniff, the name is
 * matched case-sensitively and anywhere in the condition, except as a property name.
 */
final class DisallowSizeFunctionsInLoopsRule implements Rule
{
    private const CODE = 'Squiz.PHP.DisallowSizeFunctionsInLoops.Found';

    private const FUNCTIONS = ['sizeof', 'strlen', 'count'];

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/disallow-size-functions-in-loops',
            name: 'Disallow size functions in loops',
            description: 'Reports `count()`, `sizeof()` and `strlen()` in a loop condition, where they run again on every iteration; assign the result to a variable before the loop.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::For, NodeKind::While, NodeKind::DoWhile],
        );
    }

    public function lint(LintContext $context): void
    {
        $tokens = LoopHeader::tokens($context->file, $context->node);
        if ($context->node->kind === NodeKind::For) {
            // Only the test part: between the first and the last semicolon.
            $semicolons = array_keys(array_filter($tokens, static fn($token): bool => $token->text === ';'));
            $first = $semicolons[0] ?? 0;
            $last = $semicolons[count($semicolons) - 1] ?? 0;
            $tokens = array_slice($tokens, $first + 1, $last - $first - 1, preserve_keys: true);
        }

        foreach ($tokens as $i => $token) {
            if (!in_array(
                $token->id,
                [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE],
                strict: true,
            )) {
                continue;
            }

            // A qualified name is a T_STRING run to phpcs; its last part is the function name.
            $offset = (int) strrpos('\\' . $token->text, needle: '\\');
            $name = substr($token->text, $offset);
            $previous = $tokens[$i - 1] ?? null;
            if (
                !in_array($name, self::FUNCTIONS, strict: true)
                || in_array($previous?->id, [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR], strict: true)
            ) {
                continue;
            }

            $start = $token->pos + $offset;
            $this->report->issue(
                $context,
                Issue::new(
                    "The use of {$name}() inside a loop condition is not allowed; assign the return value to a variable and use the variable in the loop condition instead",
                    new Span($start, $start + strlen($name)),
                    "`{$name}()` runs on every iteration",
                ),
                [self::CODE],
            );
        }
    }
}
