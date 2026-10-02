<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress;

use Mago\Sdk\Extension;
use Mago\Sdk\Linter\Rule;
use Rlorenzo\MagoWordPress\Internal\CommentConversion;
use Rlorenzo\MagoWordPress\Internal\LeveledRule;
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Linter\Rules\AlternativeFunctionsRule;
use Rlorenzo\MagoWordPress\Linter\Rules\AssignmentInTernaryConditionRule;
use Rlorenzo\MagoWordPress\Linter\Rules\ByteOrderMarkRule;
use Rlorenzo\MagoWordPress\Linter\Rules\CapabilitiesRule;
use Rlorenzo\MagoWordPress\Linter\Rules\CapitalPDangitRule;
use Rlorenzo\MagoWordPress\Linter\Rules\ClassNameCaseRule;
use Rlorenzo\MagoWordPress\Linter\Rules\CronIntervalRule;
use Rlorenzo\MagoWordPress\Linter\Rules\DbRestrictedClassesRule;
use Rlorenzo\MagoWordPress\Linter\Rules\DbRestrictedFunctionsRule;
use Rlorenzo\MagoWordPress\Linter\Rules\DirectDatabaseQueryRule;
use Rlorenzo\MagoWordPress\Linter\Rules\DisallowAlternativePhpTagsRule;
use Rlorenzo\MagoWordPress\Linter\Rules\DisallowSizeFunctionsInLoopsRule;
use Rlorenzo\MagoWordPress\Linter\Rules\DiscouragedConstantsRule;
use Rlorenzo\MagoWordPress\Linter\Rules\DiscouragedWpFunctionsRule;
use Rlorenzo\MagoWordPress\Linter\Rules\DontExtractRule;
use Rlorenzo\MagoWordPress\Linter\Rules\EnqueuedResourceParametersRule;
use Rlorenzo\MagoWordPress\Linter\Rules\EnqueuedResourcesRule;
use Rlorenzo\MagoWordPress\Linter\Rules\EscapedNotTranslatedRule;
use Rlorenzo\MagoWordPress\Linter\Rules\EscapeOutputRule;
use Rlorenzo\MagoWordPress\Linter\Rules\FileNameRule;
use Rlorenzo\MagoWordPress\Linter\Rules\ForeachUniqueAssignmentRule;
use Rlorenzo\MagoWordPress\Linter\Rules\ForLoopWithTestFunctionCallRule;
use Rlorenzo\MagoWordPress\Linter\Rules\GetMetaSingleRule;
use Rlorenzo\MagoWordPress\Linter\Rules\GitMergeConflictRule;
use Rlorenzo\MagoWordPress\Linter\Rules\GlobalVariablesOverrideRule;
use Rlorenzo\MagoWordPress\Linter\Rules\JumbledIncrementerRule;
use Rlorenzo\MagoWordPress\Linter\Rules\NonceVerificationRule;
use Rlorenzo\MagoWordPress\Linter\Rules\ParenthesesSpacingRule;
use Rlorenzo\MagoWordPress\Linter\Rules\PluginMenuSlugRule;
use Rlorenzo\MagoWordPress\Linter\Rules\PostsPerPageRule;
use Rlorenzo\MagoWordPress\Linter\Rules\PrefixAllGlobalsRule;
use Rlorenzo\MagoWordPress\Linter\Rules\PregQuoteDelimiterRule;
use Rlorenzo\MagoWordPress\Linter\Rules\PreparedSqlPlaceholdersRule;
use Rlorenzo\MagoWordPress\Linter\Rules\PreparedSqlRule;
use Rlorenzo\MagoWordPress\Linter\Rules\PreparedSqlUnquotedComplexPlaceholderRule;
use Rlorenzo\MagoWordPress\Linter\Rules\RequireExplicitBooleanOperatorPrecedenceRule;
use Rlorenzo\MagoWordPress\Linter\Rules\RestrictedPhpFunctionsRule;
use Rlorenzo\MagoWordPress\Linter\Rules\SafeRedirectRule;
use Rlorenzo\MagoWordPress\Linter\Rules\SlowDbQueryRule;
use Rlorenzo\MagoWordPress\Linter\Rules\StrictInArrayRule;
use Rlorenzo\MagoWordPress\Linter\Rules\TypeCastsRule;
use Rlorenzo\MagoWordPress\Linter\Rules\ValidatedSanitizedInputRule;
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

use function fwrite;

use const STDERR;

/**
 * Constructs the complete extension advertised by each worker process.
 *
 * @api
 */
final class WordPressExtension
{
    private const VERSION = '1.2.0';

    private function __construct() {}

    /**
     * @mago-expect lint:halstead Every rule takes the extension's one Report.
     */
    public static function create(?Settings $settings = null): Extension
    {
        $settings ??= new Settings();
        $report = new Report(
            $settings->honorPhpcsComments && getenv(CommentConversion::ENV) === false,
            $settings->excludePatterns,
            $settings->excludeGroups,
        );

        return new Extension(
            identifier: 'rlorenzo/mago-wordpress',
            name: 'WordPress',
            version: self::VERSION,
            linterRules: self::leveled($settings, [
                new GlobalVariablesOverrideRule($report, $settings),
                new EnqueuedResourceParametersRule($report),
                new EnqueuedResourcesRule($report),
                new FileNameRule($report, $settings),
                new PreparedSqlPlaceholdersRule($report, $settings),
                new PreparedSqlUnquotedComplexPlaceholderRule($report),
                new SafeRedirectRule($report),
                new EscapeOutputRule($report, $settings),
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
                new ValidVariableNameRule($report, $settings),
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
                new ParenthesesSpacingRule($report),
                new JumbledIncrementerRule($report),
                new ForLoopWithTestFunctionCallRule($report),
                new DisallowSizeFunctionsInLoopsRule($report),
                new RequireExplicitBooleanOperatorPrecedenceRule($report),
                new ForeachUniqueAssignmentRule($report),
                new GitMergeConflictRule($report),
                new ByteOrderMarkRule($report),
                new DisallowAlternativePhpTagsRule($report),
                new AlternativeFunctionsRule($report, $settings),
                new PreparedSqlRule($report),
                new DirectDatabaseQueryRule($report, $settings),
                new PregQuoteDelimiterRule($report),
                new ValidatedSanitizedInputRule($report, $settings),
                new NonceVerificationRule($report, $settings),
            ]),
        );
    }

    /**
     * Applies the `levels` setting; a mistyped entry is reported on stderr, which Mago shows,
     * rather than silently doing nothing.
     *
     * @param list<Rule> $rules
     * @return list<Rule>
     */
    private static function leveled(Settings $settings, array $rules): array
    {
        [$rules, $problems] = LeveledRule::apply($rules, $settings->levels);
        foreach ($problems as $problem) {
            fwrite(STDERR, "mago-wordpress: {$problem}\n");
        }

        return $rules;
    }
}
