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
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\Report;

use function ctype_space;
use function in_array;
use function preg_match;
use function str_starts_with;
use function strlen;

/**
 * Ports `Universal.UseStatements.NoLeadingBackslash`: an import `use` statement whose name
 * starts with `\` (`LeadingBackslashFound`), or a name inside a group use that does
 * (`LeadingBackslashFoundInGroup`, a parse error in PHP). The fix removes the backslash, or
 * turns it into a space when nothing else separates the name from what comes before.
 */
final class NoLeadingBackslashRule implements Rule
{
    private const SNIFF = 'Universal.UseStatements.NoLeadingBackslash';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/no-leading-backslash',
            name: 'No leading backslash',
            description: 'Reports an import `use` statement whose name starts with a backslash; imported names are always fully qualified.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Use],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $this->keywordPrefix($context);
        foreach ($file->getDescendants($context->node, NodeKind::Identifier) as $name) {
            $parent = $file->getParent($name);
            if ($parent === null || !str_starts_with($file->getText($name), '\\')) {
                continue;
            }

            $grandparent = $file->getParent($parent);
            $inGroup =
                $parent->kind === NodeKind::UseItem && $grandparent?->kind === NodeKind::MaybeTypedUseItem
                || $grandparent?->kind === NodeKind::TypedUseItemList;
            [$message, $code] = $inGroup
                ? [
                    'Parse error: partial import use statement in a use group starting with a leading backslash',
                    'LeadingBackslashFoundInGroup',
                ]
                : ['An import use statement should never start with a leading backslash', 'LeadingBackslashFound'];
            if (
                !$inGroup
                && !in_array(
                    $parent->kind,
                    [NodeKind::UseItem, NodeKind::MixedUseItemList, NodeKind::TypedUseItemList],
                    strict: true,
                )
            ) {
                continue;
            }

            $start = $name->span->start;
            $spaced = $start > 0 && ctype_space($file->contents[$start - 1]);
            $this->report->issue(
                $context,
                Issue::new($message, new Span($start, $start + 1), 'leading backslash')->withHelp(
                    'Remove the leading backslash.',
                )->withEdit(TextEdit::replace(new Span($start, $start + 1), $spaced ? '' : ' ')),
                [self::SNIFF . ".{$code}"],
            );
        }
    }

    /**
     * `use Function\Util\Foo;` imports a class (PHP 8 allows keywords in namespaced names), but
     * phpcs 3 reads `function` as the keyword and reports the `\` after it. Reported the same,
     * without the fix, which would turn the class import into a function import.
     */
    private function keywordPrefix(LintContext $context): void
    {
        $file = $context->file;
        $text = $file->getText($context->node);
        if (preg_match('/^use\s+(function|const)\\\\/i', $text, $match) !== 1) {
            return;
        }

        $start = $context->node->span->start + strlen($match[0]) - 1;
        $this->report->issue(
            $context,
            Issue::new(
                'An import use statement should never start with a leading backslash',
                new Span($start, $start + 1),
                'read as a leading backslash after `' . $match[1] . '`',
            )->withHelp('Nothing to change in PHP 8; phpcs 3 misreads this name.'),
            [self::SNIFF . '.LeadingBackslashFound'],
        );
    }
}
