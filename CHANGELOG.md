# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to
[Semantic Versioning](http://semver.org/).

## [Unreleased]

### Fixed
- The `status` command no longer reports a dirty working tree when only the composer-peel backup file or
`composer.json` have changed.

- The `status` command no longer reports a release as "not ready" when the latest Git tag is missing or doesn't
match the application version, as the tag may not exist yet at pre-release time.

## [v4.2.0] - 2026-10-04

### Added
- New `status` command.

## [v4.1.0] - 2026-10-02

### Added
- New `--dry-run` option for the `rollback` command.

## [v4.0.0] - 2026-10-02

### Changed
- The configuration structure has been flattened. `release.managed_files` is now `release.files`,
`git.commit_messages.release` is now `release.commit_message`, and `git.commit_messages.after_release` is now
`rollback.commit_message`. The `git` section has been removed.

## [v3.0.0] - 2026-10-02

### Changed
- The configuration keys for commit messages have been renamed. `git.commit_messages.before_tag` is now 
`git.commit_messages.release`, and `git.commit_messages.after_tag` is now `git.commit_messages.after_release`. Closes
[#7](https://github.com/raphaelstolt/composer-peel/issues/7).

### Added
- The `release` commit message can now include a `{{version}}` placeholder, which will be automatically replaced with
the provided release tag.

## [v2.2.1] - 2026-10-02

### Fixed
- A tagged `release` can also include changes to the `CHANGELOG.md` or files in `bin/`.

### Added 
- The allowed files of a `release` are configurable.
- New `--dry-run` option for the `release` command.

## [v2.1.1] - 2026-10-01

### Fixed
- The Git tag passed to the `release` command is validated more thoroughly. Closes [#6](https://github.com/raphaelstolt/composer-peel/issues/6). 

## [v2.1.0] - 2026-09-30

### Added
- New `--commit-message` option for the `release` and `rollback` commands, overwriting the default or configured
  commit message. For the `rollback` command it requires the `--commit` option.

## [v2.0.0] - 2026-09-30

### Changed
- The release workflow is broken into individual commands: `peel`, `release`, and `rollback --commit`.
- The `release` command no longer peels and restores `composer.json`. It commits and tags an already peeled manifest
  and aborts if `composer.json` is not exactly the peeled version of the backup file.
- The `rollback` command refuses to overwrite a `composer.json` modified since it was peeled.
- The `rollback` command leaves `composer.json` untouched if it already matches the backup file.

### Added
- New dedicated `release` command.
- New `validate` command verifying the peeled `composer.json`, including `composer validate`.
- New `--commit` option for the `rollback` command.
- New `--force` option for the `rollback` command to restore a modified `composer.json`.

### Removed
- The `tag` argument of the `peel` command. Use `peel`, `release <tag>`, and `rollback --commit` instead.

### Fixed
- The `rollback` command reports invalid configuration files instead of failing with an uncaught exception.
- The `rollback` command rejects backup files not containing a JSON object instead of failing with a type error.

## [v1.3.2] - 2026-09-29

### Fixed
- Widen `sebastian/diff` support.

## [v1.3.1] - 2026-09-29

### Fixed
- The status of Git working tree is checked.
- Guard against the usage of non-dev Composer sections.

### Added
- New `--format` and `--diff` option for the `peel` command.

## [v1.3.0] - 2026-09-28

### Added
- New `rollback` command.

## [v1.2.0] - 2026-09-27

### Added
- New configuration `init` command.

## [v1.1.0] - 2026-09-27

### Added
- Make composer.json sections to be peeled configurable. Closes [#1](https://github.com/raphaelstolt/composer-peel/issues/1).

## v1.0.0 - 2026-09-27

### Added
- Initial implementation.


[Unreleased]: https://github.com/raphaelstolt/composer-peel/compare/v4.2.0...HEAD
[v4.2.0]: https://github.com/raphaelstolt/composer-peel/compare/v4.1.0...v4.2.0
[v4.1.0]: https://github.com/raphaelstolt/composer-peel/compare/v4.0.0...v4.1.0
[v4.0.0]: https://github.com/raphaelstolt/composer-peel/compare/v3.0.0...v4.0.0
[v3.0.0]: https://github.com/raphaelstolt/composer-peel/compare/v2.2.1...v3.0.0
[v2.2.1]: https://github.com/raphaelstolt/composer-peel/compare/v2.1.1...v2.2.1
[v2.1.1]: https://github.com/raphaelstolt/composer-peel/compare/v2.1.0...v2.1.1
[v2.1.0]: https://github.com/raphaelstolt/composer-peel/compare/v2.0.0...v2.1.0
[v2.0.0]: https://github.com/raphaelstolt/composer-peel/compare/v1.3.2...v2.0.0
[v1.3.2]: https://github.com/raphaelstolt/composer-peel/compare/v1.3.1...v1.3.2
[v1.3.1]: https://github.com/raphaelstolt/composer-peel/compare/v1.3.0...v1.3.1
[v1.3.0]: https://github.com/raphaelstolt/composer-peel/compare/v1.2.0...v1.3.0
[v1.2.0]: https://github.com/raphaelstolt/composer-peel/compare/v1.1.0...v1.2.0
[v1.1.0]: https://github.com/raphaelstolt/composer-peel/compare/v1.0.0...v1.1.0

