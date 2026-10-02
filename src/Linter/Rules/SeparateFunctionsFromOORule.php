<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;
use Rlorenzo\MagoWordPress\Internal\NodeIndex;
use Rlorenzo\MagoWordPress\Internal\Report;

use function count;
use function in_array;
use function substr_count;

/**
 * Ports `Universal.Files.SeparateFunctionsFromOO`: a file that declares both functions and
 * classes, interfaces, traits or enums. Declarations nested in a function, closure or class
 * body do not count; one inside an `if` or a namespace does. Reported once per file, at the
 * later of the first function and the first OO declaration.
 */
final class SeparateFunctionsFromOORule implements Rule
{
    private const OO = [NodeKind::Class_, NodeKind::Interface, NodeKind::Trait, NodeKind::Enum];

    private const SCOPES = [
        NodeKind::Function,
        NodeKind::Method,
        NodeKind::Closure,
        NodeKind::ArrowFunction,
        NodeKind::Class_,
        NodeKind::Interface,
        NodeKind::Trait,
        NodeKind::Enum,
        NodeKind::AnonymousClass,
    ];

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/separate-functions-from-oo',
            name: 'Separate functions from OO',
            description: 'Reports a file that declares both functions and classes, interfaces, traits or enums.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $functions = self::topLevel($file, NodeIndex::ofKind($file, $context->node, NodeKind::Function));
        if ($functions === []) {
            return;
        }

        $structures = self::topLevel($file, NodeIndex::ofKinds($file, $context->node, self::OO));
        if ($structures === []) {
            return;
        }

        $function = self::keyword($file, $functions[0]);
        $structure = self::keyword($file, $structures[0]);
        $at = $function->span->start > $structure->span->start ? $function : $structure;
        $this->report->issue(
            $context,
            Issue::new(
                'A file should either contain function declarations or OO structure declarations, but not both.'
                    . ' Found '
                    . count($functions)
                    . ' function declaration(s) and '
                    . count($structures)
                    . ' OO structure declaration(s). The first function declaration was found on line '
                    . self::line($file, $function)
                    . '; the first OO declaration was found on line '
                    . self::line($file, $structure),
                $at->span,
            ),
            ['Universal.Files.SeparateFunctionsFromOO.Mixed'],
        );
    }

    /**
     * The declarations not nested in another function or OO body.
     *
     * @param list<Node> $nodes
     * @return list<Node>
     */
    private static function topLevel(SourceFile $file, array $nodes): array
    {
        $top = [];
        foreach ($nodes as $node) {
            $parent = $node;
            while (($parent = $file->getParent($parent)) !== null) {
                if (in_array($parent->kind, self::SCOPES, strict: true)) {
                    continue 2;
                }
            }

            $top[] = $node;
        }

        return $top;
    }

    /** The `function`, `class`, ... keyword, where the sniff reports. */
    private static function keyword(SourceFile $file, Node $node): Node
    {
        foreach ($file->getChildren($node) as $child) {
            if ($child->kind === NodeKind::Keyword) {
                return $child;
            }
        }

        return $node;
    }

    private static function line(SourceFile $file, Node $node): int
    {
        return substr_count($file->contents, needle: "\n", offset: 0, length: $node->span->start) + 1;
    }
}
