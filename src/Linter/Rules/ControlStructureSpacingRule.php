<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\PhpcsToken;
use Rlorenzo\MagoWordPress\Internal\PhpcsTokenStream;
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Internal\Spacing;

use function count;
use function in_array;
use function preg_match;
use function sprintf;
use function str_replace;
use function strlen;
use function strpos;
use function strrpos;
use function substr;

/**
 * Ports `WordPress.WhiteSpace.ControlStructureSpacing` as WordPress-Core configures it: the
 * spaces around a control structure's parentheses and before its brace or alternative-syntax
 * colon, the opening brace on the line of the condition, and no blank line between the end of a
 * control structure and the `}` that closes the one around it (`blank_line_after_check`).
 * The `blank_line_check` property (blank lines at the start and end of a control structure) is
 * off in every WordPress standard and is not ported.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class ControlStructureSpacingRule implements Rule
{
    private const SNIFF = 'WordPress.WhiteSpace.ControlStructureSpacing';

    private const WITH_PARENTHESES = [
        'T_IF',
        'T_ELSEIF',
        'T_WHILE',
        'T_FOR',
        'T_FOREACH',
        'T_SWITCH',
        'T_CATCH',
        'T_MATCH',
    ];

    private const WITHOUT_PARENTHESES = ['T_DO', 'T_ELSE', 'T_TRY', 'T_FINALLY'];

    /** Owners of a `}` that make the blank line after a control structure an error. */
    private const CONTROL_OWNERS = [
        'T_IF',
        'T_ELSEIF',
        'T_WHILE',
        'T_FOR',
        'T_FOREACH',
        'T_SWITCH',
        'T_CATCH',
        'T_MATCH',
        'T_DECLARE',
    ];

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/control-structure-spacing',
            name: 'Control structure spacing',
            description: 'Requires one space around the parentheses of a control structure and before its brace or colon, the opening brace on the line of the condition, and no blank line before the closing brace of the structure around it.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $tokens = Spacing::tokens($context);
        foreach ($tokens as $index => $token) {
            if (
                in_array($token->code, self::WITH_PARENTHESES, strict: true)
                || in_array($token->code, self::WITHOUT_PARENTHESES, strict: true)
            ) {
                $this->check($context, $tokens, $index);
            }
        }
    }

    /**
     * @param list<PhpcsToken> $t
     */
    private function check(LintContext $context, array $t, int $i): void
    {
        $source = $context->file->contents;
        $keyword = $t[$i];
        $code = $keyword->code;
        $end = $keyword->pos + strlen($keyword->text);
        $next = $t[$i + 1] ?? null;
        if ($next !== null && $next->code !== 'T_WHITESPACE' && !($code === 'T_ELSE' && $next->code === 'T_COLON')) {
            $this->add(
                $context,
                $keyword,
                'Space after opening control structure is required',
                'NoSpaceAfterStructureOpen',
                [TextEdit::insert($end, ' ')],
            );
        }

        [$scopeOpener, $scopeCloser] = $this->scope($t, $i);
        if ($scopeOpener === null && $code !== 'T_WHILE') {
            return;
        }

        $alternative = $scopeOpener !== null && $t[$scopeOpener]->code === 'T_COLON';
        if ($alternative && !Spacing::isWs($t, $scopeOpener - 1)) {
            $this->add(
                $context,
                $t[$scopeOpener],
                'Space between opening control structure and T_COLON is required',
                'NoSpaceBetweenStructureColon',
                [TextEdit::insert($t[$scopeOpener]->pos, ' ')],
            );
        }

        $open = PhpcsTokenStream::next($t, $i);
        if ($open === null) {
            return;
        }

        if ($t[$open]->code !== 'T_COLON' && $open === ($i + 1)) {
            $this->add(
                $context,
                $keyword,
                'No space before opening parenthesis is prohibited',
                'NoSpaceBeforeOpenParenthesis',
            );
        }

        if (Spacing::isWs($t, $i + 1)) {
            $piece = Spacing::firstPiece($source, $t[$i + 1]);
            if ($piece !== ' ') {
                $this->add(
                    $context,
                    $keyword,
                    sprintf('Expected exactly one space before opening parenthesis; "%s" found.', self::show($piece)),
                    'ExtraSpaceBeforeOpenParenthesis',
                    [Spacing::replace($t[$i + 1], ' ')],
                );
            }
        }

        $afterOpen = $t[$open + 1] ?? null;
        if ($afterOpen !== null && $afterOpen->code !== 'T_CLOSE_PARENTHESIS') {
            if ($afterOpen->code !== 'T_WHITESPACE') {
                $this->add(
                    $context,
                    $keyword,
                    'No space after opening parenthesis is prohibited',
                    'NoSpaceAfterOpenParenthesis',
                    [TextEdit::insert($t[$open]->pos + strlen($t[$open]->text), ' ')],
                );
            } elseif (!in_array($code, self::WITHOUT_PARENTHESES, strict: true)) {
                $piece = Spacing::firstPiece($source, $afterOpen);
                if (!in_array($piece, [' ', "\n", "\r\n"], strict: true)) {
                    $this->add(
                        $context,
                        $keyword,
                        sprintf(
                            'Expected exactly one space after opening parenthesis; "%s" found.',
                            self::show($piece),
                        ),
                        'ExtraSpaceAfterOpenParenthesis',
                        [self::replaceFirstPiece($afterOpen, ' ')],
                    );
                }
            }
        }

        if ($t[$open]->code !== 'T_OPEN_PARENTHESIS' || $t[$open]->closer === null) {
            $this->blankLineAfter($context, $t, $i, $scopeCloser);

            return;
        }

        $close = $t[$open]->closer;
        if (($t[$open + 1]->code ?? '') !== 'T_CLOSE_PARENTHESIS') {
            $this->checkBeforeClose($context, $t, $i, $close);
            if (!Spacing::isWs($t, $close + 1) && $scopeOpener !== null && !$alternative) {
                $this->add(
                    $context,
                    $t[$scopeOpener],
                    'Space between opening control structure and closing parenthesis is required',
                    'NoSpaceAfterCloseParenthesis',
                    [TextEdit::insert($t[$scopeOpener]->pos, ' ')],
                );
            }
        }

        if ($scopeOpener !== null && $t[$close]->line !== $t[$scopeOpener]->line) {
            $this->add(
                $context,
                $t[$open],
                'Opening brace should be on the same line as the declaration',
                'OpenBraceNotSameLine',
                [TextEdit::replace(new Span($t[$close]->pos + 1, $t[$scopeOpener]->pos), ' ')],
            );

            return;
        }

        if (Spacing::isWs($t, $close + 1)) {
            $piece = Spacing::firstPiece($source, $t[$close + 1]);
            if ($piece !== ' ') {
                $this->add(
                    $context,
                    $keyword,
                    sprintf(
                        'Expected exactly one space between closing parenthesis and opening control structure; "%s" found.',
                        self::show($piece),
                    ),
                    'ExtraSpaceAfterCloseParenthesis',
                    [self::replaceFirstPiece($t[$close + 1], ' ')],
                );
            }
        }

        $this->blankLineAfter($context, $t, $i, $scopeCloser);
    }

    /** @param list<PhpcsToken> $t */
    private function checkBeforeClose(LintContext $context, array $t, int $i, int $close): void
    {
        $before = Spacing::wsBefore($t, $close);
        if ($before === null) {
            $this->add(
                $context,
                $t[$close],
                'No space before closing parenthesis is prohibited',
                'NoSpaceBeforeCloseParenthesis',
                [TextEdit::insert($t[$close]->pos, ' ')],
            );

            return;
        }

        $piece = Spacing::hasNewline($before)
            ? Spacing::lastPiece($before)
            : Spacing::expand($context->file->contents, $before->text, $before->pos);
        if ($piece === ' ') {
            return;
        }

        $previous = PhpcsTokenStream::previous($t, $close - 1);
        if ($previous !== null && $t[$close]->line === ($t[$previous + 1]->line ?? -1)) {
            $this->add(
                $context,
                $t[$i],
                sprintf('Expected exactly one space before closing parenthesis; "%s" found.', self::show($piece)),
                'ExtraSpaceBeforeCloseParenthesis',
                [Spacing::replace($before, ' ')],
            );
        }
    }

    /**
     * `BlankLineAfterEnd`: the `}` that ends a control structure is followed by blank lines
     * before the `}` of the structure around it.
     *
     * @param list<PhpcsToken> $t
     */
    private function blankLineAfter(LintContext $context, array $t, int $i, ?int $scopeCloser): void
    {
        if ($scopeCloser === null) {
            return;
        }

        if ($t[$i]->code === 'T_MATCH') {
            $following = PhpcsTokenStream::next($t, $scopeCloser);
            if ($following !== null && in_array($t[$following]->code, ['T_SEMICOLON', 'T_COMMA'], strict: true)) {
                $scopeCloser = $following;
            }
        }

        $trailing = $this->nextNonWs($t, $scopeCloser);
        if ($trailing === null) {
            return;
        }

        if ($t[$trailing]->code === 'T_COMMENT') {
            $nextCode = PhpcsTokenStream::next($t, $scopeCloser);
            if ($nextCode !== null && in_array($t[$nextCode]->code, ['T_ELSE', 'T_ELSEIF'], strict: true)) {
                $trailing = $nextCode;
            }

            if (
                $t[$trailing]->line === $t[$scopeCloser]->line
                && preg_match('`^//[ ]?end`i', $t[$trailing]->text) === 1
            ) {
                $scopeCloser = $trailing;
                $trailing = $this->nextNonWs($t, $trailing);
                if ($trailing === null) {
                    return;
                }
            }
        }

        $follows = [
            'T_ELSE' => 'T_IF',
            'T_WHILE' => 'T_DO',
            'T_CATCH' => 'T_TRY',
        ];
        $trailingCode = $t[$trailing]->code;
        $owner = $t[$i]->code;
        if (
            ($follows[$trailingCode] ?? null) === $owner
            || $trailingCode === 'T_FINALLY' && in_array($owner, ['T_CATCH', 'T_TRY'], strict: true)
            || $trailingCode !== 'T_CLOSE_CURLY_BRACKET'
            || !$this->closesControlStructure($t, $trailing)
            || ($t[$scopeCloser]->line + 1) === $t[$trailing]->line
        ) {
            return;
        }

        $from = $t[$scopeCloser]->pos + strlen($t[$scopeCloser]->text);
        $source = $context->file->contents;
        $to = $t[$trailing]->pos;
        $newline = strrpos($source, "\n", $to - strlen($source) - 1);
        $edit =
            $t[$scopeCloser]->line === $t[$trailing]->line || $newline === false || $newline < $from
                ? TextEdit::insert($to, "\n")
                : TextEdit::replace(new Span($from, $to), "\n" . substr($source, $newline + 1, $to - $newline - 1));
        $this->add(
            $context,
            $t[$scopeCloser],
            'Blank line found after control structure',
            'BlankLineAfterEnd',
            [$edit],
        );
    }

    /**
     * Whether the `}` closes a control structure (not a function, class or plain block).
     *
     * @param list<PhpcsToken> $t
     */
    private function closesControlStructure(array $t, int $close): bool
    {
        $open = $t[$close]->opener;
        $before = $open === null ? null : PhpcsTokenStream::previous($t, $open);
        if ($before === null) {
            return false;
        }

        if (in_array($t[$before]->code, self::WITHOUT_PARENTHESES, strict: true)) {
            return true;
        }

        $paren = $t[$before]->code === 'T_CLOSE_PARENTHESIS' ? $t[$before]->opener : null;
        $keyword = $paren === null ? null : PhpcsTokenStream::previous($t, $paren);

        return $keyword !== null && in_array($t[$keyword]->code, self::CONTROL_OWNERS, strict: true);
    }

    /**
     * The `{` or alternative-syntax `:` that opens the body, and the `}` that closes it; a body
     * without either has no scope, as in phpcs.
     *
     * @param list<PhpcsToken> $t
     *
     * @return array{null|int, null|int}
     */
    private function scope(array $t, int $i): array
    {
        $k = PhpcsTokenStream::next($t, $i);
        if ($k !== null && in_array($t[$i]->code, self::WITH_PARENTHESES, strict: true)) {
            $close = $t[$k]->code === 'T_OPEN_PARENTHESIS' ? $t[$k]->closer : null;
            $k = $close === null ? null : PhpcsTokenStream::next($t, $close);
        }

        if ($k === null) {
            return [null, null];
        }

        if ($t[$k]->code === 'T_OPEN_CURLY_BRACKET' && $t[$k]->closer !== null) {
            return [$k, $t[$k]->closer];
        }

        return $t[$k]->code === 'T_COLON' ? [$k, null] : [null, null];
    }

    /** @param list<PhpcsToken> $t */
    private function nextNonWs(array $t, int $from): ?int
    {
        for ($k = $from + 1, $count = count($t); $k < $count; $k++) {
            if ($t[$k]->code !== 'T_WHITESPACE') {
                return $k;
            }
        }

        return null;
    }

    /** The span of the whitespace's first piece (up to its first line break), replaced by $with. */
    private static function replaceFirstPiece(PhpcsToken $ws, string $with): TextEdit
    {
        $newline = strpos($ws->text, "\n");
        $length = $newline === false ? strlen($ws->text) : $newline + 1;

        return TextEdit::replace(new Span($ws->pos, $ws->pos + $length), $with);
    }

    private static function show(string $text): string
    {
        return str_replace("\n", '\n', str_replace("\r", '\r', $text));
    }

    /**
     * @param list<TextEdit> $edits
     */
    private function add(LintContext $context, PhpcsToken $at, string $message, string $code, array $edits = []): void
    {
        Spacing::report($this->report, $context, $at, $message, [self::SNIFF . '.' . $code], $edits);
    }
}
