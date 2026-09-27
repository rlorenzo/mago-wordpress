<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal\WordPress;

use ReflectionClass;
use ReflectionFunction;

use function array_key_exists;
use function array_merge;
use function array_values;
use function class_exists;
use function function_exists;
use function get_defined_constants;
use function interface_exists;
use function strtolower;
use function trait_exists;

/**
 * Allowlists of `WordPress.NamingConventions.PrefixAllGlobals`, ported from WordPress Coding
 * Standards (https://github.com/WordPress/WordPress-Coding-Standards, MIT).
 *
 * The arrays are generated from the WPCS sniff's source arrays — regenerate them from WPCS rather
 * than hand-editing. Like WPCS, names that backfill a PHP built-in are exempt too.
 *
 * @internal
 */
final class PrefixAllowlists
{
    /**
     * WPCS: hook names plugins and themes may invoke.
     * Source: $allowed_core_hooks
     *
     * @var array<string, true>
     */
    public const CORE_HOOKS = [
        'add_meta_boxes' => true,
        'widget_title' => true,
    ];

    /**
     * WPCS: overridable core constants plugins and themes may define.
     * Source: $allowed_core_constants
     *
     * @var array<string, true>
     */
    public const CORE_CONSTANTS = [
        'ADMIN_COOKIE_PATH' => true,
        'AUTH_COOKIE' => true,
        'AUTOSAVE_INTERVAL' => true,
        'COOKIEHASH' => true,
        'COOKIEPATH' => true,
        'COOKIE_DOMAIN' => true,
        'EMPTY_TRASH_DAYS' => true,
        'FORCE_SSL_ADMIN' => true,
        'FORCE_SSL_LOGIN' => true,
        'LOGGED_IN_COOKIE' => true,
        'MEDIA_TRASH' => true,
        'MUPLUGINDIR' => true,
        'PASS_COOKIE' => true,
        'PLUGINDIR' => true,
        'PLUGINS_COOKIE_PATH' => true,
        'RECOVERY_MODE_COOKIE' => true,
        'SCRIPT_DEBUG' => true,
        'SECURE_AUTH_COOKIE' => true,
        'SHORTINIT' => true,
        'SITECOOKIEPATH' => true,
        'TEST_COOKIE' => true,
        'USER_COOKIE' => true,
        'WPMU_PLUGIN_DIR' => true,
        'WPMU_PLUGIN_URL' => true,
        'WP_CACHE' => true,
        'WP_CONTENT_DIR' => true,
        'WP_CONTENT_URL' => true,
        'WP_CRON_LOCK_TIMEOUT' => true,
        'WP_DEBUG' => true,
        'WP_DEBUG_DISPLAY' => true,
        'WP_DEBUG_LOG' => true,
        'WP_DEFAULT_THEME' => true,
        'WP_DEVELOPMENT_MODE' => true,
        'WP_MAX_MEMORY_LIMIT' => true,
        'WP_MEMORY_LIMIT' => true,
        'WP_PLUGIN_DIR' => true,
        'WP_PLUGIN_URL' => true,
        'WP_POST_REVISIONS' => true,
        'WP_START_TIMESTAMP' => true,
    ];

    /**
     * WPCS: pluggable functions, lowercase.
     * Source: $pluggable_functions
     *
     * @var array<string, true>
     */
    public const PLUGGABLE_FUNCTIONS = [
        'auth_redirect' => true,
        'cache_users' => true,
        'check_admin_referer' => true,
        'check_ajax_referer' => true,
        'get_avatar' => true,
        'get_currentuserinfo' => true,
        'get_user_by' => true,
        'get_user_by_email' => true,
        'get_userdata' => true,
        'get_userdatabylogin' => true,
        'graceful_fail' => true,
        'install_global_terms' => true,
        'install_network' => true,
        'is_user_logged_in' => true,
        'maybe_add_column' => true,
        'maybe_create_table' => true,
        'set_current_user' => true,
        'twenty_twenty_one_entry_meta_footer' => true,
        'twenty_twenty_one_post_thumbnail' => true,
        'twenty_twenty_one_post_title' => true,
        'twenty_twenty_one_posted_by' => true,
        'twenty_twenty_one_posted_on' => true,
        'twenty_twenty_one_setup' => true,
        'twenty_twenty_one_the_posts_navigation' => true,
        'twentyeleven_admin_header_image' => true,
        'twentyeleven_admin_header_style' => true,
        'twentyeleven_comment' => true,
        'twentyeleven_content_nav' => true,
        'twentyeleven_continue_reading_link' => true,
        'twentyeleven_header_image' => true,
        'twentyeleven_header_style' => true,
        'twentyeleven_posted_on' => true,
        'twentyeleven_setup' => true,
        'twentyfifteen_comment_nav' => true,
        'twentyfifteen_entry_meta' => true,
        'twentyfifteen_excerpt_more' => true,
        'twentyfifteen_fonts_url' => true,
        'twentyfifteen_get_color_scheme' => true,
        'twentyfifteen_get_color_scheme_choices' => true,
        'twentyfifteen_get_link_url' => true,
        'twentyfifteen_header_style' => true,
        'twentyfifteen_post_thumbnail' => true,
        'twentyfifteen_sanitize_color_scheme' => true,
        'twentyfifteen_setup' => true,
        'twentyfifteen_the_custom_logo' => true,
        'twentyfourteen_admin_header_image' => true,
        'twentyfourteen_admin_header_style' => true,
        'twentyfourteen_excerpt_more' => true,
        'twentyfourteen_font_url' => true,
        'twentyfourteen_header_image' => true,
        'twentyfourteen_header_style' => true,
        'twentyfourteen_list_authors' => true,
        'twentyfourteen_paging_nav' => true,
        'twentyfourteen_post_nav' => true,
        'twentyfourteen_post_thumbnail' => true,
        'twentyfourteen_posted_on' => true,
        'twentyfourteen_setup' => true,
        'twentyfourteen_the_attached_image' => true,
        'twentynineteen_comment_count' => true,
        'twentynineteen_comment_form' => true,
        'twentynineteen_discussion_avatars_list' => true,
        'twentynineteen_entry_footer' => true,
        'twentynineteen_get_user_avatar_markup' => true,
        'twentynineteen_post_thumbnail' => true,
        'twentynineteen_posted_by' => true,
        'twentynineteen_posted_on' => true,
        'twentynineteen_setup' => true,
        'twentynineteen_the_posts_navigation' => true,
        'twentyseventeen_edit_link' => true,
        'twentyseventeen_entry_footer' => true,
        'twentyseventeen_fonts_url' => true,
        'twentyseventeen_header_style' => true,
        'twentyseventeen_posted_on' => true,
        'twentyseventeen_time_link' => true,
        'twentysixteen_categorized_blog' => true,
        'twentysixteen_entry_date' => true,
        'twentysixteen_entry_meta' => true,
        'twentysixteen_entry_taxonomies' => true,
        'twentysixteen_excerpt' => true,
        'twentysixteen_excerpt_more' => true,
        'twentysixteen_fonts_url' => true,
        'twentysixteen_get_color_scheme' => true,
        'twentysixteen_get_color_scheme_choices' => true,
        'twentysixteen_header_style' => true,
        'twentysixteen_post_thumbnail' => true,
        'twentysixteen_sanitize_color_scheme' => true,
        'twentysixteen_setup' => true,
        'twentysixteen_the_custom_logo' => true,
        'twentyten_admin_header_style' => true,
        'twentyten_comment' => true,
        'twentyten_continue_reading_link' => true,
        'twentyten_header_image' => true,
        'twentyten_posted_in' => true,
        'twentyten_posted_on' => true,
        'twentyten_setup' => true,
        'twentythirteen_entry_date' => true,
        'twentythirteen_entry_meta' => true,
        'twentythirteen_excerpt_more' => true,
        'twentythirteen_fonts_url' => true,
        'twentythirteen_paging_nav' => true,
        'twentythirteen_post_nav' => true,
        'twentythirteen_the_attached_image' => true,
        'twentytwelve_comment' => true,
        'twentytwelve_content_nav' => true,
        'twentytwelve_entry_meta' => true,
        'twentytwelve_get_font_url' => true,
        'twentytwenty_customize_partial_blogdescription' => true,
        'twentytwenty_customize_partial_blogname' => true,
        'twentytwenty_customize_partial_site_logo' => true,
        'twentytwenty_generate_css' => true,
        'twentytwenty_get_customizer_css' => true,
        'twentytwenty_get_theme_svg' => true,
        'twentytwenty_the_theme_svg' => true,
        'twentytwentyfive_block_styles' => true,
        'twentytwentyfive_editor_style' => true,
        'twentytwentyfive_enqueue_styles' => true,
        'twentytwentyfive_format_binding' => true,
        'twentytwentyfive_pattern_categories' => true,
        'twentytwentyfive_post_format_setup' => true,
        'twentytwentyfive_register_block_bindings' => true,
        'twentytwentyfour_block_styles' => true,
        'twentytwentyfour_block_stylesheets' => true,
        'twentytwentyfour_pattern_categories' => true,
        'twentytwentytwo_styles' => true,
        'twentytwentytwo_support' => true,
        'wp_authenticate' => true,
        'wp_cache_add_multiple' => true,
        'wp_cache_delete_multiple' => true,
        'wp_cache_flush_group' => true,
        'wp_cache_flush_runtime' => true,
        'wp_cache_get_multiple' => true,
        'wp_cache_get_multiple_salted' => true,
        'wp_cache_get_salted' => true,
        'wp_cache_set_multiple' => true,
        'wp_cache_set_multiple_salted' => true,
        'wp_cache_set_salted' => true,
        'wp_cache_supports' => true,
        'wp_cache_switch_to_blog' => true,
        'wp_check_password' => true,
        'wp_clear_auth_cookie' => true,
        'wp_clearcookie' => true,
        'wp_create_nonce' => true,
        'wp_generate_auth_cookie' => true,
        'wp_generate_password' => true,
        'wp_get_cookie_login' => true,
        'wp_get_current_user' => true,
        'wp_hash' => true,
        'wp_hash_password' => true,
        'wp_install' => true,
        'wp_install_defaults' => true,
        'wp_login' => true,
        'wp_logout' => true,
        'wp_mail' => true,
        'wp_new_blog_notification' => true,
        'wp_new_user_notification' => true,
        'wp_nonce_tick' => true,
        'wp_notify_moderator' => true,
        'wp_notify_postauthor' => true,
        'wp_parse_auth_cookie' => true,
        'wp_password_change_notification' => true,
        'wp_password_needs_rehash' => true,
        'wp_rand' => true,
        'wp_redirect' => true,
        'wp_safe_redirect' => true,
        'wp_salt' => true,
        'wp_sanitize_redirect' => true,
        'wp_set_auth_cookie' => true,
        'wp_set_current_user' => true,
        'wp_set_password' => true,
        'wp_setcookie' => true,
        'wp_text_diff' => true,
        'wp_upgrade' => true,
        'wp_validate_auth_cookie' => true,
        'wp_validate_redirect' => true,
        'wp_verify_nonce' => true,
    ];

    /**
     * WPCS: pluggable classes, lowercase.
     * Source: $pluggable_classes
     *
     * @var array<string, true>
     */
    public const PLUGGABLE_CLASSES = [
        'twenty_twenty_one_customize' => true,
        'twentytwenty_customize' => true,
        'twentytwenty_non_latin_languages' => true,
        'twentytwenty_script_loader' => true,
        'twentytwenty_separator_control' => true,
        'twentytwenty_svg_icons' => true,
        'twentytwenty_walker_comment' => true,
        'twentytwenty_walker_page' => true,
        'wp_atom_server' => true,
        'wp_block_cloner' => true,
        'wp_user_search' => true,
    ];

    /** @var null|array<string, mixed> */
    private static ?array $nativeConstants = null;

    /**
     * Whether a global constant is an overridable WordPress core constant or a PHP built-in one.
     */
    public static function isAllowedConstant(string $name): bool
    {
        if (array_key_exists($name, self::CORE_CONSTANTS)) {
            return true;
        }

        if (self::$nativeConstants === null) {
            /** @var array<string, array<string, mixed>> $constants */
            $constants = get_defined_constants(categorize: true);
            unset($constants['user']);
            self::$nativeConstants = array_merge(...array_values($constants));
        }

        return array_key_exists($name, self::$nativeConstants);
    }

    /**
     * Whether a global function is pluggable or backfills a PHP built-in function.
     */
    public static function isAllowedFunction(string $name): bool
    {
        if (array_key_exists(strtolower($name), self::PLUGGABLE_FUNCTIONS)) {
            return true;
        }

        return function_exists($name) && (new ReflectionFunction($name))->isInternal();
    }

    /**
     * Whether a global class is pluggable or backfills a PHP built-in class.
     */
    public static function isAllowedClass(string $name): bool
    {
        return array_key_exists(strtolower($name), self::PLUGGABLE_CLASSES) || self::isNativeClassLike($name);
    }

    /**
     * Whether a global class, interface, trait or enum backfills a PHP built-in one.
     */
    public static function isNativeClassLike(string $name): bool
    {
        if (
            !class_exists($name, autoload: false)
            && !interface_exists($name, autoload: false)
            && !trait_exists($name, autoload: false)
        ) {
            return false;
        }

        return (new ReflectionClass($name))->isInternal();
    }
}
