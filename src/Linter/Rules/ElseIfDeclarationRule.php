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
use Rlorenzo\MagoWordPress\Internal\Report;

use function strlen;
use function trim;

/**
 * Ports `PSR2.ControlStructures.ElseIfDeclaration`: `else if` instead of `elseif`, fixed as
 * phpcbf does. Only whitespace may separate the two keywords; a comment between them hides
 * the `if` from the sniff.
 */
final class ElseIfDeclarationRule implements Rule
{
    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/else-if-declaration',
            name: 'Else if declaration',
            description: 'Reports `else if`, which should be written `elseif`.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::IfStatementBodyElseClause],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        [$else, $statement] = $file->getChildren($context->node) + [null, null];
        $if = $statement === null ? null : $file->getChildren($statement)[0] ?? null;
        if ($else === null || $if === null || $if->kind !== NodeKind::If) {
            return;
        }

        $between = new Span($else->span->end, $if->span->start);
        if (trim($file->getText($between)) !== '') {
            return;
        }

        $this->report->issue(
            $context,
            Issue::new('Usage of ELSE IF is discouraged; use ELSEIF instead', $else->span)->withEdit(TextEdit::replace(
                new Span($else->span->start, $if->span->start + strlen('if')),
                'elseif',
            )),
            ['PSR2.ControlStructures.ElseIfDeclaration.NotAllowed'],
        );
    }
}
