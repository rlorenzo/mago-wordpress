<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Span;

use function in_array;
use function strlen;
use function substr;
use function ucfirst;

/**
 * The tag checks of `Squiz.Commenting.FileComment` that WordPress-Docs keeps.
 *
 * @internal
 */
final class FileCommentTags
{
    private const REQUIRED = ['@package', '@subpackage', '@author', '@copyright'];

    private function __construct() {}

    /**
     * @param list<array{name: string, offset: int, content: ?string, ...}> $tags
     *
     * @return list<array{string, Span, string}> message, span and sniff code of each finding
     */
    public static function findings(array $tags, Span $end): array
    {
        $findings = [];
        $found = [];
        foreach ($tags as $tag) {
            $name = $tag['name'];
            $span = new Span($tag['offset'], $tag['offset'] + strlen($name));
            $required = in_array($name, self::REQUIRED, strict: true);
            if ($required && in_array($name, $found, strict: true)) {
                $findings[] = [
                    "Only one {$name} tag is allowed in a file comment",
                    $span,
                    'Duplicate' . ucfirst(substr($name, 1)) . 'Tag',
                ];
            }

            $found[] = $name;
            if ($required && $tag['content'] === null) {
                $findings[] = [
                    "Content missing for {$name} tag in file comment",
                    $span,
                    'Empty' . ucfirst(substr($name, 1)) . 'Tag',
                ];
            }
        }

        if (!in_array('@package', $found, strict: true)) {
            $findings[] = ['Missing @package tag in file comment', $end, 'MissingPackageTag'];
        }

        return $findings;
    }
}
