# composer-peel

![Test Status](https://github.com/raphaelstolt/composer-peel/workflows/test/badge.svg)
[![Version](http://img.shields.io/packagist/v/stolt/composer-peel.svg?style=flat)](https://packagist.org/packages/stolt/composer-peel)
![Downloads](https://img.shields.io/packagist/dt/stolt/composer-peel)
![PHP Version](https://img.shields.io/badge/php-8.2+-ff69b4.svg)
[![MIT License](https://img.shields.io/badge/License-MIT-green.svg)](https://choosealicense.com/licenses/mit/)
![AI skill available](https://img.shields.io/badge/ai%20skill-available-f54927.svg?style=flat)
[![PDS Skeleton](https://img.shields.io/badge/pds-skeleton-blue.svg?style=flat)](https://github.com/php-pds/skeleton)
[![Lean dist package](https://img.shields.io/badge/lean-dist%20package-00ffb6.svg?style=flat)](https://github.com/raphaelstolt/lean-package-validator)

<p align="center">
    <img src="logo.png"
         title="The Composer metadata peeler"
         alt="Composer metadata peeler logo">
</p>

A small PHP development tool for removing configurable development-only metadata from `composer.json` files when
releasing packages for distribution.

## Why?

A package's `composer.json` often contains metadata that is useful while developing and maintaining the package, but is
not needed by downstream consumers.

For example, development dependencies, development autoloading, and Composer scripts can make up a significant part of
a project's development manifest.

`composer-peel` lets you explicitly define which Composer sections should be removed from a release manifest.

> Keep the release package manifest focused on what consumers need and leave development metadata behind.

## What gets peeled?

The sections removed by `composer-peel` are configurable. They are removed from the release `composer.json`; they are
not permanently removed from the source repository's development configuration.

The default configuration removes:

| Section | Purpose |
| --- | --- |
| `require-dev` | Development-only dependencies |
| `autoload-dev` | Development-only autoloading |
| `scripts` | Composer scripts used during development and CI |
| `scripts-descriptions` | Composer script descriptions |
| `scripts-aliases` | Composer script aliases |

Runtime dependencies and package autoloading remain untouched.

## A real-world example

Even widely used packages can carry significant development metadata into their published `composer.json`.

For example, peeling the `require-dev` section from __`symfony/console` v8.1.8__ would reduce its manifest from
__1,813 bytes to 1,170 bytes__ — a saving of __643 bytes (35.5%)__.

With more than __1.2 billion installations__, that amounts to a theoretical cumulative saving of approximately
__782 GB of Composer metadata__.

The example illustrates the principle behind `composer-peel`: small savings in a package manifest can become
significant at scale.

## Installation

Install `composer-peel` as a development dependency:

```bash
composer require --dev stolt/composer-peel
```

## Usage

### Peel

Remove the configured sections from the current `composer.json`:

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

Create a backup before modifying the manifest:

```bash
composer-peel peel --backup-file=.composer-unpeeled.json
```

### Initialize configuration

The `init` command generates a `.composer-peel.php` file from the current internal defaults. This is useful when you
want the peeling policy to be explicit and version-controlled:

```bash
composer-peel init
```

An existing configuration is not overwritten by default:

```bash
composer-peel init --overwrite
```

### Preview changes

The `--dry-run` option shows:

- the manifest being processed;
- the configuration being used;
- the sections that would be removed;
- the original manifest size;
- the projected manifest size;
- the estimated size reduction.

For example:

```text
$ composer-peel peel --dry-run

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

The values above are illustrative; the actual result depends on the contents of `composer.json` and the configured
peeling rules.

To see the exact structural changes:

```bash
composer-peel peel --dry-run --diff
```

For machine-readable output:

```bash
composer-peel peel --dry-run --format=json
```

When `--format=json` and `--diff` are combined, the JSON output contains an additional `diff` key with the unified diff.

## Check status

Use `status` to get a read-only overview of the current Composer Peel state and determine whether the
repository is ready for the release workflow:

```bash
composer-peel status
```

The command inspects:

- the current Composer manifest state (peeled, unpeeled, or modified);
- whether a Composer backup exists;
- the optional Composer package version (separate from the application version);
- discovered application version sources and their consistency;
- the latest Git tag;
- the CHANGELOG version;
- the working tree state;
- overall release readiness.

### Package version vs. application version

The `status` command distinguishes between two version concepts:

- __Package version__: the optional `version` field in `composer.json`. This is metadata for the Composer package
  and is not used as an application version source.
- __Application version__: discovered from supported source files such as `bin/` files, `src/Console/Application.php`,
  `src/Application.php`, and `src/Server.php`. This follows the same detection rules as `version-aligner`.

`composer.json` is never used as an application version source.

### Example output

For a healthy, release-ready state:

```text
Composer Peel status

Composer manifest     peeled
Backup                available

Package version       1.4.0

Application versions
  bin/composer-peel              1.4.0
  src/Console/Application.php    1.4.0

Application version     1.4.0
Version consistency     consistent
Latest Git tag          v1.4.0
CHANGELOG.md            1.4.0
Working tree            clean
Release state           ready
```

For an inconsistent state:

```text
Composer Peel status

Composer manifest     peeled
Backup                available

Application versions
  bin/composer-peel              1.3.0
  src/Console/Application.php    1.4.0

Application version     1.4.0
Version consistency     inconsistent
Latest Git tag          v1.3.0
CHANGELOG.md            1.4.0
Working tree            clean
Release state           not ready

Issues:
  - bin/composer-peel contains version 1.3.0 but the other application version source contains 1.4.0
  - Application version 1.4.0 does not match the latest Git tag v1.3.0
```

The command exits with `0` when the repository is release-ready, and with a non-zero exit code when issues are
detected. It is safe to run repeatedly and does not modify any files, Git state, commits, or tags.

A custom configuration file can be specified:

```bash
composer-peel status --config=.composer-peel.php
```

## Validate the peeled manifest

Use `validate` to verify the peeled `composer.json` before releasing it:

```bash
composer-peel validate
```

The following checks are performed:

- `composer.json` contains a valid JSON object;
- the backup file exists when backups are enabled;
- the backup file contains a valid JSON object;
- none of the configured sections is still present in `composer.json`;
- the required runtime sections `name` and `require` still exist;
- all other sections are unchanged compared with the backup;
- `composer validate --no-check-lock` reports no errors for `composer.json` and the backup.

The command exits with a non-zero status code if a check fails, making it suitable for CI. Checks that depend on the
backup are skipped when backups are disabled.

The Composer validation checks require the `composer` binary to be available on `PATH`. Warnings do not fail validation,
but errors — including publish errors such as a missing `description` — do.

The lock file is not checked because it is expected to retain the development dependencies removed from the release
manifest.

To skip the Composer validation checks:

```bash
composer-peel validate --skip-composer-validate
```

A custom backup or configuration file can also be specified:

```bash
composer-peel validate --backup-file=my-backup.json --config=.composer-peel.php
```

## Roll back changes

When `peel` has created a backup, `rollback` restores the original `composer.json`:

```bash
composer-peel rollback
```

By default, the backup file is deleted after a successful rollback. Keep it with:

```bash
composer-peel rollback --keep-backup
```

Restore and commit the development manifest immediately:

```bash
composer-peel rollback --commit
```

The commit uses the configured rollback commit message. Override it for a single invocation with:

```bash
composer-peel rollback --commit --commit-message="chore: start next development cycle"
```

`--commit-message` requires `--commit`.

The `--commit` option can be combined with `--keep-backup`. If committing fails, the command exits with an error and
keeps the backup so the rollback can be retried.

Before restoring, `rollback` verifies that `composer.json` is still the peeled version of the backup. If the manifest
has been modified since it was peeled, the rollback is aborted to avoid discarding those changes.

To restore the backup anyway:

```bash
composer-peel rollback --force
```

If `composer.json` already matches the backup, it is left untouched.

Use `--dry-run` to preview the rollback checks, the commit message, and whether the backup would be removed:

```bash
composer-peel rollback --dry-run
composer-peel rollback --commit --dry-run
```

Custom backup and configuration files can also be supplied:

```bash
composer-peel rollback --backup-file=my-backup.json --config=.composer-peel.php
```

## Configuration

`composer-peel` supports an optional PHP configuration file named `.composer-peel.php` in the project root.

The configuration can define:

- which Composer sections are peeled;
- the release backup;
- which files may be included in a release commit;
- release and rollback commit messages.

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
        'files' => [
            'CHANGELOG.md',
            'bin/',
        ],
        'commit_message' => 'chore: release version {{version}}',
    ],

    'rollback' => [
        'commit_message' => 'chore: restore development Composer manifest',
    ],
];
```

### Peel sections

`peel.sections` defines the top-level Composer sections that should be removed:

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

Only explicitly configured sections are peeled. This makes the behavior predictable and lets each package decide which
metadata belongs exclusively to its development workflow.

> [!NOTE]
> `composer-peel` only permits stripping development-oriented metadata. Attempting to configure protected 
> sections such as `require` or `autoload` causes the operation to fail.

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

The backup provides the source for restoring the development manifest if the release process fails before restoration.

Make sure the backup file is not unintentionally included in the release.

### Release files

By default, the release workflow allows the peeled `composer.json`, the backup, `CHANGELOG.md`, and modified files in
`bin/` to be committed.

The default `CHANGELOG.md` and `bin/` allowances can be replaced through `release.files`:

```php
'release' => [
    'files' => [
        'CHANGELOG.md',
        'bin/',
    ],
],
```

Directories must end with `/`.

### Git commit messages

The release workflow uses separate commit messages for the peeled release manifest and the restored development manifest:

```php
'release' => [
    'commit_message' => 'chore: release version {{version}}',
],

'rollback' => [
    'commit_message' => 'chore: restore development Composer manifest',
],
```

`release.commit_message` is used for the commit containing the peeled manifest. The `{{version}}` placeholder is
replaced with the release tag.

`rollback.commit_message` is used by `rollback --commit` when restoring the development manifest.

Both can be overridden for an individual invocation with `--commit-message`.

## Release workflow

The `release` command commits and tags an __already peeled__ `composer.json`. It does not peel the manifest itself.

A complete release workflow is:

```bash
composer-peel peel
composer-peel validate
composer-peel release v1.0.0
composer-peel rollback --commit
```

The workflow is:

1. Run `peel` to remove the configured development-only sections and create the backup.
2. Optionally run `validate` to inspect the peeled manifest independently.
3. Run `release` to commit the peeled manifest and create the Git tag.
4. Run `rollback --commit` to restore the original development `composer.json` and commit the restoration.

### Release validation

Before proceeding, `release` verifies that:

- the requested tag is a valid Semantic Version, such as `v1.0.0`;
- the tag does not already exist;
- the tag is strictly greater than the latest released version;
- the working tree is in the expected release state;
- the backup exists;
- `composer.json` is exactly the backup with the configured sections removed;
- only allowed files have changed.

By default, the allowed release changes are the peeled `composer.json`, the backup, `CHANGELOG.md`, and modified files
in `bin/`.

If the release state is invalid, no commit or tag is created.

To override the release commit message:

```bash
composer-peel release v1.0.0 --commit-message="chore: release {{version}}"
```

To preview the files, commit message, and tag without creating them:

```bash
composer-peel release v1.0.0 --dry-run
```

The release tag points to the commit containing the peeled manifest. After `rollback --commit`, the development branch
contains the restored manifest:

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

> [!IMPORTANT]
> The release tag points to a commit containing a different `composer.json` from the development branch.
>
> The automated release workflow only works when the peeled-manifest commit does not need to pass the project's normal 
> development CI checks. After peeling, development dependencies, development autoloading, and Composer scripts are no 
> longer available.
>
> If your release process requires CI validation of the tagged commit, consider using `composer-peel` as a separate 
> distribution/build step instead of tagging the peeled manifest directly.

## Composer lock file

`composer-peel` modifies only `composer.json`. It does not modify `composer.lock`.

## AI skill

`composer-peel` includes a repository-local AI skill at `.agents/skills/composer-peel/SKILL.md`.

The skill teaches compatible coding agents how to inspect the configuration, preview changes, peel the configured
sections, and safely perform the release workflow.

## License

This CLI and its library are licensed under the MIT license. Please see [LICENSE.md](LICENSE.md) for more details.

## Changelog

All noteworthy changes are documented in [CHANGELOG.md](CHANGELOG.md).

## Contributing

If you're considering contributing to this project, have a look at this repository's [CONTRIBUTING.md](.github/CONTRIBUTING.md) for more advice.
