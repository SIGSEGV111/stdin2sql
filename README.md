# std2sql(1)

## NAME

std2sql — execute a SQL statement once per input line with optional placeholder binding

## SYNOPSIS

`stdin2sql.php` **-s** *SQL* [**-H** *host*] [**-P** *port*] [**-d** *dbname*] [**-x** *"extra options"*]

## DESCRIPTION

Reads stdin line by line and runs one SQL statement per line.
The current line is bound as the first parameter to the SQL statement.
Authentication is implicit and Kerberos/GSSAPI is supported.
If any authentication information is required it must be passed via *extra options* (`-x`) or environment.

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

Reads UTF-8 text from stdin. Each line is processed independently and immediatelly committed.
Trailing `\n` or `\r\n` is removed before execution.

## OUTPUT

* If the SQL returns rows, stdout receives one compact JSON **array per row**, one row per line.
* If the SQL returns no rows, nothing is printed.
* Values come from libpq’s text format. `stdin2sql.php` does not perform type decoding, so **all columns are emitted as JSON strings** regardless of their PostgreSQL types.
* all other text (errors, diagnostics) is written to stderr

## CONNECTION

Builds a libpq connection string from options and environment.
Sets `application_name=stdin2sql` unless overridden via **-x**.

Kerberos/GSSAPI is used if PostgreSQL is configured accordingly. Ensure valid Kerberos credentials (e.g., `kinit`) before running.

## ENVIRONMENT

Unless overwritten by command-line, all libpq environment variables apply.
See PostgreSQL documentation for details.

## EXIT STATUS

**0** on success.
**1** on error (connection failure, query error, I/O error).
**2** on usage error (e.g. missing **-s**).

## EXAMPLES

Insert each line as JSON into a table:

```sh
cat data.json | stdin2sql.php -s 'INSERT INTO data(payload) VALUES ($1::jsonb)' -d logdb
```

Connect with extras:

```sh
cat rows.txt | stdin2sql.php \
  -s 'INSERT INTO inbox(raw) VALUES ($1::text)' \
  -H db.internal -P 5432 -d ingest \
  -x 'sslmode=require application_name=whatever'
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

## SEE ALSO

`psql(1)`

# AUTHOR

Written by Simon Brennecke.

# COPYRIGHT

Copyright (C) 2025 Simon Brennecke, licensed under GNU GPL version 3 or later.
