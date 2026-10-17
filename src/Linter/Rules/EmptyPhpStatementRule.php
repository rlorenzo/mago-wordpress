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
use Rlorenzo\MagoWordPress\Internal\Report;

use function preg_match;
use function strlen;
use function substr;

/**
 * Ports the parts of `Generic.CodeAnalysis.EmptyPHPStatement` that Mago's `no-noop` leaves out:
 * an open tag followed by nothing but whitespace and `?>` (`EmptyPHPOpenCloseTagsDetected`),
 * and a `;` straight after `<?=` (`SemicolonWithoutCodeDetected`). A superfluous `;` anywhere
 * else stays with `no-noop`. The fix removes the open tag through the close tag and the
 * newline after it, as phpcbf does.
 */
final class EmptyPhpStatementRule implements Rule
{
    private const SNIFF = 'Generic.CodeAnalysis.EmptyPHPStatement';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/empty-php-statement',
            name: 'Empty PHP statement',
            description: 'Reports an empty `<?php ?>` or `<?= ?>` tag pair, and a `;` right after `<?=`.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::FullOpeningTag, NodeKind::ShortOpeningTag, NodeKind::EchoTag],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $node = $context->node;
        $isEcho = $node->kind === NodeKind::EchoTag;
        if ($isEcho && self::hasExpression($file, $node)) {
            return;
        }

        $start = $node->span->start;
        $contents = $file->contents;
        if ($isEcho && self::semicolonFollows($file, $node)) {
            $this->semicolon($context, $node);
            return;
        }

        $tag = $isEcho ? '<?=' : substr($contents, $start, $node->span->end - $start);
        $offset = $start + strlen($tag);
        if (preg_match('/\G\s*\?>(\r\n|\n|\r)?/', $contents, $match, offset: $offset) !== 1) {
            return;
        }

        $end = $offset + strlen($match[0]);
        $this->report->issue(
            $context,
            Issue::new(
                'Empty PHP open/close tag combination detected.',
                new Span($start, $start + strlen($tag)),
                'empty tags',
            )->withEdit(TextEdit::replace(new Span($start, $end), '')),
            [self::SNIFF . '.EmptyPHPOpenCloseTagsDetected'],
        );
    }

    private function semicolon(LintContext $context, Node $echo): void
    {
        $semicolon = $context->file->getChildren($echo)[0];
        $this->report->issue(
            $context,
            Issue::new(
                'Empty PHP statement detected: superfluous semicolon.',
                $semicolon->span,
                'superfluous semicolon',
            )->withEdit(TextEdit::replace($semicolon->span, '')),
            [self::SNIFF . '.SemicolonWithoutCodeDetected'],
        );
    }

    private static function hasExpression(SourceFile $file, Node $echo): bool
    {
        foreach ($file->getChildren($echo) as $child) {
            if ($child->kind === NodeKind::Expression) {
                return true;
            }
        }

        return false;
    }

    private static function semicolonFollows(SourceFile $file, Node $echo): bool
    {
        $terminator = $file->getChildren($echo)[0] ?? null;

        return (
            $terminator !== null
            && $terminator->kind === NodeKind::Terminator
            && $file->getText($terminator) === ';'
        );
    }
}
