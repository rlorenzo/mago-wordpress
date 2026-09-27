<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rlorenzo\MagoWordPress\Internal\PhpcsSuppressions;

use function explode;
use function hrtime;
use function str_contains;
use function str_repeat;
use function strlen;

/**
 * Each case marks a line that a report must survive with `keep();` and a
 * line that must be silenced with `drop();`. Reports carry the codes below.
 */
final class PhpcsSuppressionsTest extends TestCase
{
    private const CODES = ['WordPress.Security.SafeRedirect.wp_redirect_wp_redirect'];

    /**
     * @return iterable<string, array{string}>
     */
    public static function sources(): iterable
    {
        yield 'no comments' => ["<?php\nkeep();\n"];
        yield 'ignore on its own line silences the next line' => ["<?php\n// phpcs:ignore\ndrop();\nkeep();\n"];
        yield 'trailing ignore silences its own line' => ["<?php\ndrop(); // phpcs:ignore\nkeep();\n"];
        yield 'ignore with a note' => ["<?php\n// phpcs:ignore WordPress.Security -- reviewed\ndrop();\n"];
        yield 'blank codes before a note mean no codes' => ["<?php\n// phpcs:ignore   -- reviewed\ndrop();\n"];
        yield 'blank codes in a list mean no codes' => ["<?php\n// phpcs:disable ,\ndrop();\n"];
        yield 'ignore of another sniff' => ["<?php\n// phpcs:ignore WordPress.WP.I18n\nkeep();\n"];
        yield 'ignore list' => ["<?php\n# phpcs:ignore Generic.Foo, WordPress.Security.SafeRedirect\ndrop();\n"];
        yield 'standard prefix' => ["<?php\n/* phpcs:ignore WordPress */\ndrop();\n"];
        yield 'prefix only at dot boundaries' => ["<?php\n// phpcs:ignore WordPress.Sec\nkeep();\n"];
        yield 'codes are case-sensitive' => ["<?php\n// phpcs:ignore wordpress.security\nkeep();\n"];
        yield 'directive is case-insensitive' => ["<?php\n// PHPCS:Ignore\ndrop();\n"];
        yield 'message code' => [
            "<?php\n// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect\ndrop();\n",
        ];
        yield 'sibling message code' => [
            "<?php\n// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_other\nkeep();\n",
        ];
        yield 'docblock line' => ["<?php\n/**\n * @phpcs:disable\n */\ndrop();\n"];
        yield 'one-line docblock trails its delimiters' => ["<?php\n/** phpcs:ignore */\nkeep();\n"];
        yield 'disable and enable' => [
            "<?php\nkeep();\n// phpcs:disable\ndrop();\n\tdrop();\n// phpcs:enable\nkeep();\n",
        ];
        yield 'disable of another sniff' => ["<?php\n// phpcs:disable Generic\nkeep();\n"];
        yield 'enable of a subset' => [
            "<?php\n// phpcs:disable WordPress\ndrop();\n// phpcs:enable WordPress.Security.SafeRedirect\nkeep();\n",
        ];
        yield 'a subset cannot re-enable a bare disable' => [
            "<?php\n// phpcs:disable\n// phpcs:enable WordPress.Security\ndrop();\n",
        ];
        yield 'enable of a wider category' => [
            "<?php\n// phpcs:disable WordPress.Security.SafeRedirect\ndrop();\n// phpcs:enable WordPress\nkeep();\n",
        ];
        yield 'a region with exceptions stays open after its codes are enabled' => [
            "<?php\n// phpcs:disable WordPress.Security.SafeRedirect\n// phpcs:enable WordPress.Security.SafeRedirect.Other\n"
                . "// phpcs:enable WordPress.Security\nkeep(); // phpcs:disable WordPress\ndrop();\n",
        ];
        yield 'trailing disable covers its own line' => ["<?php\ndrop(); // phpcs:disable\ndrop();\n"];
        yield 'trailing enable covers its own line' => ["<?php\n// phpcs:disable\nkeep(); // phpcs:enable\nkeep();\n"];
        yield 'ignoreFile' => ["<?php\ndrop();\n// phpcs:ignoreFile\ndrop();\n"];
        yield 'legacy ignore line' => [
            "<?php\n// @codingStandardsIgnoreLine\ndrop();\nkeep();\ndrop(); // @codingStandardsIgnoreLine\n",
        ];
        yield 'legacy region' => [
            "<?php\n// @codingStandardsIgnoreStart\ndrop();\n// @codingStandardsIgnoreEnd\nkeep();\n",
        ];
        yield 'legacy ignore file' => ["<?php\ndrop();\n/* @codingStandardsIgnoreFile */\n"];
        yield 'text in a string is not a comment' => ["<?php\n\$a = '// phpcs:ignoreFile';\nkeep();\n"];
    }

    #[DataProvider('sources')]
    public function testSuppression(string $source): void
    {
        $suppressions = PhpcsSuppressions::fromSource($source);
        foreach (explode("\n", $source) as $index => $line) {
            if (str_contains($line, 'keep();')) {
                self::assertFalse($suppressions->isSuppressed($index + 1, self::CODES), "line {$index} is kept");
            }

            if (str_contains($line, 'drop();')) {
                self::assertTrue($suppressions->isSuppressed($index + 1, self::CODES), "line {$index} is dropped");
            }
        }
    }

    public function testIsEmptyOnlyWithoutSuppressionComments(): void
    {
        self::assertTrue(PhpcsSuppressions::fromSource("<?php\n// a comment\nf();\n")->isEmpty());
        self::assertFalse(PhpcsSuppressions::fromSource("<?php\n// phpcs:ignore\nf();\n")->isEmpty());
        self::assertFalse(PhpcsSuppressions::fromSource("<?php\n// @codingStandardsIgnoreFile\n")->isEmpty());
    }

    /**
     * Ordinary comments record nothing and lookups binary-search, so lookups
     * stay fast on a comment-heavy file. A linear scan per lookup takes
     * seconds here.
     */
    public function testLookupsScaleOnCommentHeavySource(): void
    {
        $directive = "<?php\n// phpcs:disable Generic.Foo\n";
        $source = $directive . str_repeat("// note\nf();\n", times: 20_000);
        $suppressions = PhpcsSuppressions::fromSource($source);

        $start = hrtime(true);
        for ($offset = strlen($directive); $offset < strlen($source); $offset += 12) {
            self::assertFalse($suppressions->isSuppressedAt($offset, self::CODES));
        }

        self::assertLessThan(1.0, (hrtime(true) - $start) / 1e9);
    }

    public function testOffsetLookupUsesTheOffsetsLine(): void
    {
        $suppressions = PhpcsSuppressions::fromSource("<?php\n// phpcs:ignore\nf();\ng();\n");

        self::assertFalse($suppressions->isSuppressedAt(5, self::CODES));
        self::assertTrue($suppressions->isSuppressedAt(22, self::CODES));
        self::assertTrue($suppressions->isSuppressedAt(25, self::CODES));
        self::assertFalse($suppressions->isSuppressedAt(27, self::CODES));
    }

    public function testMessageCodeMustMatchAMessageCode(): void
    {
        $suppressions = PhpcsSuppressions::fromSource(
            "<?php\n// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment\nf();\n",
        );

        self::assertTrue($suppressions->isSuppressed(3, ['WordPress.WP.I18n.MissingTranslatorsComment']));
        self::assertFalse($suppressions->isSuppressed(3, ['WordPress.WP.I18n.NonSingularStringLiteralText']));
    }
}
