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
use Rlorenzo\MagoWordPress\Internal\Calls;
use Rlorenzo\MagoWordPress\Internal\Report;

use function preg_replace;
use function trim;

/**
 * Ports `Universal.Attributes.DisallowAttributeParentheses`: `#[Attr()]` instead of `#[Attr]`
 * (`Found`). The fix removes the parentheses unless a comment is inside them.
 */
final class DisallowAttributeParenthesesRule implements Rule
{
    private const SNIFF = 'Universal.Attributes.DisallowAttributeParentheses';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/disallow-attribute-parentheses',
            name: 'Disallow attribute parentheses',
            description: 'Reports an attribute written with empty parentheses, such as `#[Attr()]`.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Attribute],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        foreach ($file->getChildren($context->node) as $arguments) {
            if ($arguments->kind !== NodeKind::PartialArgumentList || !self::isEmpty($file->getText($arguments))) {
                continue;
            }

            $opener = new Span($arguments->span->start, $arguments->span->start + 1);
            $issue = Issue::new(
                'Parentheses not allowed when instantiating an attribute class without passing parameters',
                $opener,
                'empty parentheses',
            )->withHelp('Remove the parentheses.');
            if (!Calls::hasComment($file, $arguments->span)) {
                $issue = $issue->withEdit(TextEdit::replace($arguments->span, ''));
            }

            $this->report->issue($context, $issue, [self::SNIFF . '.Found']);
        }
    }

    /** Whether the parentheses hold nothing but whitespace and comments. */
    private static function isEmpty(string $parentheses): bool
    {
        $inside = (string) preg_replace(
            '~/\*.*?\*/|(?://|\#)[^\r\n]*~s',
            replacement: '',
            subject: trim($parentheses, characters: '()'),
        );

        return trim($inside) === '';
    }
}
