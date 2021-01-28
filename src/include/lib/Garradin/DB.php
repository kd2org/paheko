<?php

namespace Garradin;

use KD2\DB\SQLite3;

class DB extends SQLite3
{
    /**
     * Application ID pour SQLite
     * @link https://www.sqlite.org/pragma.html#pragma_application_id
     */
    const APPID = 0x5da2d811;

    static protected $_instance = null;

    protected $_version = -1;

    static public function getInstance($create = false, $readonly = false)
    {
        if (null === self::$_instance) {
            self::$_instance = new DB('sqlite', ['file' => DB_FILE]);
        }

        return self::$_instance;
    }

    private function __clone()
    {
        // Désactiver le clonage, car on ne veut qu'une seule instance
    }

    public function connect(): void
    {
        if (null !== $this->db) {
            return;
        }

        parent::connect();

        // Activer les contraintes des foreign keys
        $this->db->exec('PRAGMA foreign_keys = ON;');

        // 10 secondes
        $this->db->busyTimeout(10 * 1000);

        // Performance enhancement
        // see https://www.cs.utexas.edu/~jaya/slides/apsys17-sqlite-slides.pdf
        // https://ericdraken.com/sqlite-performance-testing/
        $this->exec(sprintf('PRAGMA journal_mode = WAL; PRAGMA synchronous = NORMAL; PRAGMA journal_size_limit = %d;', 32 * 1024 * 1024));

        $this->db->createFunction('transliterate_to_ascii', ['Garradin\Utils', 'transliterateToAscii']);
    }

    public function version(): ?string
    {
        if (-1 === $this->_version) {
            $this->connect();
            $v = (int) $this->db->querySingle('PRAGMA user_version;');
            $v = self::parseVersion($v);

            if (null === $v) {
                // For legacy version before 1.1.0
                $v = $this->db->querySingle('SELECT valeur FROM config WHERE cle = \'version\';');
            }

            $this->_version = $v ?: null;
        }

        return $this->_version;
    }

    static public function parseVersion(int $v): ?string
    {
        if ($v > 0) {
            $major = intval($v / 1000000);
            $v -= $major * 1000000;
            $minor = intval($v / 10000);
            $v -= $minor * 10000;
            $release = intval($v / 100);
            $v -= $release * 100;
            $type = $v;

            if ($type == 0) {
                $type = '';
            }
            elseif ($type > 50) {
                $type = '-rc' . ($type - 50);
            }
            else {
                $type = '-beta' . $type;
            }

            $v = sprintf('%d.%d.%d%s', $major, $minor, $release, $type);
        }

        return $v ?: null;
    }

    /**
     * Save version to database
     * Only rc and beta strings are allowed, others will throw an error
     * @param string $version Version string, eg. 1.2.3-rc2
     */
    public function setVersion(string $version): void
    {
        if (!preg_match('/^(\d+)\.(\d+)\.(\d+)(?:-(beta|rc)(\d+))?$/', $version, $match)) {
            throw new \InvalidArgumentException('Invalid version number: ' . $version);
        }

        $version = ($match[1] * 100 * 100 * 100) + ($match[2] * 100 * 100) + ($match[3] * 100);

        if (isset($match[4]) && $match[4] == 'beta') {
            $version += $match[5];
        }
        elseif (isset($match[4]) && $match[4] == 'rc') {
            $version += $match[5] + 50;
        }

        $this->db->exec(sprintf('PRAGMA user_version = %d;', $version));
    }

    public function close(): void
    {
        parent::close();
        self::$_instance = null;
    }

    public function beginSchemaUpdate()
    {
        $this->toggleForeignKeys(false);
        $this->begin();
    }

    public function commitSchemaUpdate()
    {
        $this->commit();
        $this->toggleForeignKeys(true);
    }

    public function lastErrorMsg()
    {
        return $this->db->lastErrorMsg();
    }

    /**
     * @see https://www.sqlite.org/lang_altertable.html
     */
    public function toggleForeignKeys($enable)
    {
        assert(is_bool($enable));

        if (!$enable) {
            $this->db->exec('PRAGMA legacy_alter_table = ON;');
            $this->db->exec('PRAGMA foreign_keys = OFF;');
        }
        else {
            $this->db->exec('PRAGMA legacy_alter_table = OFF;');
            $this->db->exec('PRAGMA foreign_keys = ON;');
        }
    }
}
