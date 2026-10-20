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
use Rlorenzo\MagoWordPress\Internal\PhpcsTokens;
use Rlorenzo\MagoWordPress\Internal\Report;

use function array_pop;
use function count;
use function end;
use function in_array;
use function str_starts_with;
use function strlen;
use function strtolower;
use function substr;

/**
 * Ports `Universal.CodeAnalysis.ConstructorDestructorReturn`: a return type on `__construct()` or
 * `__destruct()` (an error, fixed by removing it) and a `return` with a value in one (a warning).
 * A PHP 4-style constructor, a method named like its class outside a namespace, counts too, as the
 * sniff does without a configured PHP version.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class ConstructorDestructorReturnRule implements Rule
{
    private const SNIFF = 'Universal.CodeAnalysis.ConstructorDestructorReturn';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/constructor-destructor-return',
            name: 'Constructor/destructor return',
            description: 'Reports a return type on a constructor or destructor (fixed) and a `return` with a value in one.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $tokens = PhpcsTokens::of($context->file);
        /** @var list<array{int, string}> $classes closer and name of the class-likes around the token */
        $classes = [];
        $namespaced = false;
        $namespaceEnd = null;
        foreach ($tokens->codes as $index => $code) {
            while ($classes !== [] && end($classes)[0] < $index) {
                array_pop($classes);
            }

            if ($namespaceEnd !== null && $index > $namespaceEnd) {
                $namespaced = false;
                $namespaceEnd = null;
            }

            if ($code === 'T_NAMESPACE') {
                [$namespaced, $namespaceEnd] = self::namespaceAt($tokens, $index, $namespaced, $namespaceEnd);
            } elseif (in_array($code, ['T_CLASS', 'T_INTERFACE', 'T_TRAIT', 'T_ENUM'], strict: true)) {
                $closer = $tokens->scopeCloser($index);
                if ($closer !== null && $tokens->code($index - 1) !== 'T_DOUBLE_COLON') {
                    $next = $tokens->code($index + 1) === 'T_STRING' ? $tokens->content($index + 1) : '';
                    $classes[] = [$closer, $next];
                }
            } elseif ($code === 'T_FUNCTION' && $classes !== [] && $tokens->isDirectInClassLike($index)) {
                $this->method($context, $tokens, $index, end($classes)[1], $namespaced);
            }
        }
    }

    /**
     * @return array{bool, null|int} whether a namespace is in effect, and where a braced one ends
     */
    private static function namespaceAt(PhpcsTokens $tokens, int $index, bool $namespaced, ?int $end): array
    {
        $next = $index + 1;
        if ($tokens->code($next) !== 'T_STRING') {
            // `namespace {` is the global namespace; `namespace\foo()` is not a declaration.
            return $tokens->code($next) === '{' ? [false, null] : [$namespaced, $end];
        }

        while (in_array($tokens->code($next), ['T_STRING', 'T_NS_SEPARATOR'], strict: true)) {
            ++$next;
        }

        return $tokens->code($next) === '{' ? [true, $tokens->closer($next)] : [true, null];
    }

    private function method(
        LintContext $context,
        PhpcsTokens $tokens,
        int $function,
        string $className,
        bool $namespaced,
    ): void {
        $nameAt = $function + 1;
        if (str_starts_with((string) $tokens->code($nameAt), 'T_AMPERSAND')) {
            ++$nameAt;
        }

        $open = $nameAt + 1;
        if ($tokens->code($nameAt) !== 'T_STRING' || $tokens->code($open) !== '(') {
            return;
        }

        $name = strtolower($tokens->content($nameAt));
        if ($name === '__construct' || $name === '__destruct') {
            $type = "A \"{$name}()\" magic method";
        } elseif ($className !== '' && !$namespaced && $name === strtolower($className)) {
            $type = 'A PHP 4-style constructor';
        } else {
            return;
        }

        $close = $tokens->closer($open);
        if ($close === null) {
            return;
        }

        if ($tokens->code($close + 1) === ':') {
            $this->returnType($context, $tokens, $close, $type);
        }

        $scopeCloser = $tokens->scopeCloser($function);
        if ($scopeCloser === null) {
            return;
        }

        for ($i = $close + 1; $i < $scopeCloser; $i++) {
            $code = $tokens->code($i);
            if (($code === 'T_FUNCTION' || $code === 'T_FN') && ($skip = $tokens->scopeCloser($i)) !== null) {
                $i = $skip;
            } elseif ($code === 'T_RETURN' && !in_array($tokens->code($i + 1), [';', 'T_CLOSE_TAG'], strict: true)) {
                $found = $tokens->compact($i, $tokens->endOfStatement($i + 1));
                $at = $tokens->pos($i);
                $this->report->issue(
                    $context,
                    Issue::new(
                        "{$type} can not return a value. Found: \"{$found}\"",
                        new Span($at, $at + 6),
                        'return value',
                    ),
                    [self::SNIFF . '.ReturnValueFound'],
                );
            }
        }
    }

    private function returnType(LintContext $context, PhpcsTokens $tokens, int $close, string $type): void
    {
        $first = $close + 2;
        $last = $first;
        $count = count($tokens->codes);
        while (($last + 1) < $count && !in_array($tokens->code($last + 1), ['{', ';'], strict: true)) {
            ++$last;
        }

        if ($last >= $count || in_array($tokens->code($first), ['{', ';'], strict: true)) {
            return;
        }

        $text = '';
        for ($i = $first; $i <= $last; $i++) {
            $text .= $tokens->content($i);
        }

        $from = $tokens->pos($close) + 1;
        $to = $tokens->pos($last) + strlen($tokens->content($last));
        $issue = Issue::new(
            "{$type} can not declare a return type. Found: {$text}",
            new Span($tokens->pos($first), $tokens->pos($first) + strlen($tokens->content($first))),
            'return type',
        );
        // phpcbf removes every token from the `)` to the type, comments excepted.
        $offset = $from;
        foreach (PhpToken::tokenize('<?php ' . substr($context->file->contents, $from, $to - $from)) as $token) {
            $length = strlen($token->text);
            if (
                $token->getTokenName() !== 'T_OPEN_TAG' && !$token->isIgnorable()
                || $token->getTokenName() === 'T_WHITESPACE'
            ) {
                $issue = $issue->withEdit(TextEdit::delete(new Span($offset, $offset + $length)));
            }

            if ($token->getTokenName() !== 'T_OPEN_TAG') {
                $offset += $length;
            }
        }

        $this->report->issue($context, $issue, [self::SNIFF . '.ReturnTypeFound']);
    }
}
