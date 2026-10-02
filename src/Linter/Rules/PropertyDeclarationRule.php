<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\Report;

use function array_filter;
use function array_values;
use function count;
use function in_array;
use function preg_match;
use function str_contains;
use function strlen;
use function strtolower;
use function trim;

/**
 * Ports `PSR2.Classes.PropertyDeclaration`: a property needs a visibility, no `var`, one
 * property per statement, one space after its type and its modifiers in PSR order; a leading
 * underscore in its name is a warning (`Underscore`, which `SplitRule` routes to the
 * `-warning` companion; WordPress-Core silences it, WordPress-Extra restores it). Promoted
 * constructor parameters are not properties to the sniff. The ordering and spacing codes are
 * fixed like phpcbf: the keyword moves next to the visibility.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class PropertyDeclarationRule implements Rule
{
    private const SNIFF = 'PSR2.Classes.PropertyDeclaration';

    private const VISIBILITY = ['public', 'protected', 'private'];

    private const SET_VISIBILITY = ['public(set)', 'protected(set)', 'private(set)'];

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/property-declaration',
            name: 'Property declaration',
            description: 'Reports a property without visibility, declared with `var`, declared with others in one statement, with its modifiers out of order or without one space after its type.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Property],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $property = $file->getChildren($context->node)[0] ?? null;
        if ($property === null) {
            return;
        }

        $modifiers = [];
        $var = null;
        $hint = null;
        $variables = [];
        foreach ($file->getChildren($property) as $child) {
            match ($child->kind) {
                NodeKind::Modifier => $modifiers[] = $child,
                NodeKind::Keyword => $var = $child,
                NodeKind::Hint => $hint = $child,
                NodeKind::PropertyItem => $variables[] = $file->getFirstDescendant($child, NodeKind::DirectVariable),
                default => null,
            };
        }

        $variables = array_values(array_filter($variables));
        foreach ($variables as $variable) {
            $name = $file->getText($variable);
            if (($name[1] ?? '') === '_') {
                $this->issue(
                    $context,
                    $variable->span,
                    "Property name \"{$name}\" should not be prefixed with an underscore to indicate visibility",
                    'Underscore',
                );
            }
        }

        $first = $variables[0] ?? null;
        if ($first === null) {
            return;
        }

        if ($var !== null && strtolower($file->getText($var)) === 'var') {
            $this->issue($context, $first->span, 'The var keyword must not be used to declare a property', 'VarUsed');
        }

        if (count($variables) > 1) {
            $this->issue(
                $context,
                $first->span,
                'There must not be more than one property declared per statement',
                'Multiple',
            );
        }

        if ($hint !== null) {
            $this->typeSpacing($context, $hint, $first);
        }

        $this->modifiers($context, $modifiers, $first);
    }

    /**
     * Visibility present, and the other modifiers on the right side of it.
     *
     * @param list<Node> $modifiers
     * @mago-expect lint:halstead
     */
    private function modifiers(LintContext $context, array $modifiers, Node $first): void
    {
        $file = $context->file;
        $name = $file->getText($first);
        // Modifier keyword => node, last occurrence wins as the sniff's findPrevious() does.
        /** @var array<string, Node> $by */
        $by = [];
        foreach ($modifiers as $modifier) {
            $by[strtolower($file->getText($modifier))] = $modifier;
        }

        $scopes = self::pick($by, self::VISIBILITY);
        $setScopes = self::pick($by, self::SET_VISIBILITY);
        if ($scopes === [] && $setScopes === []) {
            $this->issue($context, $first->span, "Visibility must be declared on property \"{$name}\"", 'ScopeMissing');

            return;
        }

        $visibility = [...$scopes, ...$setScopes];
        $lastVisibility = $visibility[0];
        foreach ($visibility as $node) {
            $lastVisibility = $node->span->start > $lastVisibility->span->start ? $node : $lastVisibility;
        }

        $firstVisibility = $lastVisibility;
        if ($scopes !== [] && $setScopes !== []) {
            if ($scopes[0]->span->start > $setScopes[0]->span->start) {
                $this->issue(
                    $context,
                    $first->span,
                    'The "read"-visibility must come before the "write"-visibility',
                    'AvizKeywordOrder',
                    [
                        self::removal($context, $scopes[0]),
                        TextEdit::insert($setScopes[0]->span->start, $file->getText($scopes[0]) . ' '),
                    ],
                );
            }

            $firstVisibility = $scopes[0]->span->start < $setScopes[0]->span->start ? $scopes[0] : $setScopes[0];
        }

        foreach (['final' => 'FinalAfterVisibility', 'abstract' => 'AbstractAfterVisibility'] as $keyword => $code) {
            $modifier = $by[$keyword] ?? null;
            if ($modifier !== null && $modifier->span->start > $firstVisibility->span->start) {
                $this->issue(
                    $context,
                    $first->span,
                    "The {$keyword} declaration must come before the visibility declaration",
                    $code,
                    [
                        self::removal($context, $modifier),
                        TextEdit::insert($firstVisibility->span->start, $file->getText($modifier) . ' '),
                    ],
                );
            }
        }

        foreach ([
            'static' => 'StaticBeforeVisibility',
            'readonly' => 'ReadonlyBeforeVisibility',
        ] as $keyword => $code) {
            $modifier = $by[$keyword] ?? null;
            if ($modifier !== null && $modifier->span->start < $lastVisibility->span->start) {
                $this->issue(
                    $context,
                    $first->span,
                    "The {$keyword} declaration must come after the visibility declaration",
                    $code,
                    [
                        self::removal($context, $modifier),
                        TextEdit::insert($lastVisibility->span->end, ' ' . $file->getText($modifier)),
                    ],
                );
            }
        }
    }

    /**
     * @param array<string, Node> $by
     * @param list<string> $keywords
     * @return list<Node>
     */
    private static function pick(array $by, array $keywords): array
    {
        $found = [];
        foreach ($keywords as $keyword) {
            $node = $by[$keyword] ?? null;
            if ($node !== null) {
                $found[] = $node;
            }
        }

        return $found;
    }

    /** One space between the type and the property name. */
    private function typeSpacing(LintContext $context, Node $hint, Node $variable): void
    {
        $gapSpan = new Span($hint->span->end, $variable->span->start);
        $gap = $context->file->getText($gapSpan);
        if ($gap === ' ') {
            return;
        }

        $whitespace = preg_match('/^\s*/', $gap, $match) === 1 ? $match[0] : '';
        $found = match (true) {
            $whitespace === '' => '0',
            str_contains($whitespace, "\n") => 'newline',
            default => (string) strlen($whitespace),
        };
        // phpcbf leaves a gap holding a comment alone, but adds a missing space before one.
        $edit = match (true) {
            $whitespace === '' => TextEdit::insert($hint->span->end, ' '),
            trim($gap) === '' => TextEdit::replace($gapSpan, ' '),
            default => null,
        };

        $this->issue(
            $context,
            new Span($hint->span->end - 1, $hint->span->end),
            "There must be 1 space after the property type declaration; {$found} found",
            'SpacingAfterType',
            $edit === null ? [] : [$edit],
        );
    }

    /** Removes a modifier keyword and the whitespace after it, as phpcbf does before re-adding it elsewhere. */
    private static function removal(LintContext $context, Node $modifier): TextEdit
    {
        $contents = $context->file->contents;
        $end = $modifier->span->end;
        while ($end < strlen($contents) && in_array($contents[$end], [' ', "\t", "\n", "\r"], strict: true)) {
            $end++;
        }

        return TextEdit::delete(new Span($modifier->span->start, $end));
    }

    /** @param list<TextEdit> $edits */
    private function issue(LintContext $context, Span $span, string $message, string $code, array $edits = []): void
    {
        $issue = Issue::new($message, $span);
        foreach ($edits as $edit) {
            $issue = $issue->withEdit($edit);
        }

        $this->report->issue($context, $issue, [self::SNIFF . '.' . $code]);
    }
}
