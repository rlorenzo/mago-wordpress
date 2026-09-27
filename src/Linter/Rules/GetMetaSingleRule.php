<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\CallExpression;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\Calls;
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Linter\CallRule;

use function array_keys;

/**
 * Ports `WordPress.WP.GetMetaSingle`.
 *
 * Flags calls to the `get_*meta()` functions that pass the key/meta_key
 * parameter but omit `$single`, since that can return an unexpected type.
 */
final class GetMetaSingleRule extends CallRule
{
    private const SNIFF = 'WordPress.WP.GetMetaSingle';

    /**
     * The `$key`/`$meta_key` parameter is at 0-indexed position 1, `$single` at position 2.
     */
    private const SPECIFIC = ['condition' => ['key', 1], 'recommended' => ['single', 2]];

    /**
     * The `$meta_key` parameter is at 0-indexed position 2, `$single` at position 3.
     */
    private const GENERIC = ['condition' => ['meta_key', 2], 'recommended' => ['single', 3]];

    /**
     * Function name => parameter format (condition/recommended => [name, 0-indexed position]).
     *
     * @var array<string, array<string, array{0: string, 1: int}>>
     */
    private const TARGET_FUNCTIONS = [
        'get_comment_meta' => self::SPECIFIC,
        'get_metadata' => self::GENERIC,
        'get_metadata_default' => self::GENERIC,
        'get_metadata_raw' => self::GENERIC,
        'get_post_meta' => self::SPECIFIC,
        'get_site_meta' => self::SPECIFIC,
        'get_term_meta' => self::SPECIFIC,
        'get_user_meta' => self::SPECIFIC,
    ];

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/get-meta-single',
            name: 'Get meta single',
            description: 'Reports calls to get_comment_meta(), get_post_meta(), get_site_meta(), get_term_meta(), get_user_meta(), get_metadata(), get_metadata_default() and get_metadata_raw() that pass the key/meta_key parameter without also passing $single. Omitting it can result in unexpected return types.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::FunctionCall],
        );
    }

    protected function names(): array
    {
        return array_keys(self::TARGET_FUNCTIONS);
    }

    protected function inspect(LintContext $context, CallExpression $call, string $name): void
    {
        // A spread can supply `$single`, so its position is unknown.
        if (Calls::isUnpacked($call)) {
            return;
        }

        [$conditionName, $conditionIndex] = self::TARGET_FUNCTIONS[$name]['condition'];
        if ($this->argument($context, $call, $conditionIndex, $conditionName) === null) {
            return;
        }

        [$recommendedName, $recommendedIndex] = self::TARGET_FUNCTIONS[$name]['recommended'];
        if ($this->argument($context, $call, $recommendedIndex, $recommendedName) !== null) {
            return;
        }

        Report::issue(
            $context,
            Issue::new(
                "Call to {$name}() passes \${$conditionName} without \${$recommendedName}.",
                $context->node->span,
            )->withHelp(
                "Pass the \${$recommendedName} parameter explicitly to indicate whether a single value or "
                . 'multiple values are expected to be returned.',
            ),
            [self::SNIFF . '.Missing'],
        );
    }
}
