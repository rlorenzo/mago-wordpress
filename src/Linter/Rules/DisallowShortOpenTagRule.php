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
use PhpToken;
use Rlorenzo\MagoWordPress\Internal\Report;

use function ctype_space;
use function str_starts_with;
use function substr;

/**
 * Ports `Generic.PHP.DisallowShortOpenTag`: a `<?` open tag (`Found`) and a `<?=` tag that
 * echoes something (`EchoFound`), both fixed as phpcbf does. A `<?= ?>` with nothing to echo
 * is a parse error the sniff leaves alone. The sniff's `PossibleFound` warning, for a `<?`
 * that PHP reads as inline HTML when `short_open_tag` is off, is not ported: Mago always
 * parses `<?` as a short open tag, so it is reported as `Found`.
 */
final class DisallowShortOpenTagRule implements Rule
{
    private const SNIFF = 'Generic.PHP.DisallowShortOpenTag';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/disallow-short-open-tag',
            name: 'Disallow short open tag',
            description: 'Reports the `<?` and `<?=` short open tags; WordPress writes `<?php` and `<?php echo`.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::ShortOpeningTag, NodeKind::EchoTag],
        );
    }

    public function lint(LintContext $context): void
    {
        $node = $context->node;
        $file = $context->file;
        $start = $node->span->start;
        $isEcho = $node->kind === NodeKind::EchoTag;
        $length = $isEcho ? 3 : 2;
        $next = substr($file->contents, $start + $length, 1);
        $glue = $next === '' || ctype_space($next) ? '' : ' ';

        if ($isEcho) {
            $this->reportEcho($context, $start, $glue);
            return;
        }

        $this->report->issue(
            $context,
            Issue::new(
                'Short PHP opening tag used; expected "<?php" but found "<?"',
                new Span($start, $start + $length),
                'short open tag',
            )->withEdit(TextEdit::replace(new Span($start, $start + $length), '<?php' . $glue)),
            [self::SNIFF . '.Found'],
        );
    }

    private function reportEcho(LintContext $context, int $start, string $glue): void
    {
        $file = $context->file;
        if (!str_starts_with(substr($file->contents, $start, 3), '<?=')) {
            return;
        }

        $first = null;
        foreach (PhpToken::tokenize('<?php ' . substr($file->contents, $start + 3)) as $index => $token) {
            if ($index !== 0 && !$token->isIgnorable()) {
                $first = $token;
                break;
            }
        }

        if ($first === null || $first->id === T_CLOSE_TAG) {
            return;
        }

        $this->report->issue(
            $context,
            Issue::new(
                'Short PHP opening tag used with echo; expected "<?php echo '
                . $first->text
                . ' ..." but found "<?= '
                . $first->text
                . ' ..."',
                new Span($start, $start + 3),
                'short echo tag',
            )->withEdit(TextEdit::replace(new Span($start, $start + 3), '<?php echo' . ($glue === '' ? '' : ' '))),
            [self::SNIFF . '.EchoFound'],
        );
    }
}
