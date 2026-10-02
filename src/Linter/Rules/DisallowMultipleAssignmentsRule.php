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
use Rlorenzo\MagoWordPress\Internal\PhpcsToken;
use Rlorenzo\MagoWordPress\Internal\PhpcsTokenStream;
use Rlorenzo\MagoWordPress\Internal\Report;

use function array_pop;
use function end;
use function in_array;

/**
 * Ports `Squiz.PHP.DisallowMultipleAssignments`: an `=` that is not the first thing in its
 * statement, as in `$a = $b = 1`, `foo( $a = 1 )` or `if ( $a = f() )` (FoundInControlStructure).
 * Like the sniff, assignments in `while` conditions, in the first part of `for`, in parameter
 * defaults and in class bodies (properties, constants) are allowed.
 *
 * ponytail: the sniff's findStartOfStatement() special case for match arms is not reproduced;
 * an assignment directly in a match arm is judged like any other statement.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class DisallowMultipleAssignmentsRule implements Rule
{
    /** What the sniff skips when it looks for the token before the assigned variable. */
    private const ALLOWED = [
        'T_WHITESPACE' => true,
        'T_COMMENT' => true,
        'T_DOC_COMMENT' => true,
        'T_STRING' => true,
        'T_NS_SEPARATOR' => true,
        'T_DOUBLE_COLON' => true,
        'T_ASPERAND' => true,
        'T_DOLLAR' => true,
        'T_SELF' => true,
        'T_PARENT' => true,
        'T_STATIC' => true,
    ];

    /** Tokens that end the previous statement (`File::findStartOfStatement()`). */
    private const STATEMENT_BOUNDARIES = [
        'T_OPEN_CURLY_BRACKET' => true,
        'T_OPEN_SQUARE_BRACKET' => true,
        'T_OPEN_PARENTHESIS' => true,
        'T_OPEN_SHORT_ARRAY' => true,
        'T_OPEN_TAG' => true,
        'T_OPEN_TAG_WITH_ECHO' => true,
        'T_CLOSE_TAG' => true,
        'T_COLON' => true,
        'T_COMMA' => true,
        'T_DOUBLE_ARROW' => true,
        'T_MATCH_ARROW' => true,
        'T_SEMICOLON' => true,
        'T_CLOSE_CURLY_BRACKET' => true,
    ];

    /** Tokens before the variable that make the assignment the start of its statement. */
    private const FIRST_IN_STATEMENT = [
        'T_VARIABLE' => true,
        'T_OPEN_TAG' => true,
        'T_GOTO_LABEL' => true,
        'T_INLINE_THEN' => true,
        'T_INLINE_ELSE' => true,
        'T_SEMICOLON' => true,
        'T_CLOSE_PARENTHESIS' => true,
    ];

    private const CONTROL_STRUCTURES = ['T_IF', 'T_ELSEIF', 'T_SWITCH', 'T_CASE', 'T_FOR', 'T_MATCH'];

    private const OO_SCOPES = ['T_CLASS', 'T_ANON_CLASS', 'T_INTERFACE', 'T_TRAIT', 'T_ENUM'];

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/disallow-multiple-assignments',
            name: 'Disallow multiple assignments',
            description: 'Reports an assignment that is not the first thing in its statement, such as `$a = $b = 1` or an assignment inside an if condition or a function call.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $tokens = AssignmentInConditionRule::tokens($context);
        /** @var list<int> $parens */
        $parens = [];
        /** @var list<int> $braces */
        $braces = [];
        $ooBodies = [];
        foreach ($tokens as $index => $token) {
            if (in_array($token->code, self::OO_SCOPES, strict: true) && $token->scopeOpener !== null) {
                $ooBodies[$token->scopeOpener] = true;
            }

            match ($token->code) {
                'T_OPEN_PARENTHESIS' => $parens[] = $index,
                'T_CLOSE_PARENTHESIS' => array_pop($parens),
                'T_OPEN_CURLY_BRACKET' => $braces[] = $index,
                'T_CLOSE_CURLY_BRACKET' => array_pop($braces),
                default => null,
            };

            // Member var definitions: the innermost scope is a class-like body.
            if ($token->code !== 'T_EQUAL' || $braces !== [] && ($ooBodies[end($braces)] ?? false)) {
                continue;
            }

            $code = $this->code($tokens, $index, $parens);
            if ($code !== null) {
                $this->report->issue(
                    $context,
                    Issue::new(
                        'Assignments must be the first block of code on a line',
                        new Span($token->pos, $token->pos + 1),
                        'assignment',
                    )->withHelp('Assign on its own line, before the expression that uses the value.'),
                    ["Squiz.PHP.DisallowMultipleAssignments.{$code}"],
                );
            }
        }

        // The last reader of the shared tokens (WordPressExtension registers it after
        // generic/assignment-in-condition); free them before the rules that walk the tree.
        AssignmentInConditionRule::releaseTokens($context->file);
    }

    /**
     * The message code for the `=` at $at, or NULL when the sniff allows it.
     *
     * @param list<PhpcsToken> $tokens
     * @param list<int> $parens the `(` that enclose it, outermost first
     */
    private function code(array $tokens, int $at, array $parens): ?string
    {
        if (self::isParameterDefault($tokens, $at)) {
            return null;
        }

        $owners = [];
        foreach ($parens as $open) {
            $owner = PhpcsTokenStream::previous($tokens, $open);
            $owners[] = $owner === null ? '' : $tokens[$owner]->code;
        }

        if (in_array('T_WHILE', $owners, strict: true)) {
            return null;
        }

        $variable = self::assignedVariable($tokens, $at);
        if ($variable === null) {
            return null;
        }

        // The token before the variable, past names, `::`, `@` and `$`.
        $before = $variable - 1;
        while ($before >= 0 && (self::ALLOWED[$tokens[$before]->code] ?? null) !== null) {
            $before--;
        }

        $code = $before < 0 ? 'T_OPEN_TAG' : $tokens[$before]->code;
        $keep = $code === 'T_OPEN_PARENTHESIS' || $code === 'T_OPEN_SQUARE_BRACKET';
        if ((self::STATEMENT_BOUNDARIES[$code] ?? null) !== null && !$keep) {
            // The previous statement ended there, so the variable starts this one.
            return null;
        }

        if ($code === 'T_OPEN_PARENTHESIS') {
            $owner = PhpcsTokenStream::previous($tokens, $before);
            if ($owner !== null && $tokens[$owner]->code === 'T_FOR') {
                return null;
            }
        }

        if ((self::FIRST_IN_STATEMENT[$code] ?? null) !== null) {
            return null;
        }

        foreach ($owners as $owner) {
            if (in_array($owner, self::CONTROL_STRUCTURES, strict: true)) {
                return 'FoundInControlStructure';
            }
        }

        return 'Found';
    }

    /**
     * Whether the `=` is a default value in a function, closure or arrow function's parameters
     * (or those parameters are unclosed): the sniff looks back through the statement, past whole
     * bracket pairs, for the keyword.
     *
     * @param list<PhpcsToken> $tokens
     */
    private static function isParameterDefault(array $tokens, int $at): bool
    {
        for ($i = $at - 1; $i >= 0; $i--) {
            $token = $tokens[$i];
            if ($token->opener !== null) {
                $i = $token->opener;
                continue;
            }

            if ($token->code === 'T_SEMICOLON') {
                return false;
            }

            if (in_array($token->code, ['T_FUNCTION', 'T_CLOSURE', 'T_FN'], strict: true)) {
                $open = PhpcsTokenStream::next($tokens, $i);
                while ($open !== null && $tokens[$open]->code !== 'T_OPEN_PARENTHESIS') {
                    $open = PhpcsTokenStream::next($tokens, $open);
                }

                // Unclosed parameters are a parse error the sniff bows out of.
                $close = $open === null ? null : $tokens[$open]->closer;
                if ($open === null || $close === null) {
                    return true;
                }

                return $open < $at && $at < $close;
            }
        }

        return false;
    }

    /**
     * The variable the `=` assigns to, walking back past brackets and `->`, or NULL when the
     * statement starts first.
     *
     * @param list<PhpcsToken> $tokens
     */
    private static function assignedVariable(array $tokens, int $at): ?int
    {
        for ($i = $at - 1; $i > 0; $i--) {
            $token = $tokens[$i];
            if (in_array($token->code, ['T_SEMICOLON', 'T_OPEN_CURLY_BRACKET', 'T_CLOSE_TAG'], strict: true)) {
                return null;
            }

            if ($token->opener !== null) {
                $i = $token->opener;
                continue;
            }

            if ($token->code !== 'T_VARIABLE') {
                continue;
            }

            $previous = PhpcsTokenStream::previous($tokens, $i);
            if ($previous !== null && $tokens[$previous]->code === 'T_OBJECT_OPERATOR') {
                $i = $previous;
                continue;
            }

            return $i;
        }

        return null;
    }
}
