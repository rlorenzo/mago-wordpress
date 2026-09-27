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
use Mago\Sdk\Syntax\SourceFile;

use function in_array;
use function sprintf;
use function substr;

/**
 * Ports `WordPress.NamingConventions.ValidVariableName`.
 *
 * `Program` is the only target so every variable has its full parent chain in
 * the snapshot. The sniff's `allowed_custom_properties` has no setting here, so
 * only its built-in allow lists apply (the WPCS default).
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class ValidVariableNameRule implements Rule
{
    /**
     * PHPCSUtils `Variables::$phpReservedVars`.
     */
    private const PHP_RESERVED = [
        '_SERVER',
        '_GET',
        '_POST',
        '_REQUEST',
        '_SESSION',
        '_ENV',
        '_COOKIE',
        '_FILES',
        'GLOBALS',
        'http_response_header',
        'argc',
        'argv',
        'php_errormsg',
        'HTTP_SERVER_VARS',
        'HTTP_GET_VARS',
        'HTTP_POST_VARS',
        'HTTP_SESSION_VARS',
        'HTTP_ENV_VARS',
        'HTTP_COOKIE_VARS',
        'HTTP_POST_FILES',
        'HTTP_RAW_POST_DATA',
    ];

    /**
     * Mixed-case globals set by WordPress core (WPCS `$wordpress_mixed_case_vars`).
     */
    private const WORDPRESS_MIXED_CASE_VARS = [
        'EZSQL_ERROR',
        'GETID3_ERRORARRAY',
        'is_IE',
        'is_IIS',
        'is_macIE',
        'is_NS4',
        'is_winIE',
        'PHP_SELF',
        'post_ID',
        'tag_ID',
        'user_ID',
    ];

    /**
     * Mixed-case properties of WordPress core objects (WPCS `$allowed_mixed_case_member_var_names`).
     */
    private const ALLOWED_MEMBER_NAMES = [
        'cat_ID',
        'comment_ID',
        'comment_author_IP',
        'comment_post_ID',
        'ID',
        'post_ID',
    ];

    private const PROPERTY_OWNERS = [NodeKind::Class_, NodeKind::AnonymousClass, NodeKind::Trait, NodeKind::Interface];

    private const PROPERTY_ITEM_KINDS = [NodeKind::PropertyAbstractItem, NodeKind::PropertyConcreteItem];

    private const PROPERTY_ACCESS_KINDS = [NodeKind::PropertyAccess, NodeKind::NullSafePropertyAccess];

    /**
     * Parents of a variable written after `::` or `->` (`Foo::$bar`, `$foo->$bar`).
     */
    private const CLASS_MEMBER_KINDS = [NodeKind::StaticPropertyAccess, NodeKind::ClassLikeMemberSelector];

    private const STRING_KINDS = [NodeKind::InterpolatedString, NodeKind::DocumentString];

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/valid-variable-name',
            name: 'Valid variable name',
            description: 'Reports variables, properties, and object property accesses whose names are not in snake_case, including variables interpolated in strings. PHP superglobals and reserved variables, the mixed-case globals WordPress core sets (such as $post_ID), and the mixed-case properties of WordPress core objects (such as $post->ID) are allowed.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;

        foreach ($file->getDescendants($context->node, NodeKind::DirectVariable) as $variable) {
            $this->check($context, $variable, substr($file->getText($variable), offset: 1));
        }

        foreach ($file->getDescendants($context->node, NodeKind::IndirectVariable) as $variable) {
            $inner = $file->getChildren($variable)[0] ?? null;
            $identifier = $inner === null ? null : $file->getChildren($inner)[0] ?? null;
            if (
                $identifier === null
                || $identifier->kind !== NodeKind::Identifier
                || !self::inString($file, $variable)
            ) {
                continue;
            }

            $name = $file->getText($identifier);
            if (!self::isExempt($name)) {
                $this->reportIfNotSnakeCase($context, $variable, 'Variable', $name);
            }
        }
    }

    private function check(LintContext $context, Node $variable, string $name): void
    {
        $file = $context->file;
        $parent = $file->getParent($variable);

        if ($parent !== null && in_array($parent->kind, self::PROPERTY_ITEM_KINDS, strict: true)) {
            $this->checkMemberVar($context, $variable, $parent, $name);
            return;
        }

        if (self::isExempt($name)) {
            return;
        }

        if (self::inString($file, $variable)) {
            $this->reportIfNotSnakeCase($context, $variable, 'Variable', $name);
            return;
        }

        $wrapper = $parent !== null && $parent->kind === NodeKind::Variable ? $parent : $variable;
        $this->checkAccessedProperty($context, $wrapper);

        $owner = $file->getParent($wrapper);
        if ($owner === null || !in_array($owner->kind, self::CLASS_MEMBER_KINDS, strict: true)) {
            $this->reportIfNotSnakeCase($context, $variable, 'Variable', $name);
            return;
        }

        if (!in_array($name, self::ALLOWED_MEMBER_NAMES, strict: true)) {
            $this->reportIfNotSnakeCase($context, $variable, 'Object property', $name);
        }
    }

    private function checkMemberVar(LintContext $context, Node $variable, Node $item, string $name): void
    {
        $file = $context->file;
        $node = $item;
        for ($i = 0; $i < 5 && $node !== null; $i++) {
            $node = $file->getParent($node);
        }

        if ($node === null || !in_array($node->kind, self::PROPERTY_OWNERS, strict: true)) {
            return;
        }

        if (in_array($name, self::ALLOWED_MEMBER_NAMES, strict: true)) {
            return;
        }

        $this->reportIfNotSnakeCase($context, $variable, 'Member variable', $name);
    }

    /**
     * `$object->propertyName` and `$object?->propertyName`, but not method calls.
     */
    private function checkAccessedProperty(LintContext $context, Node $wrapper): void
    {
        $file = $context->file;
        $expression = $file->getParent($wrapper);
        $access = $expression === null ? null : $file->getParent($expression);
        if ($access === null || !in_array($access->kind, self::PROPERTY_ACCESS_KINDS, strict: true)) {
            return;
        }

        $selector = $file->getChildren($access)[1] ?? null;
        $identifier = $selector === null ? null : $file->getChildren($selector)[0] ?? null;
        if ($identifier === null || $identifier->kind !== NodeKind::LocalIdentifier) {
            return;
        }

        $name = $file->getText($identifier);
        if (in_array($name, self::ALLOWED_MEMBER_NAMES, strict: true)) {
            return;
        }

        $this->reportIfNotSnakeCase($context, $identifier, 'Object property', $name);
    }

    private static function isExempt(string $name): bool
    {
        return (
            in_array($name, self::PHP_RESERVED, strict: true)
            || in_array($name, self::WORDPRESS_MIXED_CASE_VARS, strict: true)
        );
    }

    private function reportIfNotSnakeCase(LintContext $context, Node $node, string $what, string $name): void
    {
        $suggested = ValidFunctionNameRule::snakeCase($name);
        if ($suggested === $name) {
            return;
        }

        $context->report(Issue::new(
            sprintf('%s "$%s" is not in valid snake_case format.', $what, $name),
            $node->span,
        )->withHelp(sprintf('Rename it to "$%s".', $suggested)));
    }

    private static function inString(SourceFile $file, Node $node): bool
    {
        foreach ($file->getAncestors($node) as $ancestor) {
            if (in_array($ancestor->kind, self::STRING_KINDS, strict: true)) {
                return true;
            }
        }

        return false;
    }
}
