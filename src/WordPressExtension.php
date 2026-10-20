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
use Rlorenzo\MagoWordPress\Linter\Rules\AnonClassKeywordSpacingRule;
use Rlorenzo\MagoWordPress\Linter\Rules\ArbitraryParenthesesSpacingRule;
use Rlorenzo\MagoWordPress\Linter\Rules\ArrayAlignmentRule;
use Rlorenzo\MagoWordPress\Linter\Rules\ArrayBraceSpacingRule;
use Rlorenzo\MagoWordPress\Linter\Rules\ArrayDeclarationSpacingRule;
use Rlorenzo\MagoWordPress\Linter\Rules\ArrayIndentationRule;
use Rlorenzo\MagoWordPress\Linter\Rules\ArrayKeySpacingRestrictionsRule;
use Rlorenzo\MagoWordPress\Linter\Rules\AssignmentInConditionRule;
use Rlorenzo\MagoWordPress\Linter\Rules\AssignmentInTernaryConditionRule;
use Rlorenzo\MagoWordPress\Linter\Rules\AttributeBracketSpacingRule;
use Rlorenzo\MagoWordPress\Linter\Rules\BlockCommentRule;
use Rlorenzo\MagoWordPress\Linter\Rules\ByteOrderMarkRule;
use Rlorenzo\MagoWordPress\Linter\Rules\CapabilitiesRule;
use Rlorenzo\MagoWordPress\Linter\Rules\CapitalPDangitRule;
use Rlorenzo\MagoWordPress\Linter\Rules\CastSpacingRule;
use Rlorenzo\MagoWordPress\Linter\Rules\CastStructureSpacingRule;
use Rlorenzo\MagoWordPress\Linter\Rules\ClassCommentRule;
use Rlorenzo\MagoWordPress\Linter\Rules\ClassDeclarationRule;
use Rlorenzo\MagoWordPress\Linter\Rules\ClassInstantiationRule;
use Rlorenzo\MagoWordPress\Linter\Rules\ClassModifierKeywordOrderRule;
use Rlorenzo\MagoWordPress\Linter\Rules\ClassNameCaseRule;
use Rlorenzo\MagoWordPress\Linter\Rules\ClosingDeclarationCommentRule;
use Rlorenzo\MagoWordPress\Linter\Rules\ClosingTagRule;
use Rlorenzo\MagoWordPress\Linter\Rules\CommaAfterLastRule;
use Rlorenzo\MagoWordPress\Linter\Rules\CommaSpacingRule;
use Rlorenzo\MagoWordPress\Linter\Rules\CommentedOutCodeRule;
use Rlorenzo\MagoWordPress\Linter\Rules\ConcatenationSpacingRule;
use Rlorenzo\MagoWordPress\Linter\Rules\ConstantModifierKeywordOrderRule;
use Rlorenzo\MagoWordPress\Linter\Rules\ConstructorDestructorReturnRule;
use Rlorenzo\MagoWordPress\Linter\Rules\ControlSignatureRule;
use Rlorenzo\MagoWordPress\Linter\Rules\ControlStructureSpacingRule;
use Rlorenzo\MagoWordPress\Linter\Rules\CronIntervalRule;
use Rlorenzo\MagoWordPress\Linter\Rules\DbRestrictedClassesRule;
use Rlorenzo\MagoWordPress\Linter\Rules\DbRestrictedFunctionsRule;
use Rlorenzo\MagoWordPress\Linter\Rules\DeprecatedFunctionsRule;
use Rlorenzo\MagoWordPress\Linter\Rules\DirectDatabaseQueryRule;
use Rlorenzo\MagoWordPress\Linter\Rules\DirnameRule;
use Rlorenzo\MagoWordPress\Linter\Rules\DisallowAlternativePhpTagsRule;
use Rlorenzo\MagoWordPress\Linter\Rules\DisallowAttributeParenthesesRule;
use Rlorenzo\MagoWordPress\Linter\Rules\DisallowInlineTabsRule;
use Rlorenzo\MagoWordPress\Linter\Rules\DisallowLonelyIfRule;
use Rlorenzo\MagoWordPress\Linter\Rules\DisallowMixedGroupUseRule;
use Rlorenzo\MagoWordPress\Linter\Rules\DisallowMultipleAssignmentsRule;
use Rlorenzo\MagoWordPress\Linter\Rules\DisallowMultipleStatementsRule;
use Rlorenzo\MagoWordPress\Linter\Rules\DisallowNamespaceCurlyBraceSyntaxRule;
use Rlorenzo\MagoWordPress\Linter\Rules\DisallowNamespaceWithoutNameRule;
use Rlorenzo\MagoWordPress\Linter\Rules\DisallowShortArraySyntaxRule;
use Rlorenzo\MagoWordPress\Linter\Rules\DisallowShortOpenTagRule;
use Rlorenzo\MagoWordPress\Linter\Rules\DisallowShortTernaryRule;
use Rlorenzo\MagoWordPress\Linter\Rules\DisallowSizeFunctionsInLoopsRule;
use Rlorenzo\MagoWordPress\Linter\Rules\DisallowSpaceIndentRule;
use Rlorenzo\MagoWordPress\Linter\Rules\DisallowStandalonePostIncrementDecrementRule;
use Rlorenzo\MagoWordPress\Linter\Rules\DiscouragedConstantsRule;
use Rlorenzo\MagoWordPress\Linter\Rules\DiscouragedWpFunctionsRule;
use Rlorenzo\MagoWordPress\Linter\Rules\DocCommentAlignmentRule;
use Rlorenzo\MagoWordPress\Linter\Rules\DocCommentRule;
use Rlorenzo\MagoWordPress\Linter\Rules\DontExtractRule;
use Rlorenzo\MagoWordPress\Linter\Rules\DoubleQuoteUsageRule;
use Rlorenzo\MagoWordPress\Linter\Rules\DuplicateArrayKeyRule;
use Rlorenzo\MagoWordPress\Linter\Rules\ElseIfDeclarationRule;
use Rlorenzo\MagoWordPress\Linter\Rules\EmbeddedPhpRule;
use Rlorenzo\MagoWordPress\Linter\Rules\EmptyCatchCommentRule;
use Rlorenzo\MagoWordPress\Linter\Rules\EmptyPhpStatementRule;
use Rlorenzo\MagoWordPress\Linter\Rules\EmptyStatementRule;
use Rlorenzo\MagoWordPress\Linter\Rules\EndFileNewlineRule;
use Rlorenzo\MagoWordPress\Linter\Rules\EnqueuedResourceParametersRule;
use Rlorenzo\MagoWordPress\Linter\Rules\EnqueuedResourcesRule;
use Rlorenzo\MagoWordPress\Linter\Rules\EscapedNotTranslatedRule;
use Rlorenzo\MagoWordPress\Linter\Rules\EscapeOutputRule;
use Rlorenzo\MagoWordPress\Linter\Rules\FileCommentRule;
use Rlorenzo\MagoWordPress\Linter\Rules\FileHeaderRule;
use Rlorenzo\MagoWordPress\Linter\Rules\FileNameRule;
use Rlorenzo\MagoWordPress\Linter\Rules\ForbiddenFunctionsRule;
use Rlorenzo\MagoWordPress\Linter\Rules\ForeachUniqueAssignmentRule;
use Rlorenzo\MagoWordPress\Linter\Rules\ForLoopShouldBeWhileLoopRule;
use Rlorenzo\MagoWordPress\Linter\Rules\ForLoopWithTestFunctionCallRule;
use Rlorenzo\MagoWordPress\Linter\Rules\FunctionCallArgumentSpacingRule;
use Rlorenzo\MagoWordPress\Linter\Rules\FunctionCallSignatureRule;
use Rlorenzo\MagoWordPress\Linter\Rules\FunctionClosingBraceRule;
use Rlorenzo\MagoWordPress\Linter\Rules\FunctionCommentRule;
use Rlorenzo\MagoWordPress\Linter\Rules\FunctionCommentThrowTagRule;
use Rlorenzo\MagoWordPress\Linter\Rules\FunctionDeclarationArgumentSpacingRule;
use Rlorenzo\MagoWordPress\Linter\Rules\FunctionDuplicateArgumentRule;
use Rlorenzo\MagoWordPress\Linter\Rules\GetMetaSingleRule;
use Rlorenzo\MagoWordPress\Linter\Rules\GitMergeConflictRule;
use Rlorenzo\MagoWordPress\Linter\Rules\GlobalVariablesOverrideRule;
use Rlorenzo\MagoWordPress\Linter\Rules\HereNowdocIdentifierSpacingRule;
use Rlorenzo\MagoWordPress\Linter\Rules\IncludingFileRule;
use Rlorenzo\MagoWordPress\Linter\Rules\IncrementDecrementSpacingRule;
use Rlorenzo\MagoWordPress\Linter\Rules\IncrementDecrementUsageRule;
use Rlorenzo\MagoWordPress\Linter\Rules\IniSetRule;
use Rlorenzo\MagoWordPress\Linter\Rules\InlineCommentRule;
use Rlorenzo\MagoWordPress\Linter\Rules\InlineControlStructureRule;
use Rlorenzo\MagoWordPress\Linter\Rules\JumbledIncrementerRule;
use Rlorenzo\MagoWordPress\Linter\Rules\LanguageConstructSpacingRule;
use Rlorenzo\MagoWordPress\Linter\Rules\LineEndingsRule;
use Rlorenzo\MagoWordPress\Linter\Rules\LowercaseClassResolutionKeywordRule;
use Rlorenzo\MagoWordPress\Linter\Rules\LowercaseConstantRule;
use Rlorenzo\MagoWordPress\Linter\Rules\LowercasePhpTagRule;
use Rlorenzo\MagoWordPress\Linter\Rules\LowercaseTypeRule;
use Rlorenzo\MagoWordPress\Linter\Rules\LowercaseUseFunctionConstRule;
use Rlorenzo\MagoWordPress\Linter\Rules\MethodDeclarationRule;
use Rlorenzo\MagoWordPress\Linter\Rules\MethodScopeRule;
use Rlorenzo\MagoWordPress\Linter\Rules\MultiLineFunctionDeclarationRule;
use Rlorenzo\MagoWordPress\Linter\Rules\NamespaceDeclarationRule;
use Rlorenzo\MagoWordPress\Linter\Rules\NoDoubleNegativeRule;
use Rlorenzo\MagoWordPress\Linter\Rules\NoEchoSprintfRule;
use Rlorenzo\MagoWordPress\Linter\Rules\NoLeadingBackslashRule;
use Rlorenzo\MagoWordPress\Linter\Rules\NonceVerificationRule;
use Rlorenzo\MagoWordPress\Linter\Rules\NonExecutableCodeRule;
use Rlorenzo\MagoWordPress\Linter\Rules\NoReservedKeywordParameterNamesRule;
use Rlorenzo\MagoWordPress\Linter\Rules\NoSilencedErrorsRule;
use Rlorenzo\MagoWordPress\Linter\Rules\NoUselessAliasesRule;
use Rlorenzo\MagoWordPress\Linter\Rules\NullableTypeDeclarationRule;
use Rlorenzo\MagoWordPress\Linter\Rules\ObjectOperatorSpacingRule;
use Rlorenzo\MagoWordPress\Linter\Rules\OneNamespaceDeclarationPerFileRule;
use Rlorenzo\MagoWordPress\Linter\Rules\OneObjectStructurePerFileRule;
use Rlorenzo\MagoWordPress\Linter\Rules\OpeningBraceSameLineRule;
use Rlorenzo\MagoWordPress\Linter\Rules\OpeningFunctionBraceKernighanRitchieRule;
use Rlorenzo\MagoWordPress\Linter\Rules\OperatorSpacingRule;
use Rlorenzo\MagoWordPress\Linter\Rules\ParenthesesSpacingRule;
use Rlorenzo\MagoWordPress\Linter\Rules\PluginMenuSlugRule;
use Rlorenzo\MagoWordPress\Linter\Rules\PostsPerPageRule;
use Rlorenzo\MagoWordPress\Linter\Rules\PrecisionAlignmentRule;
use Rlorenzo\MagoWordPress\Linter\Rules\PrefixAllGlobalsRule;
use Rlorenzo\MagoWordPress\Linter\Rules\PregQuoteDelimiterRule;
use Rlorenzo\MagoWordPress\Linter\Rules\PreparedSqlPlaceholdersRule;
use Rlorenzo\MagoWordPress\Linter\Rules\PreparedSqlRule;
use Rlorenzo\MagoWordPress\Linter\Rules\PreparedSqlUnquotedComplexPlaceholderRule;
use Rlorenzo\MagoWordPress\Linter\Rules\PropertyDeclarationRule;
use Rlorenzo\MagoWordPress\Linter\Rules\RequireAnonClassParenthesesRule;
use Rlorenzo\MagoWordPress\Linter\Rules\RequireExplicitBooleanOperatorPrecedenceRule;
use Rlorenzo\MagoWordPress\Linter\Rules\RestrictedPhpFunctionsRule;
use Rlorenzo\MagoWordPress\Linter\Rules\ReturnTypeDeclarationRule;
use Rlorenzo\MagoWordPress\Linter\Rules\SafeRedirectRule;
use Rlorenzo\MagoWordPress\Linter\Rules\ScopeIndentRule;
use Rlorenzo\MagoWordPress\Linter\Rules\ScopeKeywordSpacingRule;
use Rlorenzo\MagoWordPress\Linter\Rules\SelfMemberReferenceRule;
use Rlorenzo\MagoWordPress\Linter\Rules\SemicolonSpacingRule;
use Rlorenzo\MagoWordPress\Linter\Rules\SeparateFunctionsFromOORule;
use Rlorenzo\MagoWordPress\Linter\Rules\ShortFormTypeKeywordsRule;
use Rlorenzo\MagoWordPress\Linter\Rules\SlowDbQueryRule;
use Rlorenzo\MagoWordPress\Linter\Rules\SpaceAfterCastRule;
use Rlorenzo\MagoWordPress\Linter\Rules\SpreadOperatorSpacingAfterRule;
use Rlorenzo\MagoWordPress\Linter\Rules\StatementAlignmentRule;
use Rlorenzo\MagoWordPress\Linter\Rules\StaticInFinalClassRule;
use Rlorenzo\MagoWordPress\Linter\Rules\StrictComparisonsRule;
use Rlorenzo\MagoWordPress\Linter\Rules\StrictInArrayRule;
use Rlorenzo\MagoWordPress\Linter\Rules\SuperfluousWhitespaceRule;
use Rlorenzo\MagoWordPress\Linter\Rules\SwitchDeclarationRule;
use Rlorenzo\MagoWordPress\Linter\Rules\TraitUseDeclarationRule;
use Rlorenzo\MagoWordPress\Linter\Rules\TypeCastsRule;
use Rlorenzo\MagoWordPress\Linter\Rules\TypeSeparatorSpacingRule;
use Rlorenzo\MagoWordPress\Linter\Rules\UnconditionalIfStatementRule;
use Rlorenzo\MagoWordPress\Linter\Rules\UnnecessaryFinalModifierRule;
use Rlorenzo\MagoWordPress\Linter\Rules\UnnecessaryHeredocRule;
use Rlorenzo\MagoWordPress\Linter\Rules\UnnecessaryStringConcatRule;
use Rlorenzo\MagoWordPress\Linter\Rules\UnusedFunctionParameterRule;
use Rlorenzo\MagoWordPress\Linter\Rules\UpperCaseConstantNameRule;
use Rlorenzo\MagoWordPress\Linter\Rules\UppercaseMagicConstantsRule;
use Rlorenzo\MagoWordPress\Linter\Rules\UselessOverridingMethodRule;
use Rlorenzo\MagoWordPress\Linter\Rules\UseStatementKeywordSpacingRule;
use Rlorenzo\MagoWordPress\Linter\Rules\ValidatedSanitizedInputRule;
use Rlorenzo\MagoWordPress\Linter\Rules\ValidClassNameRule;
use Rlorenzo\MagoWordPress\Linter\Rules\ValidFunctionNameRule;
use Rlorenzo\MagoWordPress\Linter\Rules\ValidHookNameRule;
use Rlorenzo\MagoWordPress\Linter\Rules\ValidLogicalOperatorsRule;
use Rlorenzo\MagoWordPress\Linter\Rules\ValidPostTypeSlugRule;
use Rlorenzo\MagoWordPress\Linter\Rules\ValidVariableNameRule;
use Rlorenzo\MagoWordPress\Linter\Rules\VariableCommentRule;
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
    private const VERSION = '1.4.0';

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
                // First, while the file holds the least: they build a phpcs token stream of the whole
                // file (released after the second), and peak memory is that stream plus what earlier
                // rules have cached and reported.
                new AssignmentInConditionRule($report),
                // These read the shared token stream too; DisallowMultipleAssignmentsRule frees it.
                new CommaSpacingRule($report),
                new SemicolonSpacingRule($report),
                new IncrementDecrementSpacingRule($report),
                new ConcatenationSpacingRule($report),
                new SpaceAfterCastRule($report),
                new CastStructureSpacingRule($report),
                new ControlStructureSpacingRule($report),
                new ArbitraryParenthesesSpacingRule($report),
                new OperatorSpacingRule($report),
                // These read the phpcs tokens too; generic/disallow-multiple-assignments, the last reader, frees them.
                new FunctionCallArgumentSpacingRule($report),
                new FunctionClosingBraceRule($report),
                new ReturnTypeDeclarationRule($report),
                new OpeningFunctionBraceKernighanRitchieRule($report),
                new MultiLineFunctionDeclarationRule($report),
                new FunctionDeclarationArgumentSpacingRule($report),
                new FunctionCallSignatureRule($report),
                new AnonClassKeywordSpacingRule($report),
                new AttributeBracketSpacingRule($report),
                new NamespaceDeclarationRule($report),
                new ObjectOperatorSpacingRule($report),
                new RequireAnonClassParenthesesRule($report),
                new TypeSeparatorSpacingRule($report),
                new UseStatementKeywordSpacingRule($report),
                new CastSpacingRule($report),
                new HereNowdocIdentifierSpacingRule($report),
                new NullableTypeDeclarationRule($report),
                new ScopeKeywordSpacingRule($report),
                new SpreadOperatorSpacingAfterRule($report),
                new DisallowMultipleAssignmentsRule($report),
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
                ...SplitRule::pair(
                    new IniSetRule($report),
                    $report,
                    'Ini set (warnings)',
                    'ini_set() and ini_alter() changes to options WPCS does not single out (WPCS Risky, a warning); options WordPress sets another way stay with wordpress/ini-set.',
                ),
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
                new ArrayAlignmentRule($report),
                new ArrayDeclarationSpacingRule($report),
                new ArrayBraceSpacingRule($report),
                new CommaAfterLastRule($report),
                new BlockCommentRule($report),
                new ClassCommentRule($report),
                new ClosingDeclarationCommentRule($report),
                new EmptyCatchCommentRule($report),
                new DocCommentAlignmentRule($report),
                new DocCommentRule($report),
                new FileCommentRule($report),
                new FunctionCommentRule($report),
                new FunctionCommentThrowTagRule($report),
                new InlineCommentRule($report),
                new VariableCommentRule($report),
                new LanguageConstructSpacingRule($report),
                new ParenthesesSpacingRule($report),
                new JumbledIncrementerRule($report),
                new ForLoopWithTestFunctionCallRule($report),
                new DisallowSizeFunctionsInLoopsRule($report),
                new RequireExplicitBooleanOperatorPrecedenceRule($report),
                new ForeachUniqueAssignmentRule($report),
                new UselessOverridingMethodRule($report),
                new DisallowShortOpenTagRule($report),
                new EmptyPhpStatementRule($report),
                new LowercaseTypeRule($report),
                new LowercaseConstantRule($report),
                new SeparateFunctionsFromOORule($report),
                new StaticInFinalClassRule($report),
                new DeprecatedFunctionsRule($report),
                new DuplicateArrayKeyRule($report),
                new UnconditionalIfStatementRule($report),
                new ClassDeclarationRule($report),
                new ClassInstantiationRule($report),
                new DisallowMultipleStatementsRule($report),
                new DisallowNamespaceCurlyBraceSyntaxRule($report),
                new EmbeddedPhpRule($report),
                new OpeningBraceSameLineRule($report),
                new TraitUseDeclarationRule($report),
                new ElseIfDeclarationRule($report),
                new ValidClassNameRule($report),
                new FileHeaderRule($report),
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
                new OneObjectStructurePerFileRule($report),
                new UpperCaseConstantNameRule($report),
                new ClosingTagRule($report),
                ...SplitRule::pair(
                    new IncludingFileRule($report),
                    $report,
                    'Including file (warnings)',
                    'An include/include_once outside any scope, condition or assignment (phpcs UseRequire, UseRequireOnce, warnings in WordPress-Core); the parentheses check stays with generic/including-file.',
                ),
                new ForbiddenFunctionsRule($report),
                new NoLeadingBackslashRule($report),
                new DisallowMixedGroupUseRule($report),
                new DisallowNamespaceWithoutNameRule($report),
                new LowercaseUseFunctionConstRule($report),
                new NoUselessAliasesRule($report),
                new OneNamespaceDeclarationPerFileRule($report),
                new ClassModifierKeywordOrderRule($report),
                new ConstantModifierKeywordOrderRule($report),
                new DisallowAttributeParenthesesRule($report),
                new LowercaseClassResolutionKeywordRule($report),
                new LowercasePhpTagRule($report),
                new ShortFormTypeKeywordsRule($report),
                new UppercaseMagicConstantsRule($report),
                new DirnameRule($report),
                new NoSilencedErrorsRule($report, $settings),
                new StrictComparisonsRule($report),
                new DisallowInlineTabsRule($report),
                new DisallowSpaceIndentRule($report),
                new PrecisionAlignmentRule($report),
                new ArrayIndentationRule($report),
                new ScopeIndentRule($report),
                new SuperfluousWhitespaceRule($report),
                new LineEndingsRule($report),
                new EndFileNewlineRule($report),
                new DisallowShortArraySyntaxRule($report),
                new DisallowShortTernaryRule($report),
                new ForLoopShouldBeWhileLoopRule($report),
                new UnnecessaryFinalModifierRule($report),
                new InlineControlStructureRule($report),
                new ValidLogicalOperatorsRule($report),
                new DisallowStandalonePostIncrementDecrementRule($report),
                new DisallowLonelyIfRule($report),
                new ControlSignatureRule($report),
                new IncrementDecrementUsageRule($report),
                new DoubleQuoteUsageRule($report),
                new NoEchoSprintfRule($report),
                new SwitchDeclarationRule($report),
                new UnnecessaryHeredocRule($report),
                new UnnecessaryStringConcatRule($report),
                new NoDoubleNegativeRule($report),
                new CommentedOutCodeRule($report),
                new UnusedFunctionParameterRule($report),
                new NoReservedKeywordParameterNamesRule($report),
                new AlternativeFunctionsRule($report, $settings),
                // The rules that read PhpcsTokens come last, so the tokens are built after the others have run.
                new FunctionDuplicateArgumentRule($report),
                ...SplitRule::pair(
                    new ConstructorDestructorReturnRule($report),
                    $report,
                    'Constructor/destructor return (warnings)',
                    'A `return` with a value in a constructor or destructor (WPCS ReturnValueFound, a warning); a return type stays with generic/constructor-destructor-return.',
                ),
                new ArrayKeySpacingRestrictionsRule($report),
                new StatementAlignmentRule($report),
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
