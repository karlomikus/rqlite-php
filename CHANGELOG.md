# Unreleased
- Removed the Curl and File adapters. The Guzzle adapter is now the only built-in HTTP adapter
- Removed the `ext-curl` suggestion from `composer.json`
- Changed `Http\Body` to implement `Psr\Http\Message\StreamInterface`, so response and backup bodies interoperate with PSR-7 aware libraries
- Changed `Request::$stream`, `load()`, and `boot()` to accept PSR-7 `StreamInterface` in addition to stream resources and strings
- Changed the Guzzle adapter to a PSR-18 adapter (`Adapters\Psr18`) that works with any PSR-18 client and PSR-17 factories
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
