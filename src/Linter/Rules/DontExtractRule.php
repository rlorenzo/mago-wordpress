<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\CallExpression;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Linter\CallRule;

/**
 * Ports `WordPress.PHP.DontExtract`.
 */
final class DontExtractRule extends CallRule
{
    private const SNIFF = 'WordPress.PHP.DontExtract';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/dont-extract',
            name: "Don't extract",
            description: 'Reports every call to extract(). extract() creates variables from arbitrary array keys, which obscures where variables come from and enables variable clobbering when the array contains unexpected keys.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::FunctionCall],
        );
    }

    protected function names(): array
    {
        return ['extract'];
    }

    protected function inspect(LintContext $context, CallExpression $call, string $name): void
    {
        if ($this->report->excludesGroup(self::SNIFF, 'extract')) {
            return;
        }

        $this->report->issue(
            $context,
            Issue::new('Do not use `extract()`', $context->node->span, '`extract()` call detected')->withNote(
                '`extract()` creates variables from arbitrary array keys, obscuring where variables come from and enabling variable clobbering.',
            )->withHelp('Access array elements explicitly, or use `wp_parse_args()` for defaults merging.'),
            [self::SNIFF . '.extract_extract'],
        );
    }
}
