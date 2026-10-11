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

use function sprintf;
use function strtolower;
use function strtoupper;

/**
 * Ports `Universal.Constants.LowercaseClassResolutionKeyword`: `Foo::CLASS` or `$object::Class`
 * instead of `::class` (`Uppercase`, `Mixedcase`).
 */
final class LowercaseClassResolutionKeywordRule implements Rule
{
    private const SNIFF = 'Universal.Constants.LowercaseClassResolutionKeyword';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/lowercase-class-resolution-keyword',
            name: 'Lowercase class resolution keyword',
            description: 'Reports the `::class` keyword written with capital letters, such as `Foo::CLASS`.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::ClassConstantAccess],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        foreach ($file->getChildren($context->node) as $selector) {
            if ($selector->kind !== NodeKind::ClassLikeConstantSelector) {
                continue;
            }

            $text = $file->getText($selector);
            if (strtolower($text) !== 'class' || $text === 'class') {
                continue;
            }

            $this->report->issue(
                $context,
                Issue::new(
                    sprintf(
                        "The ::class keyword for class name resolution must be in lowercase. Expected: '::class'; found: '::%s'",
                        $text,
                    ),
                    $selector->span,
                    'not in lowercase',
                )->withEdit(TextEdit::replace($selector->span, 'class')),
                [self::SNIFF . '.' . (strtoupper($text) === $text ? 'Uppercase' : 'Mixedcase')],
            );
        }
    }
}
