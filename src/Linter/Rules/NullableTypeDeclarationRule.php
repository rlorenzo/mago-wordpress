<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\PhpcsTokenStream;
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Internal\Spacing;

/**
 * Ports `PSR12.Functions.NullableTypeDeclaration`: no whitespace between the `?` of a nullable
 * type and the type (`WhitespaceFound`); a comment there, or anything that is not a type name, is
 * `UnexpectedCharactersFound`, which is not fixed.
 */
final class NullableTypeDeclarationRule implements Rule
{
    private const SNIFF = 'PSR12.Functions.NullableTypeDeclaration';

    private const MESSAGE = 'There must not be a space between the question mark and the type in nullable type declarations';

    private const VALID = [
        'T_STRING' => true,
        'T_NS_SEPARATOR' => true,
        'T_CALLABLE' => true,
        'T_ARRAY' => true, // phpcs makes the `array` type a T_STRING
        'T_SELF' => true,
        'T_PARENT' => true,
        'T_STATIC' => true,
        'T_NULL' => true,
        'T_FALSE' => true,
        'T_TRUE' => true,
    ];

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/nullable-type-declaration',
            name: 'Nullable type declaration',
            description: 'Reports a space between the question mark of a nullable type and the type.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $tokens = Spacing::tokens($context);
        foreach ($tokens as $i => $token) {
            if ($token->code !== 'T_NULLABLE') {
                continue;
            }

            // `$a instanceof static ? b : c` is a ternary; the token stream cannot tell it from `static ?int`.
            $before = PhpcsTokenStream::previous($tokens, $i);
            $beforeThat = $before === null ? null : PhpcsTokenStream::previous($tokens, $before);
            if (
                $before !== null
                && $tokens[$before]->code === 'T_STATIC'
                && $beforeThat !== null
                && $tokens[$beforeThat]->code === 'T_INSTANCEOF'
            ) {
                continue;
            }

            $next = $i + 1;
            while (Spacing::isWs($tokens, $next)) {
                $next++;
            }

            if (!isset($tokens[$next])) {
                continue;
            }

            $valid = isset(self::VALID[$tokens[$next]->code]);
            if ($valid && $next === ($i + 1)) {
                continue;
            }

            if (!$valid) {
                Spacing::report(
                    $this->report,
                    $context,
                    $token,
                    self::MESSAGE,
                    [self::SNIFF . '.UnexpectedCharactersFound'],
                );
                continue;
            }

            Spacing::report(
                $this->report,
                $context,
                $token,
                self::MESSAGE,
                [self::SNIFF . '.WhitespaceFound'],
                [TextEdit::replace(new Span($tokens[$i + 1]->pos, $tokens[$next]->pos), '')],
            );
        }
    }
}
