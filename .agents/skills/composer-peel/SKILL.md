---
name: composer-peel
description: Use this skill when preparing a PHP Composer package for release, removing configured Composer sections from composer.json, or executing a composer-peel release workflow.
---

# composer-peel

Use `composer-peel` to prepare a PHP package's `composer.json` for distribution by temporarily removing configured Composer sections and, when requested, restoring the development manifest after a tagged release.

## When to Use

Use this skill when the task involves:

* preparing `composer.json` for a package release
* removing development-only Composer configuration from a distribution manifest
* previewing which Composer sections will be peeled
* creating a release tag with a peeled Composer manifest
* restoring the development Composer manifest after a release
* restoring the development Composer manifest from a backup file

Do not manually remove Composer sections when `composer-peel` is available and the requested operation matches its purpose.

## Safety Rules

Before modifying `composer.json`:

1. Check whether `.composer-peel.php` exists.
2. Review the configured `peel.sections`.
3. Do not assume that every configured section is development-only.
4. Prefer a dry run when the requested changes or configuration are unclear.
5. Never use the release workflow unless the user explicitly asks to create or prepare a tagged release.
6. Preserve the original Composer manifest through the configured backup mechanism.

Do not modify `composer.lock` unless the requested workflow explicitly requires it.

## Core Commands

`composer-peel` is typically executed from the project root via:

```bash
vendor/bin/composer-peel
```

### Preview Changes

Use a dry run to inspect the planned changes without modifying `composer.json`:

```bash
vendor/bin/composer-peel peel --dry-run
```

If you need the dry-run output in a machine-readable format, you can use the `--format=json` option:

```bash
vendor/bin/composer-peel peel --dry-run --format=json
```

Prefer this when:

* the user asks what would be removed
* the configured sections are unfamiliar
* you are preparing a release and want to verify the result first

### Peel the Composer Manifest

To apply the configured peeling operation:

```bash
vendor/bin/composer-peel peel
```

After peeling, inspect the resulting `composer.json` and verify that the remaining manifest still represents the intended distributable package.

### Tagged Release Workflow

To execute the complete Git release workflow:

```bash
vendor/bin/composer-peel peel <tag>
```

For example:

```bash
vendor/bin/composer-peel peel v1.0.0
```

The release workflow handles the configured backup, peeling, Git commit/tag operations, and restoration of the development manifest.

Only use this command when the user explicitly requests a release/tag operation.

The tag must be a valid semantic version such as:

```text
v1.0.0
```

### Rollback Changes

To restore the development `composer.json` from a backup file (e.g., after a failed release or to revert a manual peel operation):

```bash
vendor/bin/composer-peel rollback
```

This restores `composer.json` from the backup file configured in `.composer-peel.php` (defaulting to `.composer-unpeeled.json`) and removes the backup file.

To preserve the backup file after restoration:

```bash
vendor/bin/composer-peel rollback --keep-backup
```

## Configuration

`composer-peel` reads project-specific configuration from:

```text
.composer-peel.php
```

Example:

```php
<?php

declare(strict_types=1);

return [
    'peel' => [
        'sections' => [
            'require-dev',
            'autoload-dev',
            'repositories',
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
    ],

    'git' => [
        'commit_messages' => [
            'before_tag' => 'chore(dist): prepare Composer manifest for release',
            'after_tag' => 'chore: restore development Composer manifest',
        ],
    ],
];
```

When configuration is present, inspect it before deciding which sections will be removed.

Do not introduce or change peeling configuration unless the user asks for it or the existing configuration is insufficient for the requested workflow.

## Recommended Workflow

For a normal peeling request:

1. Inspect `composer.json`.

2. Inspect `.composer-peel.php` if present.

3. Run a dry run when the intended changes need verification:

   ```bash
   vendor/bin/composer-peel peel --dry-run
   ```

4. Apply the peeling operation:

   ```bash
   vendor/bin/composer-peel peel
   ```

5. Inspect the resulting `composer.json`.

6. Run appropriate Composer/package validation.

For a tagged release:

1. Confirm the requested version/tag.

2. Inspect the current Composer and `composer-peel` configuration.

3. Preview the peeling operation if appropriate.

4. Run:

   ```bash
   vendor/bin/composer-peel peel <tag>
   ```

5. Verify the resulting Git state and tag.

6. Confirm that the development Composer manifest has been restored.

For a rollback request:

1. Ensure the backup file (e.g., `.composer-unpeeled.json`) exists.
2. Run:

   ```bash
   vendor/bin/composer-peel rollback
   ```
3. Inspect `composer.json` to confirm it has been fully restored.

## Important Distinction

There are two different operations:

```text
peel
  └── modifies composer.json

peel <tag>
  └── performs the configured release workflow
      ├── backup
      ├── peel
      ├── commit
      ├── tag
      ├── restore
      └── commit
```

Do not substitute the release workflow for a normal peeling operation.

## Validation

After a non-release peel, validate the resulting manifest and check that:

* required runtime dependencies are still present
* package metadata required for distribution remains present
* the intended Composer sections were removed
* the resulting JSON is valid
* no unrelated files were modified

After a release workflow, additionally verify:

* the expected tag was created
* the development manifest was restored
* the working tree is in the expected state

## Principles

* Prefer `composer-peel` over manual Composer manifest manipulation.
* Inspect configuration before modifying the manifest.
* Prefer dry runs when the intended changes are uncertain.
* Keep release operations explicit and intentional.
* Do not modify `composer.lock` unless explicitly required.
* Preserve the development manifest through the configured backup/restore mechanism.
