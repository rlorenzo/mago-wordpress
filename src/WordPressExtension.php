<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress;

use Mago\Sdk\Extension;
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Linter\Rules\AssignmentInTernaryConditionRule;
use Rlorenzo\MagoWordPress\Linter\Rules\CapabilitiesRule;
use Rlorenzo\MagoWordPress\Linter\Rules\CapitalPDangitRule;
use Rlorenzo\MagoWordPress\Linter\Rules\ClassNameCaseRule;
use Rlorenzo\MagoWordPress\Linter\Rules\CronIntervalRule;
use Rlorenzo\MagoWordPress\Linter\Rules\DbRestrictedClassesRule;
use Rlorenzo\MagoWordPress\Linter\Rules\DbRestrictedFunctionsRule;
use Rlorenzo\MagoWordPress\Linter\Rules\DiscouragedConstantsRule;
use Rlorenzo\MagoWordPress\Linter\Rules\DiscouragedWpFunctionsRule;
use Rlorenzo\MagoWordPress\Linter\Rules\DontExtractRule;
use Rlorenzo\MagoWordPress\Linter\Rules\EnqueuedResourceParametersRule;
use Rlorenzo\MagoWordPress\Linter\Rules\EnqueuedResourcesRule;
use Rlorenzo\MagoWordPress\Linter\Rules\EscapedNotTranslatedRule;
use Rlorenzo\MagoWordPress\Linter\Rules\FileNameRule;
use Rlorenzo\MagoWordPress\Linter\Rules\GetMetaSingleRule;
use Rlorenzo\MagoWordPress\Linter\Rules\GlobalVariablesOverrideRule;
use Rlorenzo\MagoWordPress\Linter\Rules\PluginMenuSlugRule;
use Rlorenzo\MagoWordPress\Linter\Rules\PostsPerPageRule;
use Rlorenzo\MagoWordPress\Linter\Rules\PrefixAllGlobalsRule;
use Rlorenzo\MagoWordPress\Linter\Rules\PreparedSqlPlaceholdersRule;
use Rlorenzo\MagoWordPress\Linter\Rules\PreparedSqlUnquotedComplexPlaceholderRule;
use Rlorenzo\MagoWordPress\Linter\Rules\RestrictedPhpFunctionsRule;
use Rlorenzo\MagoWordPress\Linter\Rules\SafeRedirectRule;
use Rlorenzo\MagoWordPress\Linter\Rules\SlowDbQueryRule;
use Rlorenzo\MagoWordPress\Linter\Rules\StrictInArrayRule;
use Rlorenzo\MagoWordPress\Linter\Rules\TypeCastsRule;
use Rlorenzo\MagoWordPress\Linter\Rules\ValidFunctionNameRule;
use Rlorenzo\MagoWordPress\Linter\Rules\ValidHookNameRule;
use Rlorenzo\MagoWordPress\Linter\Rules\ValidPostTypeSlugRule;
use Rlorenzo\MagoWordPress\Linter\Rules\ValidVariableNameRule;
use Rlorenzo\MagoWordPress\Linter\Rules\WpDateTimeRule;
use Rlorenzo\MagoWordPress\Linter\Rules\WpDeprecatedClassesRule;
use Rlorenzo\MagoWordPress\Linter\Rules\WpDeprecatedFunctionsRule;
use Rlorenzo\MagoWordPress\Linter\Rules\WpDeprecatedParametersRule;
use Rlorenzo\MagoWordPress\Linter\Rules\WpDeprecatedParameterValuesRule;
use Rlorenzo\MagoWordPress\Linter\Rules\WpI18nRule;
use Rlorenzo\MagoWordPress\Linter\Rules\YodaConditionsRule;

/**
 * Constructs the complete extension advertised by each worker process.
 *
 * @api
 */
final class WordPressExtension
{
    private const VERSION = '1.1.0';

    private function __construct() {}

    /**
     * @mago-expect lint:halstead Every rule takes the extension's one Report.
     */
    public static function create(?Settings $settings = null): Extension
    {
        $settings ??= new Settings();
        $report = new Report($settings->honorPhpcsComments);

        return new Extension(
            identifier: 'rlorenzo/mago-wordpress',
            name: 'WordPress',
            version: self::VERSION,
            linterRules: [
                new GlobalVariablesOverrideRule($report),
                new EnqueuedResourceParametersRule($report),
                new EnqueuedResourcesRule($report),
                new FileNameRule($report),
                new PreparedSqlPlaceholdersRule($report, $settings),
                new PreparedSqlUnquotedComplexPlaceholderRule($report),
                new SafeRedirectRule($report),
                new ValidHookNameRule($report, $settings),
                new WpI18nRule($report, $settings),
                new PrefixAllGlobalsRule($report, $settings),
                new SlowDbQueryRule($report),
                new PostsPerPageRule($report, $settings),
                new CronIntervalRule($report, $settings),
                new DiscouragedWpFunctionsRule($report),
                new DontExtractRule($report),
                new WpDateTimeRule($report),
                new CapitalPDangitRule($report),
                new WpDeprecatedFunctionsRule($report, $settings),
                new WpDeprecatedClassesRule($report, $settings),
                new WpDeprecatedParametersRule($report, $settings),
                new WpDeprecatedParameterValuesRule($report, $settings),
                new ValidPostTypeSlugRule($report),
                new StrictInArrayRule($report),
                new AssignmentInTernaryConditionRule($report),
                new ValidFunctionNameRule($report),
                new ValidVariableNameRule($report),
                new DiscouragedConstantsRule($report),
                new GetMetaSingleRule($report),
                new PluginMenuSlugRule($report),
                new CapabilitiesRule($report, $settings),
                new YodaConditionsRule($report),
                new ClassNameCaseRule($report),
                new EscapedNotTranslatedRule($report),
                new DbRestrictedFunctionsRule($report),
                new DbRestrictedClassesRule($report),
                new RestrictedPhpFunctionsRule($report),
                new TypeCastsRule($report),
            ],
        );
    }
}
