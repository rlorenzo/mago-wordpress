<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress;

use Mago\Sdk\Extension;
use Mago\Sdk\Linter\Rule;
use Rlorenzo\MagoWordPress\Internal\CommentConversion;
use Rlorenzo\MagoWordPress\Internal\LeveledRule;
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Internal\SplitRule;
use Rlorenzo\MagoWordPress\Linter\Rules\AlternativeFunctionsRule;
use Rlorenzo\MagoWordPress\Linter\Rules\AssignmentInConditionRule;
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
use Rlorenzo\MagoWordPress\Linter\Rules\DisallowMultipleAssignmentsRule;
use Rlorenzo\MagoWordPress\Linter\Rules\DisallowSizeFunctionsInLoopsRule;
use Rlorenzo\MagoWordPress\Linter\Rules\DiscouragedConstantsRule;
use Rlorenzo\MagoWordPress\Linter\Rules\DiscouragedWpFunctionsRule;
use Rlorenzo\MagoWordPress\Linter\Rules\DontExtractRule;
use Rlorenzo\MagoWordPress\Linter\Rules\ElseIfDeclarationRule;
use Rlorenzo\MagoWordPress\Linter\Rules\EmptyStatementRule;
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
use Rlorenzo\MagoWordPress\Linter\Rules\InlineControlStructureRule;
use Rlorenzo\MagoWordPress\Linter\Rules\JumbledIncrementerRule;
use Rlorenzo\MagoWordPress\Linter\Rules\MethodDeclarationRule;
use Rlorenzo\MagoWordPress\Linter\Rules\MethodScopeRule;
use Rlorenzo\MagoWordPress\Linter\Rules\NonceVerificationRule;
use Rlorenzo\MagoWordPress\Linter\Rules\NonExecutableCodeRule;
use Rlorenzo\MagoWordPress\Linter\Rules\NoSilencedErrorsRule;
use Rlorenzo\MagoWordPress\Linter\Rules\ParenthesesSpacingRule;
use Rlorenzo\MagoWordPress\Linter\Rules\PluginMenuSlugRule;
use Rlorenzo\MagoWordPress\Linter\Rules\PostsPerPageRule;
use Rlorenzo\MagoWordPress\Linter\Rules\PrefixAllGlobalsRule;
use Rlorenzo\MagoWordPress\Linter\Rules\PregQuoteDelimiterRule;
use Rlorenzo\MagoWordPress\Linter\Rules\PreparedSqlPlaceholdersRule;
use Rlorenzo\MagoWordPress\Linter\Rules\PreparedSqlRule;
use Rlorenzo\MagoWordPress\Linter\Rules\PreparedSqlUnquotedComplexPlaceholderRule;
use Rlorenzo\MagoWordPress\Linter\Rules\PropertyDeclarationRule;
use Rlorenzo\MagoWordPress\Linter\Rules\RequireExplicitBooleanOperatorPrecedenceRule;
use Rlorenzo\MagoWordPress\Linter\Rules\RestrictedPhpFunctionsRule;
use Rlorenzo\MagoWordPress\Linter\Rules\SafeRedirectRule;
use Rlorenzo\MagoWordPress\Linter\Rules\SelfMemberReferenceRule;
use Rlorenzo\MagoWordPress\Linter\Rules\SeparateFunctionsFromOORule;
use Rlorenzo\MagoWordPress\Linter\Rules\SlowDbQueryRule;
use Rlorenzo\MagoWordPress\Linter\Rules\StaticInFinalClassRule;
use Rlorenzo\MagoWordPress\Linter\Rules\StrictComparisonsRule;
use Rlorenzo\MagoWordPress\Linter\Rules\StrictInArrayRule;
use Rlorenzo\MagoWordPress\Linter\Rules\TypeCastsRule;
use Rlorenzo\MagoWordPress\Linter\Rules\UnconditionalIfStatementRule;
use Rlorenzo\MagoWordPress\Linter\Rules\UselessOverridingMethodRule;
use Rlorenzo\MagoWordPress\Linter\Rules\ValidatedSanitizedInputRule;
use Rlorenzo\MagoWordPress\Linter\Rules\ValidClassNameRule;
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
use function implode;

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
                ...SplitRule::pair(
                    new PreparedSqlPlaceholdersRule($report, $settings),
                    $report,
                    'Prepared SQL placeholders (warnings)',
                    'The $wpdb->prepare() checks WPCS reports as warnings: LIKE without wildcards, a replacement count mismatch, an unfinished prepare and an unnecessary prepare.',
                ),
                new PreparedSqlUnquotedComplexPlaceholderRule($report),
                new SafeRedirectRule($report),
                new EscapeOutputRule($report, $settings),
                ...SplitRule::pair(
                    new ValidHookNameRule($report, $settings),
                    $report,
                    'Valid hook name (warnings)',
                    'Hook names with separators other than an underscore (WPCS UseUnderscores, a warning); uppercase hook names stay with wordpress/valid-hook-name.',
                ),
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
                ...SplitRule::pair(
                    new CapabilitiesRule($report, $settings),
                    $report,
                    'Capabilities (warnings)',
                    'Capabilities passed to current_user_can() and the other capability checks that are neither core nor in custom-capabilities (WPCS Unknown, a warning).',
                ),
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
                new UselessOverridingMethodRule($report),
                new SeparateFunctionsFromOORule($report),
                new StaticInFinalClassRule($report),
                new UnconditionalIfStatementRule($report),
                new ElseIfDeclarationRule($report),
                new ValidClassNameRule($report),
                new NonExecutableCodeRule($report),
                new EmptyStatementRule($report),
                new SelfMemberReferenceRule($report),
                new MethodScopeRule($report),
                ...SplitRule::pair(
                    new MethodDeclarationRule($report),
                    $report,
                    'Method declaration (warnings)',
                    'A method name with a single leading underscore (PSR2 MethodDeclaration Underscore, a warning; WordPress-Core leaves it out).',
                ),
                ...SplitRule::pair(
                    new PropertyDeclarationRule($report),
                    $report,
                    'Property declaration (warnings)',
                    'A property name with a leading underscore (PSR2 PropertyDeclaration Underscore, a warning; WordPress-Core leaves it out).',
                ),
                new GitMergeConflictRule($report),
                new ByteOrderMarkRule($report),
                new DisallowAlternativePhpTagsRule($report),
                new NoSilencedErrorsRule($report, $settings),
                new AssignmentInConditionRule($report),
                new DisallowMultipleAssignmentsRule($report),
                new StrictComparisonsRule($report),
                new InlineControlStructureRule($report),
                new AlternativeFunctionsRule($report, $settings),
                new PreparedSqlRule($report),
                new DirectDatabaseQueryRule($report, $settings),
                new PregQuoteDelimiterRule($report),
                new ValidatedSanitizedInputRule($report, $settings),
                ...SplitRule::pair(
                    new NonceVerificationRule($report, $settings),
                    $report,
                    'Nonce verification (warnings)',
                    'Reads of $_GET and $_REQUEST without a nonce check (WPCS Recommended, a warning); $_POST and $_FILES stay with wordpress/nonce-verification.',
                ),
            ]),
        );
    }

    /**
     * Applies the `levels` setting. A mistyped entry stops the worker with its message on
     * stderr: Mago shows a worker's stderr only when the worker exits, and a level setting
     * that silently did nothing would hide the failures it was meant to raise.
     *
     * @param list<Rule> $rules
     * @return list<Rule>
     */
    private static function leveled(Settings $settings, array $rules): array
    {
        [$rules, $problems] = LeveledRule::apply($rules, $settings->levels);
        if ($problems !== []) {
            $prefix = 'mago-wordpress: invalid configuration: ';
            fwrite(STDERR, $prefix . implode("\n{$prefix}", $problems) . "\n");
            exit(1);
        }

        return $rules;
    }
}
