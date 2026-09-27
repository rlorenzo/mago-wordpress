<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\Calls;
use Rlorenzo\MagoWordPress\Internal\FileGate;
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Internal\WordPress\Lists;

use function array_map;
use function implode;
use function in_array;
use function preg_quote;
use function rtrim;
use function str_starts_with;

/**
 * Ports `WordPress.DB.RestrictedFunctions`.
 *
 * The sniff matches `Lists::DB_RESTRICTED_FUNCTIONS` as prefixes (every
 * entry ends in `_*`), case-insensitively, against the written callee name,
 * excluding the `mysql_to_rfc3339()` alias and any namespaced call (the
 * sniff's own token scan never resolves those either).
 */
final class DbRestrictedFunctionsRule implements Rule
{
    private const SNIFF = 'WordPress.DB.RestrictedFunctions';

    private const ALLOWED = ['mysql_to_rfc3339'];

    private ?FileGate $gate = null;

    /** @var null|list<string> */
    private ?array $prefixes = null;

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/db-restricted-functions',
            name: 'DB restricted function',
            description: 'Reports calls to raw mysql/mysqli/mysqlnd/maxdb extension functions.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::FunctionCall],
        );
    }

    public function lint(LintContext $context): void
    {
        $this->gate ??= new FileGate(pattern: $this->buildGatePattern());
        if (!$this->gate->passes($context->file)) {
            return;
        }

        $name = Calls::name($context->file, $context->node);
        if ($name === null) {
            return;
        }

        $normalized = Calls::normalize($name);
        if (in_array($normalized, self::ALLOWED, strict: true)) {
            return;
        }

        foreach ($this->prefixes() as $prefix) {
            if (!str_starts_with($normalized, $prefix)) {
                continue;
            }

            Report::issue(
                $context,
                Issue::new(
                    "Accessing the database directly through {$name}() should be avoided.",
                    $context->node->span,
                )->withHelp('Use the $wpdb object and its associated methods instead.'),
                [self::SNIFF],
            );

            return;
        }
    }

    /**
     * @return list<string>
     */
    private function prefixes(): array
    {
        return $this->prefixes ??= array_map(static fn(string $pattern): string => rtrim(
            $pattern,
            characters: '*',
        ), Lists::DB_RESTRICTED_FUNCTIONS);
    }

    private function buildGatePattern(): string
    {
        $alternation = implode('|', array_map(static fn(string $prefix): string => preg_quote(
            $prefix,
            delimiter: '/',
        ), $this->prefixes()));

        return "/(?<!\\w)(?:{$alternation})\\w*\\s*\\(/i";
    }
}
