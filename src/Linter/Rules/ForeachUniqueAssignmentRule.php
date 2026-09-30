<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;
use PhpToken;
use Rlorenzo\MagoWordPress\Internal\Report;

use function array_filter;
use function array_map;
use function array_slice;
use function end;
use function implode;
use function in_array;
use function ltrim;
use function strpos;

/**
 * Ports `Universal.CodeAnalysis.ForeachUniqueAssignment`: `foreach ($a as $k => $k)`, or a
 * key that is also one of the top-level targets of a list/array destructuring value.
 * PHP assigns the key last, so the value is lost. Unlike phpcbf, no fix is offered:
 * removing the key, as the sniff's fixer does, changes which value the variable gets.
 */
final class ForeachUniqueAssignmentRule implements Rule
{
    private const CODE = 'Universal.CodeAnalysis.ForeachUniqueAssignment.NotUnique';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/foreach-unique-assignment',
            name: 'Foreach unique assignment',
            description: 'Reports a `foreach` that assigns the key and the value to the same variable, so one of them is lost.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::ForeachKeyValueTarget],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        [$key, $value] = $file->getChildren($context->node) + [null, null];
        if ($key === null || $value === null) {
            return;
        }

        $keyText = self::target($file, $key);
        foreach (self::assignments($file, $value) as $assignment) {
            $assigned = self::target($file, $assignment);
            if ($assigned !== $keyText) {
                continue;
            }

            $arrow = (int) strpos($file->contents, needle: '=>', offset: $key->span->end);
            $this->report->issue(
                $context,
                Issue::new(
                    "The variables used for the key and the value in a foreach assignment should be unique. Both the key and the value will currently be assigned to: \"{$assigned}\"",
                    new Span($arrow, $arrow + 2),
                    'key and value are the same variable',
                )->withHelp('Rename the key or the value variable.'),
                [self::CODE],
            );

            return;
        }
    }

    /**
     * The value target, or the top-level targets of a list/array destructuring.
     *
     * @return list<Node>
     */
    private static function assignments(SourceFile $file, Node $value): array
    {
        $inner = $file->getChildren($value)[0] ?? null;
        if ($inner === null || !in_array($inner->kind, [NodeKind::Array, NodeKind::List], strict: true)) {
            return [$value];
        }

        $targets = [];
        foreach ($file->getChildren($inner) as $element) {
            $kind = $file->getChildren($element)[0] ?? null;
            $parts = $kind === null ? [] : $file->getChildren($kind);
            $last = end($parts);
            if ($last !== false && $kind?->kind !== NodeKind::MissingArrayElement) {
                $targets[] = $last;
            }
        }

        return $targets;
    }

    /**
     * The target without whitespace, comments or a leading `&`, as the sniff compares
     * (GetTokensAsString::noEmpties()).
     */
    private static function target(SourceFile $file, Node $node): string
    {
        $tokens = array_filter(
            array_slice(PhpToken::tokenize('<?php ' . $file->getText($node)), offset: 1),
            static fn(PhpToken $token): bool => !$token->isIgnorable(),
        );

        return ltrim(
            implode('', array_map(static fn(PhpToken $token): string => $token->text, $tokens)),
            characters: '&',
        );
    }
}
