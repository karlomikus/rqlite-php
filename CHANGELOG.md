# Unreleased
- Added `QueryOptions` and changed `query()` to accept it instead of positional arguments
- Added support for the `pretty`, `timings`, `blob_array`, `level`, `timeout`, `linearizable_timeout`, `freshness`, `freshness_strict`, `raft_index`, and `errors` query options
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
