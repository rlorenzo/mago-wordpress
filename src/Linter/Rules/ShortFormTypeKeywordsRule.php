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

use function preg_match;
use function preg_replace;

/**
 * Ports `PSR12.Keywords.ShortFormTypeKeywords`: the casts `(boolean)` and `(integer)` instead of
 * `(bool)` and `(int)` (`LongFound`).
 */
final class ShortFormTypeKeywordsRule implements Rule
{
    private const SNIFF = 'PSR12.Keywords.ShortFormTypeKeywords';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/short-form-type-keywords',
            name: 'Short form type keywords',
            description: 'Reports the casts `(boolean)` and `(integer)`; use `(bool)` and `(int)`.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::UnaryPrefixOperator],
        );
    }

    public function lint(LintContext $context): void
    {
        $text = $context->file->getText($context->node);
        $matches = [];
        if (preg_match('/^\(\s*(boolean|integer)\s*\)$/i', $text, $matches) !== 1) {
            return;
        }

        $short = preg_match('/^b/i', $matches[1]) === 1 ? 'bool' : 'int';
        $this->report->issue(
            $context,
            Issue::new(
                'Short form type keywords must be used. Found: ' . $text,
                $context->node->span,
                'long form cast',
            )->withHelp("Use `({$short})`.")->withEdit(TextEdit::replace(
                $context->node->span,
                (string) preg_replace('/boolean|integer/i', $short, $text),
            )),
            [self::SNIFF . '.LongFound'],
        );
    }
}
