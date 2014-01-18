SqliteSessionHandler
====================

PHP Session Handler using a SQLite Database

```php
$handler = new kafene\SqliteSessionHandler("sessions.db");

session_set_save_handler($handler, true);
session_start();

var_dump($_SESSION, session_save_path());

$_SESSION["color"] = "blue";
$_SESSION["animal"] = "elephant";

// Reload the page and the variables should be set in the var_dump.
```
