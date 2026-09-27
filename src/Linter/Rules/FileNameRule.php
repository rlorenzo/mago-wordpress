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
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Internal\WordPress\TestClasses;

use function array_key_exists;
use function basename;
use function preg_match;
use function preg_replace;
use function str_contains;
use function str_ends_with;
use function str_replace;
use function strlen;
use function strrchr;
use function strtolower;
use function substr;

/**
 * Ports `WordPress.Files.FileName`.
 *
 * Runs once per file from `NodeKind::Program`, which materializes the whole
 * file tree. `is_theme` (theme template-hierarchy exceptions) and
 * `strict_class_file_names` have no `Settings` field, so this hardcodes the
 * WPCS defaults: `is_theme = false` (theme exceptions never apply) and
 * `strict_class_file_names = true` (class files always need the `class-`
 * prefix check).
 */
final class FileNameRule implements Rule
{
    private const SNIFF = 'WordPress.Files.FileName';

    /**
     * Historical exceptions to the hyphenation check, kept for WP core files
     * renamed in WP 6.1.0. WPCS also lists `.inc` variants for its own test
     * suite; those do not apply to a consuming project.
     *
     * @var array<string, true>
     */
    private const HYPHENATION_EXCEPTIONS = [
        'class.wp-dependencies.php' => true,
        'class.wp-scripts.php' => true,
        'class.wp-styles.php' => true,
        'functions.wp-scripts.php' => true,
        'functions.wp-styles.php' => true,
    ];

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/file-name',
            name: 'File name',
            description: 'Checks that file names are lowercase and hyphenated, that a file holding a class is prefixed with `class-`, and that a template-tagged file under `wp-includes` ends in `-template`.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $fileName = basename($file->path);

        $class = $file->getFirstDescendant($context->node, NodeKind::Class_);
        if ($class !== null && TestClasses::is($file, $class, namespace: null)) {
            // WPCS exempts unit test classes from this sniff entirely.
            return;
        }

        $this->checkHyphenated($context, $fileName);

        if ($class !== null) {
            $this->checkClassPrefix($context, $class, $fileName);

            return;
        }

        if (str_contains('/' . str_replace(search: '\\', replace: '/', subject: $file->path), '/wp-includes/')) {
            $this->checkTemplateSuffix($context, $fileName);
        }
    }

    /**
     * The direct-child identifier that names a class-like declaration
     * (skips the leading `Keyword` child).
     */
    private function declaredName(SourceFile $file, Node $node): ?string
    {
        foreach ($file->getChildren($node) as $child) {
            if ($child->kind === NodeKind::LocalIdentifier) {
                return $file->getText($child);
            }
        }

        return null;
    }

    private function checkHyphenated(LintContext $context, string $fileName): void
    {
        $extension = $this->extension($fileName);
        $name = $extension === '' ? $fileName : substr($fileName, offset: 0, length: -strlen($extension));

        $expected = strtolower((string) preg_replace('/[^a-zA-Z0-9]/', replacement: '-', subject: $name)) . $extension;
        if ($fileName === $expected || array_key_exists($fileName, self::HYPHENATION_EXCEPTIONS)) {
            return;
        }

        Report::fileIssue(
            $context,
            Issue::new(
                "Filenames should be all lowercase with hyphens as word separators. Expected {$expected}, but found {$fileName}.",
                $context->node->span,
            )->withHelp("Rename the file to {$expected}."),
            [self::SNIFF . '.NotHyphenatedLowercase'],
        );
    }

    /**
     * The file name's last extension including its dot, or '' when it has none.
     */
    private function extension(string $fileName): string
    {
        $extension = strrchr($fileName, needle: '.');

        return $extension === false ? '' : $extension;
    }

    private function checkClassPrefix(LintContext $context, Node $class, string $fileName): void
    {
        $className = $this->declaredName($context->file, $class);
        if ($className === null) {
            return;
        }

        $expected =
            'class-'
            . strtolower(str_replace(search: '_', replace: '-', subject: $className))
            . $this->extension($fileName);

        if ($fileName === $expected) {
            return;
        }

        Report::fileIssue(
            $context,
            Issue::new(
                "Class file names should be based on the class name with \"class-\" prepended. Expected {$expected}, but found {$fileName}.",
                $context->node->span,
            )->withHelp("Rename the file to {$expected}."),
            [self::SNIFF . '.InvalidClassFileName'],
        );
    }

    /**
     * Approximates WPCS's `@subpackage Template` tag check with a plain
     * regex over the file contents, rather than requiring the tag to sit
     * inside a real docblock on its own line.
     */
    private function checkTemplateSuffix(LintContext $context, string $fileName): void
    {
        if (preg_match('/@subpackage\s+Template\b/', $context->file->contents) !== 1) {
            return;
        }

        if (str_ends_with($fileName, '-template.php') || str_ends_with($fileName, '-template.inc')) {
            return;
        }

        Report::fileIssue(
            $context,
            Issue::new(
                'Files containing template tags should have "-template" appended to the end of the file name.',
                $context->node->span,
            )->withHelp('Rename the file so it ends in "-template" before the extension.'),
            [self::SNIFF . '.InvalidTemplateTagFileName'],
        );
    }
}
