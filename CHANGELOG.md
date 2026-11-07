# Unreleased
- **Breaking:** `Rqlite` now accepts a base URL, PSR-18 client, and PSR-17 request and stream factories. Removed the custom HTTP adapters and `Http\Request`.
- **Breaking:** `backup()` returns the original PSR-7 stream; `Http\Body` has been removed. Use `getContents()` instead of `contents()`.
- **Breaking:** `HttpException::$response` is a PSR-7 response; `Http\Response` has been removed. Use PSR-7 methods to inspect responses.
- `LoggerAdapter` now decorates a PSR-18 client and logs metadata without reading request bodies.
- Removed the Curl and File adapters.
- Removed the `ext-curl` suggestion from `composer.json`
- Changed `Request::$stream`, `load()`, and `boot()` to accept PSR-7 `StreamInterface` in addition to stream resources and strings
- Added `psr/http-client` and `psr/http-factory` to the required dependencies

# 0.2.1
- Fixed null handling in JsonStream

# 0.2.0
- Added `QueryOptions`, `ExecuteOptions`, and `RequestOptions`, replacing the positional `query()` and `execute()` arguments
- Added support for the `pretty`, `timings`, `blob_array`, `level`, `timeout`, `linearizable_timeout`, `freshness`, `freshness_strict`, `raft_index`, and `errors` query and request options
- Added execute options for `transaction`, `pretty`, `timings`, `queue`, `wait`, `timeout`, `db_timeout`, `raft_index`, and `errors`
- Added the unified `/db/request` endpoint via `request()` and `Result\RequestResult`
- Removed the meaningless `associative` parameter from execute requests
- Added `ErrorFormat` and `ReadConsistencyLevel::Linearizable`
- Changed query timings to be disabled by default
- Changed HTTP adapter interface
- Added custom exceptions
- Added support for error_v2
- Added PHPStan generics to query results and result streams
- Changed HTTP responses to stream response bodies
- Changed query and execute results to generators
- Query rows are now streamed per result
- Added streaming support to the Curl, Guzzle, and File adapters

# 0.1.0
- Inital release
