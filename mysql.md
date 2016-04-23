# Quick example

``` php
// select a single record
$user = fem\mysql::select('row', "SELECT * FROM `users` WHERE `id` = %d;", [42]);

// update a record
$sql = "
	UPDATE `users`
	   SET `full_name` = '%s'
	 WHERE `id` = %d
;";
$binds = ['Zaphod Beeblebrox', 42];
fem\mysql::query($sql, $binds);
```

- [Usage](#usage)
  - [Connect](#connect)
  - [Select queries](#select-queries)
  - [Other queries](#other-queries)
- [Advanced](#advanced)
  - [Raw queries](#raw-queries)
  - [Multiple databases](#multiple-databases)
  - [Configure login](#configure-login)
  - [Error handling](#error-handling)



# Usage

All methods and properties are called static.


## Connect

Connect to the database using the login in `config/mysql.ini`.

See [Configure login](#configure-login) for details.

``` php
void connect()
```


## Select queries

Execute a `SELECT` statement and fetch the results.

Auto-escapes values if you supply them via the [`$binds`](#binds) argument.

``` php
mixed select(string $type, string $sql, array $binds=null)
```

#### `$type`

One of the constants:

- `AS_ARRAY`: multi dimensional array with an array for each record
- `AS_ROW`: single dimensional array for the first record
- `AS_STRING`: single value of the first column of the first record

#### `$sql`

The sql query to be executed.

Use a [sprintf](//php.net/manual/en/function.sprintf.php) formatted string to use automatic escaping.
I.e. use `'%s'` for adding strings and `%d` for integers and supply those, in order, via the `$binds` argument.

#### `$binds`

An array containing all user supplied values you want to escape before adding to the query.


## Other queries

Execute any statement.

Auto-escapes values if you supply them via the [`$binds`](#binds) argument.

**Note:** `query()` is protecting you from executing unsafe `UPDATE` and `DELETE` statements.
If they don't use a `WHERE` or `LIMIT` clause, they are blocked and an exception is thrown.

``` php
mysqli_result query(string $sql, array $binds=null)
```

#### `$sql`

See explanation of `$sql` at [Select queries](#sql).

#### `$binds`

See explanation of `$binds` at [Select queries](#binds).


## Properties

Use properties after execution to fetch meta data about the query.

#### `$num_rows`

The amount of rows returned for a `SELECT` statement.

This contains the full amount of rows, even if the `select()`'s [`$type`](#type) is set to `'row'` of `'field'`.

#### `$insert_id`

The last primary key for an `INSERT` statement.

#### `$affected_rows`

The amount of affected rows.


# Advanced


## Raw queries

Execute any statement.

**Note:** This method does **not** escapes values and is thus not safe for sql injection.

``` php
raw(string $sql)
```

#### `$sql`

See explanation of `$sql` at [Select queries](#sql).


## Multiple databases

You can switch between multiple databases while keeping the connection open.

Gives the mysqli connection object.
This gives the possibility to switch connections while kee

``` php
fem\mysql::connect();
$db1 = fem\mysql::get_connection_object();

fem\mysql::connect($config_for_db2);
$db2 = fem\mysql::get_connection_object();

fem\mysql::set_connection_object($db1);
fem\mysql::query($sql_for_db1);

fem\mysql::set_connection_object($db2);
fem\mysql::query($sql_for_db2);
```


## Configure login

By default login details are fetched from a `config/mysql.ini` file.

By extending the `fem\mysql` class you can use another way of fetching the login details.
Extend the `get_config()` method and return an array with the following keys:

- host
- user
- pass
- name
- port

``` php
array get_config();
```


## Error handling

When mysql returns an error `fem\mysql` throws an exception.
The `$message` and `$code` supplied to the exception are the mysql error message and error number.

If you however need access to those values yourself, you can fetch them via properties.

``` php
$number  = fem\mysql::$error_number;
$message = fem\mysql::$error_message;
```
