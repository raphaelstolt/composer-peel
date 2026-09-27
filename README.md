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

A small PHP development tool for removing configurable development-only metadata from `composer.json` files when releasing
packages for distribution.

## Why?

A package's `composer.json` often contains metadata that is useful while developing and maintaining the package, but is
not needed by downstream consumers.

For example, development dependencies, development autoloading, and Composer scripts can make up a significant part of a
project's development manifest.

`composer-peel` lets you explicitly define which Composer sections should be removed from a release manifest.

The goal is simple:

> Keep the package manifest focused on what consumers need and leave development metadata behind.

## What gets peeled?

The sections removed by `composer-peel` are configurable.

The default configuration includes the following sections:

| Section        | Purpose                                         |
| -------------- | ----------------------------------------------- |
| `require-dev`  | Development-only dependencies                   |
| `autoload-dev` | Development-only autoloading                    |
| `scripts`      | Composer scripts used during development and CI |
| `scripts-descriptions` | Composer scripts descriptions           |
| `scripts-aliases` | Composer scripts aliases                     |

Runtime dependencies and package autoloading remain untouched.

## Installation

Install `composer-peel` as a development dependency:

```bash
composer require --dev stolt/composer-peel
```

## Usage

Peel the current `composer.json`:

```bash
composer-peel peel
```

Preview the changes without modifying the manifest:

```bash
composer-peel peel --dry-run
```

Use a custom configuration:

```bash
composer-peel peel --config=.composer-peel.php
```

A backup can also be created before the manifest is modified:

```bash
composer-peel peel --backup-file=.composer-unpeeled.json
```

## Previewing changes with `--dry-run`

Use `--dry-run` to inspect what `composer-peel` would remove without modifying `composer.json`.

```bash
composer-peel peel --dry-run
```

The dry run shows:

* the manifest being processed,
* the configuration being used,
* the sections that would be removed,
* the original manifest size,
* the projected manifest size,
* the estimated size reduction.

With the default configuration:

```text
composer-peel --dry-run

Manifest:      composer.json
Configuration: internal defaults

Sections to be removed:
  - require-dev
  - autoload-dev
  - scripts

Original size:       2,480 bytes
Projected size:      1,120 bytes
Estimated reduction: 1,360 bytes (54.8%)

Dry run completed. No files were modified.
```

The values above are illustrative. The actual result depends on the contents of your `composer.json` and the configured
peeling rules.

## Configuration

`composer-peel` supports an optional PHP configuration file named `.composer-peel.php` in the project root.

A configuration can define which Composer sections are peeled, as well as release backup and Git commit behaviour:

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

### Peel sections

The `peel.sections` option defines the top-level Composer sections that should be removed:

```php
'peel' => [
    'sections' => [
        'require-dev',
        'autoload-dev',
        'scripts',
    ],
],
```

Only explicitly configured sections are peeled.

This makes the behaviour predictable and allows each package to decide which metadata belongs exclusively to its
development workflow.

### Release backup

The release backup stores the original `composer.json` before it is peeled:

```php
'release' => [
    'backup' => [
        'enabled' => true,
        'path' => '.composer-unpeeled.json',
    ],
],
```

The backup provides a recovery point if the release process fails before the original manifest is restored.

Make sure the backup file is not unintentionally included in the release.

### Git commit messages

The automated release workflow uses configurable commit messages:

```php
'git' => [
    'commit_messages' => [
        'before_tag' => 'chore(dist): prepare Composer manifest for release',
        'after_tag' => 'chore: restore development Composer manifest',
    ],
],
```

`before_tag` is used for the commit containing the peeled manifest.

`after_tag` is used for the commit restoring the original development manifest.

## Release workflow

`composer-peel` can automate the process of preparing a peeled manifest for a Git-tagged release while restoring the
original development manifest afterwards.

Run:

```bash
composer-peel peel v1.0.0
```

The workflow is:

1. Back up the original `composer.json`, if enabled.
2. Generate the peeled manifest.
3. Commit the peeled manifest.
4. Create the release tag.
5. Restore the original manifest.
6. Commit the restored manifest.

The resulting history looks like this:

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

The release tag therefore points to the peeled manifest, while the development branch continues with the original
manifest.

> [!IMPORTANT]
>
> The automated release workflow only works when the peeled-manifest commit does not need to pass the project's normal
> development CI checks.
>
> After peeling, development dependencies, development autoloading, and Composer scripts may no longer be available. CI
> jobs that depend on them can therefore fail.
>
> If your release process requires CI validation of the tagged commit, consider using `composer-peel` as a separate
> distribution/build step instead of tagging the peeled manifest directly.

## AI skill

`composer-peel` includes an AI skill for AI coding agents that need to prepare a Composer package for distribution.

The skill is located at:

```text
.agents/skills/composer-peel/SKILL.md
```

It provides agents with guidance for:

* previewing Composer manifest changes
* peeling configured Composer sections
* inspecting `.composer-peel.php` configuration
* safely preparing a package for release
* executing the tagged release workflow
* validating the resulting Composer manifest

The skill is intentionally kept alongside the `composer-peel` source so that its instructions can evolve together with
the CLI and its configuration.

Agents that support repository-local skills can use the skill to understand when and how to use `composer-peel` instead
of manually modifying `composer.json`.

## License

This CLI and its library are licensed under the MIT license. Please see [LICENSE.md](LICENSE.md) for more details.

## Changelog

All noteworthy changes are documented in the [CHANGELOG.md](CHANGELOG.md).

## Contributing

If you're considering contributing to this project, have a look at this repository's [CONTRIBUTING.md](.github/CONTRIBUTING.md)
for more advice.
