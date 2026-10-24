# cms5x-auth

A small Silverstripe 5 app where we try a reusable OAuth2 authorization server before it is installed anywhere else. This host will issue tokens. It is not an OAuth client of another service.

Application code lives in `app/src/` under `Archipro\SilverstripeOAuth2\`. Installer `Page` and `PageController` stay in the global namespace.

Discovery routes come from [`archipro/silverstripe-wellknown`](https://github.com/archiprocode/silverstripe-well-known). The protocol library is [`league/oauth2-server`](https://oauth2.thephpleague.com/) **^8.5**. League v9 needs a newer PSR-7 than Silverstripe 5 allows, so this app stays on the newest League major that installs here.

Grants, clients, and extracting a Composer package later are in [ROADMAP.md](ROADMAP.md). This README covers keys, the JWKS, and settings only.

## Keys and the JWKS

### What it is for

`/.well-known/jwks.json` lists public keys so another app can check token signatures.

The default JWKS list includes the active signing pair, `RsaKeyPair`. That pair has a private key file and a public key file. The JWKS endpoint opens only the public file. Signing opens the private file on first use. Until the public path names a usable RSA file, the JWKS request fails with a configuration error.

Any further entries are older public keys, kept so tokens signed before a rotation still verify. Those are `RsaPublicJsonWebKey` services. They have no private key. A previous key is listed only when it exists.

### How to set it up

Key material stays in files under `.oauth/` (gitignored). `app/_config/oauth2.yml` names the environment variables that hold the paths, and lists `RsaKeyPair` in the JWKS by default.

`OAUTH2_PUBLIC_KEY_PATH` must name a usable RSA public PEM before `RsaKeyPair` can be constructed. An empty path, or a missing, unreadable, empty, or non-RSA public file, throws `OAuth2ConfigurationException` at construction. The JWKS endpoint constructs that pair, so it fails the same way until the public file exists.

`OAUTH2_PRIVATE_KEY_PATH` must name a usable private PEM before the first `getPrivateKey()` call (token signing). An empty path, a missing or unreadable file, a public-only PEM on the private path, or a wrong passphrase throws `OAuth2ConfigurationException` then. League’s CryptKey would accept a public PEM on that path; this app refuses that fallback.

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
# After the public key path points at a usable file, flush so it is picked up:
curl -s "https://cms5-oauth2.ddev.site/.well-known/jwks.json?flush=1"
# one RSA key under "keys" (kty, alg, use, kid, n, e)
```

To publish an older public key as well, register another `RsaPublicJsonWebKey` and add it beside the active pair. The backtick name is one the host chooses. It is not a variable this app reads by default.

A previous key is listed only when it exists. An empty or bad path fails configuration rather than dropping the active key.

```yaml
Archipro\SilverstripeOAuth2\Discovery\RsaPublicJsonWebKey.previous:
  constructor:
    public_key_path: '`OAUTH2_PREVIOUS_PUBLIC_KEY_PATH`'

Archipro\SilverstripeWellKnown\Providers\JsonWebKeySetProvider:
  constructor:
    keys:
      - '%$Archipro\SilverstripeOAuth2\Discovery\RsaKeyPair'
      - '%$Archipro\SilverstripeOAuth2\Discovery\RsaPublicJsonWebKey.previous'
```

To change what the JWKS publishes, override the `keys` list on `JsonWebKeySetProvider`. The default already includes the active pair.

### Which type to replace

Rebind `Archipro\SilverstripeOAuth2\Discovery\RsaKeyPair` to a subclass when the active pair should not come from the env paths. Override `createPublicKey()` and `createPrivateKey()`, and still call the parent constructor. The public hook runs in the constructor; the private hook runs on the first `getPrivateKey()` call.

`RsaPublicJsonWebKey` is open as well. Rebind a named service, such as `RsaPublicJsonWebKey.previous`, when an older public key should not come from a PEM file. The subclass overrides `loadFromPath()`, calls `loadFromPem()` with the key bytes, and still calls the parent constructor.

Rebind `Archipro\SilverstripeWellKnown\Providers\JsonWebKeySetProvider` when the published document should come from another class. The default is `JsonWebKeySetProvider` with the active pair in its `keys` list.

Adding an older public key to that list, as in the setup above, does not replace a type.

## Settings

### What it is for

Settings hold the encryption key, the issuer, the URL prefix, and the access-token and refresh-token lifetimes. They also keep the active signing key from the keys chapter. A host that cannot use the env defaults rebinds one interface. That implementation returns a `SigningKey`. The default is `RsaKeyPair`, which is also the key the JWKS publishes.

### How to set it up

`OAUTH2_ENCRYPTION_KEY` and `OAUTH2_ISSUER` are backtick names on the settings service in `app/_config/oauth2.yml`. Generate the encryption key with `ddev exec vendor/bin/generate-defuse-key`. The issuer is an absolute URL, such as `https://cms5-oauth2.ddev.site`.

These three keep PHP defaults when the variable is unset. They are not backticks, because an unset backtick would wipe the default:

| Variable | Default | Accepted value |
|----------|---------|----------------|
| `OAUTH2_PATH_PREFIX` | `/oauth` | Non-empty path starting with `/` |
| `OAUTH2_ACCESS_TOKEN_TTL` | `PT1H` | `DateInterval` string, such as `PT1H` |
| `OAUTH2_REFRESH_TOKEN_TTL` | `P1M` | `DateInterval` string, such as `P1M` |

A bad or missing public key fails when `RsaKeyPair` is constructed. Settings and the JWKS share that service, so the first resolve of either path surfaces a public-key error. A bad private key fails on the first `getPrivateKey()` call. `assertValid()` separately checks the encryption key, the issuer, the path prefix, and both lifetimes. Tests get key paths, encryption key, and issuer from `phpunit.xml.dist`. Boot does not call `assertValid()`.

### Which type to replace

Rebind `Archipro\SilverstripeOAuth2\Settings\OAuth2SettingsInterface`. The default class is `EnvironmentOAuth2Settings`.

## Start DDEV

From the repo root:

```sh
ddev start
ddev composer install
```

The site is at `https://cms5-oauth2.ddev.site`. `vendor/` is gitignored, so Composer install is required after a fresh clone.

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
