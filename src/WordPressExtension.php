<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress;

use Mago\Sdk\Extension;
use Rlorenzo\MagoWordPress\Linter\Rules\AssignmentInTernaryConditionRule;
use Rlorenzo\MagoWordPress\Linter\Rules\CapitalPDangitRule;
use Rlorenzo\MagoWordPress\Linter\Rules\CronIntervalRule;
use Rlorenzo\MagoWordPress\Linter\Rules\DiscouragedWpFunctionsRule;
use Rlorenzo\MagoWordPress\Linter\Rules\DontExtractRule;
use Rlorenzo\MagoWordPress\Linter\Rules\EnqueuedResourceParametersRule;
use Rlorenzo\MagoWordPress\Linter\Rules\EnqueuedResourcesRule;
use Rlorenzo\MagoWordPress\Linter\Rules\FileNameRule;
use Rlorenzo\MagoWordPress\Linter\Rules\GlobalVariablesOverrideRule;
use Rlorenzo\MagoWordPress\Linter\Rules\PostsPerPageRule;
use Rlorenzo\MagoWordPress\Linter\Rules\PrefixAllGlobalsRule;
use Rlorenzo\MagoWordPress\Linter\Rules\PreparedSqlPlaceholdersRule;
use Rlorenzo\MagoWordPress\Linter\Rules\SafeRedirectRule;
use Rlorenzo\MagoWordPress\Linter\Rules\SlowDbQueryRule;
use Rlorenzo\MagoWordPress\Linter\Rules\StrictInArrayRule;
use Rlorenzo\MagoWordPress\Linter\Rules\ValidHookNameRule;
use Rlorenzo\MagoWordPress\Linter\Rules\ValidPostTypeSlugRule;
use Rlorenzo\MagoWordPress\Linter\Rules\ValidFunctionNameRule;
use Rlorenzo\MagoWordPress\Linter\Rules\ValidVariableNameRule;
use Rlorenzo\MagoWordPress\Linter\Rules\WpDateTimeRule;
use Rlorenzo\MagoWordPress\Linter\Rules\WpDeprecatedClassesRule;
use Rlorenzo\MagoWordPress\Linter\Rules\WpDeprecatedFunctionsRule;
use Rlorenzo\MagoWordPress\Linter\Rules\WpDeprecatedParametersRule;
use Rlorenzo\MagoWordPress\Linter\Rules\WpDeprecatedParameterValuesRule;
use Rlorenzo\MagoWordPress\Linter\Rules\WpI18nRule;

/**
 * Constructs the complete extension advertised by each worker process.
 *
 * @api
 */
final class WordPressExtension
{
    private const VERSION = '0.1.0';

    private function __construct() {}

    public static function create(?Settings $settings = null): Extension
    {
        $settings ??= new Settings();

        return new Extension(
            identifier: 'rlorenzo/mago-wordpress',
            name: 'WordPress',
            version: self::VERSION,
            linterRules: [
                new GlobalVariablesOverrideRule(),
                new EnqueuedResourceParametersRule(),
                new EnqueuedResourcesRule(),
                new FileNameRule(),
                new PreparedSqlPlaceholdersRule($settings),
                new SafeRedirectRule(),
                new ValidHookNameRule($settings),
                new WpI18nRule($settings),
                new PrefixAllGlobalsRule($settings),
                new SlowDbQueryRule(),
                new PostsPerPageRule($settings),
                new CronIntervalRule($settings),
                new DiscouragedWpFunctionsRule(),
                new DontExtractRule(),
                new WpDateTimeRule(),
                new CapitalPDangitRule(),
                new WpDeprecatedFunctionsRule($settings),
                new WpDeprecatedClassesRule($settings),
                new WpDeprecatedParametersRule($settings),
                new WpDeprecatedParameterValuesRule($settings),
                new ValidPostTypeSlugRule(),
                new StrictInArrayRule(),
                new AssignmentInTernaryConditionRule(),
                new ValidFunctionNameRule(),
                new ValidVariableNameRule(),
            ],
        );
    }
}
