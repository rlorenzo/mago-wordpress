<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\Report;

use function explode;
use function preg_match;
use function strlen;
use function strpos;

use const PREG_OFFSET_CAPTURE;

/**
 * Ports `Generic.PHP.DisallowAlternativePHPTags` for PHP 7+: ASP tags (`<%`, `<%=`) and
 * `<script language="php">` were removed in PHP 7.0, so code inside them is sent to the
 * browser as HTML. Reported per inline HTML line, like the sniff. The sniff warns on ASP
 * tags and errors on script tags; one rule has one level, so both report as warnings
 * (`<%` is also common in JavaScript templates).
 */
final class DisallowAlternativePhpTagsRule implements Rule
{
    private const SNIFF = 'Generic.PHP.DisallowAlternativePHPTags';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/disallow-alternative-php-tags',
            name: 'Disallow alternative PHP tags',
            description: 'Reports ASP-style `<%` / `<%=` and `<script language="php">` tags; PHP 7 removed them, so the code inside is output as HTML instead of running.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::Inline],
        );
    }

    public function lint(LintContext $context): void
    {
        $offset = $context->node->span->start;
        foreach (explode("\n", $context->getText()) as $line) {
            $found = self::find($line);
            if ($found !== null) {
                [$at, $tag, $message, $code] = $found;
                $this->report->issue(
                    $context,
                    Issue::new(
                        $message,
                        new Span($offset + $at, $offset + $at + strlen($tag)),
                        'not a PHP tag since PHP 7',
                    )->withHelp('Use `<?php` (or `<?=` for echo).'),
                    [self::SNIFF . '.' . $code],
                );
            }

            $offset += strlen($line) + 1;
        }
    }

    /**
     * @return null|array{int, string, string, string} offset, tag, message, sniff message code
     */
    private static function find(string $line): ?array
    {
        $match = [];
        if (
            preg_match('`(<script (?:[^>]+)?language=[\'"]?php[\'"]?(?:[^>]+)?>)`i', $line, $match, PREG_OFFSET_CAPTURE)
            === 1
        ) {
            return [
                (int) $match[1][1],
                $match[1][0],
                'Script style opening tag used; expected "<?php" but found "' . $match[1][0] . '"',
                'ScriptOpenTagFound',
            ];
        }

        foreach (['<%=' => 'MaybeASPShortOpenTagFound', '<%' => 'MaybeASPOpenTagFound'] as $tag => $code) {
            $at = strpos($line, $tag);
            if ($at !== false) {
                return [$at, $tag, "Possible use of ASP style opening tags detected; found: {$tag}", $code];
            }
        }

        return null;
    }
}
