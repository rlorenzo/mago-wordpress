<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\Report;

use function array_flip;
use function preg_match;
use function strtolower;

/**
 * Ports `Generic.PHP.LowerCaseType`: a type cast, and a reserved type name in a parameter,
 * return, property or class constant type, written in anything but lowercase (`TypeCastFound`,
 * `ParamTypeFound`, `ReturnTypeFound`, `PropertyTypeFound`, `ConstantTypeFound`). A class name
 * that is not a reserved type is left alone, in a union, intersection or nullable type too.
 */
final class LowercaseTypeRule implements Rule
{
    private const SNIFF = 'Generic.PHP.LowerCaseType';

    private const TYPES = [
        'self',
        'parent',
        'array',
        'callable',
        'bool',
        'float',
        'int',
        'string',
        'iterable',
        'void',
        'object',
        'mixed',
        'static',
        'false',
        'true',
        'null',
        'never',
    ];

    private const CASTS = '/^\(\s*(?:int|integer|bool|boolean|float|double|real|string|array|object|unset|binary)\s*\)$/i';

    /** The node that owns a type, and the code and noun the sniff reports it under. */
    private const OWNERS = [
        'FunctionLikeParameter' => ['ParamTypeFound', 'parameter'],
        'FunctionLikeReturnTypeHint' => ['ReturnTypeFound', 'return'],
        'PlainProperty' => ['PropertyTypeFound', 'property'],
        'ClassLikeConstant' => ['ConstantTypeFound', 'constant'],
    ];

    /** @var array<string, int> */
    private readonly array $types;

    public function __construct(
        private readonly Report $report,
    ) {
        $this->types = array_flip(self::TYPES);
    }

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/lowercase-type',
            name: 'Lowercase type',
            description: 'Reports a type cast or a reserved type name (`int`, `array`, `self`, ...) that is not lowercase.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [
                NodeKind::UnaryPrefixOperator,
                NodeKind::FunctionLikeParameter,
                NodeKind::FunctionLikeReturnTypeHint,
                NodeKind::PlainProperty,
                NodeKind::ClassLikeConstant,
            ],
        );
    }

    public function lint(LintContext $context): void
    {
        $node = $context->node;
        if ($node->kind === NodeKind::UnaryPrefixOperator) {
            $text = $context->file->getText($node);
            if (preg_match(self::CASTS, $text) === 1) {
                $this->report($context, $node, $text, 'TypeCastFound', 'type cast');
            }

            return;
        }

        [$code, $noun] = self::OWNERS[$node->kind->value];
        foreach ($context->file->getDescendants($node, NodeKind::Hint) as $hint) {
            $text = $context->file->getText($hint);
            if (isset($this->types[strtolower($text)])) {
                $this->report($context, $hint, $text, $code, $noun);
            }
        }
    }

    private function report(LintContext $context, Node $node, string $text, string $code, string $noun): void
    {
        $lower = strtolower($text);
        if ($text === $lower) {
            return;
        }

        $this->report->issue(
            $context,
            Issue::new(
                'PHP ' . $noun . ' declarations must be lowercase; expected "' . $lower . '" but found "' . $text . '"',
                $node->span,
                'not lowercase',
            )->withEdit(TextEdit::replace($node->span, $lower)),
            [self::SNIFF . '.' . $code],
        );
    }
}
