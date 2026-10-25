<?php

use Kami\Rqlite\Adapters\Curl;
use Kami\Rqlite\ExecuteOptions;
use Kami\Rqlite\QueryOptions;
use Kami\Rqlite\Rqlite;
use Kami\Rqlite\Result\AssociativeQueryResult;
use Kami\Rqlite\Result\ExecuteResult;
use Kami\Rqlite\Result\Results;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

// pest()->extend(Tests\TestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Execute a .sql fixture against the given rqlite client.
 *
 * Fixtures live in tests/Fixtures and may contain multiple SQL statements.
 */
function load_fixture(Rqlite $rqlite, string $fixture): void
{
    $path = __DIR__ . '/Fixtures/' . ltrim($fixture, '/');

    if (!is_file($path)) {
        throw new InvalidArgumentException("Fixture [{$fixture}] does not exist.");
    }

    $sql = file_get_contents($path);

    if ($sql === false) {
        throw new RuntimeException("Unable to read fixture [{$fixture}].");
    }

    foreach ($rqlite->execute([$sql]) as $_) {
        // Consume the result stream so statement errors are surfaced.
    }
}

/**
 * Create a client for the integration cluster.
 */
function integration_client(): Rqlite
{
    return new Rqlite(new Curl(getenv('RQLITE_URL') ?: 'http://myrqlite-service-1:4001'));
}

/**
 * Generate a unique table name so integration runs do not collide.
 */
function integration_table(string $prefix): string
{
    return sprintf('go_parity_%s_%s', $prefix, bin2hex(random_bytes(6)));
}

/**
 * Run statements through execute() and collect all results.
 *
 * @param array<mixed> $statements
 * @return list<ExecuteResult>
 */
function integration_execute(Rqlite $rqlite, array $statements, ?ExecuteOptions $options = null): array
{
    return array_values(iterator_to_array($rqlite->execute($statements, $options)->results));
}

/**
 * Run queries through query() and collect the results and their rows.
 *
 * @param array<mixed> $statements
 * @return array{
 *     results: Results<AssociativeQueryResult>,
 *     items: list<AssociativeQueryResult>,
 *     rows: list<list<array<string, mixed>>>
 * }
 */
function integration_query(Rqlite $rqlite, array $statements, ?QueryOptions $options = null): array
{
    $results = $rqlite->query($statements, $options);
    $items = [];
    $rows = [];

    foreach ($results as $result) {
        $items[] = $result;
        $rows[] = iterator_to_array($result->rows);
    }

    return ['results' => $results, 'items' => $items, 'rows' => $rows];
}

/**
 * Run queries and return the rows from every result.
 *
 * @param array<mixed> $statements
 * @return list<array<string, mixed>>
 */
function integration_rows(Rqlite $rqlite, array $statements, ?QueryOptions $options = null): array
{
    $rows = [];

    foreach (integration_query($rqlite, $statements, $options)['rows'] as $resultRows) {
        foreach ($resultRows as $row) {
            $rows[] = $row;
        }
    }

    return $rows;
}

/**
 * Drop tables, ignoring failures so cleanup never masks a test failure.
 */
function integration_drop_tables(Rqlite $rqlite, string ...$tables): void
{
    foreach ($tables as $table) {
        try {
            integration_execute($rqlite, [sprintf('DROP TABLE IF EXISTS %s', $table)]);
        } catch (Throwable) {
            // Best-effort cleanup.
        }
    }
}
