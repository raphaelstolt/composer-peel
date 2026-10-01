---
name: composer-peel
description: Use this skill when preparing a PHP Composer package for release, removing configured Composer sections from composer.json, or executing a composer-peel release workflow.
---

# composer-peel

Use `composer-peel` to prepare a PHP package's `composer.json` for distribution by temporarily removing configured Composer sections and, when requested, committing and tagging the peeled manifest and restoring the development manifest afterwards.

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

### Validate the Peeled Manifest

To verify the peeled `composer.json`:

```bash
vendor/bin/composer-peel validate
```

This checks that `composer.json` and the backup file contain valid JSON objects, the backup exists when backups are enabled, no configured section is still present, the required runtime sections `name` and `require` exist, all other sections are unchanged compared to the backup, and `composer validate --no-check-lock` reports no errors for both manifests. Each check is reported as `[PASS]`, `[FAIL]`, or `[SKIP]`, and the command exits non-zero if any check fails.

If the `composer` binary is not available, use `--skip-composer-validate` and mention to the user that the Composer checks were skipped.

The command does not modify any files and is safe to run at any time.

### Tagged Release Workflow

The tagged release workflow consists of the following commands:

```bash
vendor/bin/composer-peel peel
vendor/bin/composer-peel validate
vendor/bin/composer-peel release <tag>
vendor/bin/composer-peel rollback --commit
```

For example:

```bash
vendor/bin/composer-peel peel
vendor/bin/composer-peel validate
vendor/bin/composer-peel release v1.0.0
vendor/bin/composer-peel rollback --commit
```

1. `peel` creates the backup and removes the configured sections from `composer.json`.
2. `validate` reports on the peeled `composer.json` without modifying anything. `release` does not run these checks itself.
3. `release <tag>` commits the already peeled `composer.json` using the `before_tag` commit message and creates the Git tag. It does not peel and does not restore the development manifest.
4. `rollback --commit` restores the development `composer.json` from the backup and commits it using the `after_tag` commit message.

`release` requires a clean working tree. The only allowed changes are the peeled `composer.json`, the backup file, and any configured `managed_files` (which defaults to `CHANGELOG.md` and any files within the `bin/` directory, but replacing this array in the configuration will override these defaults). Other uncommitted or untracked files cause the command to fail.

Before committing, `release` verifies that the backup file exists and that `composer.json` is exactly the backup without the configured sections. It fails if `composer.json` has not been peeled or has been modified after peeling. It does not run the full `validate` checks (e.g., `composer validate`), so run `validate` before `release`. If a check fails, no commit or tag is created; fix the manifest via `rollback` and a fresh `peel` instead of editing it by hand.

To overwrite the default or configured `before_tag` commit message for a single release:

```bash
vendor/bin/composer-peel release v1.0.0 --commit-message="chore: release v1.0.0"
```

Only use these commands when the user explicitly requests a release/tag operation.

The tag must be a valid semantic version such as:

```text
v1.0.0
```

`release` also enforces that the tag does not already exist and is strictly greater than the latest released version.

### Rollback Changes

To restore the development `composer.json` from a backup file (e.g., after a failed release or to revert a manual peel operation):

```bash
vendor/bin/composer-peel rollback
```

This restores `composer.json` from the backup file configured in `.composer-peel.php` (defaulting to `.composer-unpeeled.json`) and removes the backup file.

The rollback is aborted if `composer.json` has been modified since it was peeled, as restoring would discard these changes. Do not use `--force` to override this unless the user confirms that the changes to `composer.json` can be discarded.

To preserve the backup file after restoration:

```bash
vendor/bin/composer-peel rollback --keep-backup
```

To commit the restored `composer.json` to Git using the configured `after_tag` commit message:

```bash
vendor/bin/composer-peel rollback --commit
```

To overwrite the default or configured commit message, add `--commit-message` (only valid together with `--commit`):

```bash
vendor/bin/composer-peel rollback --commit --commit-message="chore: start next development cycle"
```

`--commit` can be combined with `--keep-backup`. If the commit fails (e.g., outside a Git repository), the command exits with an error and the backup file is kept, while `composer.json` has already been restored.

Only use `--commit` as part of a tagged release workflow or when the user explicitly asks for the restoration to be committed.

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

5. Validate the resulting `composer.json`:

   ```bash
   vendor/bin/composer-peel validate
   ```

6. Inspect the resulting `composer.json` and run any additional package validation the project requires.

For a tagged release:

1. Confirm the requested version/tag.

2. Inspect the current Composer and `composer-peel` configuration.

3. Ensure the working tree is clean.

4. Preview the peeling operation if appropriate.

5. Peel the manifest:

   ```bash
   vendor/bin/composer-peel peel
   ```

6. Validate the peeled manifest and fix any reported failures before continuing:

   ```bash
   vendor/bin/composer-peel validate
   ```

7. Commit the peeled manifest and create the tag:

   ```bash
   vendor/bin/composer-peel release <tag>
   ```

8. Restore and commit the development manifest:

   ```bash
   vendor/bin/composer-peel rollback --commit
   ```

9. Verify the resulting Git state and tag, and confirm that the development Composer manifest has been restored.

For a rollback request:

1. Ensure the backup file (e.g., `.composer-unpeeled.json`) exists.
2. Run:

   ```bash
   vendor/bin/composer-peel rollback
   ```

   Add `--commit` only when the restored manifest should be committed to Git.
3. Inspect `composer.json` to confirm it has been fully restored.

## Important Distinction

There are four different operations:

```text
peel
  ├── backup
  └── modifies composer.json

validate
  └── verifies the peeled composer.json (read-only)

release <tag>
  ├── verify composer.json is the peeled backup
  ├── commit (peeled composer.json)
  └── tag

rollback [--commit]
  ├── restore composer.json from backup
  ├── commit (only with --commit)
  └── remove backup (unless --keep-backup)
```

Do not substitute the release workflow for a normal peeling operation.

## Validation

After a non-release peel, run `vendor/bin/composer-peel validate` and check that:

* required runtime dependencies are still present
* package metadata required for distribution remains present
* the intended Composer sections were removed
* the resulting JSON is valid
* no unrelated files were modified

After a release workflow, additionally verify:

* the expected tag was created
* the tag points to the peeled manifest commit
* the development manifest was restored and committed
* the working tree is in the expected state

## Principles

* Prefer `composer-peel` over manual Composer manifest manipulation.
* Inspect configuration before modifying the manifest.
* Prefer dry runs when the intended changes are uncertain.
* Keep release operations explicit and intentional.
* Do not modify `composer.lock` unless explicitly required.
* Preserve the development manifest through the configured backup/restore mechanism.
