SqliteSessionHandler
====================

PHP Session Handler using a SQLite Database

```php
$handler = new kafene\SqliteSessionHandler("sessions.db");
session_set_save_handler($handler, true);
```
