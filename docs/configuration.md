# Configuration

Mago can't pass custom options to extension rules, so this package reads the settings WPCS takes
as sniff properties from your project. Put them in `composer.json`:

```json
{
  "extra": {
    "mago-wordpress": {
      "text-domains": ["my-plugin"],
      "prefixes": ["myplugin", "mp_"],
      "minimum-wp-version": "6.4",
      "custom-escaping-functions": ["mp_esc"],
      "custom-auto-escaped-functions": [],
      "custom-printing-functions": [],
      "custom-sanitizing-functions": [],
      "custom-unslashing-sanitizing-functions": [],
      "custom-nonce-verification-functions": [],
      "custom-capabilities": [],
      "allowed-custom-properties": [],
      "custom-test-classes": [],
      "custom-cache-get-functions": [],
      "custom-cache-set-functions": [],
      "custom-cache-delete-functions": [],
      "max-posts-per-page": 100,
      "min-cron-interval": 900,
      "additional-word-delimiters": "",
      "honor-phpcs-comments": true,
      "strict-class-file-names": true,
      "is-theme": false,
      "treat-files-as-scoped": false,
      "exclude-groups": {
        "WordPress.PHP.DiscouragedPHPFunctions": ["serialize"]
      },
      "exclude-patterns": {
        "WordPress.Files.FileName": ["/tests/*"],
        "WordPress.PHP.YodaConditions": ["*"]
      }
    }
  }
}
```

## Defaults

Defaults mirror WPCS 3.4.1.

- `minimum-wp-version` is `6.7`. Deprecations newer than that are reported with a note, where WPCS
  lowers them to a warning.
- Rules for PHP features removed before PHP 8.1 are limited to the ones `WordPress-Core` itself runs.
- The extension targets PHP 8.1+ and the WordPress versions that support it.

## Reading from phpcs.xml

With no `extra.mago-wordpress` block, the worker reads the same values from your existing
`phpcs.xml`, so a project migrating from phpcs needs no new configuration. It picks up:

- Properties: `text_domain`, `prefixes`, `minimum_wp_version`, `customEscapingFunctions`,
  `posts_per_page`, `min_interval`, `additionalWordDelimiters`, `allowed_custom_properties`,
  `exclude`, `custom_test_classes`, `strict_class_file_names`, `is_theme`,
  `treat_files_as_scoped`, ...
- The codes it turns off (see [Turning rules off](#turning-rules-off)).

An explicitly present but empty block (`{"extra": {"mago-wordpress": {}}}`) means "use the
defaults" and does not fall back to `phpcs.xml`.

Properties are read only when set directly on the sniff's own ref, such as
`<rule ref="WordPress.WP.I18n">`. A `<rule ref="WordPress-Extra">` or other ruleset that merely
*includes* the sniff is not followed. Set properties on the sniff ref itself, as phpcs recommends.

## Settings

`custom-capabilities` lists capabilities `wordpress/capabilities` accepts.

`custom-cache-get-functions`, `custom-cache-set-functions` and `custom-cache-delete-functions` add
cache functions that `wordpress/direct-database-query` counts as caching (WPCS's
`customCacheGetFunctions`, `customCacheSetFunctions`, `customCacheDeleteFunctions`).

`custom-sanitizing-functions` and `custom-unslashing-sanitizing-functions` add sanitizing functions
(the second kind also unslash) to `wordpress/validated-sanitized-input` and
`wordpress/nonce-verification`. `custom-nonce-verification-functions` (WPCS's
`customNonceVerificationFunctions`) adds nonce-checking functions to `wordpress/nonce-verification`.

`custom-escaping-functions`, `custom-auto-escaped-functions` and `custom-printing-functions`
extend the escaping, auto-escaped and printing function lists of `wordpress/escape-output`
(WPCS's `customEscapingFunctions`, `customAutoEscapedFunctions` and `customPrintingFunctions`).

`allowed-custom-properties` lists mixed-case object properties `wordpress/valid-variable-name`
accepts (WPCS's `allowed_custom_properties`), such as `childNodes` for `DOMDocument`.

Other WPCS sniff properties, under their own names:

| Setting | WPCS property | Effect |
|:---|:---|:---|
| `exclude-groups` | `exclude` on a function-restriction sniff | WPCS sniff => the function groups it skips, e.g. `"WordPress.PHP.DevelopmentFunctions": ["error_log"]`. Honoured by every sniff this package ports (`DateTime.RestrictedFunctions`, `DB.RestrictedClasses`, `DB.RestrictedFunctions`, `DB.SlowDBQuery`, `PHP.DevelopmentFunctions`, `PHP.DiscouragedPHPFunctions`, `PHP.DontExtract`, `PHP.RestrictedPHPFunctions`, `Security.SafeRedirect`, `WP.AlternativeFunctions`, `WP.ClassNameCase`, `WP.DeprecatedClasses`, `WP.DeprecatedFunctions`, `WP.DiscouragedFunctions`, `WP.PostsPerPage`); `Security.EscapeOutput` ignores `exclude`, as in WPCS. |
| `custom-test-classes` | `custom_test_classes` | extra test base classes (fully qualified) whose subclasses `file-name`, `global-variables-override` and `prefix-all-globals` skip. WPCS sets it per sniff; here it is one list. |
| `strict-class-file-names` | `strict_class_file_names` | `false` stops `file-name` requiring the `class-` prefix on class files. |
| `is-theme` | `is_theme` | `true` lets `file-name` accept theme template-hierarchy names (`single-my_post_type.php`, `taxonomy-post_format-...`, `text_plain.php`). |
| `treat-files-as-scoped` | `treat_files_as_scoped` | `true` makes `global-variables-override` treat each file like a function: file-scope writes count only after a `global` statement (`$GLOBALS[...]` writes still count). |

## phpcs suppression comments

The rules honour the phpcs suppression comments already in your code, as PHP_CodeSniffer does:

- `phpcs:ignore`: on its own line it silences the next line; after code it silences its own line.
- `phpcs:disable` / `phpcs:enable` regions.
- `phpcs:ignoreFile`.
- The legacy `@codingStandardsIgnoreLine`, `@codingStandardsIgnoreStart` / `@codingStandardsIgnoreEnd`
  and `@codingStandardsIgnoreFile`.

A code list matches the WPCS code a rule ports at any level: `WordPress`, `WordPress.Security`,
`WordPress.Security.SafeRedirect`, or a message code such as
`WordPress.WP.I18n.MissingTranslatorsComment`.

Set `"honor-phpcs-comments": false` to report everything regardless. There is no `phpcs.xml`
equivalent.

## Turning rules off

Mago rejects extension rule codes under `[linter.rules]` in `mago.toml` (`unknown field
"wordpress/..."`). Turn this package's rules off with `exclude-patterns` instead.

It maps a WPCS code (standard, category, sniff or message code) to phpcs `<exclude-pattern>`
values, with phpcs's semantics: a regex in which `*` means `.*`, matched case-insensitively anywhere
in the path. `"*"` turns the code off everywhere.

For example, `"WordPress.WP.I18n.MissingTranslatorsComment": ["/tests/*"]` silences one message
under `tests/` and leaves the rest of `wordpress/wp-i18n` alone.

Without an `extra.mago-wordpress` block, the same exclusions are read from `phpcs.xml`:

- the sniffs its `WordPress-Core` or `WordPress-Extra` ref leaves out
- `<exclude name>`
- a `<severity>` below 5
- `<exclude-pattern>` inside a `<rule ref>`

Levels of extension rules can't be changed. Mago's own core rules are configured in `mago.toml` as
usual.
