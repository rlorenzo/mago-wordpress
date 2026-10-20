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
use Rlorenzo\MagoWordPress\Internal\Layout\LayoutToken;
use Rlorenzo\MagoWordPress\Internal\Layout\PhpcsLayout;
use Rlorenzo\MagoWordPress\Internal\Report;

use function array_key_last;
use function array_keys;
use function array_pop;
use function ceil;
use function floor;
use function in_array;
use function ltrim;
use function max;
use function sprintf;
use function str_repeat;
use function strlen;

/**
 * Ports `Generic.WhiteSpace.ScopeIndent` as WordPress-Core sets it (`exact` false, `indent` 4, `tabIndent` on,
 * heredocs, nowdocs and inline HTML ignored, the tab width 4 of the ruleset): a line must be indented at least
 * as far as the scope it is in, and exactly that for the closing braces and the keywords that open a scope.
 * It works on the phpcs token model ({@see PhpcsLayout}), and follows the sniff statement for statement,
 * `setIndents` and `adjustments` included, so the same lines are reported. The fix puts the indentation phpcbf
 * writes (`adjustIndent()`: tabs, the whole tabs of the width, and the lines of a doc block moved with it); it
 * leaves out the second report-and-fix pass that phpcbf makes.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:halstead
 * @mago-expect lint:no-insecure-comparison
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:excessive-parameter-list
 */
final class ScopeIndentRule implements Rule
{
    private const SNIFF = 'Generic.WhiteSpace.ScopeIndent';

    private const INDENT = 4;

    private const TAB_WIDTH = 4;

    private const IGNORED = ['T_INLINE_HTML' => true, 'T_HEREDOC' => true, 'T_NOWDOC' => true];

    private const METHOD_PREFIXES = [
        'T_PRIVATE' => true,
        'T_PUBLIC' => true,
        'T_PROTECTED' => true,
        'T_ABSTRACT' => true,
        'T_STATIC' => true,
        'T_FINAL' => true,
    ];

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/scope-indent',
            name: 'Scope indent',
            description: 'Reports a line indented less than the scope it is in needs, and a closing brace or scope keyword that is not exactly aligned.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $layout = PhpcsLayout::of($context->file);
        $stackPtr = $layout?->findNext(['T_OPEN_TAG' => true], 0);
        if ($layout === null || $stackPtr === null) {
            return;
        }

        $reports = $this->check($layout, $stackPtr);
        foreach ($reports as $found) {
            [$checkToken, $type, $message, $checkIndent, $tokenIndent] = $found;
            $this->reportLine($context, $layout, $checkToken, $type, $message, $checkIndent, $tokenIndent);
        }

        // Only this rule reads the layout: hand its memory back before the other rules run.
        unset($layout);
        PhpcsLayout::release($context->file);
    }

    private function reportLine(
        LintContext $context,
        PhpcsLayout $layout,
        int $checkToken,
        string $type,
        string $message,
        int $checkIndent,
        int $tokenIndent,
    ): void {
        $tokens = $layout->tokens;
        $token = $tokens[$checkToken];
        // The indentation is the whitespace token before the token, or the start of a token at column 1.
        $before = $token->column === 1 ? null : $tokens[$checkToken - 1];
        if ($before !== null) {
            $start = $before->pos;
            $end = $token->pos;
        } else {
            $start = $token->pos;
            $end =
                $start + strlen($token->origContent ?? $token->content)
                - strlen(ltrim($token->origContent ?? $token->content));
        }

        $span = new Span($start, $end > $start ? $end : $start + 1);
        $issue = Issue::new($message, $span);
        if ($token->code !== 'T_INLINE_HTML') {
            foreach (self::adjustEdits($layout, $checkToken, $checkIndent, $checkIndent - $tokenIndent) as $edit) {
                $issue = $issue->withEdit($edit);
            }
        }

        $this->report->issue($context, $issue, [self::SNIFF . '.' . $type]);
    }

    /**
     * What `adjustIndent()` writes: the indentation of the token and, for a doc block, of its other lines.
     *
     * @return list<TextEdit>
     */
    private static function adjustEdits(PhpcsLayout $layout, int $stackPtr, int $length, int $change): array
    {
        $tokens = $layout->tokens;
        $token = $tokens[$stackPtr];
        $padding = '';
        if ($length > 0) {
            $numTabs = (int) floor($length / self::TAB_WIDTH);
            if ($numTabs > 0) {
                $padding =
                    str_repeat("\t", max(0, $numTabs))
                    . str_repeat(' ', max(0, $length - ($numTabs * self::TAB_WIDTH)));
            }
        }

        $edits = [];
        if ($token->column === 1) {
            $text = $token->origContent ?? $token->content;
            $indent = strlen($text) - strlen(ltrim($text));
            $edits[] = TextEdit::replace(new Span($token->pos, $token->pos + $indent), $padding);
        } else {
            $before = $tokens[$stackPtr - 1];
            $edits[] = TextEdit::replace(
                new Span($before->pos, $before->pos + strlen($before->origContent ?? $before->content)),
                $padding,
            );
        }

        if ($token->code === 'T_DOC_COMMENT_OPEN_TAG') {
            for ($x = $stackPtr + 1; $x < (int) $token->commentCloser; $x++) {
                $inner = $tokens[$x];
                if ($inner->column !== 1) {
                    continue;
                }

                $width = $inner->code === 'T_DOC_COMMENT_WHITESPACE' ? $inner->length : 0;
                $amount = $width + $change;
                $pad = '';
                if ($amount > 0) {
                    $tabs = (int) floor($amount / self::TAB_WIDTH);
                    $pad =
                        str_repeat("\t", max(0, $tabs)) . str_repeat(' ', max(0, $amount - ($tabs * self::TAB_WIDTH)));
                }

                if ($inner->code === 'T_DOC_COMMENT_WHITESPACE') {
                    $edits[] = TextEdit::replace(
                        new Span($inner->pos, $inner->pos + strlen($inner->origContent ?? $inner->content)),
                        $pad,
                    );
                } elseif ($pad !== '') {
                    $edits[] = TextEdit::insert($inner->pos, $pad);
                }
            }
        }

        return $edits;
    }

    /**
     * The sniff's loop.
     *
     * @return list<array{int, string, string, int, int}> token, code, message, expected and found indent
     */
    private function check(PhpcsLayout $layout, int $stackPtr): array
    {
        $tokens = $layout->tokens;
        $numTokens = $layout->count;
        $indent = self::INDENT;
        $found = [];
        $lastOpenTag = $stackPtr;
        $lastCloseTag = null;
        /** @var array<int, int> $openScopes scope closer => its condition */
        $openScopes = [];
        /** @var array<int, int> $adjustments */
        $adjustments = [];
        /** @var array<int, int> $setIndents */
        $setIndents = [];
        /** @var array<int, int> $disableExactStack */
        $disableExactStack = [];
        $disableExactEnd = 0;
        $exactSetting = false;

        $first = $layout->findFirstOnLine(['T_INLINE_HTML' => true], $stackPtr);
        $first = $first === false ? 0 : $first;
        $trimmed = ltrim($tokens[$first]->content);
        if ($trimmed === '') {
            $currentIndent = $tokens[$stackPtr]->column - 1;
        } else {
            $currentIndent = strlen($tokens[$first]->content) - strlen($trimmed);
        }

        $tokenIndent = 0;
        for ($i = $stackPtr + 1; $i < $numTokens; $i++) {
            $token = $tokens[$i];
            if (
                $token->code === 'T_PHPCS_SET'
                && $token->sniffCode === self::SNIFF
                && $token->sniffProperty === 'exact'
            ) {
                $value = $token->sniffPropertyValue;
                $exactSetting = match ($value) {
                    'true' => true,
                    'false' => false,
                    default => (bool) $value,
                };
            }

            $checkToken = null;
            $checkIndent = null;
            $exact = $exactSetting;

            if ($token->code === 'T_OPEN_PARENTHESIS' && $token->parenthesisCloser !== null) {
                $disableExactStack[$token->parenthesisCloser] = $token->parenthesisCloser;
                $disableExactEnd = max($disableExactEnd, $token->parenthesisCloser);
            }

            if ($exact && $i < $disableExactEnd) {
                $exact = false;
            }

            if ($token->column === 1) {
                $trimmed = ltrim($token->content);
                if ($trimmed === '') {
                    if (isset($tokens[$i + 1]) && $token->line === $tokens[$i + 1]->line) {
                        $checkToken = $i + 1;
                        $tokenIndent = $tokens[$i + 1]->column - 1;
                    }
                } else {
                    $checkToken = $i;
                    $tokenIndent = strlen($token->content) - strlen($trimmed);
                }
            }

            $check = $checkToken === null ? null : $tokens[$checkToken];

            if (
                $check !== null && $check->code === 'T_CLOSE_PARENTHESIS' && $check->parenthesisOpener !== null
                || $token->code === 'T_CLOSE_PARENTHESIS' && $token->parenthesisOpener !== null
            ) {
                $parenCloser = $checkToken ?? $i;
                $closerToken = $tokens[$parenCloser];
                $parenOpener = (int) $closerToken->parenthesisOpener;
                if ($closerToken->line !== $tokens[$parenOpener]->line) {
                    $parens = 0;
                    if ($closerToken->nestedParenthesis !== []) {
                        $parens = array_key_last($closerToken->nestedParenthesis);
                    }

                    $condition = 0;
                    if ($closerToken->conditions !== [] && ($closerToken->parenthesisOwner === null || $parens > 0)) {
                        $condition = array_key_last($closerToken->conditions);
                    }

                    if ($parens > $condition) {
                        $parenOpener = $parens;
                        $condition = 0;
                    } elseif ($condition > 0) {
                        $parenOpener = $condition;
                        $parens = 0;
                    }

                    $exact = false;
                    $lastOpenTagConditions = array_keys($tokens[$lastOpenTag]->conditions);
                    $lastOpenTagCondition = array_pop($lastOpenTagConditions);
                    if ($condition > 0 && $lastOpenTagCondition === $condition) {
                        $first = $layout->findFirstOnLine(
                            ['T_WHITESPACE' => true, 'T_INLINE_HTML' => true],
                            $lastOpenTag,
                            true,
                        );
                        $first = $first === false ? 0 : $first;
                        $checkIndent = $tokens[$first]->column - 1;
                        if (isset($adjustments[$condition])) {
                            $checkIndent += $adjustments[$condition];
                        }

                        $currentIndent = $checkIndent;
                    } elseif (
                        $condition > 0
                        && $tokens[$condition]->scopeOpener !== null
                        && isset($setIndents[$tokens[$condition]->scopeOpener])
                    ) {
                        $checkIndent = $setIndents[$tokens[$condition]->scopeOpener];
                        if (isset($adjustments[$condition])) {
                            $checkIndent += $adjustments[$condition];
                        }

                        $currentIndent = $checkIndent;
                    } else {
                        $first = $layout->findFirstOnLine(['T_WHITESPACE' => true], $parenOpener, true);
                        $first = $first === false ? 0 : $first;
                        $checkIndent = $tokens[$first]->column - 1;
                        if (isset($adjustments[$first])) {
                            $checkIndent += $adjustments[$first];
                        }

                        if (
                            $first === $closerToken->parenthesisOpener
                            && $tokens[$first - 1]->line === $tokens[$first]->line
                        ) {
                            $first--;
                        }

                        $prev = $layout->findStartOfStatement($first, 'T_COMMA');
                        if ($prev !== $first) {
                            $first = $layout->findFirstOnLine(
                                ['T_WHITESPACE' => true, 'T_INLINE_HTML' => true],
                                $prev,
                                true,
                            );
                            if ($first !== false) {
                                $prev = $layout->findStartOfStatement($first, 'T_COMMA');
                                $first = $layout->findFirstOnLine(
                                    ['T_WHITESPACE' => true, 'T_INLINE_HTML' => true],
                                    $prev,
                                    true,
                                );
                            } else {
                                $first = $prev;
                            }
                        }

                        $first = $first === false ? 0 : $first;
                        if ($tokens[$first]->scopeCloser !== null && $tokens[$first]->scopeCloser === $first) {
                            if ($tokens[$first]->scopeCondition !== null) {
                                $scopeCloser = $first;
                                $scopeCondition = (int) $tokens[$scopeCloser]->scopeCondition;
                                $first = $layout->findFirstOnLine(['T_WHITESPACE' => true], $scopeCondition, true);
                                $first = $first === false ? 0 : $first;
                                $currentIndent = $tokens[$first]->column - 1;
                                if (isset($adjustments[$first])) {
                                    $currentIndent += $adjustments[$first];
                                }

                                if ($tokens[$scopeCondition]->code !== 'T_CLOSURE') {
                                    $currentIndent = (int) (ceil($currentIndent / $indent) * $indent);
                                }

                                $setIndents[$first] = $currentIndent;
                            }
                        } else {
                            $currentIndent = $tokens[$first]->column - 1;
                            if (isset($adjustments[$first])) {
                                $currentIndent += $adjustments[$first];
                            }

                            $setIndents[$first] = $currentIndent;
                        }
                    }
                }
            }

            if ($token->code === 'T_CLOSE_SHORT_ARRAY' || $check !== null && $check->code === 'T_CLOSE_SHORT_ARRAY') {
                $arrayCloser = $checkToken ?? $i;
                $arrayOpener = (int) $tokens[$arrayCloser]->bracketOpener;
                if ($tokens[$arrayCloser]->line !== $tokens[$arrayOpener]->line) {
                    $first = $layout->findFirstOnLine(['T_WHITESPACE' => true], $arrayOpener, true);
                    $first = $first === false ? 0 : $first;
                    $exact = false;
                    if ($first === $tokens[$arrayCloser]->bracketOpener) {
                        $first--;
                    }

                    $prev = $layout->findStartOfStatement($first, ['T_COMMA' => true, 'T_DOUBLE_ARROW' => true]);
                    if ($prev !== $first) {
                        $first = $layout->findFirstOnLine(['T_WHITESPACE' => true], $prev, true);
                        $first = $first === false ? 0 : $first;
                        $prev = $layout->findStartOfStatement($first, ['T_COMMA' => true, 'T_DOUBLE_ARROW' => true]);
                        $first = $layout->findFirstOnLine(['T_WHITESPACE' => true], $prev, true);
                        $first = $first === false ? 0 : $first;
                    } elseif ($tokens[$first]->code === 'T_WHITESPACE') {
                        $first = $layout->findNext(['T_WHITESPACE' => true], $first + 1, null, true) ?? 0;
                    }

                    $checkIndent = $tokens[$first]->column - 1;
                    if (isset($adjustments[$first])) {
                        $checkIndent += $adjustments[$first];
                    }

                    if ($tokens[$first]->scopeCloser !== null && $tokens[$first]->scopeCloser === $first) {
                        if (isset($setIndents[$first])) {
                            $currentIndent = $setIndents[$first];
                        }
                    } else {
                        $currentIndent = $tokens[$first]->column - 1;
                        if (isset($adjustments[$first])) {
                            $currentIndent += $adjustments[$first];
                        }

                        $setIndents[$first] = $currentIndent;
                    }
                }
            }

            if (
                $check !== null
                && !$exact
                && ($check->conditions !== [] || $check->scopeOpener !== null && $check->scopeOpener === $checkToken)
            ) {
                if ($check->conditions !== []) {
                    $condition = array_key_last($check->conditions);
                } else {
                    $condition = (int) $check->scopeCondition;
                }

                $first = $layout->findFirstOnLine(['T_WHITESPACE' => true], $condition, true);
                $first = $first === false ? 0 : $first;
                if (
                    isset($adjustments[$first])
                    && (
                        $adjustments[$first] < 0
                        && $tokenIndent > $currentIndent
                        || $adjustments[$first] > 0
                        && $tokenIndent < $currentIndent
                    )
                ) {
                    $adjustments[(int) $checkToken] = $adjustments[$first];
                }
            }

            if (
                $check !== null
                && (
                    isset($openScopes[$checkToken])
                    || $check->scopeCondition !== null
                    && $check->scopeCloser !== null
                    && $check->scopeCloser === $checkToken
                    && $check->line !== $tokens[(int) $check->scopeOpener]->line
                )
                || $check === null && isset($openScopes[$i])
            ) {
                $scopeCloser = $checkToken ?? $i;
                array_pop($openScopes);
                if ($tokens[$scopeCloser]->scopeCondition !== null) {
                    $first = $layout->findFirstOnLine(
                        ['T_WHITESPACE' => true, 'T_INLINE_HTML' => true],
                        $tokens[$scopeCloser]->scopeCondition,
                        true,
                    );
                    $first = $first === false ? 0 : $first;
                    while (
                        $tokens[$first]->code === 'T_CONSTANT_ENCAPSED_STRING'
                        && $tokens[$first - 1]->code === 'T_CONSTANT_ENCAPSED_STRING'
                    ) {
                        $first = $layout->findFirstOnLine(['T_WHITESPACE' => true], $first - 1, true);
                        $first = $first === false ? 0 : $first;
                    }

                    $currentIndent = $tokens[$first]->column - 1;
                    if (isset($adjustments[$first])) {
                        $currentIndent += $adjustments[$first];
                    }

                    $setIndents[$scopeCloser] = $currentIndent;
                    if ($tokens[$scopeCloser]->code === 'T_CLOSE_CURLY_BRACKET') {
                        $exact = true;
                    } else {
                        $checkToken = null;
                        $check = null;
                    }
                }
            }

            if ($check !== null && isset(PhpcsLayout::SCOPE_OPENERS[$check->code]) && $check->scopeOpener !== null) {
                $exact = true;
                if ($disableExactEnd > $checkToken) {
                    foreach ($disableExactStack as $disableExactStackEnd) {
                        if ($disableExactStackEnd < $checkToken) {
                            continue;
                        }

                        if ($check->conditions === $tokens[$disableExactStackEnd]->conditions) {
                            $exact = false;
                            break;
                        }
                    }
                }

                $lastOpener = null;
                if ($openScopes !== []) {
                    $lastOpener = $openScopes[array_key_last($openScopes)];
                }

                if (
                    $lastOpener !== null
                    && $tokens[$lastOpener]->scopeCloser !== null
                    && $tokens[$lastOpener]->level === $check->level
                    && $tokens[$lastOpener]->scopeCloser === $check->scopeCloser
                ) {
                    $currentIndent -= $indent;
                    $setIndents[$lastOpener] = $currentIndent;
                }

                if ($check->code === 'T_CLOSURE' && $tokenIndent > $currentIndent) {
                    $checkIndent = (int) (ceil($tokenIndent / $indent) * $indent);
                    $exact = false;
                }
            }

            if (
                $check !== null
                && isset(self::METHOD_PREFIXES[$check->code])
                && $tokens[$checkToken + 1]->code !== 'T_DOUBLE_COLON'
            ) {
                $next = $layout->findNext(PhpcsLayout::EMPTY, $checkToken + 1, null, true);
                if (
                    $next === null
                    || $tokens[$next]->code !== 'T_CLOSURE'
                    && $tokens[$next]->code !== 'T_VARIABLE'
                    && $tokens[$next]->code !== 'T_FN'
                ) {
                    $isMethodPrefix = true;
                    if ($check->nestedParenthesis !== []) {
                        $deepestOpen = array_key_last($check->nestedParenthesis);
                        if (
                            $tokens[$deepestOpen]->parenthesisOwner !== null
                            && $tokens[$tokens[$deepestOpen]->parenthesisOwner]->code === 'T_FUNCTION'
                        ) {
                            $isMethodPrefix = false;
                        }
                    }

                    if ($isMethodPrefix) {
                        $exact = true;
                    }
                }
            }

            if ($check !== null && $check->code === 'T_PROPERTY') {
                $exact = true;
            }

            if ($check !== null && ($check->code === 'T_OPEN_TAG' || $check->code === 'T_OPEN_TAG_WITH_ECHO')) {
                $checkIndent = $check->column - 1;
                if ($check->conditions !== []) {
                    $close = $layout->findPrevious(['T_CLOSE_TAG' => true], $checkToken - 1);
                    if ($close !== null && $check->conditions === $tokens[$close]->conditions) {
                        $lastCondition = array_key_last($check->conditions);
                        $lastOpener = $tokens[$lastCondition]->scopeOpener;
                        $lastCloser = $tokens[$lastCondition]->scopeCloser;
                        if (
                            $lastCloser !== null
                            && $tokens[$lastCloser]->line !== $check->line
                            && $lastOpener !== null
                            && isset($setIndents[$lastOpener])
                        ) {
                            $checkIndent = $setIndents[$lastOpener];
                        }
                    }
                }
            }

            if ($check !== null && $check->code === 'T_CLOSE_TAG') {
                $exact = true;
                $checkIndent = (int) (ceil($currentIndent / $indent) * $indent);
            }

            if ($check !== null && $check->code === 'T_ELSE') {
                $exact = true;
            }

            if (
                $check !== null
                && ($check->code === 'T_OBJECT_OPERATOR' || $check->code === 'T_NULLSAFE_OBJECT_OPERATOR')
                && $exact
            ) {
                $exact = false;
            }

            if ($checkIndent === null) {
                $checkIndent = $currentIndent;
            }

            if (
                $check !== null
                && !isset(self::IGNORED[$check->code])
                && ($tokenIndent !== $checkIndent && $exact || $tokenIndent < $checkIndent && !$exact)
            ) {
                $type = 'IncorrectExact';
                $error = 'Line indented incorrectly; expected ';
                if (!$exact) {
                    $error .= 'at least ';
                    $type = 'Incorrect';
                }

                $expectedTabs = (int) floor($checkIndent / self::TAB_WIDTH);
                $foundTabs = (int) floor($tokenIndent / self::TAB_WIDTH);
                $foundSpaces = $tokenIndent - ($foundTabs * self::TAB_WIDTH);
                if ($foundSpaces > 0) {
                    $error .= $foundTabs > 0
                        ? sprintf('%d tabs, found %d tabs and %d spaces', $expectedTabs, $foundTabs, $foundSpaces)
                        : sprintf('%d tabs, found %d spaces', $expectedTabs, $foundSpaces);
                } else {
                    $error .= sprintf('%d tabs, found %d', $expectedTabs, $foundTabs);
                }

                $adjustments[(int) $checkToken] = $checkIndent - $tokenIndent;
                $found[] = [(int) $checkToken, $type, $error, $checkIndent, $tokenIndent];
            }

            if ($checkToken !== null) {
                $i = $checkToken;
            }

            $token = $tokens[$i];
            if ($token->code === 'T_OPEN_SHORT_ARRAY') {
                $disableExactStack[(int) $token->bracketCloser] = (int) $token->bracketCloser;
                $disableExactEnd = max($disableExactEnd, (int) $token->bracketCloser);
            }

            if ($token->code === 'T_START_HEREDOC' || $token->code === 'T_START_NOWDOC') {
                $i = $layout->findNext(['T_END_HEREDOC' => true, 'T_END_NOWDOC' => true], $i + 1) ?? $numTokens;
                $next = $layout->findNext(PhpcsLayout::EMPTY, $i + 1, null, true);
                if ($next !== null && $tokens[$next]->code === 'T_COMMA') {
                    $i = $next;
                }

                continue;
            }

            if ($token->code === 'T_CONSTANT_ENCAPSED_STRING' || $token->code === 'T_DOUBLE_QUOTED_STRING') {
                $nextNonTextString = $layout->findNext([$token->code => true], $i + 1, null, true);
                if ($nextNonTextString !== null) {
                    $i = $nextNonTextString - 1;
                }

                continue;
            }

            if ($token->code === 'T_DOC_COMMENT_OPEN_TAG') {
                $i = $token->commentCloser ?? $i;
                continue;
            }

            if ($token->code === 'T_OPEN_TAG' || $token->code === 'T_OPEN_TAG_WITH_ECHO') {
                if ($checkToken === null) {
                    $first = $layout->findFirstOnLine(['T_WHITESPACE' => true], $i, true);
                    $first = $first === false ? 0 : $first;
                    $currentIndent = strlen($tokens[$first]->content) - strlen(ltrim($tokens[$first]->content));
                } else {
                    $currentIndent = $token->column - 1;
                }

                $lastOpenTag = $i;
                if (isset($adjustments[$i])) {
                    $currentIndent += $adjustments[$i];
                }

                $currentIndent = (int) (ceil($currentIndent / $indent) * $indent);
                $setIndents[$i] = $currentIndent;
                continue;
            }

            if ($token->code === 'T_CLOSE_TAG') {
                if ($tokens[$lastOpenTag]->line !== $token->line) {
                    $currentIndent = $token->column - 1;
                    $lastCloseTag = $i;
                } elseif ($lastCloseTag === null) {
                    $currentIndent = 0;
                } else {
                    $currentIndent = $tokens[$lastCloseTag]->column - 1;
                }

                if (isset($adjustments[$i])) {
                    $currentIndent += $adjustments[$i];
                }

                $currentIndent = (int) (ceil($currentIndent / $indent) * $indent);
                $setIndents[$i] = $currentIndent;
                continue;
            }

            if ($token->code === 'T_CLOSURE' || $token->code === 'T_ANON_CLASS') {
                $closer = (int) $token->scopeCloser;
                if ($token->line === $tokens[$closer]->line) {
                    $i = $closer;
                    continue;
                }

                $first = $layout->findFirstOnLine(['T_WHITESPACE' => true], $i, true);
                $first = $first === false ? 0 : $first;
                while (
                    $tokens[$first]->code === 'T_CONSTANT_ENCAPSED_STRING'
                    && $tokens[$first - 1]->code === 'T_CONSTANT_ENCAPSED_STRING'
                ) {
                    $first = $layout->findFirstOnLine(['T_WHITESPACE' => true], $first - 1, true);
                    $first = $first === false ? 0 : $first;
                }

                $currentIndent = $tokens[$first]->column - 1 + $indent;
                $openScopes[(int) $token->scopeCloser] = (int) $token->scopeCondition;
                if (isset($adjustments[$first])) {
                    $currentIndent += $adjustments[$first];
                }

                $currentIndent = (int) (floor($currentIndent / $indent) * $indent);
                $i = (int) $token->scopeOpener;
                $setIndents[$i] = $currentIndent;
                continue;
            }

            if ($token->scopeCondition !== null && $token->scopeOpener !== null && $token->scopeOpener === $i) {
                $closer = (int) $token->scopeCloser;
                if ($token->line === $tokens[$closer]->line) {
                    $i = $closer;
                    continue;
                }

                $condition = $tokens[$token->scopeCondition]->code;
                if ($condition === 'T_FN') {
                    $i = $closer;
                    continue;
                }

                if (isset(PhpcsLayout::SCOPE_OPENERS[$condition])) {
                    $currentIndent += $indent;
                    $setIndents[$i] = $currentIndent;
                    $openScopes[(int) $token->scopeCloser] = (int) $token->scopeCondition;
                    continue;
                }
            }

            if (
                $token->scopeCondition !== null
                && $token->scopeCloser === $i
                && in_array($tokens[$token->scopeCondition]->code, ['T_CLOSURE', 'T_ANON_CLASS', 'T_MATCH'], true)
            ) {
                $prev = false;
                $parens = 0;
                if ($token->nestedParenthesis !== []) {
                    $parens = array_key_last($token->nestedParenthesis);
                }

                $condition = 0;
                if ($token->conditions !== []) {
                    $condition = array_key_last($token->conditions);
                }

                if ($parens > 0 && $parens > $condition) {
                    $prev = $layout->findPrevious(PhpcsLayout::EMPTY, $parens - 1, null, true) ?? false;
                    $condition = 0;
                } elseif ($condition > 0) {
                    $prev = $condition;
                    $parens = 0;
                }

                if ($prev === false) {
                    $prev =
                        $layout->findPrevious(
                            ['T_EQUAL' => true, 'T_RETURN' => true],
                            $token->scopeCondition - 1,
                            null,
                            false,
                            true,
                        ) ?? false;
                    if ($prev === false) {
                        $prev = $i;
                    }
                }

                $first = $layout->findFirstOnLine(['T_WHITESPACE' => true], $prev, true);
                $first = $first === false ? 0 : $first;
                $prev = $layout->findStartOfStatement($first);
                if ($prev !== $first) {
                    $first = $layout->findFirstOnLine(['T_WHITESPACE' => true], $prev, true);
                    $first = $first === false ? 0 : $first;
                }

                $currentIndent = $tokens[$first]->column - 1;
                if ($condition > 0) {
                    $currentIndent += $indent;
                }

                if ($tokens[$first]->scopeCloser !== null && $tokens[$first]->scopeCloser === $first) {
                    if ($condition === 0 || (int) $tokens[$condition]->scopeOpener < $first) {
                        $currentIndent = $setIndents[$first] ?? 0;
                    }
                }

                $currentIndent = (int) (ceil($currentIndent / $indent) * $indent);
                $setIndents[$first] = $currentIndent;
            }
        }

        return $found;
    }
}
