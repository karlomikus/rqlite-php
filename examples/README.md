# Examples

A small command line application that demonstrates the [rqlite PHP client](../README.md).

It connects to a single rqlite node, shows the server status, creates a `tasks`
table, inserts rows in a transaction, streams query results, uses positional
and named parameters, mixes reads and writes through the unified
`/db/request` endpoint, and shows statement error handling.

## Running with Docker

The example targets PHP 8.4 and uses its own compose file with an rqlite
service:

```bash
docker compose -f examples/docker-compose.yml build
docker compose -f examples/docker-compose.yml run --rm php composer install
docker compose -f examples/docker-compose.yml run --rm php php index.php
```

The first `run` starts rqlite and waits for it to become healthy. The compose
file publishes rqlite on `localhost:4101`, so you can also explore it from the
host with the rqlite CLI or browser.

## Running without Docker

Requires PHP 8.4 with a running rqlite node:

```bash
cd examples
composer install
RQLITE_URL=http://localhost:4101 php index.php
```

## Example output

```text
Connected to rqlite at http://rqlite:4001
Server version: 8.x

Schema is ready.
Inserted task #1 (1 row(s) affected)
Inserted task #2 (1 row(s) affected)
Inserted task #3 (1 row(s) affected)

Open tasks:
  [1] Write the rqlite PHP example (done: 0)
  [2] Try the streaming results (done: 0)
  [3] Ship it (done: 0)
  (query took 0.000123s)
Total server time: 0.000456s

Fetched by named parameter: #1 Write the rqlite PHP example
Request endpoint inserted task #4
Request endpoint row: [4] Added via /db/request

Caught expected statement error: UNIQUE constraint failed: tasks.id
  statement index: 0
  sqlite error code: 19

Done.
```
