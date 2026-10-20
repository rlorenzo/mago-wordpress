<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\CallExpression;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\Calls;
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Linter\CallRule;

use function ltrim;

/**
 * Ports `Generic.PHP.DeprecatedFunctions`. phpcs asks the PHP it runs on which internal functions
 * are deprecated (`ReflectionFunction::isDeprecated()`); this rule has no such runtime, so it
 * carries the list PHP 8.4 gives with the common extensions loaded. A function PHP 8.0 removed
 * (`each()`, `create_function()`, `money_format()`) is not on it, as phpcs on PHP 8 reports none either.
 */
final class DeprecatedFunctionsRule extends CallRule
{
    private const CODE = 'Generic.PHP.DeprecatedFunctions.Deprecated';

    // The PHP 8.4 list; regenerate with ReflectionFunction::isDeprecated() when WordPress raises its PHP ceiling.
    private const FUNCTIONS = [
        'assert_options',
        'date_sunrise',
        'date_sunset',
        'gmstrftime',
        'intlcal_set',
        'intlgregcal_create_instance',
        'lcg_value',
        'libxml_disable_entity_loader',
        'mysqli_kill',
        'mysqli_ping',
        'mysqli_refresh',
        'openssl_free_key',
        'openssl_pkey_free',
        'openssl_x509_free',
        'pg_clientencoding',
        'pg_cmdtuples',
        'pg_errormessage',
        'pg_fieldisnull',
        'pg_fieldname',
        'pg_fieldnum',
        'pg_fieldprtlen',
        'pg_fieldsize',
        'pg_fieldtype',
        'pg_freeresult',
        'pg_getlastoid',
        'pg_loclose',
        'pg_locreate',
        'pg_loexport',
        'pg_loimport',
        'pg_loopen',
        'pg_loread',
        'pg_loreadall',
        'pg_lounlink',
        'pg_lowrite',
        'pg_numfields',
        'pg_numrows',
        'pg_result',
        'pg_setclientencoding',
        'shmop_close',
        'strftime',
        'strptime',
        'utf8_decode',
        'utf8_encode',
        'xml_set_object',
        'zip_close',
        'zip_entry_close',
        'zip_entry_compressedsize',
        'zip_entry_compressionmethod',
        'zip_entry_filesize',
        'zip_entry_name',
        'zip_entry_open',
        'zip_entry_read',
        'zip_open',
        'zip_read',
    ];

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/deprecated-functions',
            name: 'Deprecated functions',
            description: 'Reports calls to PHP functions that PHP 8.4 deprecates, such as `strftime()` and `utf8_encode()`.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::FunctionCall],
        );
    }

    protected function names(): array
    {
        return self::FUNCTIONS;
    }

    protected function inspect(LintContext $context, CallExpression $call, string $name): void
    {
        $written = ltrim((string) Calls::name($context->file, $context->node), characters: '\\');
        $this->report->issue(
            $context,
            Issue::new(
                "Function {$written}() has been deprecated",
                $call->callee->span,
                'deprecated function',
            )->withHelp('Stop calling it; PHP removes deprecated functions in a later release.'),
            [self::CODE],
        );
    }
}
