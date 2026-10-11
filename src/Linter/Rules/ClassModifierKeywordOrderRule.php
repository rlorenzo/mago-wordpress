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
 * Ports `Universal.Classes.ModifierKeywordOrder`: `readonly final class` instead of
 * `final readonly class` (`Incorrect`). `abstract` and `final` are never combined.
 */
final class ClassModifierKeywordOrderRule implements Rule
{
    private const SNIFF = 'Universal.Classes.ModifierKeywordOrder';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/class-modifier-keyword-order',
            name: 'Class modifier keyword order',
            description: 'Reports `readonly` written before `final` or `abstract` in a class declaration.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Class_],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $modifiers = ModifierOrder::modifiers($file, $context->node);
        $readonly = $modifiers['readonly'] ?? null;
        $extendability = $modifiers['final'] ?? $modifiers['abstract'] ?? null;
        if ($readonly === null || $extendability === null || $readonly->span->start > $extendability->span->start) {
            return;
        }

        $found = $file->getText($readonly);
        $other = $file->getText($extendability);
        $issue = Issue::new(
            sprintf(
                'Class modifier keywords are not in the correct order. Expected: "%s %s", found: "%s %s"',
                $other,
                $found,
                $found,
                $other,
            ),
            $readonly->span,
            'readonly comes first',
        );
        $edit = ModifierOrder::swap($file, $readonly, $extendability);

        $this->report->issue($context, $edit === null ? $issue : $issue->withEdit($edit), [self::SNIFF . '.Incorrect']);
    }
}
