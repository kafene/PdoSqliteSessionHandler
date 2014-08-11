PdoSqliteSessionHandler
====================

PHP Session Handler using a SQLite Database

```php
require_once "PdoSqliteSessionHandler.php";

PdoSqliteSessionHandler::register();
session_start();

var_dump($_SESSION, session_save_path());

$_SESSION["color"] = "blue";
$_SESSION["animal"] = "elephant";

// Reload the page and the variables should be set in the var_dump.
```
