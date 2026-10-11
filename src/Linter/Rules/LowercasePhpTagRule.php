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

use function strtolower;
use function strtoupper;

/**
 * Ports `Universal.PHP.LowercasePHPTag`: `<?PHP` or `<?Php` instead of `<?php`
 * (`Uppercase`, `Mixedcase`).
 */
final class LowercasePhpTagRule implements Rule
{
    private const SNIFF = 'Universal.PHP.LowercasePHPTag';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/lowercase-php-tag',
            name: 'Lowercase PHP tag',
            description: 'Reports a PHP open tag that is not lowercase, such as `<?PHP`.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::FullOpeningTag],
        );
    }

    public function lint(LintContext $context): void
    {
        $text = $context->file->getText($context->node);
        $lower = strtolower($text);
        if ($text === $lower) {
            return;
        }

        $this->report->issue(
            $context,
            Issue::new(
                'The php open tag should be in lowercase. Found: ' . $text,
                $context->node->span,
                'not in lowercase',
            )->withEdit(TextEdit::replace($context->node->span, $lower)),
            [self::SNIFF . '.' . (strtoupper($text) === $text ? 'Uppercase' : 'Mixedcase')],
        );
    }
}
