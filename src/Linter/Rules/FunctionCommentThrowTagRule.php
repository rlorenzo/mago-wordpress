<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\TriviaKind;
use Rlorenzo\MagoWordPress\Internal\LeadingComment;
use Rlorenzo\MagoWordPress\Internal\NodeIndex;
use Rlorenzo\MagoWordPress\Internal\PhpcsTokens;
use Rlorenzo\MagoWordPress\Internal\PhpText;
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Internal\ThrownExceptions;
use Rlorenzo\MagoWordPress\Internal\ThrowTags;

use function array_unique;
use function count;
use function sprintf;
use function str_ends_with;
use function strpos;
use function substr;

/**
 * Ports `Squiz.Commenting.FunctionCommentThrowTag` (`Missing`, `WrongNumber`): a function or
 * method with a docblock whose body throws needs a `@throws` tag, and one per exception class it
 * throws. The exceptions are read from the tokens the way the sniff reads them, including what it
 * gets wrong: closures and anonymous classes are skipped, nested named functions and arrow
 * functions are not.
 */
final class FunctionCommentThrowTagRule implements Rule
{
    private const SNIFF = 'Squiz.Commenting.FunctionCommentThrowTag';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/function-comment-throw-tag',
            name: 'Function comment @throws tag',
            description: 'Reports a documented function that throws without a `@throws` tag, or with a different number of tags than exceptions.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $tokens = null;
        foreach ([NodeKind::Function, NodeKind::Method] as $kind) {
            foreach (NodeIndex::ofKind($file, $context->node, $kind) as $function) {
                $comment = LeadingComment::before($file, $function->span->start);
                if ($comment === null || $comment['kind'] !== TriviaKind::DocBlockComment) {
                    continue;
                }

                $tokens ??= PhpcsTokens::of($file);
                $thrown = ThrownExceptions::forFunction($tokens, $function, $context->file);
                if ($thrown === null) {
                    continue;
                }

                $this->check($context, $comment['span'], $thrown);
            }
        }
    }

    /**
     * @param array{exceptions: list<string>, unknown: int} $thrown
     */
    private function check(LintContext $context, Span $comment, array $thrown): void
    {
        $text = $context->file->getText($comment);
        $closer = PhpText::docblockCloser($text, 0);
        if ($closer === null) {
            return;
        }

        $end = new Span($comment->start + $closer[0], $comment->start + $closer[1]);
        $tags = ThrowTags::fromDocblock($text, $comment->start);

        if ($tags === []) {
            $this->add($context, 'Missing @throws tag in function comment', $end);

            return;
        }

        if ($thrown['exceptions'] === []) {
            return;
        }

        foreach (ThrowTags::problems($thrown['exceptions'], $thrown['unknown'], $tags) as [$message, $code]) {
            $this->add($context, $message, $end, $code);
        }
    }

    private function add(LintContext $context, string $message, Span $span, string $code = 'Missing'): void
    {
        $this->report->issue($context, Issue::new($message, $span), [self::SNIFF . '.' . $code]);
    }
}
