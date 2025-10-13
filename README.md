# std2sql(1)

## NAME

std2sql — execute a SQL statement once per input line with optional placeholder binding

## SYNOPSIS

`std2sql.php` **-s** *SQL* [**-H** *host*] [**-P** *port*] [**-d** *dbname*] [**-x** *"extra options"*]

## DESCRIPTION

Reads stdin line by line and runs one SQL statement per line.
If the SQL contains `$1`, the current line is bound as the first parameter.
Authentication is via Kerberos/GSSAPI through libpq. No username or password is used.

The program connects using the provided options and `-x` extras, then:

1. Prepares the statement when `$1` is present.
2. For each input line: trims the trailing newline, executes the statement, and frees the result.
3. Exits non-zero on the first error.

## OPTIONS

**-s, --sql** *SQL*
: Required. SQL to execute. May include `$1` for the line payload.

**-H, --host** *host*
: PostgreSQL host. Defaults to `PGHOST` if unset.

**-P, --port** *port*
: PostgreSQL port. Defaults to `PGPORT` if unset.

**-d, --dbname** *dbname*
: Database name. Defaults to `PGDATABASE` if unset.

**-x, --extra** *"extra options"*
: Extra connection options appended to the libpq connection string. Example: `sslmode=require application_name=myapp`.

## INPUT

Reads UTF-8 text from stdin. Each non-empty line is processed independently.
Trailing `\n` or `\r\n` is removed before execution.

## CONNECTION

Builds a libpq connection string from options and environment.
Sets `application_name=StdinSql` unless overridden via **-x**.

Kerberos/GSSAPI is used if PostgreSQL is configured accordingly. Ensure valid Kerberos credentials (e.g., `kinit`) before running.

## ENVIRONMENT

**PGHOST**
: Default host if **-H** not supplied.

**PGPORT**
: Default port if **-P** not supplied.

**PGDATABASE**
: Default database if **-d** not supplied.

Standard libpq variables (e.g., `PGSSLMODE`, `PGSERVICE`, `PGTZ`) also apply.

## EXIT STATUS

**0** on success.
**1** on error (connection failure, query error, I/O error).
**2** on usage error (missing **-s**).

## EXAMPLES

Insert each line as JSON into a table:

```sh
cat data.ndjson | php std2sql.php -s 'INSERT INTO logs(payload) VALUES ($1::jsonb)' -d appdb
```

Call a function for each line:

```sh
awk '{print $1}' ids.txt | php std2sql.php -s 'SELECT process_id($1::bigint)' -d ops
```

Run a constant statement once per line (no binding):

```sh
yes | head -n 100 | php std2sql.php -s 'SELECT pg_sleep(0)' -d bench
```

Connect with extras:

```sh
cat rows.txt | php std2sql.php \
  -s 'INSERT INTO inbox(raw) VALUES ($1::text)' \
  -H db.internal -P 5432 -d ingest \
  -x 'sslmode=require application_name=std2sql'
```

## DIAGNOSTICS

Errors are written to stderr in the form:

```
[ERROR] <message>
```

Failures include connection errors, prepare/execute errors, and stdin read errors.

## SECURITY

* Input lines are passed via parameter binding when `$1` is used, preventing SQL injection in that mode.
* When no placeholder is present, the SQL is executed verbatim; avoid concatenating untrusted data into such statements.
* Kerberos tickets must exist and be valid at runtime.

## NOTES

* The program frees each result immediately to bound memory usage on large streams.
* libpq server-side prepared statements are created only when `$1` is present.
* Lines consisting solely of a newline become empty strings if bound.

## SEE ALSO

`psql(1)`, `libpq(3)`, PostgreSQL documentation on GSSAPI and connection parameters.

# AUTHOR

Written by Simon Brennecke.

# COPYRIGHT

Copyright (C) 2025 Simon Brennecke, licensed under GNU GPL version 3 or later.
