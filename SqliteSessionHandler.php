<?php

namespace kafene;
use \PDO;

class SqliteSessionHandler implements \SessionHandlerInterface
{

    /**
     * @var PDO
     */
    protected $pdo;

    /**
     * @var string Database Table name.
     */
    protected $table;

    /**
     * @var string Session name.
     */
    protected $name;

    /**
     * Constructor
     *
     * @param string $dbFile PDO Database Filename
     */
    function __construct($dbFilename = "php_session.db")
    {
        $this->dbFilename = $dbFilename;
    }

    /**
     * Re-initialize existing session, or creates a new one.
     * Called when a session starts or when session_start() is invoked.
     *
     * @param string $savePath The path where to store/retrieve the session.
     * @param string $name The session name.
     */
    function open($save_path, $name)
    {
        $ds = DIRECTORY_SEPARATOR;
        $defaultName = ini_get("session.name") ?: "PHPSESSID";
        $name = $name ?: $defaultName;

        if (0 === strlen($name)) {
            throw new \Exception("Session name can not be 0-length.");
        }

        $save_path = rtrim(realpath($save_path), $ds);

        if (!is_writable($save_path)) {
            throw new \Exception("Session save path is not writable.");
        }

        if (!is_dir($save_path)) {
            throw new \Exception("Session save path is not a directory.");
        }

        $path = sprintf("sqlite:%s%s%s", $save_path, $ds, $this->dbFilename);
        $opts = array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION);

        $this->name = $name;
        $this->pdo = new PDO($path, null, null, $opts);
        $this->table = $this->pdo->quote($name) ?: $name;

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS {$this->table} (
                id TEXT PRIMARY KEY NOT NULL UNIQUE,
                time INTEGER NOT NULL DEFAULT (strftime('%s', 'now')),
                data TEXT NOT NULL DEFAULT ''
            );
        ");
    }

    /**
     * Closes the current session.
     */
    function close()
    {
        $this->pdo = null;

        return true;
    }

    /**
     * Returns an encoded string of the read data.
     * If nothing was read, it must return an empty string.
     *
     * Note: this value is returned internally to PHP for processing.
     *
     * @param string $sessionId The session id.
     *
     * @return string
     */
    function read($id)
    {
        $sql = "SELECT data FROM {$this->table} WHERE id = :id LIMIT 1";
        $st = $this->pdo->prepare($sql);
        $st->bindValue(":id", $id, PDO::PARAM_STR);
        $data = "";

        if ($st->execute() && ($r = $st->fetchAll(PDO::FETCH_NUM)) && isset($r[0], $r[0][0])) {
            $data = base64_decode($r[0][0]);
        }

        return $data;
    }

    /**
     * Writes the session data to the session storage.
     *
     * Called by session_write_close(),
     * when session_register_shutdown() fails,
     * or during a normal shutdown.
     *
     * Note: $this->close() is called immediately after this function.
     *
     * PHP will call this method when the session is ready to be saved
     * and closed. It encodes the session data from the $_SESSION superglobal
     * to a serialized string and passes this along with the session ID to
     * this method for storage. The serialization method used is specified in
     * the session.serialize_handler setting.
     *
     * Note: this method is normally called by PHP after the output buffers
     * have been closed unless explicitly called by session_write_close().
     *
     * @see http://stackoverflow.com/questions/418898/sqlite-upsert-not-insert-or-replace
     *
     * @param string $sessionId The session id.
     * @param string $sessionData The encoded session data.
     *     This data is the result of the PHP internally encoding the
     *     $_SESSION superglobal to a serialized string and passing it
     *     as this parameter. Please note sessions use an alternative
     *     serialization method.
     *
     * @return boolean
     */
    function write($id, $data)
    {
        $time = time();
        $data = base64_encode($data);

        $sql = "INSERT OR IGNORE INTO {$this->table} (id, time, data) VALUES (:id, :time, :data)";
        $st = $this->pdo->prepare($sql);
        $st->bindValue(":id", $id, PDO::PARAM_STR);
        $st->bindValue(":time", $time, PDO::PARAM_INT);
        $st->bindValue(":data", $data, PDO::PARAM_STR);
        $st->execute();

        $sql = "UPDATE {$this->table} SET time = :time, data = :data WHERE id = :id";
        $st = $this->pdo->prepare($sql);
        $st->bindValue(":id", $id, PDO::PARAM_STR);
        $st->bindValue(":time", $time, PDO::PARAM_INT);
        $st->bindValue(":data", $data, PDO::PARAM_STR);
        $st->execute();

        return true;
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
    function destroy($id)
    {
        $sql = "DELETE FROM {$this->table} WHERE id = :id";
        $st = $this->pdo->prepare($sql);
        $st->bindValue(":id", $id, PDO::PARAM_STR);
        $st->execute();

        $name = session_name();
        if (isset($_COOKIE[$this->name]) && !headers_sent()) {
            $_COOKIE[$this->name] = null;
            $GLOBALS['_COOKIE'][$this->name] = null;
            $params = session_get_cookie_params();

            $path = isset($params["path"]) ? $params["path"] : null;
            $domain = isset($params["domain"]) ? $params["domain"] : null;
            $secure = isset($params["secure"]) ? $params["secure"] : null;
            $httponly = isset($params["httponly"]) ? $params["httponly"] : null;

            setcookie($this->name, "", 1, $path, $domain, $secure, $httponly);
        }

        return true;
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
    function gc($lifetime)
    {
        $sql = "DELETE FROM {$this->table} WHERE time < :time";
        $st = $this->pdo->prepare($sql);
        $st->bindValue(":time", time() - intval($lifetime), PDO::PARAM_INT);
        $st->execute();

        return true;
    }
}
