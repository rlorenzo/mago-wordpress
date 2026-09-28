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
use Rlorenzo\MagoWordPress\Internal\DocBlocks;
use Rlorenzo\MagoWordPress\Internal\NodeIndex;
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Internal\Strings;

use function in_array;
use function ltrim;
use function preg_match;
use function sprintf;
use function strtolower;

/**
 * Ports `WordPress.NamingConventions.ValidFunctionName`.
 *
 * `Program` is the only target so every method has its parent chain up to the
 * enclosing class-like in the snapshot.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class ValidFunctionNameRule implements Rule
{
    private const SNIFF = 'WordPress.NamingConventions.ValidFunctionName';

    private const CODE = 'wordpress/valid-function-name';

    /**
     * PHPCSUtils `FunctionDeclarations::$magicMethods`, lowercased.
     */
    private const MAGIC_METHODS = [
        '__construct',
        '__destruct',
        '__call',
        '__callstatic',
        '__get',
        '__set',
        '__isset',
        '__unset',
        '__sleep',
        '__wakeup',
        '__serialize',
        '__unserialize',
        '__tostring',
        '__set_state',
        '__clone',
        '__invoke',
        '__debuginfo',
    ];

    private const CLASS_LIKE_KINDS = [
        NodeKind::Class_,
        NodeKind::AnonymousClass,
        NodeKind::Interface,
        NodeKind::Trait,
        NodeKind::Enum,
    ];

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: self::CODE,
            name: 'Valid function name',
            description: 'Reports function and method names that are not in snake_case, and names prefixed with a double underscore that are not PHP magic methods. Methods of classes that extend a class or implement an interface are skipped, as are PHP 4 style constructors and destructors, functions documented as @deprecated, and names made of underscores only.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $deprecatedStarts = DocBlocks::deprecatedStarts($file);

        foreach ([NodeKind::Function, NodeKind::Method] as $kind) {
            foreach (NodeIndex::ofKind($file, $context->node, $kind) as $function) {
                $this->check($context, $function, $deprecatedStarts);
            }
        }
    }

    /**
     * @param array<int, true> $deprecatedStarts
     */
    private function check(LintContext $context, Node $function, array $deprecatedStarts): void
    {
        $file = $context->file;
        $identifier = null;
        foreach ($file->getChildren($function) as $child) {
            if ($deprecatedStarts[$child->span->start] ?? false) {
                return;
            }

            if ($child->kind === NodeKind::LocalIdentifier) {
                $identifier = $child;
                break;
            }
        }

        if ($identifier === null || ($deprecatedStarts[$function->span->start] ?? false)) {
            return;
        }

        $name = $file->getText($identifier);
        if (ltrim($name, characters: '_') === '') {
            return;
        }

        if ($function->kind === NodeKind::Function) {
            $this->checkFunction($context, $identifier, $name);
            return;
        }

        $member = $file->getParent($function);
        $owner = $member === null ? null : $file->getParent($member);
        if ($owner !== null && in_array($owner->kind, self::CLASS_LIKE_KINDS, strict: true)) {
            $this->checkMethod($context, $identifier, $name, $owner);
        }
    }

    private function checkFunction(LintContext $context, Node $identifier, string $name): void
    {
        if (strtolower($name) === '__autoload') {
            return;
        }

        $this->checkName($context, $identifier, $name, className: null);
    }

    private function checkMethod(LintContext $context, Node $identifier, string $name, Node $owner): void
    {
        $file = $context->file;
        $className = '[Anonymous Class]';
        foreach ($file->getChildren($owner) as $child) {
            if ($child->kind === NodeKind::Extends || $child->kind === NodeKind::Implements) {
                return;
            }

            if ($child->kind === NodeKind::LocalIdentifier) {
                $className = $file->getText($child);
            }
        }

        $lower = strtolower($name);
        $php4Names = $owner->kind === NodeKind::AnonymousClass
            ? []
            : [strtolower($className), '_' . strtolower($className)];
        if (in_array($lower, $php4Names, strict: true) || in_array($lower, self::MAGIC_METHODS, strict: true)) {
            return;
        }

        $this->checkName($context, $identifier, $name, $className);
    }

    /**
     * $className is null for a function, and the class name for a method.
     */
    private function checkName(LintContext $context, Node $identifier, string $name, ?string $className): void
    {
        [$kind, $qualifiedSubject, $subject] = $className === null
            ? ['Function', sprintf('Function name "%s"', $name), sprintf('Function name "%s"', $name)]
            : [
                'Method',
                sprintf('Method name "%s::%s"', $className, $name),
                sprintf('Method name "%s" in class %s', $name, $className),
            ];

        if (preg_match('`^__[^_]`', $name) === 1) {
            $this->report->issue(
                $context,
                Issue::new(
                    $qualifiedSubject
                    . ' is invalid; only PHP magic methods should be prefixed with a double underscore.',
                    $identifier->span,
                )->withHelp('Remove the leading double underscore.'),
                [self::SNIFF . ".{$kind}DoubleUnderscore"],
            );
        }

        $suggested = Strings::snakeCase($name);
        if ($suggested !== $name) {
            $this->report->issue(
                $context,
                Issue::new($subject . ' is not in snake case format.', $identifier->span)->withHelp(sprintf(
                    'Rename it to "%s".',
                    $suggested,
                )),
                [self::SNIFF . ".{$kind}NameInvalid"],
            );
        }
    }
}
