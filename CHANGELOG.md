# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[Unreleased]: https://github.com/Amoifr/pickle-panther-bundle/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/Amoifr/pickle-panther-bundle/releases/tag/v0.1.0
