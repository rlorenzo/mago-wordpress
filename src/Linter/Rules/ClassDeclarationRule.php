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

use function count;
use function implode;
use function preg_match;
use function sprintf;
use function str_contains;
use function strlen;
use function strtolower;
use function trim;
use function ucfirst;

/**
 * Ports `PSR2.Classes.ClassDeclaration` (with the `PEAR.Classes.ClassDeclaration` it extends) as
 * WordPress-Core sets it: one space between the modifiers and the keyword, after the keyword and
 * the name and around `extends` and `implements`, those keywords on the line of the declaration,
 * a multi-line `implements` list with one name per line indented four spaces, the closing brace on
 * the line after the body and on a line of its own. Fixes what phpcbf fixes. WordPress-Core turns
 * off `OpenBraceNewLine` and `OpenBraceWrongLine`, so a brace on its own line is
 * `generic/opening-brace-same-line`'s to report; the PEAR checks that follow (`OpenBraceNotAlone`,
 * `SpaceBeforeBrace`) are ported. Not ported: `MissingBrace`, a parse error Mago reports itself.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:halstead
 * @mago-expect lint:excessive-parameter-list
 * @mago-expect lint:no-insecure-comparison
 */
final class ClassDeclarationRule implements Rule
{
    private const SNIFF = 'PSR2.Classes.ClassDeclaration';

    private const MODIFIERS = ['T_ABSTRACT' => true, 'T_FINAL' => true, 'T_READONLY' => true];

    /** Spaces a continued `implements` list is indented. */
    private const INDENT = 4;

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/class-declaration',
            name: 'Class declaration',
            description: 'Reports spacing, line breaks and the closing brace of a class, interface, trait or enum declaration that PSR2 does not allow.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        if (preg_match('/\b(?:class|interface|trait|enum)\b/i', $context->file->contents) !== 1) {
            return;
        }

        $layout = Layout::of($context->file);
        $issues = new LayoutIssues($this->report, $context, $layout, self::SNIFF);
        foreach ($layout->classLikes() as $keyword) {
            $opener = $layout->t[$keyword]->scopeOpener;
            if ($opener === null) {
                continue;
            }

            $this->checkBrace($layout, $issues, $keyword, $opener);
            $this->open($layout, $issues, $keyword, $opener);
            $this->close($layout, $issues, $keyword, $opener);
        }
    }

    /**
     * The PEAR sniff, which stops when the brace is on the line of the declaration or too far from
     * it (WordPress-Core leaves those to Generic.Classes.OpeningBraceSameLine).
     */
    private function checkBrace(Layout $layout, LayoutIssues $issues, int $keyword, int $brace): void
    {
        $last = $layout->previous('T_WHITESPACE', $brace - 1, $keyword, exclude: true) ?? $keyword;
        $classLine = $layout->endLine[$last];
        $braceLine = $layout->line($brace);
        if ($braceLine === $classLine || $braceLine > ($classLine + 1)) {
            return;
        }

        $type = strtolower($layout->t[$keyword]->text);
        $after = $brace + 1;
        if (isset($layout->t[$after]) && $layout->content($after) !== $layout->eol) {
            $next = $layout->next('T_WHITESPACE', $after, null, exclude: true);
            $fix = $next !== null && $layout->isPhpcsIgnore($next) ? null : $issues->fix();
            $fix?->newlineAfter($brace);
            $issues->error(
                $brace,
                sprintf('Opening %s brace must be on a line by itself', $type),
                'OpenBraceNotAlone',
                $fix,
            );
        }

        if ($layout->code($brace - 1) === 'T_WHITESPACE') {
            $spaces = $layout->content($brace - 1) === $layout->eol ? 0 : $layout->length[$brace - 1];
            $first = $layout->firstOnLine($keyword, skipWhitespace: true);
            $expected = $layout->column[$first] - 1;
            if ($spaces !== $expected) {
                $fix = $issues->fix();
                $indent = Layout::spaces($expected);
                if ($spaces === 0) {
                    $fix->before($brace, $indent);
                } else {
                    $fix->replace($brace - 1, $indent);
                }

                $issues->error(
                    $brace,
                    sprintf('Expected %d spaces before opening brace; %d found', $expected, $spaces),
                    'SpaceBeforeBrace',
                    $fix,
                );
            }
        }
    }

    private function open(Layout $layout, LayoutIssues $issues, int $keyword, int $brace): void
    {
        $type = strtolower($layout->t[$keyword]->text);
        $this->modifierSpacing($layout, $issues, $keyword, $type);
        $classIndent = $this->classIndent($layout, $keyword);
        $name = $layout->next('T_STRING', $keyword);
        $this->keywordSpacing($layout, $issues, $keyword, $type);
        if ($name !== null && (($layout->t[$name + 2] ?? null)?->line ?? -1) === $layout->line($name)) {
            $gap = strlen($layout->content($name + 1));
            if ($gap !== 1) {
                $fix = $issues->fix();
                $fix->replace($name + 1, ' ');
                $issues->error(
                    $name,
                    sprintf('Expected 1 space after %s name; %d found', $type, $gap),
                    'SpaceAfterName',
                    $fix,
                );
            }
        }

        foreach (['extends' => 'T_EXTENDS', 'implements' => 'T_IMPLEMENTS'] as $word => $code) {
            $this->keywordPlacement($layout, $issues, $keyword, $brace, $word, $code, $type);
        }

        $this->names($layout, $issues, $keyword, $brace, $name, $classIndent);
    }

    private function modifierSpacing(Layout $layout, LayoutIssues $issues, int $keyword, string $type): void
    {
        $prevNonSpace = $layout->previous('T_WHITESPACE', $keyword - 1, null, exclude: true);
        $prevNonEmpty = $layout->previousContent($keyword - 1);
        if ($prevNonEmpty === null || $prevNonSpace === null || !isset(self::MODIFIERS[$layout->code($prevNonEmpty)])) {
            return;
        }

        $spaces = 0;
        $code = 'SpaceBeforeKeyword';
        if ($layout->endLine[$prevNonEmpty] !== $layout->line($keyword)) {
            $spaces = 'newline';
            $code = 'NewlineBeforeKeyword';
        } elseif ($layout->code($keyword - 1) === 'T_WHITESPACE') {
            $spaces = $layout->length[$keyword - 1];
        }

        if ($spaces === 1) {
            return;
        }

        $message = sprintf(
            'Expected 1 space between %s and %s keywords; %s found',
            strtolower($layout->t[$prevNonEmpty]->text),
            $type,
            (string) $spaces,
        );
        if ($prevNonSpace !== $prevNonEmpty) {
            $issues->error($keyword, $message, $code);

            return;
        }

        $fix = $issues->fix();
        if ($spaces === 0) {
            $fix->before($keyword, ' ');
        } else {
            $fix->replace($keyword - 1, ' ');
            for ($i = $keyword - 2; $i > $prevNonSpace; $i--) {
                $fix->replace($i, ' ');
            }
        }

        $issues->error($keyword, $message, $code, $fix);
    }

    private function classIndent(Layout $layout, int $keyword): int
    {
        for ($i = $keyword - 1; $i > 0; $i--) {
            if ($layout->line($i) === $layout->line($keyword)) {
                continue;
            }

            return $layout->code($i + 1) === 'T_WHITESPACE' ? $layout->length[$i + 1] : 0;
        }

        return 0;
    }

    private function keywordSpacing(Layout $layout, LayoutIssues $issues, int $keyword, string $type): void
    {
        $gap = 0;
        if ($layout->code($keyword + 1) === 'T_WHITESPACE') {
            $gap = (($layout->t[$keyword + 2] ?? null)?->line ?? -1) !== $layout->line($keyword)
                ? 'newline'
                : $layout->length[$keyword + 1];
        }

        if ($gap === 1) {
            return;
        }

        $fix = $issues->fix();
        if ($gap === 0) {
            $fix->after($keyword, ' ');
        } else {
            $fix->replace($keyword + 1, ' ');
        }

        $issues->error(
            $keyword,
            sprintf('Expected 1 space after %s keyword; %s found', $type, (string) $gap),
            'SpaceAfterKeyword',
            $fix,
        );
    }

    private function keywordPlacement(
        Layout $layout,
        LayoutIssues $issues,
        int $compare,
        int $brace,
        string $word,
        string $code,
        string $type,
    ): void {
        $keyword = $layout->next($code, $compare + 1, $brace);
        if ($keyword === null) {
            return;
        }

        $codeName = ucfirst($word);
        if ($layout->line($keyword) !== $layout->line($compare)) {
            $fix = $issues->fix();
            $comments = [];
            for ($i = $compare + 1; $i < $keyword; $i++) {
                if ($layout->code($i) === 'T_COMMENT') {
                    $comments[] = trim($layout->t[$i]->text);
                }

                if ($layout->code($i) === 'T_WHITESPACE' || $layout->code($i) === 'T_COMMENT') {
                    $fix->replace($i, ' ');
                }
            }

            $fix->after($compare, ' ');
            if ($comments !== []) {
                $i = $keyword;
                while (isset($layout->t[$i + 1]) && $layout->line($i + 1) === $layout->line($keyword)) {
                    $i++;
                }

                $fix->before($i, ' ' . implode(' ', $comments));
            }

            $issues->error(
                $keyword,
                sprintf('The %s keyword must be on the same line as the %s name', $word, $type),
                $codeName . 'Line',
                $fix,
            );

            return;
        }

        $gap = $layout->length[$keyword - 1];
        if ($gap !== 1) {
            $fix = $issues->fix();
            $fix->replace($keyword - 1, ' ');
            $issues->error(
                $keyword,
                sprintf('Expected 1 space before %s keyword; %d found', $word, $gap),
                'SpaceBefore' . $codeName,
                $fix,
            );
        }
    }

    private function names(
        Layout $layout,
        LayoutIssues $issues,
        int $keyword,
        int $brace,
        ?int $name,
        int $classIndent,
    ): void {
        $listCode = $layout->code($keyword) === 'T_INTERFACE' ? 'T_EXTENDS' : 'T_IMPLEMENTS';
        $implements = $layout->next($listCode, $keyword + 1, $brace);
        $multiLine = false;
        if ($implements !== null) {
            $prev = $layout->previousContent($brace - 1, $implements);
            $multiLine = $prev !== null && $layout->endLine[$prev] !== $layout->line($implements);
        }

        $start = $name ?? $keyword;
        $find = ['T_STRING', $listCode];
        $classNames = [];
        $next = $layout->next($find, $start + 2, $brace - 1);
        while ($next !== null) {
            $classNames[] = $next;
            $next = $layout->next($find, $next + 1, $brace - 1);
        }

        $count = count($classNames);
        $checking = false;
        $implementsToken = null;
        $nextComma = null;
        foreach ($classNames as $n => $className) {
            if ($layout->code($className) === $listCode) {
                $checking = true;
                $implementsToken = $className;

                continue;
            }

            $qualified =
                $layout->code($className - 1) === 'T_NS_SEPARATOR'
                && ($layout->code($className - 2) === 'T_STRING' || $layout->code($className - 2) === 'T_NAMESPACE');
            if ($checking && $multiLine && !$qualified) {
                $this->listItem(
                    $layout,
                    $issues,
                    $className,
                    $implements ?? $keyword,
                    $implementsToken ?? $className,
                    $classIndent,
                    $listCode,
                );
            } elseif (!$qualified) {
                $this->itemSpacing($layout, $issues, $className, $nextComma ?? $className);
            }

            if (
                $checking
                && $layout->code($className + 1) !== 'T_NS_SEPARATOR'
                && $layout->code($className + 1) !== 'T_COMMA'
            ) {
                if ($n !== ($count - 1) && $layout->code($className + 2) !== $listCode) {
                    $fix = $issues->fix();
                    $fix->replace($className + 1, '');
                    $issues->error(
                        $className,
                        sprintf(
                            'Expected 0 spaces between "%s" and comma; %d found',
                            $layout->t[$className]->text,
                            $layout->length[$className + 1],
                        ),
                        'SpaceBeforeComma',
                        $fix,
                    );
                }

                $nextComma = $layout->next('T_COMMA', $className);
            } else {
                $nextComma = $className + 1;
            }
        }
    }

    private function listItem(
        Layout $layout,
        LayoutIssues $issues,
        int $className,
        int $implements,
        int $implementsToken,
        int $classIndent,
        string $listCode,
    ): void {
        $prev = $layout->previous(['T_NS_SEPARATOR', 'T_WHITESPACE'], $className - 1, $implements, exclude: true);
        if ($prev === null) {
            return;
        }

        $extends = $listCode === 'T_EXTENDS';
        $classLine = $layout->line($className);
        $prevLine = $layout->endLine[$prev];
        $first = $prev === $implementsToken && $classLine !== ($prevLine + 1);
        if ($first || !$layout->isComment($prev) && $prevLine !== ($classLine - 1) || $prevLine === $classLine) {
            $message = $first
                ? sprintf(
                    'The first item in a multi-line %1$s list must be on the line following the %1$s keyword',
                    $extends ? 'extends' : 'implements',
                )
                : sprintf(
                    'Only one interface may be specified per line in a multi-line %s declaration',
                    $extends ? 'extends' : 'implements',
                );
            $code = $first
                ? ($extends ? 'FirstExtendsInterfaceSameLine' : 'FirstInterfaceSameLine')
                : ($extends ? 'ExtendsInterfaceSameLine' : 'InterfaceSameLine');
            $fix = $issues->fix();
            for ($i = $prev + 1; $i < $className; $i++) {
                if ($layout->code($i) !== 'T_WHITESPACE') {
                    break;
                }

                $fix->replace($i, '');
            }

            $fix->newlineAfter($prev);
            $issues->error($className, $message, $code, $fix);

            return;
        }

        $space = $layout->previous('T_WHITESPACE', $className - 1, $implements);
        if ($space === null) {
            return;
        }

        $found = $layout->line($space) !== $classLine ? 0 : $layout->length[$space];
        $expected = $classIndent + self::INDENT;
        if ($found === $expected) {
            return;
        }

        $fix = $issues->fix();
        if ($found === 0) {
            $fix->after($space, Layout::spaces($expected));
        } else {
            $fix->replace($space, Layout::spaces($expected));
        }

        $issues->error(
            $className,
            sprintf('Expected %d spaces before interface name; %d found', $expected, $found),
            'InterfaceWrongIndent',
            $fix,
        );
    }

    private function itemSpacing(Layout $layout, LayoutIssues $issues, int $className, int $nextComma): void
    {
        $before = $layout->code($className - 1);
        if ($before === 'T_COMMA' || $before === 'T_NS_SEPARATOR' && $layout->code($className - 2) === 'T_COMMA') {
            $fix = $issues->fix();
            $fix->before($nextComma + 1, ' ');
            $issues->error(
                $nextComma + 1,
                sprintf('Expected 1 space before "%s"; 0 found', $layout->t[$className]->text),
                'NoSpaceBeforeName',
                $fix,
            );

            return;
        }

        $prev = $before === 'T_NS_SEPARATOR' ? $className - 2 : $className - 1;
        $last = $layout->previous('T_WHITESPACE', $prev, null, exclude: true);
        if ($last === null) {
            return;
        }

        $content = '';
        for ($i = $last + 1; $i <= $prev; $i++) {
            $content .= $layout->content($i);
        }

        if ($content === ' ') {
            return;
        }

        $fix = $issues->fix();
        if ($layout->code($prev) === 'T_WHITESPACE') {
            $fix->replace($prev, ' ');
            while ($layout->code(--$prev) === 'T_WHITESPACE') {
                $fix->replace($prev, ' ');
            }
        } else {
            $fix->after($prev, ' ');
        }

        $issues->error(
            $className,
            sprintf('Expected 1 space before "%s"; %d found', $layout->t[$className]->text, strlen($content)),
            'SpaceBeforeName',
            $fix,
        );
    }

    private function close(Layout $layout, LayoutIssues $issues, int $keyword, int $opener): void
    {
        $closer = $layout->t[$keyword]->scopeCloser;
        if ($closer === null) {
            return;
        }

        $prevContent = $layout->previous('T_WHITESPACE', $closer - 1, null, exclude: true);
        if (
            $prevContent !== null
            && $prevContent !== $opener
            && $layout->endLine[$prevContent] !== ($layout->line($closer) - 1)
        ) {
            $fix = $issues->fix();
            for ($i = $prevContent + 1; $layout->line($i) !== $layout->line($closer); $i++) {
                $fix->replace($i, '');
            }

            if (!str_contains($layout->content($prevContent), $layout->eol)) {
                $fix->newlineAfter($prevContent);
            }

            $issues->error(
                $closer,
                sprintf(
                    'The closing brace for the %s must go on the next line after the body',
                    $layout->t[$keyword]->text,
                ),
                'CloseBraceAfterBody',
                $fix,
            );
        }

        $next = $layout->next(['T_WHITESPACE', 'T_COMMENT', 'T_SEMICOLON'], $closer + 1, null, exclude: true);
        if ($next !== null && $layout->line($next) === $layout->line($closer)) {
            $issues->error(
                $closer,
                sprintf('Closing %s brace must be on a line by itself', strtolower($layout->t[$keyword]->text)),
                'CloseBraceSameLine',
            );
        }
    }
}
