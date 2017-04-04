# phlib/db-helper

[![Build Status](https://img.shields.io/travis/phlib/db-helper/master.svg?style=flat-square)](https://travis-ci.org/phlib/db-helper)
[![Codecov](https://img.shields.io/codecov/c/github/phlib/db-helper.svg?style=flat-square)](https://codecov.io/gh/phlib/db-helper)
[![Latest Stable Version](https://img.shields.io/packagist/v/phlib/db-helper.svg?style=flat-square)](https://packagist.org/packages/phlib/db-helper)
[![Total Downloads](https://img.shields.io/packagist/dt/phlib/db-helper.svg?style=flat-square)](https://packagist.org/packages/phlib/db-helper)
![Licence](https://img.shields.io/github/license/phlib/db-helper.svg?style=flat-square)

DB helpers to complement phlib/db

## Installation

```php
composer require phlib/db-helper
```

## Usage

```php
// Get an Adapter
$config = [
    'host' => 'localhost',
    'username' => 'myuser',
    'password' => 'mypassword',
    'dbname' => 'mydatabase'
];
$db = new \Phlib\Db\Adapter($config);
```

### BulkInsert

Insert and/or update many rows into a table.

This increases the write performance for adding large numbers (eg. thousands+)
of rows, over the typical pseudo-prepared statements used by native PDO.

```php
$insertFields = [
    'product_id',
    'product_name',
    'product_qty'
];
$updateFields = [
    'product_name',
    'product_qty'
];
$bulkInsert = new BulkInsert($adapter, 'product', $insertFields, $updateFields);

// Many calls to add() will write to the DB in batches
$bulkInsert->add($singleProductData);

// One final manual call to write() to complete
$bulkInsert->write();
```

### QueryPlanner

Test the number of rows that a `SELECT` statement will query.

```php
$queryPlanner = new QueryPlanner($adapter, $sqlSelect);
$queryPlanner->getNumberOfRowsInspected(); // eg. 46234
```

### BigResult

Run a `SELECT` statement which is expected to be slow (eg. >5s) due to quantity
of data.

Query buffering is disabled to reduce the time to first row and avoid consuming
PHP's memory for the statement result, and MySQL's query timeout is increased.

```php
$bigResult = new BigResult($adapter);
$pdoStmt = $bigResult->query($sqlSelect, $bind);
```

Optionally, prevent very large queries from running by using the QueryPlanner
inspection:

```php
$bigResult = new BigResult($adapter);
$queryRowLimit = 20000000;
$pdoStmt = $bigResult->query($sqlSelect, $bind, $queryRowLimit);
```

### Replication

The Replication helper monitors slave lag, which it stores in Memcache. This
known lag can then be used to throttle long-running processes by introducing
variable amounts of sleep.

Set up slave monitoring using the CLI script (you might consider using Monit to
run this automatically):

```sh
./vendor/bin/db replication:monitor -c path/to/config.php -p /var/run/db-replication.pid -d start
```

```php
$config = require 'path/to/config.php';
$replication = Replication::createFromConfig($config);

while ([...]) {
    [... some repetitive iteration, like writing thousands of records ...]
    
    $replication->throttle();
}
```

Your config file might look something like this:

```php
<?php
$config = [
    // master
    'host'     => '10.0.0.1',
    'username' => 'foo',
    'password' => 'bar',
    'slaves'   => [
        [
            'host'     => '10.0.0.2',
            'username' => 'foo',
            'password' => 'bar'
        ]
    ],
    'storage' => [
        'class' => \Phlib\DbHelper\Replication\Memcache::class,
        'args'  => [[]]
    ],
];

return $config;
```

## License

This package is free software: you can redistribute it and/or modify
it under the terms of the GNU Lesser General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU Lesser General Public License for more details.

You should have received a copy of the GNU Lesser General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
