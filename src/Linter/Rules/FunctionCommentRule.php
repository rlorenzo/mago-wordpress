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
use Mago\Sdk\Syntax\TriviaKind;
use Rlorenzo\MagoWordPress\Internal\DocComment;
use Rlorenzo\MagoWordPress\Internal\LeadingComment;
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Internal\Strings;

use function count;
use function explode;
use function in_array;
use function ltrim;
use function max;
use function preg_match;
use function preg_replace;
use function sprintf;
use function str_contains;
use function str_repeat;
use function strlen;
use function strrpos;
use function strtolower;
use function strtoupper;
use function substr;
use function trim;
use function usort;

/**
 * Ports `Squiz.Commenting.FunctionComment` as `WordPress-Docs` sets it, for functions and methods
 * (not closures, as in the sniff): a missing docblock, `//` where a docblock belongs, blank lines
 * between the docblock and the declaration, an empty `@see`, the `@return` checks that WordPress
 * keeps (`DuplicateReturn`, `MissingReturnType`, `InvalidReturnVoid`, `InvalidNoReturn`), the `@throws`
 * checks, and every `@param` check: missing tags, name and case mismatches, spacing after the type,
 * full stops, and a type that disagrees with the type hint.
 *
 * `WordPress-Docs` turns off `IncorrectParamVarName`, `InvalidReturn`, `InvalidReturnNotVoid`,
 * `MissingReturn`, `ParamCommentNotCapital`, `SpacingAfterParamName`, `ScalarTypeHintMissing` and
 * `TypeHintMissing`; this rule does not report them. It assumes PHP 8, as the sniff does when
 * phpcs has no `php_version`.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class FunctionCommentRule implements Rule
{
    private const SNIFF = 'Squiz.Commenting.FunctionComment';

    /** `Common::$allowedTypes` of phpcs. */
    private const ALLOWED_TYPES = [
        'array',
        'boolean',
        'float',
        'integer',
        'mixed',
        'object',
        'string',
        'resource',
        'callable',
    ];

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/function-comment',
            name: 'Function comment',
            description: 'Checks the docblock of a function or method: that it exists, and its @param, @return and @throws tags, as WordPress-Docs does.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Function, NodeKind::Method],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $function = $context->node;
        // `Missing` and `WrongStyle` belong to missing-docs, which reports both for functions.
        $comment = LeadingComment::before($file, $function->span->start);
        if ($comment === null || $comment['kind'] !== TriviaKind::DocBlockComment) {
            return;
        }

        $this->spacingAfter($context, $comment['span'], $function);
        $text = $file->getText($comment['span']);
        $tags = DocComment::tags($text, $comment['span']->start);
        foreach ($tags as $tag) {
            if ($tag['name'] === '@see' && $tag['content'] === null) {
                $this->add(
                    $context,
                    'Content missing for @see tag in function comment',
                    self::tagSpan($tag),
                    'EmptySees',
                );
            }
        }

        $this->returns($context, $function, $tags);
        $this->throws($context, $tags);
        $this->params($context, $function, $comment['span'], $tags);
    }

    private function add(LintContext $context, string $message, Span $span, string $code, ?TextEdit $edit = null): void
    {
        $issue = Issue::new($message, $span);
        if ($edit !== null) {
            $issue = $issue->withEdit($edit);
        }

        $this->report->issue($context, $issue, [self::SNIFF . '.' . $code]);
    }

    /** @param array{name: string, offset: int, ...} $tag */
    private static function tagSpan(array $tag): Span
    {
        return new Span($tag['offset'], $tag['offset'] + strlen($tag['name']));
    }

    /** The `function` keyword: the sniff reports there, after the attributes and modifiers. */
    private static function keyword(SourceFile $file, Node $function): Span
    {
        foreach ($file->getChildren($function) as $child) {
            if ($child->kind === NodeKind::Keyword) {
                return $child->span;
            }
        }

        return $function->span;
    }

    private function spacingAfter(LintContext $context, Span $comment, Node $function): void
    {
        $text = $context->file->contents;
        $gap = substr($text, $comment->end, $function->span->start - $comment->end);
        // Anything but whitespace between them (an attribute, a phpcs annotation) is left alone.
        if (trim($gap) !== '') {
            return;
        }

        $blank = null;
        $offset = 0;
        foreach (preg_split('~(?<=\n)~', $gap) ?: [] as $index => $line) {
            if ($index > 0 && str_ends_with($line, "\n")) {
                $blank = $comment->end + $offset;
                break;
            }

            $offset += strlen($line);
        }

        if ($blank === null) {
            return;
        }

        $lineStart = Strings::lastNewlineBefore($text, $function->span->start);
        $declaration = $lineStart === false ? 0 : $lineStart + 1;
        $this->add(
            $context,
            'There must be no blank lines between the function comment and the declaration',
            new Span($blank, $blank + 1),
            'SpacingAfter',
            TextEdit::delete(new Span($blank, $declaration)),
        );
    }

    /**
     * The `@return` checks WordPress-Docs keeps.
     *
     * @param list<array{name: string, offset: int, content: ?string, contentOffset: int, continuation: list<array{text: string, indent: int, offset: int, spaceOffset: int, spaceLength: int}>, spaceBeforeClose: bool}> $tags
     */
    private function returns(LintContext $context, Node $function, array $tags): void
    {
        $return = null;
        foreach ($tags as $tag) {
            if ($tag['name'] !== '@return') {
                continue;
            }

            if ($return !== null) {
                $this->add(
                    $context,
                    'Only 1 @return tag is allowed in a function comment',
                    self::tagSpan($tag),
                    'DuplicateReturn',
                );

                return;
            }

            $return = $tag;
        }

        if ($return === null) {
            return;
        }

        if ($return['content'] === null || $return['content'] === '') {
            $this->add(
                $context,
                'Return type missing for @return tag in function comment',
                self::tagSpan($return),
                'MissingReturnType',
            );

            return;
        }

        $parts = [];
        preg_match('`^((?:\|?(?:array\([^\)]*\)|[\\\\a-z0-9_\[\]]+))*)( .*)?`i', $return['content'], $parts);
        if (!isset($parts[1])) {
            return;
        }

        $type = $parts[1];
        $names = explode('|', $type);
        $first = self::firstReturn($context->file, $function);
        if ($type === 'void') {
            if ($first !== null && !$first['empty']) {
                $this->add(
                    $context,
                    'Function return type is void, but function contains return statement',
                    self::tagSpan($return),
                    'InvalidReturnVoid',
                );
            }

            return;
        }

        if (
            $type !== 'mixed'
            && $type !== 'never'
            && !in_array('void', $names, strict: true)
            && $first === null
            && self::hasBody($context->file, $function)
        ) {
            $this->add(
                $context,
                'Function return type is not void, but function has no return statement',
                self::tagSpan($return),
                'InvalidNoReturn',
            );
        }
    }

    private static function hasBody(SourceFile $file, Node $function): bool
    {
        return self::body($file, $function) !== null;
    }

    private static function body(SourceFile $file, Node $function): ?Node
    {
        foreach ($file->getChildren($function) as $child) {
            if ($child->kind === NodeKind::Block) {
                return $child;
            }

            if ($child->kind === NodeKind::MethodBody) {
                return $file->getFirstDescendant($child, NodeKind::Block);
            }
        }

        return null;
    }

    /**
     * The first `return`, `yield` or `yield from` of the function in source order, not counting
     * those of closures and anonymous classes inside it, and whether it is a bare `return;`.
     *
     * @return null|array{empty: bool}
     */
    private static function firstReturn(SourceFile $file, Node $function): ?array
    {
        $body = self::body($file, $function);
        if ($body === null) {
            return null;
        }

        $first = null;
        foreach ([NodeKind::Return, NodeKind::Yield, NodeKind::YieldFrom] as $kind) {
            foreach ($file->getDescendants($body, $kind) as $node) {
                if (
                    ($first === null || $node->span->start < $first->span->start)
                    && !self::inside($file, $node, $body)
                ) {
                    $first = $node;
                }
            }
        }

        if ($first === null) {
            return null;
        }

        $text = $file->getText($first);
        $keyword = [];
        preg_match('~^(return|yield(?:\s+from)?)~i', $text, $keyword);
        $rest = substr($text, strlen($keyword[0] ?? ''));

        return ['empty' => ltrim($rest) === '' || ltrim($rest)[0] === ';'];
    }

    /** Whether a closure or anonymous class lies between the node and the body. */
    private static function inside(SourceFile $file, Node $node, Node $body): bool
    {
        $parent = $file->getParent($node);
        while ($parent !== null && $parent !== $body) {
            if ($parent->kind === NodeKind::Closure || $parent->kind === NodeKind::AnonymousClass) {
                return true;
            }

            $parent = $file->getParent($parent);
        }

        return false;
    }

    /**
     * @param list<array{name: string, offset: int, content: ?string, contentOffset: int, continuation: list<array{text: string, indent: int, offset: int, spaceOffset: int, spaceLength: int}>, spaceBeforeClose: bool}> $tags
     */
    private function throws(LintContext $context, array $tags): void
    {
        foreach ($tags as $tag) {
            if ($tag['name'] !== '@throws') {
                continue;
            }

            $exception = null;
            $comment = null;
            $matches = [];
            if ($tag['content'] !== null && preg_match('/([^\s]+)(?:\s+(.*))?/', $tag['content'], $matches) === 1) {
                $exception = $matches[1];
                if (isset($matches[2]) && trim($matches[2]) !== '') {
                    $comment = $matches[2];
                }
            }

            if ($exception === null) {
                $this->add(
                    $context,
                    'Exception type and comment missing for @throws tag in function comment',
                    self::tagSpan($tag),
                    'InvalidThrows',
                );

                continue;
            }

            if ($comment === null) {
                $this->add(
                    $context,
                    'Comment missing for @throws tag in function comment',
                    self::tagSpan($tag),
                    'EmptyThrows',
                );

                continue;
            }

            foreach ($tag['continuation'] as $line) {
                $comment .= ' ' . $line['text'];
            }

            $comment = trim($comment);
            $span = new Span($tag['contentOffset'], $tag['contentOffset'] + strlen($tag['content'] ?? ''));
            if (strtoupper($comment[0]) !== $comment[0]) {
                $this->add($context, '@throws tag comment must start with a capital letter', $span, 'ThrowsNotCapital');
            }

            if (substr($comment, -1) !== '.') {
                $this->add($context, '@throws tag comment must end with a full stop', $span, 'ThrowsNoFullStop');
            }
        }
    }

    /**
     * @param list<array{name: string, offset: int, content: ?string, contentOffset: int, continuation: list<array{text: string, indent: int, offset: int, spaceOffset: int, spaceLength: int}>, spaceBeforeClose: bool}> $tags
     */
    private function params(LintContext $context, Node $function, Span $docblock, array $tags): void
    {
        $params = [];
        $maxType = 0;
        foreach ($tags as $tag) {
            if ($tag['name'] === '@param') {
                $params[] = $this->parseParam($context, $tag, $maxType);
            }
        }

        $real = self::realParams($context->file, $function);
        $keyword = self::keyword($context->file, $function);
        $found = [];
        foreach ($params as $position => $param) {
            if ($param['type'] === '') {
                continue;
            }

            $this->typeHints($context, $param, $real[$position] ?? null, $keyword);
            if ($param['var'] === '') {
                continue;
            }

            $found[] = $param['var'];
            $this->spacingAfterType($context, $param, $maxType);
            $realParam = $real[$position] ?? null;
            if ($realParam !== null) {
                $name = $param['var'];
                if ($name[0] === '&') {
                    $name = substr($name, 1);
                    $found[count($found) - 1] = $name;
                    if (!$realParam['reference'] && $realParam['name'] === $name) {
                        $this->add(
                            $context,
                            sprintf(
                                'Doc comment for parameter %s is prefixed with "&" but parameter is not passed by reference',
                                $name,
                            ),
                            self::tagSpan($param['tag']),
                            'ParamNameUnexpectedAmpersandPrefix',
                        );
                    }
                }

                if ($realParam['name'] !== $name) {
                    $caseOnly = strtolower($name) === strtolower($realParam['name']);
                    $this->add(
                        $context,
                        sprintf(
                            'Doc comment for parameter %s does not match %sactual variable name %s',
                            $name,
                            $caseOnly ? 'case of ' : '',
                            $realParam['name'],
                        ),
                        self::tagSpan($param['tag']),
                        $caseOnly ? 'ParamNameNoCaseMatch' : 'ParamNameNoMatch',
                    );
                }
            } elseif (substr($param['var'], -4) !== ',...') {
                $this->add(
                    $context,
                    'Superfluous parameter comment',
                    self::tagSpan($param['tag']),
                    'ExtraParamComment',
                );
            }

            if ($param['comment'] !== '' && substr($param['comment'], -1) !== '.') {
                $this->add(
                    $context,
                    'Parameter comment must end with a full stop',
                    self::tagSpan($param['tag']),
                    'ParamCommentFullStop',
                );
            }
        }

        foreach ($real as $realParam) {
            if (!in_array($realParam['name'], $found, strict: true)) {
                $this->add(
                    $context,
                    sprintf('Doc comment for parameter "%s" missing', $realParam['name']),
                    new Span($docblock->start, $docblock->start + 3),
                    'MissingParamTag',
                );
            }
        }
    }

    /**
     * One `@param` tag split into type, variable and comment, as the sniff does, reporting the
     * tags that lack a part.
     *
     * @param array{name: string, offset: int, content: ?string, contentOffset: int, continuation: list<array{text: string, indent: int, offset: int, spaceOffset: int, spaceLength: int}>, spaceBeforeClose: bool} $tag
     *
     * @return array{tag: array{name: string, offset: int, content: ?string, contentOffset: int, ...}, type: string, var: string, comment: string, typeSpace: int, varSpace: int, lines: list<array{comment: string, indent: int, spaceOffset: int, spaceLength: int}>}
     */
    private function parseParam(LintContext $context, array $tag, int &$maxType): array
    {
        $param = [
            'tag' => $tag,
            'type' => '',
            'var' => '',
            'comment' => '',
            'typeSpace' => 0,
            'varSpace' => 0,
            'lines' => [],
        ];
        $span = self::tagSpan($tag);
        $content = $tag['content'];
        if ($content === null) {
            $this->add($context, 'Missing parameter type', $span, 'MissingParamType');

            return $param;
        }

        $matches = [];
        $pattern = '/((?:(?![$.]|&(?=\$)).)*)(?:((?:\.\.\.)?(?:\$|&)[^\s]+)(?:(\s+)(.*))?)?/';
        if (preg_match($pattern, $tag['spaceBeforeClose'] ? $content . ' ' : $content, $matches) === 1) {
            $length = strlen($matches[1]);
            $param['type'] = trim($matches[1]);
            $param['typeSpace'] = $length - strlen($param['type']);
            $maxType = max($maxType, strlen($param['type']));
        }

        if ($content[0] === '$') {
            $this->add($context, 'Missing parameter type', $span, 'MissingParamType');

            return $param;
        }

        if (!isset($matches[2])) {
            $this->add($context, 'Missing parameter name', $span, 'MissingParamName');

            return $param;
        }

        $param['var'] = $matches[2];
        if (!isset($matches[4])) {
            $this->add($context, 'Missing parameter comment', $span, 'MissingParamComment');
            $param['lines'][] = ['comment' => '', 'indent' => 0, 'spaceOffset' => 0, 'spaceLength' => 0];

            return $param;
        }

        $param['varSpace'] = strlen($matches[3]);
        $param['comment'] = $matches[4];
        $param['lines'][] = [
            'comment' => $matches[4],
            'indent' => $param['varSpace'],
            'spaceOffset' => 0,
            'spaceLength' => 0,
        ];
        foreach ($tag['continuation'] as $line) {
            $param['comment'] .= ' ' . $line['text'];
            $param['lines'][] = [
                'comment' => $line['text'],
                'indent' => $line['indent'],
                'spaceOffset' => $line['spaceOffset'],
                'spaceLength' => $line['spaceLength'],
            ];
        }

        return $param;
    }

    /**
     * `IncorrectTypeHint` and `InvalidTypeHint`: the documented type against the declared one.
     * (`TypeHintMissing` is off in WordPress-Docs.)
     *
     * @param array{tag: array{name: string, offset: int, ...}, type: string, var: string, ...} $param
     * @param null|array{name: string, reference: bool, hint: string} $real
     */
    private function typeHints(LintContext $context, array $param, ?array $real, Span $keyword): void
    {
        $names = explode('|', $param['type']);
        if (count($names) > 1) {
            return;
        }

        $name = $names[0];
        if ($name === '') {
            return;
        }

        if ($name[0] === '?') {
            $name = substr($name, 1);
        }

        $suggested = self::suggestType($name);
        $hint = '';
        if (str_contains($suggested, 'array') || substr($suggested, -2) === '[]') {
            $hint = 'array';
        } elseif (str_contains($suggested, 'callable') || str_contains($suggested, 'callback')) {
            $hint = 'callable';
        } elseif (!in_array($suggested, self::ALLOWED_TYPES, strict: true)) {
            $hint = $suggested;
        }

        $hint = match ($suggested) {
            'string' => 'string',
            'int', 'integer' => 'int',
            'float' => 'float',
            'bool', 'boolean' => 'bool',
            'object' => 'object',
            'mixed' => 'mixed',
            default => $hint,
        };
        if ($real === null) {
            return;
        }

        $declared = $real['hint'];
        if ($hint !== '' && $param['var'] !== '') {
            $compare = substr($hint, -strlen($declared));
            if ($declared !== '' && $declared !== $compare && $declared !== '?' . $compare) {
                $this->add(
                    $context,
                    sprintf('Expected type hint "%s"; found "%s" for %s', $hint, $declared, $param['var']),
                    $keyword,
                    'IncorrectTypeHint',
                );
            }

            return;
        }

        if ($hint === '' && $declared !== '') {
            $this->add(
                $context,
                sprintf('Unknown type hint "%s" found for %s', $declared, $param['var']),
                $keyword,
                'InvalidTypeHint',
            );
        }
    }

    /**
     * @param array{tag: array{name: string, offset: int, content: ?string, contentOffset: int, ...}, type: string, var: string, comment: string, typeSpace: int, varSpace: int, lines: list<array{comment: string, indent: int, spaceOffset: int, spaceLength: int}>} $param
     */
    private function spacingAfterType(LintContext $context, array $param, int $maxType): void
    {
        $spaces = $maxType - strlen($param['type']) + 1;
        if ($param['typeSpace'] === $spaces) {
            return;
        }

        $tag = $param['tag'];
        $content = $tag['content'] ?? '';
        $new =
            $param['type']
            . str_repeat(' ', max(0, $spaces))
            . $param['var']
            . str_repeat(' ', max(0, $param['varSpace']))
            . ($param['lines'][0]['comment'] ?? '');
        $edit = TextEdit::replace(new Span($tag['contentOffset'], $tag['contentOffset'] + strlen($content)), $new);
        $issue = Issue::new(
            sprintf('Expected %d spaces after parameter type; %d found', $spaces, $param['typeSpace']),
            self::tagSpan($tag),
        )->withEdit($edit);
        foreach ($param['lines'] as $number => $line) {
            if ($number === 0 || $line['indent'] === 0) {
                continue;
            }

            $indent = $line['indent'] - ($param['typeSpace'] - $spaces);
            if ($indent <= 0) {
                continue;
            }

            $issue = $issue->withEdit(TextEdit::replace(
                new Span($line['spaceOffset'], $line['spaceOffset'] + $line['spaceLength']),
                str_repeat(' ', $indent),
            ));
        }

        $this->report->issue($context, $issue, [self::SNIFF . '.SpacingAfterParamType']);
    }

    /**
     * The declared parameters: name (`...$rest` for a variadic), whether passed by reference, and
     * the type hint without whitespace.
     *
     * @return list<array{name: string, reference: bool, hint: string}>
     */
    private static function realParams(SourceFile $file, Node $function): array
    {
        $list = null;
        foreach ($file->getChildren($function) as $child) {
            if ($child->kind === NodeKind::FunctionLikeParameterList) {
                $list = $child;
            }
        }

        $params = [];
        if ($list === null) {
            return $params;
        }

        foreach ($file->getChildren($list) as $param) {
            $cursor = $param->span->start;
            $hint = '';
            $variable = null;
            foreach ($file->getChildren($param) as $child) {
                if ($child->kind === NodeKind::DirectVariable) {
                    $variable = $child;

                    break;
                }

                if ($child->kind === NodeKind::Hint) {
                    $hint = (string) preg_replace('~\s+~', '', $file->getText($child));
                }

                $cursor = $child->span->end;
            }

            if ($variable === null) {
                continue;
            }

            $prefix = substr($file->contents, $cursor, $variable->span->start - $cursor);
            $variadic = str_contains($prefix, '...');
            $params[] = [
                'name' => ($variadic ? '...' : '') . $file->getText($variable),
                'reference' => str_contains($prefix, '&'),
                'hint' => $hint,
            ];
        }

        return $params;
    }

    /** `Common::suggestType()` of phpcs. */
    private static function suggestType(string $type): string
    {
        if ($type === '') {
            return '';
        }

        if (in_array($type, self::ALLOWED_TYPES, strict: true)) {
            return $type;
        }

        $lower = strtolower($type);
        switch ($lower) {
            case 'bool':
            case 'boolean':
                return 'boolean';
            case 'double':
            case 'real':
            case 'float':
                return 'float';
            case 'int':
            case 'integer':
                return 'integer';
            case 'array()':
            case 'array':
                return 'array';
        }

        if (str_contains($lower, 'array(')) {
            $matches = [];
            if (preg_match('/^array\(\s*([^\s^=^>]*)(\s*=>\s*(.*))?\s*\)/i', $type, $matches) === 0) {
                return 'array';
            }

            $first = self::suggestType($matches[1] ?? '');
            $second = self::suggestType($matches[3] ?? '');

            return 'array(' . $first . ($second === '' ? '' : ' => ' . $second) . ')';
        }

        return in_array($lower, self::ALLOWED_TYPES, strict: true) ? $lower : $type;
    }
}
