<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\Report;

use function str_starts_with;
use function strlen;

/**
 * Ports `Generic.Files.ByteOrderMark`: a UTF-8 or UTF-16 byte order mark at the start of
 * the file is output before any PHP runs, which breaks headers and sessions.
 */
final class ByteOrderMarkRule implements Rule
{
    private const CODE = 'Generic.Files.ByteOrderMark.Found';

    private const MARKS = [
        'UTF-8' => "\xEF\xBB\xBF",
        'UTF-16 (BE)' => "\xFE\xFF",
        'UTF-16 (LE)' => "\xFF\xFE",
    ];

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/byte-order-mark',
            name: 'Byte order mark',
            description: 'Reports a byte order mark at the start of a file; it is sent as output before any PHP runs and breaks headers, sessions and redirects.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        foreach (self::MARKS as $name => $mark) {
            if (!str_starts_with($context->file->contents, $mark)) {
                continue;
            }

            $this->report->issue(
                $context,
                Issue::new(
                    "File contains {$name} byte order mark, which may corrupt your application",
                    $context->node->span,
                )->withSecondaryAnnotation(new Span(0, strlen($mark)), 'byte order mark')->withHelp(
                    'Save the file as UTF-8 without a BOM.',
                ),
                [self::CODE],
            );

            return;
        }
    }
}
