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
use Rlorenzo\MagoWordPress\Internal\ClosingComment;
use Rlorenzo\MagoWordPress\Internal\PhpcsTokens;
use Rlorenzo\MagoWordPress\Internal\Report;

use function sprintf;

/**
 * Ports `Squiz.Commenting.ClosingDeclarationComment` as `WordPress-Docs` sets it: the
 * `//end class`, `//end foo()` comment after the closing brace of a class, interface, trait, enum,
 * function or method, when there is one but it is `Incorrect` or `Misplaced`. `WordPress-Docs`
 * turns off `Missing`, so a declaration without the comment is fine.
 */
final class ClosingDeclarationCommentRule implements Rule
{
    private const SNIFF = 'Squiz.Commenting.ClosingDeclarationComment';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/closing-declaration-comment',
            name: 'Closing declaration comment',
            description: 'Reports a `//end class` style comment after a closing brace that is wrong or not directly after it.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $tokens = PhpcsTokens::of($context->file);
        $text = $context->file->contents;
        foreach ($tokens->codes as $index => $code) {
            $found = ClosingComment::of($tokens, $text, $index, $code);
            if ($found === null) {
                continue;
            }

            [$closer, $problem, $expected, $edit] = $found;
            $this->report->issue(
                $context,
                Issue::new(
                    sprintf(
                        $problem === 'Misplaced' ? 'Expected %s directly after closing brace' : 'Expected %s',
                        $expected,
                    ),
                    new Span($closer, $closer + 1),
                )->withEdit($edit),
                [self::SNIFF . '.' . $problem],
            );
        }
    }
}
