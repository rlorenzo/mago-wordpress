<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Syntax\CallExpression;
use Mago\Sdk\Syntax\Node;
use Rlorenzo\MagoWordPress\Internal\Calls;
use Rlorenzo\MagoWordPress\Internal\FileGate;

use function array_map;
use function implode;
use function preg_quote;

/**
 * Base for a rule that reports a call to one of a fixed set of names.
 *
 * @internal
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

    public function lint(LintContext $context): void
    {
        $this->gate ??= self::buildGate($this->names());
        if (!$this->gate->passes($context->file)) {
            return;
        }

        // The rule normalizes the wanted set once, not once per node.
        $this->wanted ??= Calls::normalizeAll($this->names());

        $name = Calls::matchWanted($context->file, $context->node, $this->wanted);
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
        $alternation = implode('|', array_map(static fn(string $name): string => preg_quote(
            $name,
            delimiter: '/',
        ), $names));

        return new FileGate(pattern: "/(?<!\\w)(?:{$alternation})(?!\\w)|\\buse\\s[^;]*\\bfunction\\b/i");
    }
}
