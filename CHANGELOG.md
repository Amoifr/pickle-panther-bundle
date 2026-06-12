# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- `pickle_panther.debug` config option (default `false`): when enabled, a
  screenshot is captured after every step, not only on failures. The
  `E2E_DEBUG=1` environment variable still works and forces it on.

### Changed
- HTML report is now multi-page: `report.html` is a home page listing each YAML
  scenario file as a link (with a status icon and per-file scenario/step
  counts), and every file gets its own page (`report-N.html`) — containing all
  its scenarios and steps — with a breadcrumb back to the home page. Captures
  still resolve relatively next to the report.

### Fixed
- Screenshots are now cleared once at the start of the run (by
  `HtmlReportExtension`) instead of in every test's `setUp`. Previously each test
  class wiped the previous classes' captures, so the final report only kept the
  last class's screenshots — most visible with `debug` enabled.
- The report no longer shows a spurious "Non spécifié" entry: test-level records
  (added without a scenario file) are excluded from the rendered scenarios.

## [0.2.0] - 2026-06-12

### Added
- `pickle-panther:sentences` console command: documents every registered
  sentence (Markdown) by introspecting the tagged providers — no hardcoded
  list. Writes to stdout or a file (`--output`), optionally filtered by
  `--locale`.

### Changed
- `symfony/console` is now a required dependency (needed by the new command).

## [0.1.0] - 2026-06-11

First public release — the YAML/Panther E2E engine extracted into a reusable
Symfony bundle.

### Added
- YAML-driven scenario engine on top of Symfony Panther (`ScenarioRunner`,
  `ScenarioParser`, `BasePantherTest`).
- Bilingual (French/English) scenario DSL: `nom|name`, `etapes|steps`,
  `contexte|context`, `navigateur|browser`, `identifié|identified`,
  `titre|title`.
- Two ways to pass step arguments: explicit `args` (bound by name or position)
  and inline values written inside the brackets (bound positionally in
  placeholder order).
- `#[Sentence]` attribute (repeatable, multi-locale) with a backwards-compatible
  `#[Description]` alias; sentence providers are autoconfigured and aggregated by
  `SentenceRegistry`.
- Bundled sentences: `CommonSentences` (navigation, clicking, typing, waiting,
  assertions) and `AdminSentences` (generic back-office menus/datagrids).
- Pluggable authentication: `AuthenticatorInterface` plus a configurable
  `FormLoginAuthenticator`, enabled through `pickle_panther.auth`.
- Self-contained HTML report (`HtmlReporter` + PHPUnit `HtmlReportExtension`)
  with grouped scenarios, context badges, screenshots, embedded logo and author
  credits.
- Bundle configuration (`pickle_panther`): locale, scenarios directory, report
  output, browser (headless, desktop/mobile viewports, Chrome args) and auth.
- Demo test application with unit and functional (real browser) test suites.

[Unreleased]: https://github.com/Amoifr/pickle-panther-bundle/compare/v0.2.0...HEAD
[0.2.0]: https://github.com/Amoifr/pickle-panther-bundle/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/Amoifr/pickle-panther-bundle/releases/tag/v0.1.0
