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
use PhpToken;
use Rlorenzo\MagoWordPress\Internal\ArrayShape;
use Rlorenzo\MagoWordPress\Internal\LineIndex;
use Rlorenzo\MagoWordPress\Internal\Report;
use Throwable;

use function count;
use function in_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function preg_match;
use function str_contains;
use function strlen;
use function strtolower;

/**
 * Ports `Universal.Arrays.DuplicateArrayKey`. A key made only of literals and operators is
 * evaluated, as PHPCSUtils does with `eval()`, and a key that is a variable, a constant or a call
 * is skipped. Integer keys are tracked for PHP below 8.0 and from 8.0 on (they differ after a
 * negative key), as the sniff does without a configured PHP version.
 *
 * @mago-expect lint:no-eval
 * @mago-expect lint:no-error-control-operator
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class DuplicateArrayKeyRule implements Rule
{
    private const SNIFF = 'Universal.Arrays.DuplicateArrayKey';

    /** Token names a key may hold; anything else (a variable, a constant, a call) is not evaluated. */
    private const ACCEPTED = [
        'T_LNUMBER' => true,
        'T_DNUMBER' => true,
        'T_CONSTANT_ENCAPSED_STRING' => true,
        'T_START_HEREDOC' => true,
        'T_ENCAPSED_AND_WHITESPACE' => true,
        'T_END_HEREDOC' => true,
        'T_BOOLEAN_AND' => true,
        'T_BOOLEAN_OR' => true,
        'T_LOGICAL_AND' => true,
        'T_LOGICAL_OR' => true,
        'T_LOGICAL_XOR' => true,
        'T_IS_EQUAL' => true,
        'T_IS_NOT_EQUAL' => true,
        'T_IS_IDENTICAL' => true,
        'T_IS_NOT_IDENTICAL' => true,
        'T_IS_SMALLER_OR_EQUAL' => true,
        'T_IS_GREATER_OR_EQUAL' => true,
        'T_SPACESHIP' => true,
        'T_COALESCE' => true,
        'T_POW' => true,
        'T_SL' => true,
        'T_SR' => true,
        'T_INT_CAST' => true,
        'T_DOUBLE_CAST' => true,
        'T_STRING_CAST' => true,
        'T_BOOL_CAST' => true,
        'T_ARRAY_CAST' => true,
        'T_OBJECT_CAST' => true,
    ];

    private const SYMBOLS = '.!+-*/%<>&|^~?:()[]{}';

    private const CALLABLE_BEFORE = ['T_CONSTANT_ENCAPSED_STRING', 'T_END_HEREDOC', ')', ']', '}'];

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/duplicate-array-key',
            name: 'Duplicate array key',
            description: 'Reports an array item whose literal key repeats an earlier one, so the earlier value is overwritten.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Array, NodeKind::LegacyArray],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $array = $context->node;
        if (ArrayShape::isList($file, $array)) {
            return;
        }

        // [PHP below 8, PHP 8+] key => line first seen, and the highest integer key so far.
        $seen = [[], []];
        $max = [null, null];
        foreach ($file->getChildren($array) as $element) {
            $pair = $file->getChildren($element)[0] ?? null;
            if ($pair === null || $pair->kind === NodeKind::MissingArrayElement) {
                continue;
            }

            $key = null;
            $node = $pair;
            if ($pair->kind === NodeKind::KeyValueArrayElement) {
                $node = $file->getChildren($pair)[0] ?? $pair;
                $key = self::keyOf($file->getText($node));
            }

            if ($key === null) {
                // An item without a key, or with a key that is not a literal, takes the next integer.
                if ($pair->kind !== NodeKind::KeyValueArrayElement) {
                    $this->implicit($seen, $max, LineIndex::of($file, $node->span->start));
                }

                continue;
            }

            $this->explicit($context, $node, $key, $seen, $max);
        }
    }

    /**
     * @param array{array<int|string, int>, array<int|string, int>} $seen
     * @param array{null|int, null|int} $max
     */
    private function implicit(array &$seen, array &$max, int $line): void
    {
        $max[0] = ($max[0] ?? -1) + 1;
        $seen[0][$max[0]] = $line;
        $max[1] = ($max[1] ?? -1) + 1;
        $seen[1][$max[1]] = $line;
    }

    /**
     * @param array{array<int|string, int>, array<int|string, int>} $seen
     * @param array{null|int, null|int} $max
     */
    private function explicit(LintContext $context, Node $node, int|string $key, array &$seen, array &$max): void
    {
        $line = LineIndex::of($context->file, $node->span->start);
        $isInt = is_int($key);
        $errors = [];
        foreach ([0, 1] as $version) {
            if (isset($seen[$version][$key])) {
                $errors[$version] = [
                    $isInt ? ($version === 0 ? ' when using PHP < 8.0' : ' when using PHP >= 8.0') : '',
                    $isInt ? ($version === 0 ? 'ForPHPlt80' : 'ForPHPgte80') : '',
                    $seen[$version][$key],
                ];
            }
        }

        if ($errors === []) {
            $seen[0][$key] = $line;
            $seen[1][$key] = $line;
            if (is_int($key)) {
                if ($max[0] === null && $key > -1 || $max[0] !== null && $key > $max[0]) {
                    $max[0] = $key;
                }

                if ($max[1] === null || $key > $max[1]) {
                    $max[1] = $key;
                }
            }

            return;
        }

        // The same error for both PHP versions is reported once.
        if (isset($errors[0], $errors[1]) && $errors[0][2] === $errors[1][2]) {
            $errors = [['', '', $errors[0][2]]];
        }

        $type = $isInt ? 'integer' : 'string';
        foreach ($errors as [$suffix, $code, $first]) {
            $this->report->issue(
                $context,
                Issue::new(
                    "Duplicate array key found. The value will be overwritten{$suffix}. The {$type} array key \"{$key}\" was first seen on line {$first}",
                    $node->span,
                    'duplicate key',
                ),
                [self::SNIFF . '.Found' . $code],
            );
        }
    }

    /** `AbstractArrayDeclarationSniff::getActualArrayKey()`: the key PHP gives the literal, or NULL. */
    private static function keyOf(string $source): int|string|null
    {
        $tokens = PhpToken::tokenize('<?php ' . $source);
        $code = '';
        $previous = null;
        for ($i = 1, $count = count($tokens); $i < $count; $i++) {
            $token = $tokens[$i];
            $name = $token->getTokenName();
            if ($token->isIgnorable()) {
                $code .= $name === 'T_WHITESPACE' ? ' ' : '';
                continue;
            }

            $accepted = match (true) {
                $name === 'T_STRING' => in_array(strtolower($token->text), ['true', 'false', 'null'], strict: true),
                $name === 'T_NAME_FULLY_QUALIFIED' => in_array(
                    strtolower($token->text),
                    ['\\true', '\\false', '\\null'],
                    strict: true,
                ),
                $token->text === '(' && in_array($previous, self::CALLABLE_BEFORE, strict: true) => false,
                isset(self::ACCEPTED[$name ?? '']) => true,
                default => strlen($token->text) === 1 && str_contains(self::SYMBOLS, $token->text),
            };
            if ($name === 'T_DOUBLE_CAST') {
                $code .= '(float)';
                $previous = $name;
                continue;
            }

            if (!$accepted) {
                return null;
            }

            $code .= $token->text;
            $previous = $name ?? $token->text;
        }

        try {
            $value = @eval('return ' . $code . ';' . "\n");
        } catch (Throwable) {
            return null;
        }

        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? 1 : 0,
            is_int($value) => $value,
            is_float($value) => (int) $value,
            is_string($value) => preg_match('`^(?:0|-?[1-9][0-9]*)$`D', $value) === 1 ? (int) $value : $value,
            default => null,
        };
    }
}
