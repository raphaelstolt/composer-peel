# composer-peel

![Test Status](https://github.com/raphaelstolt/composer-peel/workflows/test/badge.svg)
[![Version](http://img.shields.io/packagist/v/stolt/composer-peel.svg?style=flat)](https://packagist.org/packages/stolt/composer-peel)
![Downloads](https://img.shields.io/packagist/dt/stolt/composer-peel)
![PHP Version](https://img.shields.io/badge/php-8.2+-ff69b4.svg)
[![MIT License](https://img.shields.io/badge/License-MIT-green.svg)](https://choosealicense.com/licenses/mit/)
![Ai skill available](https://img.shields.io/badge/ai%20skill-available-f54927.svg?style=flat)
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

> Keep the release package manifest i.e. `composer.json` focused on what consumers need and leave development metadata behind.

## What gets peeled?

The sections removed by `composer-peel` are configurable. These are removed from the package's published composer.json;
they are not removed from the source repository's development configuration permanently. Runtime dependencies and package
autoloading remain untouched.

The default configuration includes the following sections:

| Section        | Purpose                                         |
| -------------- | ----------------------------------------------- |
| `require-dev`  | Development-only dependencies                   |
| `autoload-dev` | Development-only autoloading                    |
| `scripts`      | Composer scripts used during development and CI |
| `scripts-descriptions` | Composer scripts descriptions           |
| `scripts-aliases` | Composer scripts aliases                     |

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

The `init` command is useful when you want to make the peeling policy explicit and version-controlled
in your repository. It generates a configuration file from the current internal defaults, which you can then customise.

```bash
composer-peel init
```

By default, the `init` command will not overwrite an existing configuration file. If you want to overwrite it, use the
`--overwrite` option:

```bash
composer-peel init --overwrite
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
$ composer-peel --dry-run

Manifest:      composer.json
Configuration: internal defaults

Sections to be removed:
  - require-dev
  - autoload-dev
  - scripts
  - scripts-descriptions
  - scripts-aliases

Original size:       2,480 bytes
Projected size:      1,120 bytes
Estimated reduction: 1,360 bytes (54.8%)

Dry run completed. No files were modified.
```

The values above are illustrative. The actual result depends on the contents of your `composer.json` and the configured
peeling rules.

If you also want to see the exact structural changes, you can use the `--diff` option:

```bash
composer-peel peel --dry-run --diff
```

If you need the dry-run output in a machine-readable format, you can use the `--format=json` option:

```bash
composer-peel peel --dry-run --format=json
```

When both `--format=json` and `--diff` are used, the resulting JSON object will contain an additional `diff` key with
the unified diff string.

```json
{
    "manifest": "composer.json",
    "configuration": "internal defaults",
    "removed_sections": [
        "require-dev",
        "autoload-dev",
        "scripts",
        "scripts-descriptions",
        "scripts-aliases"
    ],
    "original_size_bytes": 2480,
    "projected_size_bytes": 1120,
    "reduction_bytes": 1360,
    "reduction_percentage": 54.8
}
```

## Validating the peeled manifest

Use the `validate` command to verify a peeled `composer.json` before releasing it:

```bash
composer-peel validate
```

The following checks are performed:

* `composer.json` contains a valid JSON object,
* the backup file exists when backups are enabled,
* the backup file contains a valid JSON object,
* none of the configured sections is still present in `composer.json`,
* the required runtime sections `name` and `require` still exist,
* all other sections are unchanged compared to the backup,
* `composer validate --no-check-lock` reports no errors for `composer.json` and the backup file.

```text
$ composer-peel validate

[PASS] composer.json contains valid JSON
[PASS] Backup .composer-unpeeled.json exists
[PASS] Backup .composer-unpeeled.json contains valid JSON
[PASS] Configured sections are absent
[PASS] Required runtime sections exist
[PASS] Runtime sections match the backup
[PASS] composer validate reports no errors for composer.json
[PASS] composer validate reports no errors for .composer-unpeeled.json

Peeled composer.json validated successfully.
```

The command exits with a non-zero status code if any check fails, which makes it usable in CI. Checks depending on
the backup are skipped when backups are disabled via the configuration.

The `composer validate` checks require the `composer` binary to be available on the `PATH`. Warnings do not fail the
validation, but errors, including publish errors like a missing `description`, do. The lock file is not checked,
because it is expected to still contain the peeled development dependencies. To skip these checks, use the
`--skip-composer-validate` option:

```bash
composer-peel validate --skip-composer-validate
```

A custom backup file or configuration file can be specified as well:

```bash
composer-peel validate --backup-file=my-backup.json --config=.composer-peel.php
```

## Rolling back changes

If a backup file was created during the `peel` process, you can restore `composer.json` to its original state using
the `rollback` command.

```bash
composer-peel rollback
```

By default, the `rollback` command will delete the backup file after successfully restoring the manifest. If you want
to keep the backup file, use the `--keep-backup` option:

```bash
composer-peel rollback --keep-backup
```

To commit the restored `composer.json` to Git right away, use the `--commit` option. The commit uses the configured
`after_tag` [commit message](#git-commit-messages):

```bash
composer-peel rollback --commit
```

To overwrite the default or configured commit message, use the `--commit-message` option. It requires the `--commit`
option:

```bash
composer-peel rollback --commit --commit-message="chore: start next development cycle"
```

The `--commit` option can be combined with `--keep-backup`. If the commit fails, e.g. because the current directory is
not a Git repository, the command exits with an error and the backup file is kept.

Before restoring, the `rollback` command verifies that `composer.json` still is the peeled version of the backup, i.e.
the backup without the configured sections. If `composer.json` has been modified since it was peeled, the rollback is
aborted, as restoring the backup would discard these changes. To restore the backup anyway, use the `--force` option:

```bash
composer-peel rollback --force
```

If `composer.json` already matches the backup, it is left untouched.

You can also specify a custom backup file or configuration file during rollback:

```bash
composer-peel rollback --backup-file=my-backup.json --config=.composer-peel.php
```

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
            'scripts-descriptions',
            'scripts-aliases',
        ],
    ],
    'release' => [
        'backup' => [
            'enabled' => true,
            'path' => '.composer-unpeeled.json',
        ],
        'managed_files' => [
            'CHANGELOG.md',
            'bin/',
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
        'scripts-descriptions',
        'scripts-aliases',
    ],
],
```

Only explicitly configured sections are peeled.

This makes the behaviour predictable and allows each package to decide which metadata belongs exclusively to its
development workflow.

> [!NOTE]
>
> `composer-peel` only permits stripping development-oriented metadata. Attempting to configure protected sections
> such as `require` or `autoload` will cause the operation to fail with an error.

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

`after_tag` is used by `rollback --commit` for the commit restoring the original development manifest.

Both messages can be overwritten per invocation via the `--commit-message` option of the `release` and
`rollback --commit` commands.

### Release workflow

Use the `release` command to commit and tag an **already peeled** `composer.json`.

The `release` command does not peel the manifest itself. Run `peel`, and optionally `validate`, first:

```bash
composer-peel peel
composer-peel validate
composer-peel release v1.0.0
composer-peel rollback --commit
```

The `release` command validates the requested tag before proceeding. It requires that:
- the tag is a valid Semantic Version (e.g. `v1.0.0`),
- the tag does not already exist,
- the tag is strictly greater than the latest released version.

The workflow is:

1. Run `peel` to remove the configured development-only sections from `composer.json`.
2. Optionally run `validate` to inspect the peeled manifest independently.
3. Run `release` to commit the peeled manifest and create the Git tag.
4. Run `rollback --commit` to restore the original development `composer.json` and commit the changes.

The `release` command requires a clean working tree. By default, the only allowed changes are the peeled `composer.json`, the
backup file created by `peel`, the `CHANGELOG.md` file, and any modified files in the `bin/` directory.

You can override which files are allowed to be modified and committed during the release by adding a `managed_files` array to the `release` section in your `.composer-peel.php`. This will replace the default `CHANGELOG.md` and `bin/` allowances. Directories should end with a trailing slash (`/`).

Before committing, the `release` command also verifies that `composer.json` is exactly the backup file without the
configured sections. The release is aborted without creating a commit or tag if the backup file is missing, if
`composer.json` has not been peeled, or if it has been modified after peeling. In the latter case, run `rollback` and
`peel` again. For the full set of checks, including `composer validate`, use the
[`validate`](#validating-the-peeled-manifest) command.

The commit messages used for the release workflow can be configured through `.composer-peel.php`. To overwrite the
default or configured commit message for a single release, use the `--commit-message` option:

```bash
composer-peel release v1.0.0 --commit-message="chore: release v1.0.0"
```

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
> The release tag points to a commit containing a different `composer.json` than the development branch.
>
> The automated release workflow only works when the peeled-manifest commit does not need to pass the project's normal
> development CI checks.
>
> After peeling, development dependencies, development autoloading, and Composer scripts may no longer be available. CI
> jobs that depend on them can therefore fail.
>
> If your release process requires CI validation of the tagged commit, consider using `composer-peel` as a separate
> distribution/build step instead of tagging the peeled manifest directly.

## Composer lock file

`composer-peel` modifies only `composer.json`. It does not modify `composer.lock`.

## AI skill

`composer-peel` includes a repository-local AI skill at `.agents/skills/composer-peel/SKILL.md`.

The skill teaches compatible coding agents how to inspect the configuration, preview changes, peel
the configured sections, and safely perform the release workflow.

## License

This CLI and its library are licensed under the MIT license. Please see [LICENSE.md](LICENSE.md) for more details.

## Changelog

All noteworthy changes are documented in the [CHANGELOG.md](CHANGELOG.md).

## Contributing

If you're considering contributing to this project, have a look at this repository's [CONTRIBUTING.md](.github/CONTRIBUTING.md)
for more advice.
