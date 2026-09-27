<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\CallExpression;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;
use Rlorenzo\MagoWordPress\Internal\Values;
use Rlorenzo\MagoWordPress\Internal\WordPress\Lists;
use Rlorenzo\MagoWordPress\Linter\CallRule;

use function array_key_exists;
use function array_keys;
use function str_ends_with;
use function strtolower;

/**
 * Ports `WordPress.WP.EnqueuedResourceParameters`.
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class EnqueuedResourceParametersRule extends CallRule
{
    /**
     * Parameter slots shared by the four functions: `$handle`, `$src`, `$deps`, `$ver`, and the
     * fifth parameter (`$args`/`$in_footer` for scripts, `$media` for styles).
     */
    private const SLOT_COUNT = 5;
    private const HANDLE_SLOT = 0;
    private const SRC_SLOT = 1;
    private const DEPS_SLOT = 2;
    private const VER_SLOT = 3;
    private const FIFTH_SLOT = 4;

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/enqueued-resource-parameters',
            name: 'Enqueued resource parameters',
            description: 'Checks calls to `wp_enqueue_script()`, `wp_register_script()`, `wp_enqueue_style()`, and '
            . '`wp_register_style()` that register a resource by `$src`: the `$ver` (version) parameter should be '
            . 'an explicit value (missing or literally `false` falls back to the WordPress core version, and '
            . '`null` disables versioning entirely), and for scripts the fifth parameter (`$args`/`$in_footer`) '
            . 'should be passed explicitly, since by default the script is printed in the `<head>`, where it '
            . 'blocks rendering.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::FunctionCall],
        );
    }

    protected function names(): array
    {
        return array_keys(Lists::ENQUEUE_FUNCTIONS);
    }

    protected function inspect(LintContext $context, CallExpression $call, string $name): void
    {
        $isScript = str_ends_with($name, '_script');

        $slots = $this->collectSlots($call, $isScript);
        if ($slots === null) {
            return;
        }

        $srcNode = $slots[self::SRC_SLOT] ?? null;
        if ($srcNode === null) {
            return;
        }

        $src = Values::unparenthesize($context->file, $srcNode);
        if (self::falseOrNullWord($context->file, $src) !== null) {
            return;
        }

        if ($src->kind === NodeKind::LiteralString && Values::literalString($context->file, $src) === '') {
            return;
        }

        $this->checkVersion($context, $slots[self::VER_SLOT] ?? null);

        if ($isScript && !array_key_exists(self::FIFTH_SLOT, $slots)) {
            $context->report(Issue::new(
                'Enqueued script does not set `$in_footer` explicitly',
                $context->node->span,
                'No `$args`/`$in_footer` argument passed for this script',
            )->withNote('By default, scripts are printed in the `<head>`, where they block page rendering.')->withHelp(
                'Pass an explicit 5th argument: `true` (or `[\'in_footer\' => true]`) to load the script in the '
                . 'footer, or `false` to keep it in the head deliberately.',
            ));
        }
    }

    private function checkVersion(LintContext $context, ?Node $verNode): void
    {
        if ($verNode === null) {
            $context->report(Issue::new(
                'Enqueued resource is missing the `$ver` (version) parameter',
                $context->node->span,
                'No version passed for this resource',
            )->withNote(
                'Without a version, WordPress falls back to its core version, so browsers and CDNs are not '
                . 'cache-busted when the asset itself changes.',
            )->withHelp("Pass the asset's own version string as the 4th (`\$ver`) argument."));

            return;
        }

        $version = Values::unparenthesize($context->file, $verNode);
        $word = self::falseOrNullWord($context->file, $version);
        if ($word === null) {
            return;
        }

        [$what, $effect] = $word === 'false'
            ? ['`false`', 'WordPress falls back to its core version']
            : ['`null`', 'no version is added at all'];

        $context->report(Issue::new(
            "Enqueued resource version is explicitly {$what}",
            $version->span,
            "With {$what} as the version, {$effect}",
        )->withNote(
            'Browsers and CDNs use the version query string to cache-bust; it should change when the asset changes.',
        )->withHelp("Pass the asset's own version string as the 4th (`\$ver`) argument."));
    }

    /**
     * Maps the call's arguments onto the five parameter slots.
     *
     * Returns NULL (conservative bail-out) when the argument shape cannot be proven: a spread
     * argument, an unrecognized named argument, or a slot filled twice.
     *
     * @return null|array<int, Node>
     */
    private function collectSlots(CallExpression $call, bool $isScript): ?array
    {
        $slots = [];
        $nextPositional = 0;

        foreach ($call->arguments as $argument) {
            if ($argument->unpacked) {
                return null;
            }

            $slot = $argument->name === null ? $nextPositional++ : self::parameterSlot($argument->name, $isScript);
            if ($slot === null) {
                return null;
            }

            if ($slot >= self::SLOT_COUNT || array_key_exists($slot, $slots)) {
                return null;
            }

            $slots[$slot] = $argument->value;
        }

        return $slots;
    }

    private static function parameterSlot(string $name, bool $isScript): ?int
    {
        $lower = strtolower($name);

        return match (true) {
            $lower === 'handle' => self::HANDLE_SLOT,
            $lower === 'src' => self::SRC_SLOT,
            $lower === 'deps' => self::DEPS_SLOT,
            $lower === 'ver' => self::VER_SLOT,
            self::isFifthSlotName($lower, $isScript) => self::FIFTH_SLOT,
            default => null,
        };
    }

    /**
     * Whether `$lower` is the fifth positional parameter's name for the given function kind:
     * `$args`/`$in_footer` for scripts, `$media` for styles.
     */
    private static function isFifthSlotName(string $lower, bool $isScript): bool
    {
        if ($isScript) {
            return $lower === 'args' || $lower === 'in_footer';
        }

        return $lower === 'media';
    }

    /**
     * Whether a fully unwrapped node is the keyword `false` or `null`, and if so, which.
     */
    private static function falseOrNullWord(SourceFile $file, Node $node): ?string
    {
        if ($node->kind !== NodeKind::Keyword) {
            return null;
        }

        $text = strtolower($file->getText($node));

        return $text === 'false' || $text === 'null' ? $text : null;
    }
}
