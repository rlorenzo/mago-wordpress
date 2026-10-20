<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\Layout;
use Rlorenzo\MagoWordPress\Internal\LayoutIssues;
use Rlorenzo\MagoWordPress\Internal\Report;

use function ltrim;
use function rtrim;
use function sprintf;
use function str_contains;
use function stripos;
use function strlen;
use function substr;
use function trim;

/**
 * Ports `Squiz.PHP.EmbeddedPhp` as WordPress-Core sets it: a multi-line `<?php ... ?>` block has
 * its tags on lines of their own (`ContentAfterOpen`, `ContentBeforeOpen`, `ContentBeforeEnd`,
 * `ContentAfterEnd`); a one-line block has one space inside each tag (`SpacingAfterOpen`,
 * `SpacingBeforeClose`) and one statement ending in `;` (`NoSemicolon`, `ShortOpenEchoNoSemicolon`,
 * `MultipleStatements`); a block with nothing in it is `Empty`. Fixes what phpcbf fixes.
 * WordPress-Core turns off `SpacingBefore`, `Indent`, `OpenTagIndent` and `SpacingAfter`, so
 * those are not ported.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:halstead
 * @mago-expect lint:excessive-parameter-list
 */
final class EmbeddedPhpRule implements Rule
{
    private const SNIFF = 'Squiz.PHP.EmbeddedPhp';

    private const OPEN = ['T_OPEN_TAG', 'T_OPEN_TAG_WITH_ECHO'];

    private const ALTERNATIVE_END = [
        'T_ENDIF' => true,
        'T_ENDWHILE' => true,
        'T_ENDFOR' => true,
        'T_ENDFOREACH' => true,
        'T_ENDSWITCH' => true,
        'T_ENDDECLARE' => true,
    ];

    private const COLON_OWNERS = [
        'T_IF' => true,
        'T_ELSEIF' => true,
        'T_WHILE' => true,
        'T_FOR' => true,
        'T_FOREACH' => true,
        'T_SWITCH' => true,
        'T_DECLARE' => true,
    ];

    /** The tokens before a `{` that does not open a scope: a member name, a variable, or a statement start. */
    private const NOT_A_SCOPE = [
        'T_OBJECT_OPERATOR' => true,
        'T_NULLSAFE_OBJECT_OPERATOR' => true,
        'T_DOUBLE_COLON' => true,
        'T_DOLLAR' => true,
        'T_VARIABLE' => true,
        'T_NS_SEPARATOR' => true,
        'T_SEMICOLON' => true,
        'T_OPEN_CURLY_BRACKET' => true,
        'T_CLOSE_CURLY_BRACKET' => true,
        'T_OPEN_TAG' => true,
        'T_OPEN_TAG_WITH_ECHO' => true,
    ];

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/embedded-php',
            name: 'Embedded PHP',
            description: 'Reports PHP tags in templates that are not on a line of their own around multi-line code, not spaced by one space around a one-line statement, or that hold no statement or one without `;`.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        if (!str_contains($context->file->contents, '?>')) {
            return;
        }

        $layout = Layout::of($context->file);
        $issues = new LayoutIssues($this->report, $context, $layout, self::SNIFF);
        $close = null;
        foreach ($layout->t as $i => $token) {
            if ($token->code !== 'T_OPEN_TAG' && $token->code !== 'T_OPEN_TAG_WITH_ECHO') {
                continue;
            }

            if ($close === null || $close < $i) {
                $close = $layout->next('T_CLOSE_TAG', $i) ?? $layout->count;
            }

            $closeTag = $close === $layout->count ? null : $close;
            if ($closeTag === null || $layout->line($i) !== $layout->line($closeTag)) {
                $this->multiline($layout, $issues, $i, $closeTag);
            } else {
                $this->inline($layout, $issues, $i, $closeTag);
            }
        }
    }

    private function multiline(Layout $layout, LayoutIssues $issues, int $open, ?int $closeTag): void
    {
        if ($layout->previous(self::OPEN, $open - 1) === null) {
            return;
        }

        $firstContent = $layout->next('T_WHITESPACE', $open + 1, null, exclude: true);
        if ($firstContent === null) {
            return;
        }

        if ($closeTag !== null) {
            if ($layout->next('T_WHITESPACE', $closeTag + 1, null, exclude: true) === null) {
                return;
            }

            if ($firstContent === $closeTag) {
                $this->empty($layout, $issues, $open, $closeTag);

                return;
            }
        }

        $indent = null;
        if ($layout->line($firstContent) === $layout->line($open)) {
            $fix = $issues->fix();
            $fix->replace($open, rtrim($layout->content($open)));
            $fix->newlineAfter($open);
            $fix->after($open, Layout::spaces($this->lineIndent($layout, $open)));
            if ($layout->code($open + 1) === 'T_WHITESPACE') {
                $fix->replace($open + 1, '');
            }

            $issues->error($open, 'Opening PHP tag must be on a line by itself', 'ContentAfterOpen', $fix);
        } else {
            $indent = $this->lineIndent($layout, $open);
        }

        $this->beforeOpen($layout, $issues, $open);
        if ($closeTag !== null) {
            $this->closeTag($layout, $issues, $open, $closeTag, $firstContent, $indent);
        }
    }

    private function beforeOpen(Layout $layout, LayoutIssues $issues, int $open): void
    {
        $last = $layout->previous('T_WHITESPACE', $open - 1, null, exclude: true);
        if ($last === null || $layout->line($last) !== $layout->line($open)) {
            return;
        }

        $html = $layout->code($last) === 'T_INLINE_HTML' && trim($layout->t[$last]->text) !== '';
        $code = $layout->code($last - 1);
        if (!$html && ($code === 'T_INLINE_HTML' || $layout->line($last - 1) !== $layout->line($open))) {
            return;
        }

        $fix = $issues->fix();
        $fix->before($open, $layout->eol . Layout::spaces($this->lineIndent($layout, $last)));
        if ($layout->code($open - 1) === 'T_INLINE_HTML' && trim($layout->t[$open - 1]->text) === '') {
            $fix->replace($open - 1, '');
        }

        $issues->error($open, 'Opening PHP tag must be on a line by itself', 'ContentBeforeOpen', $fix);
    }

    private function closeTag(
        Layout $layout,
        LayoutIssues $issues,
        int $open,
        int $closeTag,
        int $firstContent,
        ?int $indent,
    ): void {
        $lastContent = $layout->previous('T_WHITESPACE', $closeTag - 1, $open + 1, exclude: true);
        $after = $layout->next('T_WHITESPACE', $closeTag + 1, null, exclude: true);
        if ($lastContent !== null && $layout->endLine[$lastContent] === $layout->line($closeTag)) {
            $closerIndent =
                $layout->line($firstContent) === $layout->line($lastContent) && $indent !== null
                    ? $indent
                    : $this->lineIndent($layout, $closeTag);
            $fix = $issues->fix();
            if ($layout->code($closeTag - 1) === 'T_WHITESPACE') {
                $fix->replace($closeTag - 1, '');
            }

            $fix->before($closeTag, Layout::spaces($closerIndent));
            $fix->newlineBefore($closeTag);
            $issues->error($closeTag, 'Closing PHP tag must be on a line by itself', 'ContentBeforeEnd', $fix);

            return;
        }

        if ($after === null || $layout->line($after) !== $layout->line($closeTag)) {
            return;
        }

        $fix = $issues->fix();
        $fix->newlineAfter($closeTag);
        $fix->after($closeTag, Layout::spaces($this->lineIndent($layout, $closeTag)));
        $code = $layout->code($after);
        if ($code === 'T_INLINE_HTML') {
            $fix->replace($after, ltrim($layout->t[$after]->text));
        } elseif ($code === 'T_OPEN_TAG' || $code === 'T_OPEN_TAG_WITH_ECHO') {
            $fix->replace($after, $layout->t[$after]->text);
        }

        $issues->error($closeTag, 'Closing PHP tag must be on a line by itself', 'ContentAfterEnd', $fix);
    }

    private function inline(Layout $layout, LayoutIssues $issues, int $open, int $closeTag): void
    {
        if ($layout->next('T_WHITESPACE', $open + 1, $closeTag, exclude: true) === null) {
            $this->empty($layout, $issues, $open, $closeTag);

            return;
        }

        $leading = 0;
        $isLong = false;
        if ($layout->code($open) === 'T_OPEN_TAG' && stripos($layout->t[$open]->text, '<?php') === 0) {
            $leading = 1;
            $isLong = true;
        }

        if ($layout->code($open + 1) === 'T_WHITESPACE') {
            $leading += $layout->length[$open + 1];
        }

        if ($leading !== 1) {
            $fix = $issues->fix();
            if ($isLong) {
                $fix->replace($open + 1, '');
            } elseif ($layout->code($open + 1) === 'T_WHITESPACE') {
                $fix->replace($open + 1, ' ');
            } else {
                $fix->after($open, ' ');
            }

            $issues->error(
                $open,
                sprintf('Expected 1 space after opening PHP tag; %d found', $leading),
                'SpacingAfterOpen',
                $fix,
            );
        }

        $this->statement($layout, $issues, $open, $closeTag);
        $this->spaceBeforeClose($layout, $issues, $open, $closeTag);
    }

    private function statement(Layout $layout, LayoutIssues $issues, int $open, int $closeTag): void
    {
        $prev = $layout->previousContent($closeTag - 1, $open);
        if ($prev === null || $prev === $open) {
            return;
        }

        if ($layout->code($prev) !== 'T_SEMICOLON') {
            if (!$this->isScopeEdge($layout, $prev)) {
                $fix = $issues->fix();
                $fix->after($prev, ';');
                $issues->error(
                    $open,
                    'Inline PHP statement must end with a semicolon',
                    $layout->code($open) === 'T_OPEN_TAG_WITH_ECHO' ? 'ShortOpenEchoNoSemicolon' : 'NoSemicolon',
                    $fix,
                );
            }

            return;
        }

        $count = 1;
        for ($i = $open + 1; $i < $prev; $i++) {
            if ($layout->code($i) === 'T_SEMICOLON') {
                $count++;
            }
        }

        if ($count > 1) {
            $issues->error(
                $open,
                sprintf('Inline PHP statement must contain a single statement; %d found', $count),
                'MultipleStatements',
            );
        }
    }

    private function spaceBeforeClose(Layout $layout, LayoutIssues $issues, int $open, int $closeTag): void
    {
        $before = $closeTag - 1;
        $code = $layout->code($before);
        $text = $layout->t[$before]->text;
        $comment = $code === 'T_COMMENT' || $code === 'T_DOC_COMMENT';
        $trailing = 0;
        if ($code === 'T_WHITESPACE') {
            $trailing = $layout->length[$before];
        } elseif ($comment && substr($text, -1) === ' ') {
            $trailing = strlen($text) - strlen(rtrim($text));
        }

        if ($trailing === 1) {
            return;
        }

        $fix = $issues->fix();
        if ($trailing === 0) {
            $fix->before($closeTag, ' ');
        } elseif ($comment) {
            $fix->replace($before, rtrim($text) . ' ');
        } else {
            $fix->replace($before, ' ');
        }

        $issues->error(
            $open,
            sprintf('Expected 1 space before closing PHP tag; %d found', $trailing),
            'SpacingBeforeClose',
            $fix,
        );
    }

    private function empty(Layout $layout, LayoutIssues $issues, int $open, int $closeTag): void
    {
        $fix = $issues->fix();
        for ($i = $open; $i <= $closeTag; $i++) {
            $fix->replace($i, '');
        }

        $before = $layout->t[$open - 1] ?? null;
        $after = $layout->t[$closeTag + 1] ?? null;
        if (
            $after !== null
            && $after->line !== $layout->line($closeTag)
            && $before !== null
            && $before->code === 'T_INLINE_HTML'
            && $before->line === $layout->line($open)
            && $layout->column[$open - 1] === 1
            && trim($before->text) === ''
        ) {
            $fix->replace($open - 1, '');
        }

        $issues->error($open, 'Empty embedded PHP tag found', 'Empty', $fix);
    }

    /** `calculateLineIndent()`: the indent of the line the token is on. */
    private function lineIndent(Layout $layout, int $i): int
    {
        $first = $i;
        while ($first > 0 && $layout->column[$first] !== 1) {
            $first--;
        }

        while (
            $first > 0
            && $layout->code($first) === 'T_COMMENT'
            && $layout->t[$first]->text !== ltrim($layout->t[$first]->text)
        ) {
            $first--;
            while ($first > 0 && $layout->column[$first] !== 1) {
                $first--;
            }
        }

        $code = $layout->code($first);
        if ($code === 'T_WHITESPACE') {
            return ($layout->column[$first + 1] ?? 1) - 1;
        }

        if ($code === 'T_INLINE_HTML' || $code === 'T_END_HEREDOC' || $code === 'T_END_NOWDOC') {
            $content = $layout->content($first);

            return strlen($content) - strlen(ltrim($content));
        }

        return 0;
    }

    /** Whether the token is the opener or the closer of a scope, so a statement may end with it. */
    private function isScopeEdge(Layout $layout, int $i): bool
    {
        $code = $layout->code($i);
        if (isset(self::ALTERNATIVE_END[$code])) {
            return true;
        }

        if ($code === 'T_OPEN_CURLY_BRACKET' || $code === 'T_CLOSE_CURLY_BRACKET') {
            $open = $code === 'T_OPEN_CURLY_BRACKET' ? $i : $layout->t[$i]->opener;
            $before = $open === null ? null : $layout->previousContent($open - 1);

            return $before !== null && !isset(self::NOT_A_SCOPE[$layout->code($before)]);
        }

        return $code === 'T_COLON' && $this->isScopeColon($layout, $i);
    }

    private function isScopeColon(Layout $layout, int $colon): bool
    {
        $before = $layout->previousContent($colon - 1);
        if ($before === null) {
            return false;
        }

        $code = $layout->code($before);
        if ($code === 'T_ELSE') {
            return true;
        }

        if ($code === 'T_CLOSE_PARENTHESIS') {
            $open = $layout->t[$before]->opener;
            $owner = $open === null ? null : $layout->previousContent($open - 1);

            return $owner !== null && isset(self::COLON_OWNERS[$layout->code($owner)]);
        }

        // `case 1:` and `default:`.
        $first = null;
        for ($j = $colon - 1; $j >= 0; $j--) {
            $token = $layout->t[$j];
            if (
                $token->code === 'T_SEMICOLON'
                || $token->code === 'T_OPEN_CURLY_BRACKET'
                || $token->code === 'T_CLOSE_CURLY_BRACKET'
                || $token->code === 'T_COLON'
                || $token->code === 'T_OPEN_TAG'
                || $token->code === 'T_CLOSE_TAG'
            ) {
                break;
            }

            if ($token->opener !== null && $token->code === 'T_CLOSE_PARENTHESIS') {
                $j = $token->opener;
            }

            if (!$layout->isEmpty($j)) {
                $first = $j;
            }
        }

        return $first !== null && ($layout->code($first) === 'T_CASE' || $layout->code($first) === 'T_DEFAULT');
    }
}
