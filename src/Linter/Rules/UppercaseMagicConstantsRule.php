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

use function in_array;
use function sprintf;
use function strtolower;
use function strtoupper;

/**
 * Ports `Universal.Constants.UppercaseMagicConstants`: `__line__` and `__Dir__` instead of
 * `__LINE__` and `__DIR__` (`Lowercase`, `Mixedcase`).
 */
final class UppercaseMagicConstantsRule implements Rule
{
    private const SNIFF = 'Universal.Constants.UppercaseMagicConstants';

    /** PHPCS' `Tokens::$magicConstants`. */
    private const MAGIC_CONSTANTS = [
        '__class__',
        '__dir__',
        '__file__',
        '__function__',
        '__line__',
        '__method__',
        '__namespace__',
        '__trait__',
    ];

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/uppercase-magic-constants',
            name: 'Uppercase magic constants',
            description: 'Reports a magic constant (`__LINE__`, `__FILE__`, `__DIR__`, ...) that is not written in uppercase.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::MagicConstant],
        );
    }

    public function lint(LintContext $context): void
    {
        $text = $context->file->getText($context->node);
        $upper = strtoupper($text);
        if ($text === $upper || !in_array(strtolower($text), self::MAGIC_CONSTANTS, strict: true)) {
            return;
        }

        $this->report->issue(
            $context,
            Issue::new(
                sprintf('Magic constants should be in uppercase. Expected: %s; found: %s', $upper, $text),
                $context->node->span,
                'not in uppercase',
            )->withEdit(TextEdit::replace($context->node->span, $upper)),
            [self::SNIFF . '.' . (strtolower($text) === $text ? 'Lowercase' : 'Mixedcase')],
        );
    }
}
