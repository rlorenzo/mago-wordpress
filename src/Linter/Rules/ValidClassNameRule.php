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
use Rlorenzo\MagoWordPress\Internal\Report;

use function array_shift;
use function explode;
use function preg_match;
use function rtrim;
use function strtoupper;
use function substr;
use function trim;
use function ucfirst;

/**
 * Ports `PEAR.NamingConventions.ValidClassName`: a class, interface, trait or enum name must
 * start with a capital letter (`StartWithCapital`) and each `_`-separated word after the
 * first must too (`Invalid`), so `My_Class` and `MyClass` pass and `My_class` does not.
 */
final class ValidClassNameRule implements Rule
{
    private const SNIFF = 'PEAR.NamingConventions.ValidClassName';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/valid-class-name',
            name: 'Valid class name',
            description: 'Reports a class, interface, trait or enum name that is not Capitalized_Words_With_Underscores (or PascalCase).',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Class_, NodeKind::Interface, NodeKind::Trait, NodeKind::Enum],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $keyword = null;
        $name = null;
        foreach ($file->getChildren($context->node) as $child) {
            $keyword ??= $child->kind === NodeKind::Keyword ? $child : null;
            if ($child->kind === NodeKind::LocalIdentifier) {
                $name = trim($file->getText($child));
                break;
            }
        }

        if ($keyword === null || $name === null) {
            return;
        }

        $type = ucfirst($file->getText($keyword));
        if (preg_match('/^[A-Z]/', $name) !== 1) {
            $this->issue($context, $keyword, "{$type} name must begin with a capital letter", 'StartWithCapital');
        }

        $bits = explode('_', $name);
        array_shift($bits);
        foreach ($bits as $bit) {
            if ($bit === '' || $bit[0] !== strtoupper($bit[0])) {
                $this->issue($context, $keyword, self::invalid($type, $name), 'Invalid');

                return;
            }
        }
    }

    private static function invalid(string $type, string $name): string
    {
        $bits = explode('_', trim($name, characters: '_'));
        $first = (string) array_shift($bits);
        if ($first === '') {
            return "{$type} name is not valid";
        }

        $suggestion = ucfirst($first) . '_';
        foreach ($bits as $bit) {
            $suggestion .= $bit === '' ? '' : strtoupper($bit[0]) . substr($bit, offset: 1) . '_';
        }

        return "{$type} name is not valid; consider " . rtrim($suggestion, characters: '_') . ' instead';
    }

    private function issue(LintContext $context, Node $keyword, string $message, string $code): void
    {
        $this->report->issue($context, Issue::new($message, $keyword->span), [self::SNIFF . '.' . $code]);
    }
}
