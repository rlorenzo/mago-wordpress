<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Internal\UseImports;

use function array_filter;
use function array_slice;
use function count;
use function implode;
use function sprintf;

/**
 * Ports `Universal.UseStatements.DisallowMixedGroupUse`: a group use statement that imports more
 * than one kind of construct (classes, functions and constants), `Found`. Unlike the sniff it
 * does not split the statement.
 */
final class DisallowMixedGroupUseRule implements Rule
{
    private const SNIFF = 'Universal.UseStatements.DisallowMixedGroupUse';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/disallow-mixed-group-use',
            name: 'Disallow mixed group use',
            description: 'Reports a group use statement that imports more than one kind of construct (classes, functions, constants).',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Use],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        if ($file->getFirstDescendant($context->node, NodeKind::MixedUseItemList) === null) {
            return;
        }

        $counts = ['name' => 0, 'function' => 0, 'const' => 0];
        foreach (UseImports::items($file, $context->node) as $import) {
            ++$counts[$import['type']];
        }

        if (count(array_filter($counts)) < 2) {
            return;
        }

        $nouns = [
            'name' => ['namespace/OO name', 'namespaces/OO names'],
            'function' => ['function', 'functions'],
            'const' => ['constant', 'constants'],
        ];
        $phrases = [];
        foreach (array_filter($counts) as $type => $count) {
            $phrases[] = sprintf('%d %s', $count, $nouns[$type][$count === 1 ? 0 : 1]);
        }

        $tail = implode(' and ', array_slice($phrases, offset: -2));
        $found = implode(', ', [...array_slice($phrases, offset: 0, length: -2), $tail]);
        $keyword = $file->getFirstDescendant($context->node, NodeKind::Keyword);
        $this->report->issue(
            $context,
            Issue::new(
                sprintf(
                    'Group use statements should import one type of construct. Mixed group use statement found importing %s.',
                    $found,
                ),
                $keyword->span ?? $context->node->span,
                'a group that mixes constructs',
            )->withHelp('Split it into one `use` statement per kind of construct.'),
            [self::SNIFF . '.Found'],
        );
    }
}
