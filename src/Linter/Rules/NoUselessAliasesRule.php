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
use Rlorenzo\MagoWordPress\Internal\Calls;
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Internal\UseImports;

use function sprintf;
use function strcasecmp;
use function strrpos;
use function substr;

/**
 * Ports `Universal.UseStatements.NoUselessAliases`: an import aliased to the name it already has
 * (`use Foo\Bar as Bar;`). Names compare case-insensitively, constants case-sensitively. The fix
 * removes ` as Bar`, unless a comment is in the way.
 */
final class NoUselessAliasesRule implements Rule
{
    private const SNIFF = 'Universal.UseStatements.NoUselessAliases';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/no-useless-aliases',
            name: 'No useless aliases',
            description: 'Reports an import aliased to the name it already has, such as `use Foo\Bar as Bar;`.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Use],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        foreach (UseImports::items($file, $context->node) as [
            'type' => $type,
            'name' => $name,
            'nameNode' => $nameNode,
            'alias' => $alias,
        ]) {
            $aliasName = $alias === null ? null : $file->getFirstDescendant($alias, NodeKind::LocalIdentifier);
            if ($aliasName === null) {
                continue;
            }

            $aliasText = $file->getText($aliasName);
            $position = strrpos($name, needle: '\\');
            $short = $position === false ? $name : substr($name, $position + 1);
            $same = $type === 'const' ? $short === $aliasText : strcasecmp($short, $aliasText) === 0;
            if (!$same) {
                continue;
            }

            $removal = new Span($nameNode->span->end, $aliasName->span->end);
            $issue = Issue::new(
                sprintf('Useless alias "%s" found for import of "%s"', $aliasText, $name),
                $aliasName->span,
                'the alias is the name the import already has',
            )->withHelp('Remove the alias.');
            if (!Calls::hasComment($file, $removal)) {
                $issue = $issue->withEdit(TextEdit::replace($removal, ''));
            }

            $this->report->issue($context, $issue, [self::SNIFF . '.Found']);
        }
    }
}
