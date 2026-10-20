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
use Mago\Sdk\Syntax\NodeKind;
use PhpToken;
use Rlorenzo\MagoWordPress\Internal\FileTokens;
use Rlorenzo\MagoWordPress\Internal\Report;

use function count;
use function stripos;
use function strlen;

use const T_ATTRIBUTE;
use const T_CLASS;
use const T_COMMENT;
use const T_CONST;
use const T_DOC_COMMENT;
use const T_DOUBLE_COLON;
use const T_FUNCTION;
use const T_NAME_FULLY_QUALIFIED;
use const T_NAME_QUALIFIED;
use const T_NAME_RELATIVE;
use const T_NEW;
use const T_NS_SEPARATOR;
use const T_NULLSAFE_OBJECT_OPERATOR;
use const T_OBJECT_OPERATOR;
use const T_READONLY;
use const T_STATIC;
use const T_STRING;
use const T_VARIABLE;
use const T_WHITESPACE;

/**
 * Ports `PSR12.Classes.ClassInstantiation`: `new Foo` without parentheses (`MissingParentheses`),
 * with the `()` phpcbf adds. The class name may be a variable, a property or static member chain,
 * and may carry `[...]` or `{...}` dimensions; `new (expr)` and anonymous classes are left alone.
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class ClassInstantiationRule implements Rule
{
    private const SNIFF = 'PSR12.Classes.ClassInstantiation';

    private const NAME = [
        T_STRING => true,
        T_NAME_QUALIFIED => true,
        T_NAME_FULLY_QUALIFIED => true,
        T_NAME_RELATIVE => true,
        T_NS_SEPARATOR => true,
        T_STATIC => true,
        T_VARIABLE => true,
        T_OBJECT_OPERATOR => true,
        T_NULLSAFE_OBJECT_OPERATOR => true,
        T_DOUBLE_COLON => true,
        T_WHITESPACE => true,
        T_COMMENT => true,
        T_DOC_COMMENT => true,
    ];

    private const DOLLAR = 36;

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/class-instantiation',
            name: 'Class instantiation',
            description: 'Reports `new Foo` without parentheses.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $contents = $context->file->contents;
        if (stripos($contents, 'new') === false) {
            return;
        }

        /** @var list<PhpToken> $tokens */
        $tokens = FileTokens::raw($context->file);
        $count = count($tokens);
        foreach ($tokens as $index => $token) {
            if ($token->id !== T_NEW || $this->isIdentifier($tokens, $index)) {
                continue;
            }

            $end = $this->nameEnd($tokens, $index, $count);
            if ($end === null) {
                continue;
            }

            $last = $end - 1;
            while (
                $last > $index
                && (
                    $tokens[$last]->id === T_WHITESPACE
                    || $tokens[$last]->id === T_COMMENT
                    || $tokens[$last]->id === T_DOC_COMMENT
                )
            ) {
                $last--;
            }

            $this->report->issue(
                $context,
                Issue::new(
                    'Parentheses must be used when instantiating a new class',
                    new Span($token->pos, $token->pos + strlen($token->text)),
                )->withEdit(TextEdit::insert($tokens[$last]->pos + strlen($tokens[$last]->text), '()')),
                [self::SNIFF . '.MissingParentheses'],
            );
        }
    }

    /**
     * `Foo::NEW`, `const NEW` and `function new()` lex as T_NEW but are identifiers.
     *
     * @param list<PhpToken> $tokens
     */
    private function isIdentifier(array $tokens, int $new): bool
    {
        for ($i = $new - 1; $i >= 0; $i--) {
            $id = $tokens[$i]->id;
            if ($id === T_WHITESPACE || $id === T_COMMENT || $id === T_DOC_COMMENT) {
                continue;
            }

            return $id === T_DOUBLE_COLON || $id === T_CONST || $id === T_FUNCTION;
        }

        return false;
    }

    /**
     * The index of the token that ends the class name, null when the `new` has parentheses or
     * makes an anonymous class.
     *
     * @param list<PhpToken> $tokens
     */
    private function nameEnd(array $tokens, int $new, int $count): ?int
    {
        for ($i = $new + 1; $i < $count; $i++) {
            $id = $tokens[$i]->id;
            if ($id === self::DOLLAR || (self::NAME[$id] ?? null) !== null) {
                continue;
            }

            if ($id === T_ATTRIBUTE || $id === T_READONLY || $id === T_CLASS) {
                return null;
            }

            $text = $tokens[$i]->text;
            if ($text === '[' || $text === '{') {
                $i = $this->close($tokens, $i, $count, $text === '[' ? ']' : '}');

                continue;
            }

            return $text === '(' ? null : $i;
        }

        return null;
    }

    /** @param list<PhpToken> $tokens */
    private function close(array $tokens, int $open, int $count, string $closer): int
    {
        $opener = $tokens[$open]->text;
        $depth = 0;
        for ($i = $open; $i < $count; $i++) {
            $text = $tokens[$i]->text;
            $depth += $text === $opener ? 1 : ($text === $closer ? -1 : 0);
            if ($depth === 0) {
                return $i;
            }
        }

        return $count - 1;
    }
}
