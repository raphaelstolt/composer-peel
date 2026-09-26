# composer-peel

![Test Status](https://github.com/raphaelstolt/composer-peel/workflows/test/badge.svg)
[![Version](http://img.shields.io/packagist/v/stolt/composer-peel.svg?style=flat)](https://packagist.org/packages/stolt/composer-peel)
![Downloads](https://img.shields.io/packagist/dt/stolt/composer-peel)
![PHP Version](https://img.shields.io/badge/php-8.2+-ff69b4.svg)
[![MIT License](https://img.shields.io/badge/License-MIT-green.svg)](https://choosealicense.com/licenses/mit/)
[![PDS Skeleton](https://img.shields.io/badge/pds-skeleton-blue.svg?style=flat)](https://github.com/php-pds/skeleton)
[![Lean dist package](https://img.shields.io/badge/lean-dist%20package-00ffb6.svg?style=flat)](https://github.com/raphaelstolt/lean-package-validator)

<p align="center">
    <img src="logo.png"
         title="The Composer metadata peeler"
         alt="Composer metadata peeler logo">
</p>

A small development tool to strip or peel development-only metadata from Composer manifests when releasing PHP packages.

### Why?

A PHP package's `composer.json` often contains metadata required during development but irrelevant to its consumers.

Development dependencies, local repository definitions, scripts, and other root-only configurations can be useful while
building a package, yet unnecessary in a release-oriented Composer manifest.

While Composer handles the separation of development and runtime dependencies, preparing a clean, minimal manifest for
distribution can still require manual adjustments or custom scripting.

`composer-peel` aims to simplify this process by removing configurable, non-runtime-required Composer metadata while
preserving the information needed to consume the package.

The goal is straightforward: less development-related metadata and smaller package distributions.

### What gets peeled?

`composer-peel` removes configurable Composer metadata that is not required when consuming a released package.

The goal is to retain the metadata needed by downstream consumers while excluding development-specific configuration.

Depending on your package and configuration, metadata considered for removal may include:

| Composer field | Purpose                                  |
| -------------- | ---------------------------------------- |
| `require-dev`  | Development-only dependencies            |
| `autoload-dev` | Development-only autoloading rules       |
| `scripts`      | Development-only Composer scripts        |
| `scripts-descriptions` | Composer scripts descriptions       |
| `scripts-aliases` | Composer scripts aliases         |

The exact fields removed depend on the peeling rules applied by `composer-peel`. Runtime dependencies and package
autoloading metadata should be preserved.

## Installation

```bash
composer require --dev stolt/composer-peel
```

## Usage

```bash
composer-peel peel [--dry-run]
composer-peel peel [--backup-file=.composer-unpeeled.json]
composer-peel peel [--config=.composer-peel.php]

# Automatically run the peel and release workflow
composer-peel peel <version-tag> [--config=.composer-peel.php]
```

### Previewing changes with `--dry-run`

Use the `--dry-run` option to preview the changes `composer-peel` would apply to your `composer.json` without modifying
the original file.

```bash
composer-peel peel --dry-run
```

The dry run reports:

* The configuration file used, or the default configuration.
* Composer sections that would be removed, such as `require-dev`, `autoload-dev`, and `scripts`.
* The original `composer.json` file size.
* The projected file size after peeling.
* The estimated size reduction in bytes and percentage.

Example output:

```text
Manifest:      composer.json
Configuration: .composer-peel.php | internal defaults

Sections to be removed:
  - require-dev
  - autoload-dev
  - scripts

Original size:       2,480 bytes
Projected size:      1,120 bytes
Estimated reduction: 1,360 bytes (54.8%)

Dry run completed. No files were modified.
```

If no custom configuration file is provided, the command uses its default configuration.

The dry run is useful for assessing the impact of metadata minimisation and reviewing the applied configuration before
modifying your Composer manifest.

## Configuration

`composer-peel` supports a PHP-based configuration file named `.composer-peel.php` in your project root.

Use it to customise release backup behaviour and Git commit messages.

Create a `.composer-peel.php` configuration file in your project root:

```php
<?php

declare(strict_types=1);

return [
    'peel' => [
        'sections' => [
            'require-dev',
            'autoload-dev',
            'scripts',
        ],
    ],

    'release' => [
        'backup' => [
            'enabled' => true,
            'path' => '.composer-unpeeled.json',
        ],
    ],

    'git' => [
        'commit_messages' => [
            'before_tag' => 'chore(dist): prepare Composer manifest for release',
            'after_tag' => 'chore: restore development Composer manifest',
        ],
    ],
];
```

### Peel configuration

The `peel.sections` configuration defines which top-level `composer.json` sections should be removed from the peeled
manifest.

```php
'peel' => [
    'sections' => [
        'require-dev',
        'autoload-dev',
        'scripts',
    ],
],
```

Supported sections include:

| Section        | Description                                     |
| -------------- | ----------------------------------------------- |
| `require-dev`  | Development-only Composer dependencies          |
| `autoload-dev` | Development-only autoloading configuration      |
| `scripts`      | Composer scripts used during development and CI |
| `scripts-descriptions` | Composer scripts descriptions           |
| `scripts-aliases` | Composer scripts aliases                     |

Only sections explicitly listed in the configuration are peeled.

If no custom configuration is provided, `composer-peel` uses its default set of sections.

### Release backup

The release backup stores the original `composer.json` before it is peeled. When enabled, the backup provides a recovery
point if the release process fails before the original manifest is restored. Ensure that the backup file is not
unintentionally included in the tagged release.

| Option                   | Description                                                  |
| ------------------------ | ------------------------------------------------------------ |
| `release.backup.enabled` | Enables or disables backing up the original `composer.json`. |
| `release.backup.path`    | Path to the backup file, relative to the project root.       |

> [!TIP]
> Keep backups enabled to provide a recovery point if the release process fails before the original manifest is
restored.

### Git commit messages

The Git commit messages used during the release workflow can be customised to match your project's conventions.

| Configuration                    | Description                                                                                                                |
| -------------------------------- | -------------------------------------------------------------------------------------------------------------------------- |
| `git.commit_messages.before_tag` | Commit message for the peeled `composer.json`. This commit is the target of the release tag.                               |
| `git.commit_messages.after_tag`  | Commit message for the commit that restores the original, unpeeled `composer.json` after the release tag has been created. |

Both messages can be customised to match your project's Git conventions. The defaults assume [Conventional Commits](https://www.conventionalcommits.org/en/v1.0.0/).

## Release workflow

`composer-peel` supports preparing a peeled Composer manifest for a tagged release while retaining the original
development manifest on the working branch.

You can automate this entire lifecycle by passing a Git tag to the `peel` command:

```bash
composer-peel peel v1.0.0
```

The command orchestrates the following workflow sequence:

1. Back up the original `composer.json` (requires backup to be enabled).
2. Generate the peeled Composer manifest.
3. Commit the peeled manifest using the configured `before_tag` commit message.
4. Create the release Git tag pointing to the peeled-manifest commit.
5. Restore the original, unpeeled `composer.json`.
6. Commit the restored manifest using the configured `after_tag` commit message.

The resulting Git history follows this sequence:

```text
Development commit
       │
       ▼
Peeled manifest commit
       │
       └── Release tag (v1.0.0)
       │
       ▼
Restored development manifest commit
```

The release Git tag references the peeled manifest, while the development branch continues with the original manifest.

> [!IMPORTANT]
> __CI compatibility__: This release workflow only works when the release step is not validated by CI against the
> peeled-manifest commit. Since development dependencies and scripts are no longer be available after peeling, CI jobs
> that rely on them will fail. Ensure your release process accounts for this limitation before enabling the workflow.

## License

This CLI and its library are licensed under the MIT license. Please see [LICENSE.md](LICENSE.md) for more details.

## Changelog

All noteworthy changes are documented in the [CHANGELOG.md](CHANGELOG.md).

## Contributing

If you're considering contributing to this project, have a look at this repository's [CONTRIBUTING.md](.github/CONTRIBUTING.md)
for more advice.
