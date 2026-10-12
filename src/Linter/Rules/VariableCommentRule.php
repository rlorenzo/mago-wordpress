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
use Mago\Sdk\Syntax\TriviaKind;
use Rlorenzo\MagoWordPress\Internal\DocComment;
use Rlorenzo\MagoWordPress\Internal\LeadingComment;
use Rlorenzo\MagoWordPress\Internal\PhpText;
use Rlorenzo\MagoWordPress\Internal\Report;

use function strlen;
use function substr;

/**
 * Ports `Squiz.Commenting.VariableComment` as `WordPress-Docs` sets it, for the properties of a
 * class or trait: the docblock checks `DuplicateVar`, `EmptySees`, `MissingVar` and `EmptyVar`.
 * A property without a docblock (`Missing`, `WrongStyle`) is `missing-docs`' to report.
 *
 * `WordPress-Docs` turns off `IncorrectVarType`, `TagNotAllowed` and `VarOrder`; this rule does
 * not report them. Only the first variable of a declaration such as `public $a, $b;` has the
 * docblock, as in the sniff.
 */
final class VariableCommentRule implements Rule
{
    private const SNIFF = 'Squiz.Commenting.VariableComment';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/variable-comment',
            name: 'Variable comment',
            description: 'Reports a property docblock without a `@var` tag, with an empty `@var` or `@see`, or with more than one `@var`.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::PlainProperty],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $comment = LeadingComment::before($file, $context->node->span->start);
        if ($comment === null || $comment['kind'] !== TriviaKind::DocBlockComment) {
            return;
        }

        $span = $comment['span'];
        $text = $file->getText($span);
        $closer = PhpText::docblockCloser($text, 0);
        if ($closer === null) {
            return;
        }

        $end = new Span($span->start + $closer[0], $span->start + $closer[1]);
        $var = null;
        foreach (DocComment::tags($text, $span->start) as $tag) {
            $tagSpan = new Span($tag['offset'], $tag['offset'] + strlen($tag['name']));
            if ($tag['name'] === '@var') {
                if ($var !== null) {
                    $this->add(
                        $context,
                        'Only one @var tag is allowed in a member variable comment',
                        $tagSpan,
                        'DuplicateVar',
                    );
                    continue;
                }

                $var = [$tag, $tagSpan];
            } elseif ($tag['name'] === '@see' && $tag['content'] === null) {
                $this->add($context, 'Content missing for @see tag in member variable comment', $tagSpan, 'EmptySees');
            }
        }

        if ($var === null) {
            $this->add($context, 'Missing @var tag in member variable comment', $end, 'MissingVar');

            return;
        }

        if ($var[0]['content'] === null) {
            $this->add($context, 'Content missing for @var tag in member variable comment', $var[1], 'EmptyVar');
        }
    }

    private function add(LintContext $context, string $message, Span $span, string $code): void
    {
        $this->report->issue($context, Issue::new($message, $span), [self::SNIFF . '.' . $code]);
    }
}
