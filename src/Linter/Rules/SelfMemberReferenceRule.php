<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;
use PhpToken;
use Rlorenzo\MagoWordPress\Internal\NodeIndex;
use Rlorenzo\MagoWordPress\Internal\Report;

use function in_array;
use function ltrim;
use function preg_replace;
use function str_contains;
use function strlen;
use function strpos;
use function strrpos;
use function strtolower;
use function substr;

/**
 * Ports `Squiz.Classes.SelfMemberReference`: inside a class (not a closure, an anonymous class,
 * a trait, interface or enum), a static reference to the class by its own name instead of
 * `self::` (`NotUsed`), `self` in another case (`IncorrectCase`), and whitespace around the
 * `::` (`SpaceBefore`, `SpaceAfter`). The name compares case-sensitively, as the sniff does; a
 * qualified name is matched against the namespace of the class. WordPress-Core silences
 * `NotUsed` and WordPress-Extra restores it. All four are fixed as phpcbf does.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class SelfMemberReferenceRule implements Rule
{
    private const SNIFF = 'Squiz.Classes.SelfMemberReference';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/self-member-reference',
            name: 'Self member reference',
            description: 'Reports a static reference to the enclosing class by its name instead of `self::`, `self` in another case, and spaces around the `::`.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        if (!str_contains($context->file->contents, '::')) {
            return;
        }

        // Targets the program: a narrower target's snapshot has no ancestors to find the class by.
        foreach (NodeIndex::ofKinds($context->file, $context->node, [
            NodeKind::StaticMethodCall,
            NodeKind::StaticPropertyAccess,
            NodeKind::ClassConstantAccess,
            NodeKind::StaticMethodPartialApplication,
        ]) as $node) {
            $this->check($context, $node);
        }
    }

    private function check(LintContext $context, Node $node): void
    {
        $file = $context->file;
        $class = self::enclosingClass($file, $node);
        [$expression, $member] = $file->getChildren($node) + [null, null];
        $called = $expression === null ? null : $file->getChildren($expression)[0] ?? null;
        if ($class === null || $called === null || $member === null) {
            return;
        }

        $text = $file->getText($called);
        if ($called->kind === NodeKind::Keyword && strtolower($text) === 'self') {
            if ($text !== 'self') {
                $this->issue(
                    $context,
                    $called->span,
                    "Must use \"self::\" for local static member reference; found \"{$text}::\"",
                    'IncorrectCase',
                    [TextEdit::replace($called->span, 'self')],
                );

                return;
            }
        } elseif ($called->kind === NodeKind::Identifier && self::namesClass($file, $class, $text)) {
            $this->issue(
                $context,
                $called->span,
                'Must use "self::" for local static member reference',
                'NotUsed',
                [TextEdit::replace($called->span, 'self')],
            );
        }

        $this->spacing($context, $called, $member);
    }

    /** Whether $name (as written) is the class itself, compared as the sniff does. */
    private static function namesClass(SourceFile $file, Node $class, string $name): bool
    {
        $declared = '';
        foreach ($file->getChildren($class) as $child) {
            if ($child->kind === NodeKind::LocalIdentifier) {
                $declared = $file->getText($child);
                break;
            }
        }

        $name = (string) preg_replace('/\s+|\/\*.*?\*\//s', replacement: '', subject: $name);
        if (!str_contains($name, '\\')) {
            return $name === $declared;
        }

        $namespace = '';
        foreach ($file->getAncestors($class) as $ancestor) {
            if ($ancestor->kind === NodeKind::Namespace) {
                $identifier = $file->getFirstDescendant($ancestor, NodeKind::Identifier);
                $namespace = $identifier === null ? '' : $file->getText($identifier) . '\\';
                break;
            }
        }

        return ltrim($name, characters: '\\') === $namespace . $declared;
    }

    /** No whitespace right before or after the `::`. */
    private function spacing(LintContext $context, Node $called, Node $member): void
    {
        $from = $called->span->end;
        $tokens = PhpToken::tokenize('<?php ' . $context->file->getText(new Span($from, $member->span->start)));
        $offset = $from - strlen('<?php ');
        foreach ($tokens as $index => $token) {
            if ($token->id !== T_DOUBLE_COLON) {
                continue;
            }

            $before = $index > 1 ? $tokens[$index - 1] : null;
            $after = $tokens[$index + 1] ?? null;
            // Both are reported on the token before the `::`, as the sniff does; phpcs splits
            // whitespace at each newline, so that token is the whitespace's last line.
            $at = $called->span;
            $found = '';
            if ($before !== null) {
                $at = self::span($before, $offset);
                $lineStart = strrpos($before->text, needle: "\n");
                $found = $lineStart === false ? $before->text : substr($before->text, $lineStart + 1);
                $at = $before->id === T_WHITESPACE ? new Span($at->end - strlen($found), $at->end) : $at;
            }

            if ($before !== null && $before->id === T_WHITESPACE) {
                $this->issue(
                    $context,
                    $at,
                    'Expected 0 spaces before double colon; ' . strlen($found) . ' found',
                    'SpaceBefore',
                    [TextEdit::delete(self::span($before, $offset))],
                );
            }

            if ($after !== null && $after->id === T_WHITESPACE) {
                $lineEnd = strpos($after->text, needle: "\n");
                $this->issue(
                    $context,
                    $at,
                    'Expected 0 spaces after double colon; '
                    . ($lineEnd === false ? strlen($after->text) : $lineEnd + 1)
                    . ' found',
                    'SpaceAfter',
                    [TextEdit::delete(self::span($after, $offset))],
                );
            }

            return;
        }
    }

    private static function span(PhpToken $token, int $offset): Span
    {
        return new Span($offset + $token->pos, $offset + $token->pos + strlen($token->text));
    }

    /** The named class the reference sits in, or NULL when a closure or other scope comes first. */
    private static function enclosingClass(SourceFile $file, Node $node): ?Node
    {
        $child = $node;
        foreach ($file->getAncestors($node) as $ancestor) {
            [$child, $below] = [$ancestor, $child];
            // The attributes of a class or closure come before its scope opens.
            if ($below->kind === NodeKind::AttributeList) {
                continue;
            }

            if ($ancestor->kind === NodeKind::Class_) {
                return $ancestor;
            }

            if (in_array(
                $ancestor->kind,
                [
                    NodeKind::AnonymousClass,
                    NodeKind::Closure,
                    NodeKind::Trait,
                    NodeKind::Interface,
                    NodeKind::Enum,
                ],
                strict: true,
            )) {
                return null;
            }
        }

        return null;
    }

    /** @param list<TextEdit> $edits */
    private function issue(LintContext $context, Span $span, string $message, string $code, array $edits): void
    {
        $issue = Issue::new($message, $span);
        foreach ($edits as $edit) {
            $issue = $issue->withEdit($edit);
        }

        $this->report->issue($context, $issue, [self::SNIFF . '.' . $code]);
    }
}
