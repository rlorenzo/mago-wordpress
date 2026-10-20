<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Reporting\Issue;

/**
 * Reports the findings of one phpcs whitespace sniff at a token of a Layout, with the fix if it
 * has one.
 *
 * @internal
 */
final class LayoutIssues
{
    public function __construct(
        private readonly Report $report,
        private readonly LintContext $context,
        private readonly Layout $layout,
        private readonly string $sniff,
    ) {}

    public function error(int $token, string $message, string $code, ?LayoutFix $fix = null): void
    {
        $issue = Issue::new($message, $this->layout->span($token));
        foreach ($fix?->edits() ?? [] as $edit) {
            $issue = $issue->withEdit($edit);
        }

        $this->report->issue($this->context, $issue, [$this->sniff . '.' . $code]);
    }

    public function fix(): LayoutFix
    {
        return new LayoutFix($this->layout);
    }
}
