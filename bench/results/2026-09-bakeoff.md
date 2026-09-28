# 2026-09-bakeoff: top 10 WordPress.org plugins + WordPress core

Bake-off of `bench/run.sh` (phpcs WordPress-Extra vs mago + mago-wordpress) across the top 10
WordPress.org plugins by active installs and WordPress core's `src/`. Machine: Apple M4 MacBook Air.
Versions: PHP 8.4.24, mago 1.50.0, PHP_CodeSniffer 3.13.6, WPCS 3.4.1, this package v1.0.0.
Measured 2026-09-28.

`classic-editor` (rank 4 by active installs) was skipped: only 1 PHP file after excludes.
`wp-mail-smtp` (rank 11) took its place.

## Results

| Codebase | Version | PHP files | phpcs `WordPress-Extra` | `mago lint` + extension | Speed-up |
|:---|:---|---:|---:|---:|---:|
| WooCommerce | 11.1.2 | 3,528 | 35.73 s | 3.37 s | 10.6× |
| WordPress core | trunk | 1,868 | 22.53 s | 3.08 s | 7.3× |
| Elementor | 4.3.2 | 1,460 | 14.29 s | 1.97 s | 7.3× |
| Google Site Kit | 1.188.0 | 1,869 | 13.60 s | 1.91 s | 7.1× |
| Yoast SEO | 28.5 | 1,511 | 9.84 s | 1.30 s | 7.6× |
| WPForms Lite | 2.0.2.1 | 963 | 8.89 s | 1.29 s | 6.9× |
| LiteSpeed Cache | 7.9.1 | 212 | 2.08 s | 0.62 s | 3.4× |
| WP Mail SMTP | 4.9.0 | 185 | 1.68 s | 0.46 s | 3.7× |
| All-in-One WP Migration | 7.111 | 147 | 1.34 s | 0.26 s | 5.2× |
| Contact Form 7 | 6.1.7 | 111 | 1.09 s | 0.37 s | 2.9× |
| Akismet | 5.7.2 | 29 | 0.53 s | 0.23 s | 2.3× |
| **Total** | | **11,883** | **111.60 s** | **14.86 s** | **7.5×** |

No run failed (script exit 0 for all 11 codebases). mago never lost a single comparison.

## mago issue counts by rule, per codebase

### WooCommerce

`uptime` before this run: `9:16  up 1 day, 20:39, 6 users, load averages: 6.22 8.46 8.30`

```
error[wordpress/valid-variable-name]: 13190
error[wordpress/file-name]: 4536
warning[wordpress/prefix-all-globals]: 3507
error[wordpress/valid-function-name]: 859
warning[wordpress/yoda-conditions]: 748
warning[wordpress/capabilities]: 189
warning[wordpress/strict-in-array]: 89
warning[wordpress/wp-date-time]: 52
warning[wordpress/discouraged-wp-functions]: 42
error[wordpress/prepared-sql-placeholders]: 39
warning[wordpress/wp-deprecated-parameters]: 28
warning[wordpress/slow-db-query]: 24
warning[wordpress/wp-i18n]: 22
error[wordpress/global-variables-override]: 16
warning[wordpress/posts-per-page]: 15
warning[wordpress/safe-redirect]: 14
error[wordpress/valid-post-type-slug]: 4
warning[wordpress/enqueued-resource-parameters]: 2
warning[wordpress/get-meta-single]: 2
warning[wordpress/assignment-in-ternary-condition]: 1
warning[wordpress/prepared-sql-unquoted-complex-placeholder]: 1
warning[wordpress/valid-hook-name]: 1
warning[wordpress/wp-deprecated-functions]: 1
```

### WordPress core

`uptime` before this run: `9:23  up 1 day, 20:46, 7 users, load averages: 5.66 9.18 9.11`

```
warning[wordpress/wp-i18n]: 15368
error[wordpress/valid-variable-name]: 10073
warning[wordpress/prefix-all-globals]: 1868
warning[wordpress/yoda-conditions]: 1835
error[wordpress/global-variables-override]: 1048
error[wordpress/file-name]: 813
error[wordpress/valid-function-name]: 707
warning[wordpress/discouraged-wp-functions]: 440
warning[wordpress/safe-redirect]: 195
warning[wordpress/strict-in-array]: 103
warning[wordpress/slow-db-query]: 64
warning[wordpress/wp-deprecated-functions]: 47
warning[wordpress/discouraged-constants]: 36
error[wordpress/db-restricted-functions]: 30
warning[wordpress/posts-per-page]: 24
warning[wordpress/get-meta-single]: 16
error[wordpress/valid-post-type-slug]: 16
warning[wordpress/enqueued-resources]: 12
warning[wordpress/enqueued-resource-parameters]: 10
warning[wordpress/valid-hook-name]: 10
warning[wordpress/wp-date-time]: 7
warning[wordpress/wp-deprecated-classes]: 7
warning[wordpress/wp-deprecated-parameters]: 7
error[wordpress/prepared-sql-placeholders]: 5
warning[wordpress/wp-deprecated-parameter-values]: 3
error[wordpress/db-restricted-classes]: 2
warning[wordpress/assignment-in-ternary-condition]: 1
error[wordpress/dont-extract]: 1
```

### Elementor

`uptime` before this run: `9:11  up 1 day, 20:34, 6 users, load averages: 4.95 7.00 7.64`

```
error[wordpress/file-name]: 1377
warning[wordpress/valid-hook-name]: 396
warning[wordpress/prefix-all-globals]: 151
warning[wordpress/slow-db-query]: 53
warning[wordpress/strict-in-array]: 51
warning[wordpress/discouraged-wp-functions]: 34
error[wordpress/valid-variable-name]: 32
warning[wordpress/posts-per-page]: 19
warning[wordpress/wp-date-time]: 13
warning[wordpress/yoda-conditions]: 8
error[wordpress/valid-post-type-slug]: 6
warning[wordpress/enqueued-resource-parameters]: 3
error[wordpress/valid-function-name]: 3
warning[wordpress/capabilities]: 2
warning[wordpress/safe-redirect]: 2
error[wordpress/dont-extract]: 1
warning[wordpress/enqueued-resources]: 1
warning[wordpress/get-meta-single]: 1
```

### Google Site Kit

`uptime` before this run: `9:20  up 1 day, 20:43, 7 users, load averages: 7.92 10.09 9.21`

```
error[wordpress/valid-variable-name]: 16567
error[wordpress/file-name]: 3477
warning[wordpress/prefix-all-globals]: 2006
warning[wordpress/yoda-conditions]: 1096
error[wordpress/valid-function-name]: 823
warning[wordpress/discouraged-wp-functions]: 91
warning[wordpress/strict-in-array]: 75
warning[wordpress/wp-i18n]: 24
warning[wordpress/wp-date-time]: 5
error[wordpress/valid-post-type-slug]: 1
```

### Yoast SEO

`uptime` before this run: `9:14  up 1 day, 20:37, 6 users, load averages: 9.41 8.83 8.32`

```
warning[wordpress/yoda-conditions]: 1572
warning[wordpress/prefix-all-globals]: 1512
error[wordpress/file-name]: 1304
error[wordpress/prepared-sql-placeholders]: 33
warning[wordpress/valid-hook-name]: 28
warning[wordpress/slow-db-query]: 15
warning[wordpress/capabilities]: 14
warning[wordpress/discouraged-wp-functions]: 13
warning[wordpress/wp-i18n]: 3
error[wordpress/dont-extract]: 2
error[wordpress/global-variables-override]: 2
warning[wordpress/posts-per-page]: 2
error[wordpress/valid-variable-name]: 1
warning[wordpress/wp-date-time]: 1
```

### WPForms Lite

`uptime` before this run: `9:22  up 1 day, 20:45, 7 users, load averages: 8.52 10.30 9.45`

```
warning[wordpress/yoda-conditions]: 1622
error[wordpress/file-name]: 1411
warning[wordpress/prefix-all-globals]: 382
warning[wordpress/posts-per-page]: 11
warning[wordpress/discouraged-wp-functions]: 2
error[wordpress/global-variables-override]: 1
warning[wordpress/strict-in-array]: 1
error[wordpress/valid-function-name]: 1
```

### LiteSpeed Cache

`uptime` before this run: `9:16  up 1 day, 20:39, 6 users, load averages: 7.21 8.79 8.41`

```
warning[wordpress/prefix-all-globals]: 738
warning[wordpress/wp-i18n]: 278
error[wordpress/file-name]: 272
warning[wordpress/discouraged-wp-functions]: 4
```

### WP Mail SMTP

`uptime` before this run: `9:23  up 1 day, 20:46, 7 users, load averages: 6.60 9.54 9.23`

```
error[wordpress/file-name]: 352
warning[wordpress/yoda-conditions]: 310
warning[wordpress/prefix-all-globals]: 223
warning[wordpress/wp-i18n]: 5
warning[wordpress/discouraged-wp-functions]: 4
error[wordpress/valid-variable-name]: 4
warning[wordpress/capabilities]: 2
warning[wordpress/enqueued-resource-parameters]: 2
warning[wordpress/posts-per-page]: 1
```

### All-in-One WP Migration

`uptime` before this run: `9:23  up 1 day, 20:46, 7 users, load averages: 8.09 9.96 9.37`

```
warning[wordpress/strict-in-array]: 94
warning[wordpress/discouraged-wp-functions]: 57
warning[wordpress/yoda-conditions]: 35
warning[wordpress/capabilities]: 15
warning[wordpress/prefix-all-globals]: 11
warning[wordpress/wp-i18n]: 9
error[wordpress/file-name]: 2
```

### Contact Form 7

`uptime` before this run: `9:16  up 1 day, 20:39, 6 users, load averages: 7.37 8.87 8.43`

```
error[wordpress/file-name]: 60
warning[wordpress/prefix-all-globals]: 34
warning[wordpress/capabilities]: 33
warning[wordpress/discouraged-wp-functions]: 8
warning[wordpress/posts-per-page]: 4
error[wordpress/prepared-sql-placeholders]: 3
warning[wordpress/enqueued-resource-parameters]: 2
warning[wordpress/slow-db-query]: 2
error[wordpress/valid-variable-name]: 2
warning[wordpress/get-meta-single]: 1
warning[wordpress/safe-redirect]: 1
warning[wordpress/strict-in-array]: 1
error[wordpress/valid-post-type-slug]: 1
warning[wordpress/yoda-conditions]: 1
```

### Akismet

`uptime` before this run: `9:22  up 1 day, 20:45, 7 users, load averages: 9.33 10.51 9.51`

```
warning[wordpress/yoda-conditions]: 96
warning[wordpress/prefix-all-globals]: 32
warning[wordpress/strict-in-array]: 21
error[wordpress/file-name]: 10
warning[wordpress/discouraged-wp-functions]: 9
warning[wordpress/enqueued-resource-parameters]: 2
warning[wordpress/get-meta-single]: 1
warning[wordpress/safe-redirect]: 1
warning[wordpress/slow-db-query]: 1
```

## Reproduction

```shell
git clone https://github.com/rlorenzo/mago-wordpress.git
cd mago-wordpress && composer install

# WordPress.org plugins — download each release zip from https://wordpress.org/plugins/<slug>/
# and unzip; WordPress core:
git clone --depth 1 https://github.com/WordPress/wordpress-develop.git

PHPCS=/path/to/phpcs bench/run.sh /path/to/plugins/woocommerce woocommerce wc  # WooCommerce
PHPCS=/path/to/phpcs bench/run.sh /path/to/wordpress-develop/src default wp  # WordPress core
PHPCS=/path/to/phpcs bench/run.sh /path/to/plugins/elementor elementor elementor  # Elementor
PHPCS=/path/to/phpcs bench/run.sh /path/to/plugins/google-site-kit google-site-kit googlesitekit  # Google Site Kit
PHPCS=/path/to/phpcs bench/run.sh /path/to/plugins/wordpress-seo wordpress-seo wpseo  # Yoast SEO
PHPCS=/path/to/phpcs bench/run.sh /path/to/plugins/wpforms-lite wpforms-lite wpforms  # WPForms Lite
PHPCS=/path/to/phpcs bench/run.sh /path/to/plugins/litespeed-cache litespeed-cache litespeed  # LiteSpeed Cache
PHPCS=/path/to/phpcs bench/run.sh /path/to/plugins/wp-mail-smtp wp-mail-smtp wp_mail_smtp  # WP Mail SMTP
PHPCS=/path/to/phpcs bench/run.sh /path/to/plugins/all-in-one-wp-migration all-in-one-wp-migration ai1wm  # All-in-One WP Migration
PHPCS=/path/to/phpcs bench/run.sh /path/to/plugins/contact-form-7 contact-form-7 wpcf7  # Contact Form 7
PHPCS=/path/to/phpcs bench/run.sh /path/to/plugins/akismet akismet akismet  # Akismet
```

`PHPCS` must point at a phpcs install with `wp-coding-standards/wpcs` 3.4.1 registered (`phpcs --config-set installed_paths ...` or a project-local `composer require --dev wp-coding-standards/wpcs`).

