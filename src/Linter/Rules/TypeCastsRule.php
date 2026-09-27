<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\FileGate;

use function in_array;
use function preg_replace;
use function strtolower;

/**
 * Ports `WordPress.PHP.TypeCasts`.
 *
 * `(double)`/`(real)` normalize to `(float)` (fixable), `(unset)` is
 * forbidden (removed in PHP 8.0), and `(binary)` is discouraged. WPCS also
 * warns on a `b"..."`/`b'...'` binary string literal under the same
 * "binary" message, because its tokenizer reports that prefix as the same
 * token as the `(binary)` cast; this rule matches that by checking string
 * literals for the `b` prefix directly, since Mago keeps it as ordinary
 * literal text.
 *
 * The sniff itself reports `(unset)`/`(binary)` at different severities
 * than the fixable `(double)`/`(real)` normalization, but one rule has one
 * `defaultLevel`, so every report here uses `Level::Error`, one level
 * stricter than WPCS's `Warning` for `(binary)`/`b"..."`.
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class TypeCastsRule implements Rule
{
    private const DOUBLE_CAST_SPELLINGS = ['(double)', '(real)'];

    private ?FileGate $gate = null;

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/type-casts',
            name: 'Type casts',
            description: 'Normalizes "(double)"/"(real)" to "(float)", forbids "(unset)", and discourages "(binary)" and binary string literals.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::UnaryPrefix, NodeKind::LiteralString, NodeKind::InterpolatedString],
        );
    }

    public function lint(LintContext $context): void
    {
        $this->gate ??= new FileGate(pattern: '/\(\s*(?:double|real|unset|binary)\s*\)|\bb["\']/i');
        if (!$this->gate->passes($context->file)) {
            return;
        }

        if ($context->node->kind === NodeKind::UnaryPrefix) {
            $this->inspectCast($context);

            return;
        }

        $this->inspectBinaryStringLiteral($context);
    }

    private function inspectCast(LintContext $context): void
    {
        $operator = $context->file->getChildren($context->node)[0] ?? null;
        if ($operator === null || $operator->kind !== NodeKind::UnaryPrefixOperator) {
            return;
        }

        $written = $context->file->getText($operator);
        $normalized = strtolower(preg_replace('/\s+/', replacement: '', subject: $written) ?? $written);

        if (in_array($normalized, self::DOUBLE_CAST_SPELLINGS, strict: true)) {
            $context->report(Issue::new(
                "Normalized type keywords must be used; expected \"(float)\" but found \"{$written}\".",
                $operator->span,
            )->withEdit(TextEdit::replace($operator->span, '(float)')));

            return;
        }

        if ($normalized === '(unset)') {
            $context->report(Issue::new(
                'Using the "(unset)" cast is forbidden as the cast was removed in PHP 8.0.',
                $operator->span,
            )->withHelp('Use the unset() language construct instead.'));

            return;
        }

        if ($normalized === '(binary)') {
            $context->report(Issue::new(
                "Using binary casting is strongly discouraged. Found: \"{$written}\".",
                $operator->span,
            ));
        }
    }

    private function inspectBinaryStringLiteral(LintContext $context): void
    {
        $text = $context->file->getText($context->node);
        if (($text[0] ?? '') !== 'b' && ($text[0] ?? '') !== 'B') {
            return;
        }

        if (($text[1] ?? '') !== '"' && ($text[1] ?? '') !== "'") {
            return;
        }

        $context->report(Issue::new(
            "Using binary casting is strongly discouraged. Found: \"{$text}\".",
            $context->node->span,
        ));
    }
}
