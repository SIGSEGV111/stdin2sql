#!/usr/bin/php
<?php
declare(strict_types=1);

/**
 * stdin2sql.php
 * Reads stdin line-by-line and executes the given SQL for each line.
 * If the SQL contains a $1 placeholder, the current line is bound as the parameter.
 * Kerberos/GSSAPI auth via libpq (no username/password).
 *
 * Examples:
 *  echo "hello" | php stdin2sql.php -s 'SELECT $1::text' -d mydb
 *  cat ids.txt | php stdin2sql.php --sql 'INSERT INTO logs(raw) VALUES($1::text)' -H dbhost -P 5432 -d mydb -x "sslmode=require application_name=stdin2sql.php"
 */

const APP_NAME = 'stdin2sql';
const EXIT_OK = 0;
const EXIT_USAGE = 2;
const EXIT_ERR = 1;

function parseArgs() : array
{
	$opts = getopt(
		's:H:P:d:x:',
		['sql:', 'host:', 'port:', 'dbname:', 'extra:']
	);

	$sql = $opts['s'] ?? $opts['sql'] ?? null;
	if ($sql === null || $sql === '')
	{
		fwrite(STDERR, "Usage: php stdin2sql.php -s '<SQL>' [-H host] [-P port] [-d dbname] [-x 'extra options']\n");
		exit(EXIT_USAGE);
	}

	$host = $opts['H'] ?? $opts['host'] ?? null;
	$port = $opts['P'] ?? $opts['port'] ?? null;
	$dbname = $opts['d'] ?? $opts['dbname'] ?? null;
	$extra = $opts['x'] ?? $opts['extra'] ?? '';

	return [$sql, $host, $port, $dbname, $extra];
}

function buildConnectionString(string|null $host, string|null $port, string|null $dbname, string $extra) : string
{
	$parts = [];

	if ($host !== null && $host !== '')
	{
		$parts[] = 'host=' . escapeshellarg($host);
	}
	if ($port !== null && $port !== '')
	{
		$parts[] = 'port=' . escapeshellarg($port);
	}
	if ($dbname !== null && $dbname !== '')
	{
		$parts[] = 'dbname=' . escapeshellarg($dbname);
	}

	// Always set application_name for traceability; allow user override in $extra.
	if (stripos($extra, 'application_name=') === false)
	{
		$parts[] = 'application_name=' . escapeshellarg(APP_NAME);
	}

	$extra = trim($extra);
	if ($extra !== '')
	{
		$parts[] = $extra;
	}

	return implode(' ', $parts);
}

function openDatabase(string $conn_str)
{
	$conn = @pg_connect($conn_str);
	if ($conn === false)
		throw new RuntimeException('Failed to connect to PostgreSQL: ' . pg_last_error());
	return $conn;
}

function prepareStatement($conn, string $sql) : string
{
	$name = 'stmt_' . md5($sql);
	$res = @pg_prepare($conn, $name, $sql);
	if ($res === false)
		throw new RuntimeException('Failed to prepare statement: ' . pg_last_error($conn));
	pg_free_result($res);
	return $name;
}

function countParameters($conn, string $stmt_name) : int
{
	$res = pg_query_params(
		$conn,
		"SELECT cardinality(parameter_types) AS n FROM pg_prepared_statements WHERE name = $1",
		[$stmt_name]
	);
	$row = pg_fetch_assoc($res);
	pg_free_result($res);
	return (int)$row['n'];
}

function executeForEachLine($conn, string $sql, string $stmt_name) : void
{
	$count_parameters = countParameters($conn, $stmt_name);
	if($count_parameters < 0 || $count_parameters > 1)
		throw new RuntimeException("SQL statement must have 0 or 1 parameter, but found $count_parameters");

	while (($line = fgets(STDIN)) !== false)
	{
		if($count_parameters)
		{
			$payload = rtrim($line, "\r\n");
			$res = @pg_execute($conn, $stmt_name, [$payload]);
		}
		else
		{
			$res = @pg_execute($conn, $stmt_name, []);
		}

		if ($res === false)
			throw new RuntimeException('Query failed: ' . pg_last_error($conn));

		while(($row = pg_fetch_row($res)) !== false)
			echo(json_encode($row) . "\n");

		pg_free_result($res);
	}

	if (!feof(STDIN))
		throw new RuntimeException('Error reading from STDIN.');
}

function main() : void
{
	[$sql, $host, $port, $dbname, $extra] = parseArgs();
	$conn_str = buildConnectionString($host, $port, $dbname, $extra);
	$conn = openDatabase($conn_str);

	try
	{
		$stmt_name = prepareStatement($conn, $sql);
		executeForEachLine($conn, $sql, $stmt_name);
	}
	finally
	{
		pg_close($conn);
	}
}

try
{
	main();
	exit(EXIT_OK);
}
catch (Throwable $e)
{
	fwrite(STDERR, "[ERROR] " . $e->getMessage() . "\n");
	exit(EXIT_ERR);
}
