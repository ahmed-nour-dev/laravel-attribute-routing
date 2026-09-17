# Changelog

All notable changes to `laravel-attribute-routing` are documented here.
This project follows [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Fixed

- `#[WithPermission(...)]` with multiple `Permitted`/`BackedEnum`/string arguments now
  collapses them into a single "any of" middleware entry (e.g. `permission:a,b`) instead
  of one middleware per argument, which silently required *all* of them (AND) because
  Laravel's middleware pipeline is AND, not OR. To require several different permissions
  (AND), stack `#[WithPermission]` — it is repeatable — so each instance still contributes
  its own middleware entry. Differently-named middleware within one attribute (e.g. a
  `Permitted` case and a raw `can:...` string) are unaffected and still AND together, since
  they can't be OR'd via a single middleware call. ([#1])

[#1]: https://github.com/ahmed-nour-dev/laravel-attribute-routing/issues/1

## [1.0.1] - 2026-08-20

Metadata only. No code changes.

### Changed

- Repository moved to `github.com/ahmed-nour-dev/laravel-attribute-routing`.
  Package metadata, README badges, and links updated to the new URL; the old
  URLs continue to redirect.
- The author entry now points at a profile page instead of an email address.

## [1.0.0] - 2026-08-20

First stable release. No code changes since 0.1.1 — this release promotes the
existing API to a stability commitment now that it has been running unchanged in
production for months.

### Changed

- The public API is now covered by Semantic Versioning. The attribute classes,
  their constructor arguments, the `Permitted` contract, and the config keys will
  not change incompatibly before 2.0.0.

## [0.1.1] - 2026-08-20

### Changed

- `attribute-routing:list` now renders in the same style as `php artisan route:list`:
  borderless layout with dot leaders, coloured HTTP verbs, `{parameters}` highlighted,
  all-verb routes collapsed to `ANY`, and controller names shortened against the
  application namespace. Middleware, excluded middleware, and `where` constraints moved
  behind `-v`, and long lines are truncated to the terminal width instead of wrapping.

## [0.1.0] - 2026-08-20

Initial release, extracted from a production Laravel application where it has
been registering 200+ routes since 2026.

### Added

- Support for Laravel 12 and 13 on PHP 8.2+.

- HTTP verb attributes: `#[Get]`, `#[Post]`, `#[Put]`, `#[Patch]`, `#[Delete]`,
  `#[Options]`, `#[Any]`, `#[MatchRoute]` — all repeatable.
- Class-level composition: `#[Prefix]`, `#[Version]`, `#[Middleware]`, `#[Name]`.
- `#[WithPermission]` mapping permission enums to middleware, via the
  `Permitted` contract, plain backed enums, or raw strings.
- `#[Throttle]` for rate limiting, `#[WithoutMiddleware]` for opting out of
  inherited middleware, `#[SkipDiscovery]` for excluding a class.
- Auto-discovery service provider that respects Laravel's route caching.
- `php artisan attribute-routing:list` for inspecting what was discovered.
- Publishable config for discovery paths and the permission middleware format.
