SqliteSessionHandler
====================

PHP Session Handler using a SQLite Database

```php
$savePath = rtrim(session_save_path(), DIRECTORY_SEPARATOR);
$dbFile = $savePath.DIRECTORY_SEPARATOR."php_session.db";
$pdo = new \PDO("sqlite:".$dbFile);
$table = "php_session";
$handler = new kafene\SqliteSessionHandler($pdo, $table);
session_set_save_handler($handler, true);
```
