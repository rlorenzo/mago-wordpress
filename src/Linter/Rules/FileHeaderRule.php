<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;
use Mago\Sdk\Syntax\TriviaKind;
use Rlorenzo\MagoWordPress\Internal\LineIndex;
use Rlorenzo\MagoWordPress\Internal\NodeIndex;
use Rlorenzo\MagoWordPress\Internal\Report;

use function array_key_exists;
use function array_keys;
use function array_push;
use function array_search;
use function in_array;
use function preg_match;
use function stripos;
use function strtolower;
use function usort;

/**
 * Ports the grouping and ordering checks of `PSR12.Files.FileHeader` (`IncorrectGrouping`,
 * `IncorrectOrder`): the header of a file is the opening tag, an optional file docblock, `declare`
 * statements, the namespace, then `use`, `use function` and `use const` imports, each kind in one
 * block and in that order. The spacing checks stay with `mago format`. Files with inline HTML are
 * skipped, as the sniff skips files with several PHP blocks.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class FileHeaderRule implements Rule
{
    private const SNIFF = 'PSR12.Files.FileHeader';

    private const ORDER = [
        'tag' => 'opening PHP tag',
        'docblock' => 'file-level docblock',
        'declare' => 'declare statements',
        'namespace' => 'namespace declaration',
        'use' => 'class-based use imports',
        'use function' => 'function-based use imports',
        'use const' => 'constant-based use imports',
    ];

    private const TAGS = [NodeKind::FullOpeningTag, NodeKind::OpeningTag, NodeKind::ShortOpeningTag];

    /** A docblock before one of these documents the declaration, not the file. */
    private const DOCUMENTING = [
        NodeKind::Class_,
        NodeKind::Interface,
        NodeKind::Trait,
        NodeKind::Enum,
        NodeKind::Function,
        NodeKind::Switch,
    ];

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/file-header',
            name: 'File header',
            description: 'Reports file header statements (docblock, `declare`, namespace, imports) that are grouped or ordered incorrectly.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        if (NodeIndex::ofKind($file, $context->node, NodeKind::Inline) !== []) {
            return;
        }

        $lines = self::lines($file, $context->node);
        $found = [];
        $previous = null;
        foreach ($lines as $line) {
            if ($previous !== null && $previous['type'] !== $line['type'] && array_key_exists($line['type'], $found)) {
                $this->report->issue(
                    $context,
                    Issue::new(
                        'Similar statements must be grouped together inside header blocks; the first "'
                        . $line['type']
                        . '" statement was found on line '
                        . self::line($file, $found[$line['type']]->start)
                        . '',
                        $line['span'],
                    ),
                    [self::SNIFF . '.IncorrectGrouping'],
                );
            }

            $found[$line['type']] ??= $line['span'];
            $previous = $line;
        }

        $this->checkOrder($context, $found);
    }

    /** @param array<string, Span> $found */
    private function checkOrder(LintContext $context, array $found): void
    {
        $types = array_keys(self::ORDER);
        $position = 0;
        foreach (array_keys($found) as $type) {
            if ($type === 'tag') {
                continue;
            }

            $index = (int) array_search($type, $types, strict: true);
            if ($index > $position) {
                $position = $index;
                continue;
            }

            $previous = 'tag';
            foreach ($types as $key => $candidate) {
                if ($key >= $index) {
                    break;
                }
                if (array_key_exists($candidate, $found)) {
                    $previous = $candidate;
                }
            }

            $position = $index;
            $this->report->issue(
                $context,
                Issue::new(
                    'The ' . self::ORDER[$type] . ' must follow the ' . self::ORDER[$previous] . ' in the file header',
                    $found[$type],
                ),
                [self::SNIFF . '.IncorrectOrder'],
            );
        }
    }

    /**
     * The header statements in source order.
     *
     * @return list<array{type: string, span: Span}>
     */
    private static function lines(SourceFile $file, Node $program): array
    {
        $statements = self::statements($file, $program);
        $lines = [];
        $codeStart = PHP_INT_MAX;
        foreach ($statements as $statement) {
            $inner = $file->getChildren($statement)[0] ?? null;
            $type = $inner === null ? null : self::type($file, $inner);
            if ($inner !== null && in_array($inner->kind, self::TAGS, strict: true)) {
                continue;
            }

            if ($type === null) {
                $codeStart = $statement->span->start;
                break;
            }

            $lines[] = ['type' => $type, 'span' => $statement->span];
        }

        $docblock = self::docblock($file, $statements, $codeStart);
        if ($docblock !== null) {
            $lines[] = ['type' => 'docblock', 'span' => $docblock];
        }

        usort($lines, static fn(array $a, array $b): int => $a['span']->start <=> $b['span']->start);

        return [['type' => 'tag', 'span' => $program->span], ...$lines];
    }

    /**
     * The top-level statements, looking inside namespaces written without braces.
     *
     * @return list<Node>
     */
    private static function statements(SourceFile $file, Node $parent): array
    {
        $statements = [];
        foreach ($file->getChildren($parent) as $child) {
            if ($child->kind !== NodeKind::Statement) {
                continue;
            }

            $statements[] = $child;
            $inner = $file->getChildren($child)[0] ?? null;
            if ($inner === null || $inner->kind !== NodeKind::Namespace) {
                continue;
            }

            $implicit = $file->getFirstDescendant($inner, NodeKind::NamespaceImplicitBody);
            if ($implicit !== null) {
                array_push($statements, ...self::statements($file, $implicit));
            }
        }

        return $statements;
    }

    private static function type(SourceFile $file, Node $inner): ?string
    {
        if ($inner->kind === NodeKind::Declare) {
            return $file->getFirstDescendant($inner, NodeKind::DeclareColonDelimitedBody) === null
            && $file->getFirstDescendant($inner, NodeKind::Block) === null
                ? 'declare'
                : null;
        }

        if ($inner->kind === NodeKind::Namespace) {
            return $file->getFirstDescendant($inner, NodeKind::NamespaceImplicitBody) !== null ? 'namespace' : null;
        }

        if ($inner->kind !== NodeKind::Use) {
            return null;
        }

        $match = [];
        if (
            preg_match(
                '~^use(?:\s|/\*.*?\*/|(?://|\#)[^\r\n]*)+(function|const)\b(?!\\\\)~is',
                $file->getText($inner),
                $match,
            ) === 1
        ) {
            return 'use ' . strtolower($match[1]);
        }

        return 'use';
    }

    /**
     * The file docblock: the first one before the code starts that is not documenting a
     * declaration and is not an `@var` annotation.
     *
     * @param list<Node> $statements
     */
    private static function docblock(SourceFile $file, array $statements, int $codeStart): ?Span
    {
        foreach ($file->getTrivia() as $trivia) {
            if ($trivia->kind !== TriviaKind::DocBlockComment) {
                continue;
            }

            if ($trivia->span->start >= $codeStart) {
                // A docblock right before the first code statement is that statement's own.
                return null;
            }

            $next = self::following($file, $statements, $trivia->span->end);

            if (
                $next !== null && in_array($next->kind, self::DOCUMENTING, strict: true)
                || stripos($file->getText($trivia->span), needle: '@var') !== false
            ) {
                continue;
            }

            return $trivia->span;
        }

        return null;
    }

    /** @param list<Node> $statements */
    private static function following(SourceFile $file, array $statements, int $offset): ?Node
    {
        foreach ($statements as $statement) {
            if ($statement->span->start >= $offset) {
                return $file->getChildren($statement)[0] ?? $statement;
            }
        }

        return null;
    }

    private static function line(SourceFile $file, int $offset): int
    {
        return LineIndex::of($file, $offset);
    }
}
