<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;
use Rlorenzo\MagoWordPress\Internal\Report;

use function strtolower;

/**
 * Ports `Generic.CodeAnalysis.UnnecessaryFinalModifier`: a `final` method, constant or property
 * in a `final` class. Each `final` keyword is reported, as the sniff does, which includes the
 * `final` of a property (a parse error the sniff does not care about). An enum or an anonymous
 * class is not a `T_CLASS`, so is not checked.
 */
final class UnnecessaryFinalModifierRule implements Rule
{
    private const CODE = 'Generic.CodeAnalysis.UnnecessaryFinalModifier.Found';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/unnecessary-final-modifier',
            name: 'Unnecessary final modifier',
            description: 'Reports `final` on a member of a `final` class.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::Class_],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $children = $file->getChildren($context->node);
        if (!self::hasFinal($file, $children)) {
            return;
        }

        foreach ($children as $member) {
            if ($member->kind !== NodeKind::ClassLikeMember) {
                continue;
            }

            $declaration = $file->getChildren($member)[0] ?? null;
            if ($declaration === null) {
                continue;
            }

            if ($declaration->kind === NodeKind::Property) {
                $declaration = $file->getChildren($declaration)[0] ?? null;
                if ($declaration === null) {
                    continue;
                }
            }

            foreach ($file->getChildren($declaration) as $modifier) {
                if ($modifier->kind !== NodeKind::Modifier || strtolower($file->getText($modifier)) !== 'final') {
                    continue;
                }

                $this->report->issue(
                    $context,
                    Issue::new(
                        'Unnecessary FINAL modifier in FINAL class',
                        $modifier->span,
                        'redundant `final`',
                    )->withHelp('Remove `final`; nothing can extend or override a member of a final class.'),
                    [self::CODE],
                );
            }
        }
    }

    /** @param list<Node> $children */
    private static function hasFinal(SourceFile $file, array $children): bool
    {
        foreach ($children as $child) {
            if ($child->kind === NodeKind::Modifier && strtolower($file->getText($child)) === 'final') {
                return true;
            }
        }

        return false;
    }
}
