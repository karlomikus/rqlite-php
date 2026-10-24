# Unreleased
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
