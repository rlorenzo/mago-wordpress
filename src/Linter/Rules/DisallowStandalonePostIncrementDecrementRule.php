<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;
use PhpToken;
use Rlorenzo\MagoWordPress\Internal\PhpcsTokens;
use Rlorenzo\MagoWordPress\Internal\Report;

use function array_slice;
use function count;
use function in_array;
use function preg_match;
use function preg_replace;
use function ucfirst;

use const T_COMMENT;
use const T_DOC_COMMENT;
use const T_DOUBLE_COLON;
use const T_NAME_FULLY_QUALIFIED;
use const T_NAME_QUALIFIED;
use const T_NAME_RELATIVE;
use const T_NAMESPACE;
use const T_NS_SEPARATOR;
use const T_OBJECT_OPERATOR;
use const T_STATIC;
use const T_STRING;
use const T_VARIABLE;

/**
 * Ports `Universal.Operators.DisallowStandalonePostIncrementDecrement`: `$i++;` or `$i--;` as
 * a statement of its own, on a variable, property or array element. The fix moves the
 * operator to the front, as phpcbf does. The sniff's `MultipleOperatorsFound` only fires on
 * code PHP cannot parse (`++$i--;`), so it is not ported.
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class DisallowStandalonePostIncrementDecrementRule implements Rule
{
    private const SNIFF = 'Universal.Operators.DisallowStandalonePostIncrementDecrement';

    /** The tokens the sniff accepts in the operand, besides `[...]` after a variable or name. */
    private const ALLOWED = [
        T_VARIABLE,
        T_STRING,
        T_STATIC,
        T_OBJECT_OPERATOR,
        T_DOUBLE_COLON,
        T_NS_SEPARATOR,
        T_NAMESPACE,
        T_NAME_QUALIFIED,
        T_NAME_FULLY_QUALIFIED,
        T_NAME_RELATIVE,
    ];

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/disallow-standalone-post-increment-decrement',
            name: 'Disallow stand-alone post-increment/decrement',
            description: 'Reports `$i++;` and `$i--;` as statements of their own; use `++$i;` and `--$i;`.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            // The whole file, before NonceVerificationRule releases the shared PhpcsTokens.
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        if (preg_match('/\+\+|--/', $context->file->contents) !== 1) {
            return;
        }

        foreach ($context->file->getNodes(NodeKind::ExpressionStatement) as $statement) {
            $this->check($context, $statement);
        }
    }

    private function check(LintContext $context, Node $statement): void
    {
        $file = $context->file;
        $expression = $file->getChildren($statement)[0] ?? null;
        $postfix = $expression === null ? null : $file->getChildren($expression)[0] ?? null;
        if ($postfix === null || $postfix->kind !== NodeKind::UnaryPostfix) {
            return;
        }

        [$operand, $operator] = $file->getChildren($postfix) + [null, null];
        if (
            $operand === null
            || $operator === null
            || !self::isSimple($file->getText($operand))
            || self::inParentheses($file, $operator)
        ) {
            return;
        }

        $symbol = $file->getText($operator);
        $type = $symbol === '++' ? 'increment' : 'decrement';
        $compact = (string) preg_replace(
            '/\s+/',
            replacement: ' ',
            subject: self::withoutComments($file->getText($operand)),
        );
        $this->report->issue(
            $context,
            Issue::new(
                "Stand-alone post-{$type} statement found. Use pre-{$type} instead: {$symbol}{$compact}.",
                $operator->span,
                "post-{$type}",
            )
                ->withHelp("Use `{$symbol}{$compact}`.")
                ->withEdit(TextEdit::delete($operator->span))
                ->withEdit(TextEdit::insert($postfix->span->start, $symbol)),
            [self::SNIFF . '.Post' . ucfirst($type) . 'Found'],
        );
    }

    /**
     * Whether the statement sits inside parentheses, such as in a closure passed as an argument
     * or written in a condition: the sniff skips any token with `nested_parenthesis`. Read from
     * the phpcs tokens the other token-based rules share.
     */
    private static function inParentheses(SourceFile $file, Node $operator): bool
    {
        $tokens = PhpcsTokens::of($file);
        $index = $tokens->indexAt($operator->span->start);

        return $index !== null && $tokens->nested($index) !== [];
    }

    /** Whether the operand holds only the tokens the sniff allows, as in `$a->b[$c]` or `X::$y`. */
    private static function isSimple(string $operand): bool
    {
        $tokens = array_slice(PhpToken::tokenize('<?php ' . $operand), offset: 1);
        $last = null;
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            if ($token->isIgnorable()) {
                continue;
            }

            if ($token->text === '[' && in_array($last, [T_VARIABLE, T_STRING], strict: true)) {
                $i = self::bracketCloser($tokens, $i);
                continue;
            }

            if (!in_array($token->id, self::ALLOWED, strict: true)) {
                return false;
            }

            $last = $token->id;
        }

        return true;
    }

    /**
     * @param list<PhpToken> $tokens
     */
    private static function bracketCloser(array $tokens, int $open): int
    {
        $depth = 0;
        $count = count($tokens);
        for ($i = $open; $i < $count; $i++) {
            $depth += match ($tokens[$i]->text) {
                '[' => 1,
                ']' => -1,
                default => 0,
            };
            if ($depth === 0) {
                return $i;
            }
        }

        return $count;
    }

    private static function withoutComments(string $code): string
    {
        $text = '';
        foreach (array_slice(PhpToken::tokenize('<?php ' . $code), offset: 1) as $token) {
            $text .= in_array($token->id, [T_COMMENT, T_DOC_COMMENT], strict: true) ? '' : $token->text;
        }

        return $text;
    }
}
