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
use Rlorenzo\MagoWordPress\Internal\ClassReferences;
use Rlorenzo\MagoWordPress\Internal\FileGate;
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Internal\WordPress\Lists;

use function strcasecmp;

/**
 * Ports `WordPress.DB.RestrictedClasses`.
 *
 * The sniff flags `Lists::DB_RESTRICTED_CLASSES` (mysqli, PDO, PDOStatement)
 * used with `new`, `extends`, `implements`, or after `::`, resolved against
 * the current namespace the same way PHP resolves a class name (unlike a
 * function or constant name, which falls back to the global namespace).
 * `self`/`static`/`parent` never resolve to a class name, matching the
 * sniff's own exclusion of them. `exclude` (a WPCS ruleset property to turn
 * off individual groups) has no `Settings` field, so it is not supported;
 * every match always reports.
 */
final class DbRestrictedClassesRule implements Rule
{
    private const SNIFF = 'WordPress.DB.RestrictedClasses';

    private ?FileGate $gate = null;

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/db-restricted-classes',
            name: 'DB restricted class',
            description: 'Reports usage of the mysqli, PDO and PDOStatement classes.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [
                NodeKind::Instantiation,
                NodeKind::Extends,
                NodeKind::Implements,
                NodeKind::StaticMethodCall,
                NodeKind::StaticPropertyAccess,
                NodeKind::ClassConstantAccess,
            ],
        );
    }

    public function lint(LintContext $context): void
    {
        // Every match puts a restricted class name directly in the source.
        $this->gate ??= FileGate::forWords(Lists::DB_RESTRICTED_CLASSES);
        if (!$this->gate->passes($context->file)) {
            return;
        }

        foreach ($this->candidates($context->file, $context->node) as $identifier) {
            $this->reportIfRestricted($context, $identifier);
        }
    }

    /**
     * Returns the class-name identifier nodes a target node references.
     *
     * @return list<Node>
     */
    private function candidates(SourceFile $file, Node $node): array
    {
        return match ($node->kind) {
            NodeKind::Instantiation => ClassReferences::identifier($file, $file->getChildren($node)[1] ?? null),
            NodeKind::Extends, NodeKind::Implements => ClassReferences::heritage($file, $node),
            default => ClassReferences::identifier($file, $file->getChildren($node)[0] ?? null),
        };
    }

    private function reportIfRestricted(LintContext $context, Node $identifier): void
    {
        $name = ClassReferences::globalName($context->file, $identifier);
        if ($name === null) {
            return;
        }

        foreach (Lists::DB_RESTRICTED_CLASSES as $class) {
            if (strcasecmp($name, $class) !== 0) {
                continue;
            }

            $this->report->issue(
                $context,
                Issue::new(
                    "Accessing the database directly through {$class} should be avoided.",
                    $identifier->span,
                )->withHelp('Use the $wpdb object and its associated methods instead.'),
                [self::SNIFF . ".mysql__{$name}"],
            );

            return;
        }
    }
}
