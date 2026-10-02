<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\Report;

use function in_array;
use function str_starts_with;
use function strtolower;
use function strtoupper;
use function substr;

/**
 * Ports `Universal.NamingConventions.NoReservedKeywordParameterNames`: a parameter of a
 * function, method, closure or arrow function named after a reserved keyword (or `parent`,
 * `self`), which reads badly in a call with named arguments.
 */
final class NoReservedKeywordParameterNamesRule implements Rule
{
    private const SNIFF = 'Universal.NamingConventions.NoReservedKeywordParameterNames';

    /** The sniff's list, lowercase; the magic constants are shown uppercase in the message. */
    private const RESERVED = [
        'abstract',
        'and',
        'array',
        'as',
        'break',
        'callable',
        'case',
        'catch',
        'class',
        'clone',
        'const',
        'continue',
        'declare',
        'default',
        'die',
        'do',
        'echo',
        'else',
        'elseif',
        'empty',
        'enddeclare',
        'endfor',
        'endforeach',
        'endif',
        'endswitch',
        'endwhile',
        'enum',
        'eval',
        'exit',
        'extends',
        'final',
        'finally',
        'fn',
        'for',
        'foreach',
        'function',
        'global',
        'goto',
        'if',
        'implements',
        'include',
        'include_once',
        'instanceof',
        'insteadof',
        'interface',
        'isset',
        'list',
        'match',
        'namespace',
        'new',
        'or',
        'print',
        'private',
        'protected',
        'public',
        'readonly',
        'require',
        'require_once',
        'return',
        'static',
        'switch',
        'throw',
        'trait',
        'try',
        'unset',
        'use',
        'var',
        'while',
        'xor',
        'yield',
        '__class__',
        '__dir__',
        '__file__',
        '__function__',
        '__line__',
        '__method__',
        '__namespace__',
        '__trait__',
        'int',
        'float',
        'bool',
        'string',
        'true',
        'false',
        'null',
        'void',
        'iterable',
        'object',
        'resource',
        'mixed',
        'numeric',
        'never',
        'parent',
        'self',
    ];

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/no-reserved-keyword-parameter-names',
            name: 'No reserved keyword parameter names',
            description: 'Reports a function parameter named after a reserved keyword, such as `$string` or `$default`, which is confusing in a call with named arguments.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::FunctionLikeParameter],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        foreach ($file->getChildren($context->node) as $variable) {
            if ($variable->kind !== NodeKind::DirectVariable) {
                continue;
            }

            $name = $file->getText($variable);
            $lower = strtolower(substr($name, 1));
            if (!in_array($lower, self::RESERVED, strict: true)) {
                return;
            }

            $keyword = str_starts_with($lower, '__') ? strtoupper($lower) : $lower;
            $this->report->issue(
                $context,
                Issue::new(
                    "It is recommended not to use reserved keyword \"{$keyword}\" as function parameter name. Found: {$name}",
                    $variable->span,
                    'reserved keyword',
                )->withHelp('Rename the parameter.'),
                [self::SNIFF . ".{$lower}Found"],
            );

            return;
        }
    }
}
