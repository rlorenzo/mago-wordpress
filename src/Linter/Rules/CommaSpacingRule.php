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

use function array_pop;
use function count;
use function in_array;
use function ltrim;
use function sprintf;
use function strlen;
use function trim;

/**
 * Ports `Universal.WhiteSpace.CommaSpacing` as WordPress-Core runs it: no space before a comma
 * and one space (or a line break) after it. WordPress-Core turns off the codes for commas in
 * function declarations, closure `use` lists and calls (the function sniffs own those) and for
 * a trailing comment aligned after a comma; only the commas outside any parentheses or inside
 * array, list, control-structure and `declare` parentheses and attribute blocks are checked,
 * plus a comma written after a trailing comment.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class CommaSpacingRule implements Rule
{
    private const SNIFF = 'Universal.WhiteSpace.CommaSpacing';

    private const OPENERS = ['T_OPEN_CURLY_BRACKET', 'T_OPEN_SQUARE_BRACKET', 'T_OPEN_PARENTHESIS', 'T_OBJECT'];

    private const CLOSERS = [
        'T_CLOSE_CURLY_BRACKET',
        'T_CLOSE_SQUARE_BRACKET',
        'T_CLOSE_PARENTHESIS',
        'T_CLOSE_SHORT_ARRAY',
        'T_CLOSE_USE_GROUP',
    ];

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/comma-spacing',
            name: 'Comma spacing',
            description: 'Disallows whitespace before a comma and requires one space or a line break after it.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $t = Spacing::tokens($context);
        $source = $context->file->contents;
        /** @var list<int> $parens */
        $parens = [];
        /** @var list<int> $attributes */
        $attributes = [];
        foreach ($t as $i => $token) {
            switch ($token->code) {
                case 'T_OPEN_PARENTHESIS':
                    if ($token->closer !== null) {
                        $parens[] = $i;
                    }

                    break;
                case 'T_CLOSE_PARENTHESIS':
                    if ($token->opener !== null) {
                        array_pop($parens);
                    }

                    break;
                case 'T_ATTRIBUTE':
                    $attributes[] = $i;
                    break;
                case 'T_CLOSE_SQUARE_BRACKET':
                    if ($attributes !== [] && $token->opener === $attributes[count($attributes) - 1]) {
                        array_pop($attributes);
                    }

                    break;
                case 'T_COMMA':
                    $this->check($context, $source, $t, $i, $parens, $attributes !== []);
                    break;
            }
        }
    }

    /**
     * @param list<PhpcsToken> $t
     * @param list<int> $parens
     * @mago-expect lint:excessive-parameter-list
     */
    private function check(
        LintContext $context,
        string $source,
        array $t,
        int $i,
        array $parens,
        bool $inAttribute,
    ): void {
        if (!$this->before($context, $source, $t, $i, $parens, $inAttribute)) {
            return;
        }

        $this->after($context, $source, $t, $i, $parens, $inAttribute);
    }

    /**
     * The code suffix of the comma's surroundings; null for the ones WordPress-Core silences.
     *
     * @param list<PhpcsToken> $t
     * @param list<int> $parens
     */
    private function suffix(array $t, array $parens, bool $inAttribute): ?string
    {
        if ($parens === []) {
            return $inAttribute ? 'InAttributeBlock' : '';
        }

        $open = $parens[count($parens) - 1];
        $prev = PhpcsTokenStream::previous($t, $open);
        if (Spacing::isFunctionParenthesis($t, $open)) {
            return null;
        }

        $code = $prev === null ? '' : $t[$prev]->code;
        if ($code === 'T_DECLARE') {
            return 'InDeclare';
        }

        $calls = [
            'T_USE',
            'T_ANON_CLASS',
            'T_ISSET',
            'T_UNSET',
            'T_STRING',
            'T_VARIABLE',
            'T_SELF',
            'T_STATIC',
            'T_PARENT',
        ];

        return in_array($code, $calls, strict: true) ? null : '';
    }

    /**
     * @param list<PhpcsToken> $t
     * @param list<int> $parens
     *
     * @return bool whether to go on with the space after the comma
     * @mago-expect lint:excessive-parameter-list
     */
    private function before(
        LintContext $context,
        string $source,
        array $t,
        int $i,
        array $parens,
        bool $inAttribute,
    ): bool {
        $comma = $t[$i];
        $prevNonWs = $i - 1;
        while ($prevNonWs >= 0 && $t[$prevNonWs]->code === 'T_WHITESPACE') {
            --$prevNonWs;
        }

        $prevNonEmpty = PhpcsTokenStream::previous($t, $i);
        $nextNonEmpty = PhpcsTokenStream::next($t, $i);
        if ($prevNonWs < 0 || $prevNonEmpty === null) {
            return true;
        }

        if (
            $prevNonWs !== $prevNonEmpty
            && $t[$prevNonEmpty]->code !== 'T_COMMA'
            && $nextNonEmpty !== null
            && Spacing::endLine($t[$prevNonEmpty]) !== $t[$nextNonEmpty]->line
        ) {
            $edits = [
                Spacing::replace($comma, ''),
                TextEdit::insert($t[$prevNonEmpty]->pos + strlen($t[$prevNonEmpty]->text), ','),
            ];
            $nextNonWs = $i + 1;
            while (Spacing::isWs($t, $nextNonWs)) {
                ++$nextNonWs;
            }

            if (
                Spacing::isWs($t, $i - 1)
                && !Spacing::hasNewline($t[$i - 1])
                && isset($t[$nextNonWs])
                && $comma->line !== $t[$nextNonWs]->line
            ) {
                $edits[] = Spacing::replace($t[$i - 1], '');
            }

            Spacing::report(
                $this->report,
                $context,
                $comma,
                'Comma found after comment, expected the comma after the end of the code',
                [self::SNIFF . '.CommaAfterComment'],
                $edits,
            );

            return false;
        }

        $prev = $t[$prevNonWs];
        if (
            $prev->code === 'T_COMMA'
            || in_array($prev->code, self::OPENERS, strict: true)
            || in_array($prev->code, ['T_OPEN_SHORT_ARRAY', 'T_OPEN_USE_GROUP', 'T_ATTRIBUTE'], strict: true)
        ) {
            return true;
        }

        $suffix = $this->suffix($t, $parens, $inAttribute);
        if ($suffix === null) {
            return true;
        }

        $expectNewline =
            in_array($t[$prevNonEmpty]->code, ['T_END_HEREDOC', 'T_END_NOWDOC'], strict: true)
            && ltrim($t[$prevNonEmpty]->text) === $t[$prevNonEmpty]->text;
        $found = 0;
        $phrase = 'no spaces';
        if (Spacing::endLine($prev) !== $comma->line) {
            $found = 'newline';
            $phrase = ($prev->line + 1) === $comma->line ? 'a new line' : 'multiple new lines';
        } elseif (($prevNonWs + 1) !== $i) {
            $found = Spacing::width($source, $t[$prevNonWs + 1]);
            $phrase = $found . ($found === 1 ? ' space' : ' spaces');
        }

        $expected = $expectNewline ? 'newline' : 0;
        if ($found === $expected) {
            return true;
        }

        $unfixable = $found === 'newline' && $prev->code === 'T_COMMENT' && !str_ends_with($prev->text, '*/');
        $gap = new Span($prev->pos + strlen($prev->text), $comma->pos);
        $edits = $unfixable ? [] : [TextEdit::replace($gap, $expectNewline ? "\n" : '')];
        Spacing::report(
            $this->report,
            $context,
            $comma,
            sprintf(
                'Expected %s between "%s" and the comma. Found: %s',
                $expectNewline ? 'a new line' : 'no space',
                trim($prev->text),
                $phrase,
            ),
            [self::SNIFF . '.SpaceBefore' . $suffix],
            $edits,
        );

        return true;
    }

    /**
     * @param list<PhpcsToken> $t
     * @param list<int> $parens
     * @mago-expect lint:excessive-parameter-list
     */
    private function after(
        LintContext $context,
        string $source,
        array $t,
        int $i,
        array $parens,
        bool $inAttribute,
    ): void {
        $comma = $t[$i];
        $nextNonWs = $i + 1;
        while (Spacing::isWs($t, $nextNonWs)) {
            ++$nextNonWs;
        }

        if (!isset($t[$nextNonWs])) {
            return;
        }

        $next = $t[$nextNonWs];
        if (
            $next->code === 'T_COMMA'
            || in_array($next->code, self::CLOSERS, strict: true)
            || $next->code === 'T_ATTRIBUTE_END'
        ) {
            return;
        }

        $suffix = $this->suffix($t, $parens, $inAttribute);
        if ($suffix === null) {
            return;
        }

        $end = $comma->pos + strlen($comma->text);
        $message = 'Expected 1 space between the comma and "' . trim($next->text) . '". Found: %s';
        if (Spacing::isWs($t, $i + 1)) {
            $piece = Spacing::firstPiece($source, $t[$i + 1]);
            if ($piece === ' ' || in_array(ltrim($piece, ' '), ["\n", "\r\n"], strict: true)) {
                return;
            }

            $nextNonEmpty = PhpcsTokenStream::next($t, $i);
            if (
                in_array($next->code, ['T_COMMENT', 'T_DOC_COMMENT'], strict: true)
                && ($nextNonEmpty === null || $comma->line !== $t[$nextNonEmpty]->line)
            ) {
                return; // TooMuchSpaceAfterCommaBeforeTrailingComment, which WordPress-Core silences.
            }

            if ($next->line !== $comma->line) {
                $phrase = $next->line === ($comma->line + 1) ? 'a new line' : 'multiple new lines';
            } else {
                $width = Spacing::width($source, $t[$i + 1]);
                if ($width === 1) {
                    return;
                }

                $phrase = $width . ' spaces';
            }

            $this->add($context, $comma, sprintf($message, $phrase), 'TooMuchSpaceAfter' . $suffix, [TextEdit::replace(
                new Span($end, $next->pos),
                ' ',
            )]);

            return;
        }

        $this->add($context, $comma, sprintf($message, 'no spaces'), 'NoSpaceAfter' . $suffix, [TextEdit::insert(
            $end,
            ' ',
        )]);
    }

    /**
     * @param list<TextEdit> $edits
     */
    private function add(LintContext $context, PhpcsToken $at, string $message, string $code, array $edits): void
    {
        Spacing::report($this->report, $context, $at, $message, [self::SNIFF . '.' . $code], $edits);
    }
}
