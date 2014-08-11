<?php

class PdoSqliteSessionHandler implements \SessionHandlerInterface {
    private $pdo, $dsn, $table;

    public static $dbFilename = 'php_session.sqlite.db';

    public static $dbOptions = [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_NUM,
        \PDO::ATTR_PERSISTENT => true,
        \PDO::ATTR_MAX_COLUMN_LEN => 32,
        \PDO::ATTR_EMULATE_PREPARES => false,
        \PDO::ATTR_CASE => \PDO::CASE_LOWER,
        \PDO::ATTR_CURSOR => \PDO::CURSOR_FWDONLY,
        # \PDO::ATTR_AUTOCOMMIT => false,
    ];

    public static $dbInitCommands = [
        'PRAGMA encoding="UTF-8";',
        'PRAGMA auto_vacuum=FULL;',
        'PRAGMA locking_mode=EXCLUSIVE;',
        'PRAGMA synchronous=FULL;',
        'PRAGMA temp_store=MEMORY;',
        'PRAGMA secure_delete=1;',
        'PRAGMA writable_schema=0;',
    ];

    /**
     * Re-initialize existing session, or creates a new one.
     * Called when a session starts or when session_start() is invoked.
     *
     * @param string $savePath The path where to store/retrieve the session.
     * @param string $name The session name.
     */
    public function open($savePath, $sessionName) {
        if (!is_null($this->pdo)) {
            throw new \BadMethodCallException('Bad call to open(): connection already opened.');
        }

        if (!ctype_alnum($sessionName)) {
            throw new \InvalidArgumentException('Invalid session name. Must be alphanumeric.');
        }

        if (false === realpath($savePath)) {
            mkdir($savePath, 0700, true);
        }

        if (!is_dir($savePath) || !is_writable($savePath)) {
            throw new \InvalidArgumentException('Invalid session save path.');
        }

        //$this->dsn = 'sqlite:'.$savePath.DIRECTORY_SEPARATOR.static::$dbFilename;
        $this->dsn = 'sqlite:'.__DIR__.'/php_session.sqlite.db';

        $this->pdo = new \PDO($this->dsn, NULL, NULL, static::$dbOptions);
        $this->table = '"'.strtolower($sessionName).'"';

        foreach (static::$dbInitCommands as $cmd) {
            $this->pdo->exec(str_replace('{{TABLE}}', $this->table, $cmd));
        }

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS {$this->table} (
                id TEXT PRIMARY KEY NOT NULL,
                data TEXT CHECK (TYPEOF(data) = 'text') NOT NULL DEFAULT '',
                time INTEGER CHECK (TYPEOF(time) = 'integer') NOT NULL
            ) WITHOUT ROWID;"
        ); # time DEFAULT (strftime('%s', 'now'))

        return true;
    }

    /**
     * Closes the current session.
     */
    public function close() {
        $this->pdo = null;
        return true;
    }

    /**
     * Returns an encoded string of the read data.
     * If nothing was read, it must return an empty string.
     * This value is returned internally to PHP for processing.
     *
     * @param string $id The session id.
     *
     * @return string
     */
    public function read($id) {
        $sql = "SELECT data FROM {$this->table} WHERE id = :id LIMIT 1";
        $sth = $this->getDb()->prepare($sql);
        $sth->bindParam(':id', $id, \PDO::PARAM_STR);
        $sth->execute();
        $rows = $sth->fetchAll(\PDO::FETCH_NUM);
        return $rows ? base64_decode($rows[0][0]) : '';
    }

    /**
     * Writes the session data to the session storage.
     *
     * Called by session_write_close(),
     * when session_register_shutdown() fails,
     * or during a normal shutdown.
     *
     * close() is called immediately after this function.
     *
     * @param string $id The session id.
     * @param string $data The encoded session data.
     *
     * @return boolean
     */
    public function write($id, $data) {
        $sql = "REPLACE INTO {$this->table} (id, data, time) VALUES (:id, :data, :time)";
        $sth = $this->getDb()->prepare($sql);
        $sth->bindParam(':id', $id, \PDO::PARAM_STR);
        $sth->bindValue(':data', base64_encode($data), \PDO::PARAM_STR);
        $sth->bindValue(':time', time(), \PDO::PARAM_INT);
        return $sth->execute();
    }

    /**
     * Destroys a session.
     *
     * Called by session_regenerate_id() (with $destroy = TRUE),
     * session_destroy() and when session_decode() fails.
     *
     * @param string $id The session ID being destroyed.
     *
     * @return boolean
     */
    public function destroy($id) {
        $sql = "DELETE FROM {$this->table} WHERE id = :id";
        $sth = $this->getDb()->prepare($sql);
        $sth->bindParam(':id', $id, \PDO::PARAM_STR);
        return $sth->execute();
    }

    /**
     * Cleans up expired sessions.
     * Called by session_start(), based on session.gc_divisor,
     * session.gc_probability and session.gc_lifetime settings.
     *
     * @param string $lifetime Sessions that have not updated for
     *     the last `$lifetime` seconds will be removed.
     *
     * @return boolean
     */
    public function gc($lifetime) {
        $sql = "DELETE FROM {$this->table} WHERE time < :time";
        $sth = $this->getDb()->prepare($sql);
        $sth->bindValue(':time', time() - $lifetime, \PDO::PARAM_INT);
        return $sth->execute();
    }

    public function getDb() {
        return $this->pdo;
    }

    public function getDsn() {
        return $this->dsn;
    }

    public function getTable() {
        return trim($this->table, '"');
    }

    public static function register($dbFilename = '') {
        $status = session_status();

        if (PHP_SESSION_ACTIVE === $status) {
            throw new \LogicException('A session is already open.');
        }

        if (PHP_SESSION_DISABLED === $status) {
            throw new \LogicException('PHP sessions are disabled.');
        }

        $handler = new static();

        if ($dbFilename) {
            static::$dbFileName = $dbFilename;
        }

        session_set_save_handler($handler);
        session_register_shutdown();

        return $handler;
    }
}
