# contempt/config

[![CI](https://github.com/contempt-framework/config/actions/workflows/ci.yml/badge.svg)](https://github.com/contempt-framework/config/actions/workflows/ci.yml)

Typed configuration objects, layered configuration providers and deterministic precedence.

> This repository is a **read-only split** of the Contempt monorepo.
> Issues and pull requests belong in
> [contempt-framework/contempt](https://github.com/contempt-framework/contempt).

## Installation

```bash
composer require contempt/config
```

## Documentation

<https://contempt.lemric.com/docs>

## Environment bootstrap

`DotEnvBootstrap` uses `symfony/dotenv` at the process boundary and returns an
immutable environment snapshot. The default policy loads dotenv files only for
`dev` and `test`; production requires an explicit `Enabled` policy. Native
process variables keep precedence and `putenv()` is never enabled. Map the raw
strings with `EnvironmentConfigurationProvider`, then hydrate a declared typed
configuration object before injecting values into application services.

## License

MIT. See [LICENSE](LICENSE).
