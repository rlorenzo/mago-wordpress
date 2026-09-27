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
use Rlorenzo\MagoWordPress\Linter\CallRule;

use function array_key_exists;
use function count;
use function ltrim;
use function preg_match;
use function str_starts_with;
use function strlen;
use function strtolower;

/**
 * Ports `WordPress.NamingConventions.ValidPostTypeSlug`.
 *
 * WPCS mixes `addError()` and `addWarning()` in this sniff; a Mago rule
 * reports at one level, so every check here reports at `Level::Error`, the
 * level of the sniff's majority (slug validity) checks.
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class ValidPostTypeSlugRule extends CallRule
{
    private const SNIFF = 'WordPress.NamingConventions.ValidPostTypeSlug';

    private const MAX_LENGTH = 20;

    private const VALID_CHARACTERS = '/^[a-z0-9_-]+$/';

    /**
     * Reserved post type names, per the `register_post_type()` reference.
     * Last updated for WordPress 7.0.0, as in the WPCS source.
     *
     * @var array<string, true>
     */
    private const RESERVED_NAMES = [
        'action' => true,
        'attachment' => true,
        'author' => true,
        'custom_css' => true,
        'customize_changeset' => true,
        'nav_menu_item' => true,
        'oembed_cache' => true,
        'order' => true,
        'page' => true,
        'post' => true,
        'revision' => true,
        'theme' => true,
        'user_request' => true,
        'wp_block' => true,
        'wp_font_face' => true,
        'wp_font_family' => true,
        'wp_global_styles' => true,
        'wp_navigation' => true,
        'wp_template' => true,
        'wp_template_part' => true,
    ];

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/valid-post-type-slug',
            name: 'Valid post type slug',
            description: 'Validates the post type slug passed to register_post_type(): invalid characters, reserved names, a reserved prefix, and a slug longer than 20 characters.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::FunctionCall],
        );
    }

    protected function names(): array
    {
        return ['register_post_type'];
    }

    protected function inspect(LintContext $context, CallExpression $call, string $name): void
    {
        if (count($call->arguments) === 0) {
            // No arguments at all reads as incomplete/live-coding, not a violation.
            return;
        }

        $argument = $this->argument($context, $call, 0, 'post_type');
        if ($argument === null) {
            $this->reportEmpty($context);

            return;
        }

        $literal = $this->literal($context->file, $argument);
        if ($literal === null) {
            Report::issue(
                $context,
                Issue::new(
                    "register_post_type() called with a post type slug that is not a string literal: {$context->file->getText(
                        $argument,
                    )}.",
                    $argument->span,
                )->withHelp('It is not possible to automatically determine the validity of a dynamic post type slug.'),
                [self::SNIFF . '.NotStringLiteral'],
            );

            return;
        }

        [$postType, $dynamic] = $literal;
        if ($dynamic) {
            Report::issue(
                $context,
                Issue::new(
                    "The post type slug may, or may not, get too long with dynamic contents and could contain invalid characters. Found: \"{$postType}\".",
                    $argument->span,
                )->withHelp('Prefer a fully static post type slug so its validity can be checked.'),
                [self::SNIFF . '.PartiallyDynamic'],
            );
        }

        if ($postType === '') {
            // A purely interpolated slug such as "{$slug}" was passed, just not checkable.
            if (!$dynamic) {
                $this->reportEmpty($context);
            }

            return;
        }

        if (preg_match(self::VALID_CHARACTERS, $postType) !== 1) {
            Report::issue(
                $context,
                Issue::new(
                    "register_post_type() called with invalid post type \"{$postType}\". Only lowercase alphanumeric characters, dashes, and underscores are allowed.",
                    $argument->span,
                )->withHelp('Use only lowercase letters, digits, dashes and underscores in the post type slug.'),
                [self::SNIFF . '.InvalidCharacters'],
            );
        }

        // register_post_type() runs the slug through sanitize_key(), which lowercases it.
        $reserved = array_key_exists(strtolower($postType), self::RESERVED_NAMES);
        if ($reserved) {
            Report::issue(
                $context,
                Issue::new(
                    "register_post_type() called with reserved post type \"{$postType}\". Reserved post types interfere with the functioning of WordPress itself.",
                    $argument->span,
                )->withHelp('Choose a post type slug that is not reserved by WordPress core.'),
                [self::SNIFF . '.Reserved'],
            );
        }

        if (!$reserved && str_starts_with(strtolower($postType), 'wp_')) {
            Report::issue(
                $context,
                Issue::new(
                    "The post type passed to register_post_type() uses a prefix reserved for WordPress itself. Found: \"{$postType}\".",
                    $argument->span,
                )->withHelp('Do not prefix a plugin or theme post type slug with "wp_".'),
                [self::SNIFF . '.ReservedPrefix'],
            );
        }

        if (strlen($postType) > self::MAX_LENGTH) {
            Report::issue(
                $context,
                Issue::new(
                    'A post type slug must not exceed '
                    . self::MAX_LENGTH
                    . " characters. Found: \"{$postType}\" ("
                    . strlen($postType)
                    . ' characters).',
                    $argument->span,
                )->withHelp('Shorten the post type slug to 20 characters or fewer.'),
                [self::SNIFF . '.TooLong'],
            );
        }
    }

    private function reportEmpty(LintContext $context): void
    {
        Report::issue(
            $context,
            Issue::new(
                'register_post_type() called without a post type slug. The slug must be a non-empty string.',
                $context->node->span,
            )->withHelp('Pass a non-empty post type slug as the first argument.'),
            [self::SNIFF . '.Empty'],
        );
    }

    /**
     * Extracts the decoded text of a string/heredoc/nowdoc literal, and
     * whether it has dynamic (interpolated) content. Returns NULL when the
     * value is not a string literal at all.
     *
     * @return array{string, bool}|null
     */
    private function literal(SourceFile $file, Node $node): ?array
    {
        $node = Values::unwrap($file, $node);

        $static = Values::literalString($file, $node);
        if ($static !== null) {
            return [$static, false];
        }

        if ($node->kind !== NodeKind::CompositeString) {
            return null;
        }

        $string = $file->getChildren($node)[0] ?? $node;
        $isDocument = $string->kind === NodeKind::DocumentString;
        $nowdoc = $isDocument && str_starts_with($file->getText($string), "<<<'");

        $text = '';
        $dynamic = false;
        foreach (Strings::compositeParts($file, $node) as $partText) {
            if ($partText === null) {
                $dynamic = true;
                continue;
            }

            $text .= $partText;
        }

        if ($isDocument) {
            // Approximates WPCS's own fallback for PHP 7.3+ flexible heredoc/nowdoc indentation.
            $text = ltrim($text);
        }

        // A composite quoted string always interpolates; a heredoc only when it has a dynamic part.
        return [$text, !$nowdoc && (!$isDocument || $dynamic)];
    }
}
