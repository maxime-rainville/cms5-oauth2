# cms5x-auth

A small Silverstripe 5 app where we build a reusable **OAuth2 authorization server**: this host issues tokens to apps, rather than acting as an OAuth client of someone else.

The intended foundations are [`league/oauth2-server`](https://oauth2.thephpleague.com/) (protocol) and [`archipro/silverstripe-wellknown`](https://github.com/archiprocode/silverstripe-well-known) (discovery and JWKS). This installer does not require those packages yet; [ROADMAP.md](ROADMAP.md) is the scope and sequence, including the step that adds them.

ArchiPro already has outbound OAuth **clients** (for example Xero under `app/src/Component/Integration/Xero/` in [archipro-website](https://github.com/archiprocode/archipro-website)). Those use `league/oauth2-client` and are unrelated to this server. This app is where the shared **server** shape is tried before anything is installed on the website.

## Approach

Build the server here until the handshake quality gate in the roadmap passes. Then extract Composer package `archipro/silverstripe-oauth2-server` for other Silverstripe 5 hosts. [archipro-website](https://github.com/archiprocode/archipro-website) is the first intended consumer; how that host rebinds Injector ports is in the roadmap’s adoption section, not in this README.

## Compatibility

The sandbox runs PHP 8.3 and MySQL 8.0, the same majors as the website (CI image `mysql:8.0.32`, PHP 8.3). Packages extracted later must install on Silverstripe 5.

Do not change the website repo in this stage.

## Out of scope for now

- Provider-specific product flows
- A CMS login module (Opauth, Bigfork, and similar exist; we have not chosen one)
- Edits to archipro-website
- Outbound OAuth client helpers (`league/oauth2-client`)

## Start DDEV

From the repo root:

```sh
ddev start
ddev composer install
```

The site is at `https://cms5x-auth.ddev.site`. `vendor/` is gitignored, so Composer install is required after a fresh clone.

If `.env` is missing, copy `.env.example` and set `SS_DATABASE_SERVER`, `SS_DATABASE_USERNAME`, `SS_DATABASE_PASSWORD`, and `SS_DATABASE_NAME` to `db` (DDEV’s database service). Then:

```sh
ddev sake dev/build
```

## Tests

`ddev test` runs the Default PHPUnit suite (`app/tests` only). Extra arguments are forwarded:

```sh
ddev test
ddev test app/tests/SmokeTest.php
```

## Quality tools

Same tools as CI, local via DDEV (or `ddev composer …`):

```sh
ddev phpstan
ddev phpcs
ddev check
```

`ddev check` runs PHPStan, PHPCS, then PHPUnit. Agents: see `.cursor/rules/quality-ci.mdc`. CI: `.github/workflows/ci.yml`.
