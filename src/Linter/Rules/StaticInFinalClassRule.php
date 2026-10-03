<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;
use PhpToken;
use Rlorenzo\MagoWordPress\Internal\NodeIndex;
use Rlorenzo\MagoWordPress\Internal\Report;

use function array_filter;
use function array_values;
use function end;
use function in_array;
use function str_contains;
use function strtolower;

/**
 * Ports `Universal.CodeAnalysis.StaticInFinalClass`: `static` where `self` means the same,
 * in a final class, an anonymous class or an enum: a method's or arrow function's `static`
 * return type (`ReturnType`), and `static::` (`ScopeResolution`), `new static`
 * (`NewInstance`) and `instanceof static` (`InstanceOf`) in a method body. Closures, traits,
 * interfaces and code outside a method body are left alone, as in the sniff. Fixed to `self`.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class StaticInFinalClassRule implements Rule
{
    private const SNIFF = 'Universal.CodeAnalysis.StaticInFinalClass';

    private const ACCESS = [
        NodeKind::ClassConstantAccess,
        NodeKind::StaticMethodCall,
        NodeKind::StaticPropertyAccess,
        NodeKind::StaticMethodPartialApplication,
    ];

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/static-in-final-class',
            name: 'Static in final class',
            description: 'Reports `static` used for late static binding in a final class, anonymous class or enum, where `self` means the same.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        if (!str_contains(strtolower($file->contents), 'static')) {
            return;
        }

        foreach (NodeIndex::ofKind($file, $context->node, NodeKind::Keyword) as $keyword) {
            if (strtolower($file->getText($keyword)) === 'static') {
                $this->check($context, $keyword);
            }
        }
    }

    private function check(LintContext $context, Node $static): void
    {
        $file = $context->file;
        $parent = $file->getParent($static);
        if ($parent?->kind === NodeKind::Hint) {
            $this->returnType($context, $static);

            return;
        }

        $owner = $parent?->kind === NodeKind::Expression ? $file->getParent($parent) : null;
        if ($owner === null || !self::inFinalMethodBody($file, $static)) {
            return;
        }

        [$code, $found] = match (true) {
            $owner->kind === NodeKind::Instantiation, self::isInstantiated($file, $owner) => [
                'NewInstance',
                'new static',
            ],
            $owner->kind === NodeKind::Binary && ($file->getChildren($owner)[2] ?? null) === $parent => [
                'InstanceOf',
                self::lastToken($file, $file->getChildren($owner)[0]) . ' instanceof static',
            ],
            in_array($owner->kind, self::ACCESS, strict: true) => [
                'ScopeResolution',
                'static::' . self::firstToken($file, $file->getChildren($owner)[1] ?? $owner),
            ],
            default => [null, ''],
        };
        if ($code !== null) {
            $this->issue($context, $static, $code, "\"{$found}\"");
        }
    }

    /** `new static::$name`: the access is the class an instantiation creates. */
    private static function isInstantiated(SourceFile $file, Node $access): bool
    {
        if (!in_array($access->kind, self::ACCESS, strict: true)) {
            return false;
        }

        $node = $access;
        do {
            $node = $file->getParent($node);
        } while ($node !== null && in_array($node->kind, [NodeKind::Access, NodeKind::Expression], strict: true));

        return $node?->kind === NodeKind::Instantiation;
    }

    private function returnType(LintContext $context, Node $static): void
    {
        $file = $context->file;
        $node = $static;
        do {
            $node = $file->getParent($node);
        } while ($node !== null && $node->kind !== NodeKind::FunctionLikeReturnTypeHint);

        $function = $node === null ? null : $file->getParent($node);
        if ($node === null || $function === null || self::firstStatic($file, $node) !== $static) {
            return;
        }

        $scope = match ($function->kind) {
            NodeKind::Method => self::directScope($file, $function),
            NodeKind::ArrowFunction => self::nearestScope($file, $function),
            default => null,
        };
        if ($scope !== null && self::isFinal($file, $scope)) {
            $this->issue($context, $static, 'ReturnType', '"static" return type');
        }
    }

    private static function firstStatic(SourceFile $file, Node $hint): ?Node
    {
        foreach ($file->getDescendants($hint, NodeKind::Keyword) as $keyword) {
            if (strtolower($file->getText($keyword)) === 'static') {
                return $keyword;
            }
        }

        return null;
    }

    /** Whether the node sits in the body of a method of a final class, an anonymous class or an enum. */
    private static function inFinalMethodBody(SourceFile $file, Node $node): bool
    {
        $inBody = false;
        while (($node = $file->getParent($node)) !== null) {
            $inBody = $inBody || $node->kind === NodeKind::MethodBody;
            if (in_array($node->kind, [NodeKind::Closure, NodeKind::Function], strict: true)) {
                return false;
            }

            if ($node->kind === NodeKind::Method) {
                $scope = self::directScope($file, $node);

                return $inBody && $scope !== null && self::isFinal($file, $scope);
            }
        }

        return false;
    }

    private static function directScope(SourceFile $file, Node $method): ?Node
    {
        $member = $file->getParent($method);

        return $member === null ? null : $file->getParent($member);
    }

    private static function nearestScope(SourceFile $file, Node $node): ?Node
    {
        while (($node = $file->getParent($node)) !== null) {
            if (in_array($node->kind, [NodeKind::Class_, NodeKind::AnonymousClass, NodeKind::Enum], strict: true)) {
                return $node;
            }
        }

        return null;
    }

    private static function isFinal(SourceFile $file, Node $scope): bool
    {
        if (in_array($scope->kind, [NodeKind::AnonymousClass, NodeKind::Enum], strict: true)) {
            return true;
        }

        if ($scope->kind !== NodeKind::Class_) {
            return false;
        }

        foreach ($file->getChildren($scope) as $child) {
            if ($child->kind === NodeKind::Modifier && strtolower($file->getText($child)) === 'final') {
                return true;
            }
        }

        return false;
    }

    private static function lastToken(SourceFile $file, ?Node $node): string
    {
        $tokens = $node === null ? [] : self::tokens($file->getText($node));
        $last = end($tokens);

        return $last === false ? '' : $last->text;
    }

    private static function firstToken(SourceFile $file, Node $node): string
    {
        return self::tokens($file->getText($node))[0]->text ?? '';
    }

    /** @return list<PhpToken> */
    private static function tokens(string $code): array
    {
        return array_values(array_filter(
            PhpToken::tokenize('<?php ' . $code),
            static fn(PhpToken $token): bool => !$token->isIgnorable(),
        ));
    }

    private function issue(LintContext $context, Node $static, string $code, string $found): void
    {
        $this->report->issue(
            $context,
            Issue::new(
                "Use \"self\" instead of \"static\" when using late static binding in a final OO construct. Found: {$found}",
                $static->span,
            )->withEdit(TextEdit::replace($static->span, 'self')),
            [self::SNIFF . '.' . $code],
        );
    }
}
