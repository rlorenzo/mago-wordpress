# Mago Extension Template

A small, end-to-end template for building external linter rules and analyzer plugins for [Mago](https://github.com/carthage-software/mago).

The example extension contains:

- a linter rule selected by an exact syntax-node kind;
- a machine-applicable edit based on Mago's resolved names;
- an analyzer plugin with a targeted method return-type provider;
- unit tests and a real Mago corpus test using an external worker;
- formatting, linting, analysis, and CI commands.

## Start a new extension

Create a repository from this template, then replace the example identity before writing new capabilities:

1. Rename `rlorenzo/mago-wordpress` in `composer.json`.
2. Replace the `Rlorenzo\MagoWordPress` namespace and PSR-4 mappings.
3. Rename `WordPressExtension` and update its identifier, name, and version.
4. Rename the analyzer plugin identifier and every linter issue code.
5. Replace or remove the example rule, provider, fixtures, and corpus expectations.
6. Set the package author, description, keywords, and license.

Keep the package-owned extension factory as the only registration API consumers need. Typed factory arguments may expose intentional options, but consumers should not have to reconstruct rule and plugin lists themselves.

## Package structure

```text
src/
├── WordPressExtension.php
├── Analyzer/
│   ├── WordPressPlugin.php
│   └── Providers/
│       └── ContainerReturnTypeProvider.php
└── Linter/
    └── Rules/
        └── NoLegacyHelperRule.php
tests/
├── Unit/
└── corpus/
    ├── mago.toml
    ├── worker.php
    └── src/
```

Put lifecycle callbacks under `src/Analyzer/Hooks/`, semantic providers under `src/Analyzer/Providers/`, linter rules under `src/Linter/Rules/`, and worker reducers under `src/Worker/`.

## Install the extension

Applications install Mago and the finished extension together:

```shell
composer require --dev carthage-software/mago rlorenzo/mago-wordpress
```

The application owns its worker entrypoint. Create `.mago/extensions.php`:

```php
<?php

declare(strict_types=1);

use Rlorenzo\MagoWordPress\WordPressExtension;
use Mago\Sdk\Worker;

require dirname(__DIR__) . '/vendor/autoload.php';

new Worker(WordPressExtension::create())->run();
```

Register it in `mago.toml`:

```toml
[extension-hosts.wordpress]
command = ["php", ".mago/extensions.php"]
```

Several extension factories may be passed to the same `Worker`. Standard output is reserved for protocol frames; write development diagnostics to standard error.

## Development

```shell
composer install   # also points git at .githooks (pre-commit runs `just check`)
just check         # composer validate, format-check, PHPUnit, mago lint + analyze, corpus
```

CI runs `just check` on PHP 8.1, 8.4 and 8.5 for every push and pull request. Skip the hook once with `git commit --no-verify`.
