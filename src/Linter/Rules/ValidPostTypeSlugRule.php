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
use Rlorenzo\MagoWordPress\Linter\CallRule;

use function count;
use function ltrim;
use function preg_match;
use function str_starts_with;
use function stripcslashes;
use function strlen;
use function strtolower;
use function strtr;
use function substr;

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
            $context->report(Issue::new(
                "register_post_type() called with a post type slug that is not a string literal: {$context->file->getText(
                    $argument,
                )}.",
                $argument->span,
            )->withHelp('It is not possible to automatically determine the validity of a dynamic post type slug.'));

            return;
        }

        [$postType, $dynamic] = $literal;
        if ($postType === '') {
            $this->reportEmpty($context);

            return;
        }

        if ($dynamic) {
            $context->report(Issue::new(
                "The post type slug may, or may not, get too long with dynamic contents and could contain invalid characters. Found: \"{$postType}\".",
                $argument->span,
            )->withHelp('Prefer a fully static post type slug so its validity can be checked.'));
        }

        if (preg_match(self::VALID_CHARACTERS, $postType) !== 1) {
            $context->report(Issue::new(
                "register_post_type() called with invalid post type \"{$postType}\". Only lowercase alphanumeric characters, dashes, and underscores are allowed.",
                $argument->span,
            )->withHelp('Use only lowercase letters, digits, dashes and underscores in the post type slug.'));
        }

        if (isset(self::RESERVED_NAMES[$postType])) {
            $context->report(Issue::new(
                "register_post_type() called with reserved post type \"{$postType}\". Reserved post types interfere with the functioning of WordPress itself.",
                $argument->span,
            )->withHelp('Choose a post type slug that is not reserved by WordPress core.'));
        } elseif (str_starts_with(strtolower($postType), 'wp_')) {
            $context->report(Issue::new(
                "The post type passed to register_post_type() uses a prefix reserved for WordPress itself. Found: \"{$postType}\".",
                $argument->span,
            )->withHelp('Do not prefix a plugin or theme post type slug with "wp_".'));
        }

        if (strlen($postType) > self::MAX_LENGTH) {
            $context->report(Issue::new(
                'A post type slug must not exceed '
                . self::MAX_LENGTH
                . " characters. Found: \"{$postType}\" ("
                . strlen($postType)
                . ' characters).',
                $argument->span,
            )->withHelp('Shorten the post type slug to 20 characters or fewer.'));
        }
    }

    private function reportEmpty(LintContext $context): void
    {
        $context->report(Issue::new(
            'register_post_type() called without a post type slug. The slug must be a non-empty string.',
            $context->node->span,
        )->withHelp('Pass a non-empty post type slug as the first argument.'));
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

        if ($node->kind === NodeKind::LiteralString) {
            return [$this->unquote($file->getText($node)), false];
        }

        if ($node->kind !== NodeKind::CompositeString) {
            return null;
        }

        $string = $file->getChildren($node)[0] ?? $node;
        $isDocument = $string->kind === NodeKind::DocumentString;
        $nowdoc = $isDocument && str_starts_with($file->getText($string), "<<<'");

        $text = '';
        $dynamic = false;
        foreach ($file->getChildren($string) as $part) {
            $inner = $file->getChildren($part)[0] ?? $part;
            if ($inner->kind !== NodeKind::LiteralStringPart) {
                $dynamic = true;
                continue;
            }

            $partText = $file->getText($inner);
            $text .= $nowdoc ? $partText : stripcslashes($partText);
        }

        if ($isDocument) {
            // Approximates WPCS's own fallback for PHP 7.3+ flexible heredoc/nowdoc indentation.
            $text = ltrim($text);
        }

        if ($nowdoc) {
            $dynamicWarn = false;
        } elseif ($isDocument) {
            $dynamicWarn = $dynamic;
        } else {
            $dynamicWarn = true;
        }

        return [$text, $dynamicWarn];
    }

    private function unquote(string $literal): string
    {
        $body = substr($literal, offset: 1, length: -1);

        return str_starts_with($literal, "'") ? strtr($body, ["\\'" => "'", '\\\\' => '\\']) : stripcslashes($body);
    }
}
