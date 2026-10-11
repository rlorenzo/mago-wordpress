<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\ModifierOrder;
use Rlorenzo\MagoWordPress\Internal\Report;

use function sprintf;

/**
 * Ports `Universal.Constants.ModifierKeywordOrder`: `public final const` instead of
 * `final public const` (`Incorrect`).
 */
final class ConstantModifierKeywordOrderRule implements Rule
{
    private const SNIFF = 'Universal.Constants.ModifierKeywordOrder';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/constant-modifier-keyword-order',
            name: 'Constant modifier keyword order',
            description: 'Reports a visibility keyword written before `final` in a class constant declaration.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::ClassLikeConstant],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $modifiers = ModifierOrder::modifiers($file, $context->node);
        $final = $modifiers['final'] ?? null;
        $visibility = $modifiers['public'] ?? $modifiers['protected'] ?? $modifiers['private'] ?? null;
        if ($final === null || $visibility === null || $final->span->start < $visibility->span->start) {
            return;
        }

        $first = $file->getText($visibility);
        $second = $file->getText($final);
        $issue = Issue::new(
            sprintf(
                'Constant modifier keywords are not in the correct order. Expected: "%s %s", found: "%s %s"',
                $second,
                $first,
                $first,
                $second,
            ),
            $visibility->span,
            'visibility comes first',
        );
        $edit = ModifierOrder::swap($file, $visibility, $final);

        $this->report->issue($context, $edit === null ? $issue : $issue->withEdit($edit), [self::SNIFF . '.Incorrect']);
    }
}
