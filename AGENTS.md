# AGENTS

- Run PHP via Docker only: `docker compose run --rm php COMMAND` (repo is mounted at `/app`). Install deps with `docker compose run --rm php composer install`.

## Checks

- Tests are Pest (not phpunit): `docker compose run --rm php vendor/bin/pest`. The default run excludes the `integration` group (`phpunit.xml`).
- Integration tests hit real rqlite nodes (helpers live in `tests/Pest.php`, SQL fixture in `tests/Fixtures/`):
  - Single node: `docker compose up -d --wait myrqlite-service-1` then `docker compose run --rm php vendor/bin/pest --group=integration --exclude-group=cluster`
  - Full cluster: start all three `myrqlite-service-*` nodes and run `--group=integration` (the replication test is tagged `cluster`).
- Outside Docker, integration tests default to the compose hostnames (`http://myrqlite-service-1:4001`); override with `RQLITE_URL`, `RQLITE_URL_2`, `RQLITE_URL_3`.
- CI (PHP 8.2–8.5 matrix) = unit tests + single-node integration tests + `vendor/bin/phpstan analyse` (level 8, `src/` only). Run PHPStan before finishing. Rector (`rector.php`, covers `src/` + `tests/`) is manual and not run in CI; there is no other formatter.
- If applicable document changes in `CHANGELOG.md` (keep it short, link to PRs/issues for details).

## Library layout

- Single composer library, `Kami\Rqlite\` → `src/`. Entrypoint is `Rqlite.php`; the rest is options objects, result objects, exceptions, and `Http/` plumbing.
- Transport goes through `HttpAdapter::send(): Http\Response`; `Http\Body` implements PSR-7 `StreamInterface`. Built-in adapters: `Adapters\PsrHttpAdapter` (PSR-18 client + PSR-17 factories) and `Adapters\LoggerAdapter` (decorator).

## rqlite client quirks (easy to break)

- Responses are parsed as streams: `query()`/`execute()`/`request()` return a single-pass `Results` iterator and rows are generators. Consume rows before advancing to the next result; never `iterator_to_array()` without consuming. Decoding and statement errors throw during iteration, not at call time.
- `Http\Request::query()` must omit `false` and `null` option values: rqlite treats the mere presence of boolean query flags (`timings`, `transaction`, `queue`, ...) as true, so a disabled flag must not be sent at all.
- New endpoint options go on `QueryOptions`/`ExecuteOptions`/`RequestOptions`, not as new method parameters.

## Examples

- `examples/` is a separate composer project with its own compose file: `docker compose -f examples/docker-compose.yml ...` (rqlite on localhost:4101).
