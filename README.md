# MySQL Handler

A simple class that handles MySQL database instances with support for both TCP and Socket connections.  I created this class as a standard way to interact with MySQL databases from within other libraries with very low overhead, but it's perfectly capable of being used as a standalone handler.

## Getting Started

### Prerequisites

You will need to have the following environment variables present (usually done with a .env file):

```dotenv
MYSQL_HOST="localhost"
MYSQL_DATABASE="manevia_db"
MYSQL_USER="root"
MYSQL_PASSWORD="secretPassword"
MYSQL_SOCKET="NULL"
```

Depending on how you load environment variables, you may be able to reference already defined variables when setting the required environment variables above.  This helps prevent having to maintain values in multiple locations.  For example:

```dotenv
DATABASE_HOST="localhost"
DATABASE_DATABASE="manevia_db"
DATABASE_USER="roo"
DATABASE_PASSWORD="secretPassword"
DATABASE_SOCKET="NULL"

MYSQL_HOST="${DATABASE_HOST}"
MYSQL_DATABASE="${DATABASE_NAME}"
MYSQL_USER="${DATABASE_USER}"
MYSQL_PASSWORD="${DATABASE_PASSWORD}"
MYSQL_SOCKET="${DATABASE_SOCKET}"
```
### Installing

##### Via Composer

Run the following command in the same directory as your composer.json file:

`composer require jabarihunt/mysql`

##### Via Github

1. Clone this repository into a working directory: `git clone git@github.com:jabarihunt/mysql.git`

2. Include or require the MySQL class in your project...

```php
require('/path/to/cloned/directory/src/MySQL.php');
```
...or if using an auto-loader...

```php
 use jabarihunt/MySQL;
```

## Usage

This class does not require instantiation since it uses the singleton design pattern for connections.  You may simply begin using the available public methods.  If none of the methods are called, a database connection is never created.  You may alias the class as another name using `use/as`, as demonstrated below: 

```php
use jabarihunt/MySQL as DB;

/*
 * QUERY THE DATABASE WITH A PREPARED STATEMENT (RECOMMENDED)
 * 
 * prepare($query, $paramValues, $paramTypeString = NULL):
 * CONVENIENCE METHOD THAT RETURNS ARRAY OF DATA FOR QUERIES THAT RETURN A RESULT SET, THE NUMBER OF AFFECTED ROWS FOR ALL
 * OTHER QUERIES, OR FALSE ON ERROR.  $paramTypeString IS OPTIONAL, ALL VALUES WILL BE SENT AS STRINGS IF NOT PROVIDED.
 */

    $data = DB::prepare('SELECT name, email FROM users WHERE age > ? AND status = ? AND days_active >= ?', [357, 'retired', 30], 'isi');

/*
 * QUERY THE DATABASE AS A STANDARD QUERY
 * 
 * query($query):
 * CONVENIENCE METHOD THAT RETURNS AN ARRAY OF DATA FOR QUERIES THAT RETURN A RESULT SET, TRUE ON SUCCESS, OR FALSE ON ERROR.
 */

    $data = DB::query("SELECT name, email FROM users WHERE age > 69 AND status = 'retired' AND days_active >= 30");

/*
 * SANITIZE VALUES WHEN NOT USING PREPARED STATEMENTS
 * THIS METHOD USES PHP filter_var() SANITIZATION BASED ON THE DATA TYPE
 */

    $string = 'Am I a good string or a naughty string?';

    $string = DB::sanitize($string, DB::DATA_TYPE_INTEGER);     // All characters removed since sanitizing as an int data type!
    $string = DB::sanitize($string);                            // String remains since method defaults to DB::DATA_TYPE_TEXT
    $string = DB::sanitize(NULL);                               // Null values are converted to an empty string before sanitizing

    // CLASS DATA TYPES ARE BASED ON MYSQL DATA TYPES.  DATA TYPES ARE DECLARED IN THE CLASS AS SHOWN BELOW:

        const DATA_TYPE_INTEGER  = ['tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'bit'];
        const DATA_TYPE_REAL     = ['float', 'double', 'decimal'];
        const DATA_TYPE_TEXT     = ['char', 'varchar', 'tinytext', 'text', 'mediumtext', 'longtext'];
        const DATA_TYPE_BINARY   = ['binary', 'varbinary', 'blob', 'tinyblob', 'mediumblob', 'longblob'];
        const DATA_TYPE_TEMPORAL = ['date', 'time', 'year', 'datetime', 'timestamp'];
        const DATA_TYPE_SPATIAL  = ['point', 'linestring', 'polygon', 'geometry', 'multipoint', 'multilinestring', 'multipolygon', 'geometrycollection'];
        const DATA_TYPE_OTHER    = ['enum', 'set'];

/*
 * GET MySQLi OBJECT
 */

    $mysqlObject = DB::getMySQLObject();

/*
 * MYSQL DUMP -> CONVENIENCE METHOD FOR DUMPING A DATABASE SOMEWHERE ON THE MACHINE | USE WITH CAUTION!!!
 * CREATES MYSQL DUMP FILE AT GIVEN PATH WITH FORMAT: <database>_<year>-<month>-<day>_<unix timestamp>.sql
 */

    DB::backup('path/to/my/backup/folder');
```

## Contributing

1. Fork Repository
2. Create a descriptive branch name
3. Make edits to your branch
4. Squash (rebase) your commits
5. Create a pull request

## License

This project is licensed under the MIT License - see the [LICENSE.md](LICENSE.md) file for details
