<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal\WordPress;

use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function in_array;
use function str_starts_with;
use function strrchr;
use function strtolower;
use function substr;

/**
 * WPCS `IsUnitTestTrait`: whether a class-like is, or extends, a known
 * WordPress/PHPUnit test class.
 *
 * @internal
 * @mago-expect lint:kan-defect
 */
final class TestClasses
{
    /** Known test class names, lowercased. */
    private const NAMES = [
        'wp_unittestcase',
        'wp_unittestcase_base',
        'phpunit_adapter_testcase',
        'wp_ajax_unittestcase',
        'wp_canonical_unittestcase',
        'wp_font_face_unittestcase',
        'wp_test_rest_controller_testcase',
        'wp_test_rest_post_type_controller_testcase',
        'wp_test_rest_testcase',
        'wp_test_xml_testcase',
        'wp_xmlrpc_unittestcase',
        'phpunit_framework_testcase',
        'phpunit\\framework\\testcase',
        'testcase',
    ];

    private function __construct() {}

    /**
     * The lowercased namespace a node is declared in, or '' in the global namespace.
     */
    public static function namespaceOf(SourceFile $file, Node $node): string
    {
        foreach ($file->getAncestors($node) as $ancestor) {
            if ($ancestor->kind !== NodeKind::Namespace) {
                continue;
            }

            foreach ($file->getChildren($ancestor) as $child) {
                if ($child->kind === NodeKind::Identifier) {
                    return strtolower($file->getText($child));
                }
            }

            return '';
        }

        return '';
    }

    /**
     * Whether the class-like's own name, or the first name after its
     * `extends`, is a known test class. `use` imports are not followed.
     *
     * @param ?string $namespace The lowercased enclosing namespace to resolve
     *                           names against, or null to match on the last
     *                           `\`-separated segment of a name only.
     * @param list<string> $custom WPCS `custom_test_classes`, lowercased
     */
    public static function is(SourceFile $file, Node $classLike, ?string $namespace, array $custom = []): bool
    {
        foreach ($file->getChildren($classLike) as $child) {
            // The declared name precedes the `extends` clause.
            if (
                $child->kind === NodeKind::LocalIdentifier
                && self::known($file->getText($child), $namespace, $custom)
            ) {
                return true;
            }

            if ($child->kind !== NodeKind::Extends) {
                continue;
            }

            foreach ($file->getDescendants($child) as $name) {
                if (
                    $name->kind === NodeKind::LocalIdentifier
                    || $name->kind === NodeKind::QualifiedIdentifier
                    || $name->kind === NodeKind::FullyQualifiedIdentifier
                ) {
                    return self::known($file->getText($name), $namespace, $custom);
                }
            }

            return false;
        }

        return false;
    }

    /**
     * @param list<string> $custom
     */
    private static function known(string $name, ?string $namespace, array $custom): bool
    {
        $name = strtolower($name);
        $lastSlash = strrchr($name, needle: '\\');
        $qualified = match (true) {
            $namespace === null => $lastSlash === false ? $name : substr($lastSlash, offset: 1),
            str_starts_with($name, '\\') => substr($name, offset: 1),
            $namespace !== '' => $namespace . '\\' . $name,
            default => $name,
        };

        return in_array($qualified, self::NAMES, strict: true) || in_array($qualified, $custom, strict: true);
    }
}
