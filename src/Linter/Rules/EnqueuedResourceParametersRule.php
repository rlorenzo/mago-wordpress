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
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Internal\Strings;
use Rlorenzo\MagoWordPress\Internal\Values;
use Rlorenzo\MagoWordPress\Internal\WordPress\Lists;
use Rlorenzo\MagoWordPress\Linter\CallRule;

use function array_key_exists;
use function array_keys;
use function explode;
use function in_array;
use function ltrim;
use function preg_match;
use function str_ends_with;
use function str_replace;
use function str_starts_with;
use function strtolower;
use function substr;
use function trim;

/**
 * Ports `WordPress.WP.EnqueuedResourceParameters`.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class EnqueuedResourceParametersRule extends CallRule
{
    private const SNIFF = 'WordPress.WP.EnqueuedResourceParameters';

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

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/enqueued-resource-parameters',
            name: 'Enqueued resource parameters',
            description: 'Checks calls to `wp_enqueue_script()`, `wp_register_script()`, `wp_enqueue_style()`, and '
            . '`wp_register_style()` that pass a `$src`: the `$ver` (version) parameter should be '
            . 'an explicit value (missing or a falsy literal such as `false`, `0`, or `\'\'` falls back to the WordPress core version, and '
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
        // WPCS cannot resolve `namespace\` relative calls, so it never reports them.
        if (str_starts_with(strtolower($context->file->getText($call->callee)), 'namespace\\')) {
            return;
        }

        $isScript = str_ends_with($name, '_script');

        $slots = $this->collectSlots($call, $isScript);
        if ($slots === null) {
            return;
        }

        // Like WPCS, any passed `$src` counts, even `false`, `null`, or `''`.
        if (!array_key_exists(self::SRC_SLOT, $slots)) {
            return;
        }

        $this->checkVersion($context, $slots[self::VER_SLOT] ?? null);

        if ($isScript && !array_key_exists(self::FIFTH_SLOT, $slots)) {
            $this->report->issue(
                $context,
                Issue::new(
                    'Enqueued script does not set `$in_footer` explicitly',
                    $context->node->span,
                    'No `$args`/`$in_footer` argument passed for this script',
                )->withNote(
                    'By default, scripts are printed in the `<head>`, where they block page rendering.',
                )->withHelp(
                    'Pass an explicit 5th argument: `true` (or `[\'in_footer\' => true]`) to load the script in the '
                    . 'footer, or `false` to keep it in the head deliberately.',
                ),
                [self::SNIFF . '.NotInFooter'],
            );
        }
    }

    private function checkVersion(LintContext $context, ?Node $verNode): void
    {
        if ($verNode === null) {
            $this->report->issue(
                $context,
                Issue::new(
                    'Enqueued resource is missing the `$ver` (version) parameter',
                    $context->node->span,
                    'No version passed for this resource',
                )->withNote(
                    'Without a version, WordPress falls back to its core version, so browsers and CDNs are not '
                    . 'cache-busted when the asset itself changes.',
                )->withHelp("Pass the asset's own version string as the 4th (`\$ver`) argument."),
                [self::SNIFF . '.MissingVersion'],
            );

            return;
        }

        $version = Values::unparenthesize($context->file, $verNode);
        if (self::falseOrNullWord($context->file, $version) === 'null') {
            $this->report->issue(
                $context,
                Issue::new(
                    'Enqueued resource version is explicitly `null`',
                    $version->span,
                    'With `null` as the version, no version is added at all',
                )->withNote(
                    'Browsers and CDNs use the version query string to cache-bust; it should change when the asset changes.',
                )->withHelp("Pass the asset's own version string as the 4th (`\$ver`) argument."),
                [self::SNIFF . '.MissingVersion'],
            );

            return;
        }

        if (!self::isFalsy($context->file, $version)) {
            return;
        }

        $this->report->issue(
            $context,
            Issue::new(
                'Enqueued resource version is falsy',
                $version->span,
                'With a falsy version, WordPress falls back to its core version',
            )->withNote(
                'Browsers and CDNs use the version query string to cache-bust; it should change when the asset changes.',
            )->withHelp("Pass the asset's own version string as the 4th (`\$ver`) argument."),
            [self::SNIFF . '.NoExplicitVersion'],
        );
    }

    /**
     * Whether a fully unwrapped node is one of the literals WPCS recognizes as falsy: `false`, a
     * zero int or float, a `''` or `'0'` string without interpolation, or an empty array.
     */
    private static function isFalsy(SourceFile $file, Node $node): bool
    {
        return match ($node->kind) {
            NodeKind::Keyword, NodeKind::ConstantAccess => self::falseOrNullWord($file, $node) === 'false',
            NodeKind::LiteralInteger, NodeKind::LiteralFloat => self::isZeroNumber($file->getText($node)),
            NodeKind::LiteralString => in_array(Values::literalString($file, $node), ['', '0'], strict: true),
            NodeKind::CompositeString => self::isFalsyDocument($file, $node),
            NodeKind::Array, NodeKind::LegacyArray => $file->getChildren($node) === [],
            default => false,
        };
    }

    private static function isZeroNumber(string $text): bool
    {
        $text = strtolower(str_replace(search: '_', replace: '', subject: $text));

        // Zero when every digit is zero: after the hex/binary/octal prefix, or in a decimal's mantissa.
        $digits = preg_match('/^0[xbo]/', $text) === 1 ? substr($text, offset: 2) : explode('e', $text)[0];

        return trim($digits, characters: '0.') === '';
    }

    /**
     * Whether a heredoc/nowdoc without interpolation has an empty or `0` body. A double-quoted
     * composite string always interpolates, so it is never falsy.
     */
    private static function isFalsyDocument(SourceFile $file, Node $node): bool
    {
        $string = $file->getChildren($node)[0] ?? $node;
        if ($string->kind !== NodeKind::DocumentString) {
            return false;
        }

        $text = '';
        foreach (Strings::compositeParts($file, $node) as $part) {
            if ($part === null) {
                return false;
            }

            $text .= $part;
        }

        // WPCS compares the complete text exactly; a padded ' 0 ' is a truthy version.
        return $text === '' || $text === '0';
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
     * Whether a fully unwrapped node is `false` or `null` (optionally fully qualified), and if so, which.
     */
    private static function falseOrNullWord(SourceFile $file, Node $node): ?string
    {
        if ($node->kind !== NodeKind::Keyword && $node->kind !== NodeKind::ConstantAccess) {
            return null;
        }

        $text = strtolower(ltrim($file->getText($node), characters: '\\'));

        return $text === 'false' || $text === 'null' ? $text : null;
    }
}
