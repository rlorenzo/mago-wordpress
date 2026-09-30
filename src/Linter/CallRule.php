<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Syntax\CallExpression;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;
use Rlorenzo\MagoWordPress\Internal\Calls;
use Rlorenzo\MagoWordPress\Internal\FileGate;
use Rlorenzo\MagoWordPress\Internal\Values;

use function in_array;
use function strtolower;

/**
 * Base for a rule that reports a call to one of a fixed set of names.
 *
 * @internal
 * @mago-expect lint:cyclomatic-complexity
 */
abstract class CallRule implements Rule
{
    /** @var null|array<string, true> */
    private ?array $wanted = null;

    private ?FileGate $gate = null;

    /**
     * The call names that this rule reports. The match ignores case.
     *
     * @return list<string>
     */
    abstract protected function names(): array;

    /**
     * Checks one matched call. $name is the normalized matched name.
     */
    abstract protected function inspect(LintContext $context, CallExpression $call, string $name): void;

    /**
     * Checks a matched name that is referenced without a call: a
     * `use function` import or a first-class callable such as `foo(...)`.
     * $reference is the `UseItem` or the callable. Only a rule that targets
     * `NodeKind::TypedUseItemSequence` or
     * `NodeKind::FunctionPartialApplication` reaches this, as WPCS's
     * function restriction sniffs report both.
     */
    protected function inspectReference(LintContext $context, Node $reference, string $name): void {}

    public function lint(LintContext $context): void
    {
        $this->gate ??= self::buildGate($this->names());
        if (!$this->gate->passes($context->file)) {
            return;
        }

        // The rule normalizes the wanted set once, not once per node.
        $this->wanted ??= Calls::normalizeAll($this->names());
        $wanted = $this->wanted;

        if ($this->lintReference($context, $wanted)) {
            return;
        }

        $name = Calls::matchWanted($context->file, $context->node, $wanted);
        if ($name === null) {
            return;
        }

        $this->inspect($context, CallExpression::fromNode($context->file, $context->node), $name);
    }

    /**
     * Returns the value of an argument, read positionally or by name.
     *
     * @param string|list<string>|null $parameter
     */
    protected function argument(
        LintContext $context,
        CallExpression $call,
        int $index,
        string|array|null $parameter = null,
    ): ?Node {
        return Calls::argument($context->file, $call, $index, $parameter);
    }

    /**
     * Checks a `use function` statement or a first-class callable, and
     * returns whether the node was one.
     *
     * @param array<string, true> $wanted
     */
    private function lintReference(LintContext $context, array $wanted): bool
    {
        $kind = $context->node->kind;
        if ($kind !== NodeKind::TypedUseItemSequence && $kind !== NodeKind::FunctionPartialApplication) {
            return false;
        }

        $reference = self::reference($context->file, $context->node);
        $name = $reference === null ? null : self::referencedName($context->file, $reference);
        if ($reference !== null && $name !== null && ($wanted[$name] ?? false)) {
            $this->inspectReference($context, $reference, $name);
        }

        return true;
    }

    /**
     * The callable itself, or the first item of a `use function` statement.
     * WPCS checks only the name right after the `function` keyword, so a
     * later item in `use function a, b;` is skipped.
     */
    private static function reference(SourceFile $file, Node $node): ?Node
    {
        if ($node->kind === NodeKind::FunctionPartialApplication) {
            return $node;
        }

        [$type, $item] = $file->getChildren($node) + [null, null];

        return $type !== null && strtolower($file->getText($type)) === 'function' ? $item : null;
    }

    /**
     * The normalized global function name a reference names, or NULL. An
     * import counts only when it names an unqualified function, as in WPCS.
     * A callable resolves like a call: an imported name wins over the
     * written one, and a `namespace\` relative name never matches.
     */
    private static function referencedName(SourceFile $file, Node $node): ?string
    {
        // Mago 1.47 does not narrow a nullsafe comparison, so the checks are explicit.
        $identifier = $file->getChildren($node)[0] ?? null;
        if ($identifier === null) {
            return null;
        }

        $identifier = Values::unwrap($file, $identifier);
        if ($identifier->kind !== NodeKind::Identifier) {
            return null;
        }

        $written = $file->getChildren($identifier)[0] ?? null;
        if ($written === null) {
            return null;
        }

        if ($node->kind === NodeKind::UseItem) {
            return $written->kind === NodeKind::LocalIdentifier ? Calls::normalize($file->getText($written)) : null;
        }

        if (!in_array($written->kind, [NodeKind::LocalIdentifier, NodeKind::FullyQualifiedIdentifier], strict: true)) {
            return null;
        }

        $resolved = $file->getResolvedName($identifier);
        if ($resolved !== null && $resolved->imported) {
            return Calls::normalize($resolved->name);
        }

        return Calls::normalize($file->getText($written));
    }

    /**
     * Builds the file gate from the rule's call names.
     *
     * Every match puts a wanted name in the source as a whole word. This
     * holds for a plain call, a method selector and a fully qualified call,
     * whatever trivia sits before the parenthesis. The one exception is a
     * call through an aliased `use function` import. The second branch
     * keeps that case in, because it passes every file with a function
     * import.
     *
     * @param list<string> $names
     */
    private static function buildGate(array $names): FileGate
    {
        return FileGate::forWords($names, pattern: '/\buse\s[^;]*\bfunction\b/i');
    }
}
