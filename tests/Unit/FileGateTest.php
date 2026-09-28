<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Tests;

use Mago\Sdk\Internal\Syntax\NodeStore;
use Mago\Sdk\Internal\Syntax\ResolvedNameStore;
use Mago\Sdk\Internal\Syntax\TriviaStore;
use Mago\Sdk\PHPVersion;
use Mago\Sdk\Syntax\SourceFile;
use PHPUnit\Framework\TestCase;
use Rlorenzo\MagoWordPress\Internal\FileGate;

use function preg_match;

final class FileGateTest extends TestCase
{
    private const WORDS = ['get_post', 'WP_Query', 'Foo\Bar', '404'];

    private const OR_PATTERN = '/\buse\s[^;]*\bfunction\b/i';

    /**
     * The word set gives the same answer as the whole-word regex it replaced.
     */
    public function testMatchesTheWholeWordRegex(): void
    {
        $regex = '/(?<!\w)(?:get_post|WP_Query|Foo\\\\Bar|404)(?!\w)|\buse\s[^;]*\bfunction\b/i';
        $sources = [
            '<?php GET_POST(1);',
            '<?php get_posts(1);',
            '<?php my_get_post();',
            '<?php new \wp_query();',
            '<?php $x->get_post;',
            '<?php Foo\Bar::x();',
            '<?php Foo\Barn::x();',
            '<?php $a = 404;',
            '<?php $a = 4040;',
            '<?php use function a\b as c;',
            '<?php use a\b;',
            '<?php',
        ];

        foreach ($sources as $source) {
            // A fresh gate per source, so its per-file cache plays no part.
            $gate = FileGate::forWords(self::WORDS, pattern: self::OR_PATTERN);
            self::assertSame(preg_match($regex, $source) === 1, $gate->passes(self::file($source)), $source);
        }

        // One gate across the files, as in a worker: each new file is screened again.
        $gate = FileGate::forWords(self::WORDS, pattern: self::OR_PATTERN);
        foreach ([...$sources, ...$sources] as $source) {
            self::assertSame(preg_match($regex, $source) === 1, $gate->passes(self::file($source)), $source);
        }
    }

    private static function file(string $source): SourceFile
    {
        return new SourceFile(
            PHPVersion::fromParts(major: 8, minor: 1),
            'fixture.php',
            $source,
            [],
            new NodeStore([], records: '', nodeCount: 0),
            new ResolvedNameStore(starts: '', records: '', bytes: '', nameCount: 0),
            new TriviaStore(records: '', triviaCount: 0),
            null,
        );
    }
}
