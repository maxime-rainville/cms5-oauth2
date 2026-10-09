# cms5x-auth

A small Silverstripe 5 app where we try a reusable OAuth2 authorization server before it is installed anywhere else. This host will issue tokens. It is not an OAuth client of another service.

Application code lives in `app/src/` under `Archipro\SilverstripeOAuth2\`. Installer `Page` and `PageController` stay in the global namespace.

Discovery routes come from [`archipro/silverstripe-wellknown`](https://github.com/archiprocode/silverstripe-well-known). The protocol library is [`league/oauth2-server`](https://oauth2.thephpleague.com/) **^8.5**. League v9 needs a newer PSR-7 than Silverstripe 5 allows, so this app stays on the newest League major that installs here.

Grants, clients, and extracting a Composer package later are in [ROADMAP.md](ROADMAP.md). This README covers keys, the JWKS, and settings only.

## Keys and the JWKS

### What it is for

`/.well-known/jwks.json` lists public keys so another app can check token signatures.

One entry is the active signing pair, `RsaKeyPair`. It has a private key file and a public key file. Any further entries are older public keys, kept so tokens signed before a rotation still verify. Those are `RsaPublicJsonWebKey` services. They have no private key.

Serving the JWKS does not open the private key file. With no public key configured, the document is `{"keys":[]}`.

### How to set it up

Key material stays in files under `.oauth/` (gitignored). `app/_config/oauth2.yml` names the environment variables that hold the paths.

An empty or unset public path is left out of the JWKS. A public path that is set, but missing, unreadable, empty, or not an RSA public key, throws `OAuth2ConfigurationException` and the request fails. The private path is not opened for the JWKS. A missing or unusable private key fails when `assertValid()` runs, or when a caller asks the pair for the private key.

```sh
mkdir -p .oauth
openssl genrsa -out .oauth/private.key 2048
openssl rsa -in .oauth/private.key -pubout -out .oauth/public.key
chmod 600 .oauth/private.key
```

Set these in `.env` (names match `.env.example`). Relative paths are resolved from the project root.

| Variable | Role |
|----------|------|
| `OAUTH2_PRIVATE_KEY_PATH` | Private PEM for the active pair |
| `OAUTH2_PUBLIC_KEY_PATH` | Public PEM for the active pair |
| `OAUTH2_PRIVATE_KEY_PASSPHRASE` | Optional. Empty means the private key has no passphrase |

```sh
curl -s https://cms5x-auth.ddev.site/.well-known/jwks.json
# {"keys": []}

# After you set a public key path, flush so the new file is picked up:
curl -s "https://cms5x-auth.ddev.site/.well-known/jwks.json?flush=1"
# one RSA key under "keys" (kty, alg, use, kid, n, e)
```

To publish an older public key as well, register another `RsaPublicJsonWebKey` and add it beside the active pair. The backtick name is one the host chooses. It is not a variable this app reads by default. An empty path is skipped. A bad path fails the request rather than dropping the active key.

```yaml
Archipro\SilverstripeOAuth2\Discovery\RsaPublicJsonWebKey.previous:
  constructor:
    public_key_path: '`OAUTH2_PREVIOUS_PUBLIC_KEY_PATH`'

Archipro\SilverstripeWellKnown\Providers\JsonWebKeySetProvider:
  class: Archipro\SilverstripeOAuth2\Discovery\PublishedJsonWebKeySetProvider
  constructor:
    -
      - '%$Archipro\SilverstripeOAuth2\Discovery\RsaKeyPair'
      - '%$Archipro\SilverstripeOAuth2\Discovery\RsaPublicJsonWebKey.previous'
```

### Which type to replace

Rebind `Archipro\SilverstripeWellKnown\Providers\JsonWebKeySetProvider` when the published document should not be built by `PublishedJsonWebKeySetProvider`. That default class drops keys that have nothing to publish. Adding an older public key, as in the setup above, does not replace a type. `RsaKeyPair` is final and is configured with env paths.

## Settings

### What it is for

Settings hold the encryption key, the issuer, the URL prefix, and the access-token and refresh-token lifetimes. They also keep the active signing pair from the keys chapter, so validation can ask for the private key. A host that cannot use the env defaults rebinds one interface. That implementation still returns an `RsaKeyPair`.

### How to set it up

`OAUTH2_ENCRYPTION_KEY` and `OAUTH2_ISSUER` are backtick names on the settings service in `app/_config/oauth2.yml`. Generate the encryption key with `ddev exec vendor/bin/generate-defuse-key`. The issuer is an absolute URL, such as `https://cms5x-auth.ddev.site`.

These three keep PHP defaults when the variable is unset. They are not backticks, because an unset backtick would wipe the default:

| Variable | Default | Accepted value |
|----------|---------|----------------|
| `OAUTH2_PATH_PREFIX` | `/oauth` | Non-empty path starting with `/` |
| `OAUTH2_ACCESS_TOKEN_TTL` | `PT1H` | `DateInterval` string, such as `PT1H` |
| `OAUTH2_REFRESH_TOKEN_TTL` | `P1M` | `DateInterval` string, such as `P1M` |

`assertValid()` checks the private key, the encryption key, the issuer, the path prefix, and both lifetimes. It does not read the public key file. CI still boots with no key files because constructing the pair leaves an empty public path unpublished. Boot does not call `assertValid()`.

### Which type to replace

Rebind `Archipro\SilverstripeOAuth2\Settings\OAuth2SettingsInterface`. The default class is `EnvironmentOAuth2Settings`.

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

`ddev test` runs PHPUnit for `app/tests` only. Extra arguments are forwarded:

```sh
ddev test
ddev test app/tests/SmokeTest.php
```

## Quality tools

Same tools as CI, local via DDEV:

```sh
ddev phpstan
ddev phpcs
ddev check
```

`ddev check` runs PHPStan, PHPCS, then PHPUnit. See `.cursor/rules/quality-ci.mdc` and `.github/workflows/ci.yml`.
