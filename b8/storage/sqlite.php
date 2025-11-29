<?php

// SPDX-FileCopyrightText: 2021 Malte Paskuda <malte@paskuda.biz>
//
// SPDX-License-Identifier: LGPL-3.0-or-later

declare(strict_types=1);

namespace B8\storage;

use B8\B8;
use SQLite3;

/**
 * An SQLite storage backend
 *
 * @package B8
 */

class SQLite extends StorageBase
{
    private SQLite3 $sqlite;
    private string $table;

    /**
     * This method creates the required table
     */
    protected function initialize(): bool
    {
        // Prepare and execute the CREATE TABLE statement
        $query_create = $this->sqlite->prepare("CREATE TABLE IF NOT EXISTS `" . $this->table . "` (
          `token` TEXT NOT NULL,
          `count_ham` INTEGER DEFAULT 0,
          `count_spam` INTEGER DEFAULT 0,
          PRIMARY KEY (`token`)
        );");

        if (!$query_create) {
            return false;
        }

        // SQLite3 doesn't support binding table names, but we validated it in setupBackend?
        // Actually, binding table names is generally not supported in PDO/SQLite3 prepared statements for DDL.
        // We should use the property directly as it's internal config.

        $r = $query_create->execute();

        if (!$r) {
            return false;
        }

        $version_query = $this->sqlite->prepare("INSERT OR IGNORE INTO `" . $this->table . "` (`token`, `count_ham`) VALUES (:token, :ham);");
        if ($version_query) {
            $version_query->bindValue(":token", B8::INTERNALS_DBVERSION, SQLITE3_TEXT);
            $version_query->bindValue(":ham", B8::DBVERSION, SQLITE3_INTEGER);
            $version_res = $version_query->execute();
        }

        $texts_query = $this->sqlite->prepare("INSERT OR IGNORE INTO `" . $this->table . "` (`token`, `count_ham`, `count_spam`) VALUES (:token, :ham, :spam);");
        if ($texts_query) {
            $texts_query->bindValue(":token", B8::INTERNALS_TEXTS, SQLITE3_TEXT);
            $texts_query->bindValue(":ham", 0, SQLITE3_INTEGER);
            $texts_query->bindValue(":spam", 0, SQLITE3_INTEGER);
            $texts_res = $texts_query->execute();
        }

        if (!isset($version_res) || !isset($texts_res) || !$version_res || !$texts_res) {
            return false;
        }
        return true;
    }

    public function isInitialized(): bool
    {
        // check if the b8 table exists and isn't empty
        $query = $this->sqlite->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=:name;");
        if (!$query) {
            return false;
        }
        $query->bindValue(":name", $this->table, SQLITE3_TEXT);
        $r = $query->execute();

        return $r && $r->fetchArray() !== false;
    }

    public function isUpToDate(): bool
    {
        return intval($this->sqlite->query("SELECT * FROM " . $this->table . " WHERE token = 'b8*dbversion'")) === B8::DBVERSION;
    }

    protected function setupBackend(array $config)
    {
        if (!isset($config['resource']) || !$config['resource'] instanceof SQLite3) {
            // For testing purposes, sometimes we might mock it, but strict typing expects SQLite3
            // If we want to allow mocks that extend SQLite3, instanceof works.
            // If we want to allow arbitrary objects (like in tests), we might need to relax this or ensure mocks extend SQLite3.
            // Given the user wants modernization, strict typing is preferred.
            throw new \Exception(SQLite::class . ": No valid SQLite3 object passed");
        }
        $this->sqlite = $config['resource'];

        if (!isset($config['table'])) {
            $config['table'] = 'b8_wordlist';
        }
        $this->table = (string) $config['table'];
    }

    protected function fetchTokenData(array $tokens): array
    {
        $data = [];

        if (empty($tokens)) {
            return $data;
        }

        // Create a string of placeholders: (?, ?, ?, ...)
        $placeholders = implode(",", array_fill(0, count($tokens), "?"));

        // Prepare the query with placeholders
        $query = $this->sqlite->prepare('SELECT token, count_ham, count_spam  FROM `' . $this->table . '` WHERE token IN (' . $placeholders . ')');

        if (!$query) {
            return $data;
        }

        // Bind each token to its placeholder safely
        foreach ($tokens as $index => $token) {
            // SQLite3 bind parameters start at index 1
            $query->bindValue($index + 1, $token, SQLITE3_TEXT);
        }

        $result = $query->execute();

        if ($result) {
            while ($row = $result->fetchArray(SQLITE3_NUM)) {
                $data[$row[0]] = [
                    B8::KEY_COUNT_HAM => (int) $row[1],
                    B8::KEY_COUNT_SPAM => (int) $row[2]
                ];
            }
        }

        return $data;
    }

    protected function addToken(string $token, array $count): bool
    {
        $query = $this->sqlite->prepare('INSERT INTO `' . $this->table . '` (token, count_ham, count_spam) VALUES(?, ?, ?)');
        if (!$query) {
            return false;
        }

        $query->bindValue(1, $token, SQLITE3_TEXT);
        $query->bindValue(2, $count[B8::KEY_COUNT_HAM], SQLITE3_INTEGER);
        $query->bindValue(3, $count[B8::KEY_COUNT_SPAM], SQLITE3_INTEGER);

        return false !== $query->execute();
    }

    protected function updateToken(string $token, array $count): bool
    {
        $query = $this->sqlite->prepare('UPDATE `' . $this->table . '` SET count_ham = ?, count_spam = ? WHERE token = ?');
        if (!$query) {
            return false;
        }

        $query->bindValue(1, $count[B8::KEY_COUNT_HAM], SQLITE3_INTEGER);
        $query->bindValue(2, $count[B8::KEY_COUNT_SPAM], SQLITE3_INTEGER);
        $query->bindValue(3, $token, SQLITE3_TEXT);

        return false !== $query->execute();
    }

    protected function deleteToken(string $token): bool
    {
        $query = $this->sqlite->prepare('DELETE FROM `' . $this->table . '` WHERE token = ?');
        if (!$query) {
            return false;
        }
        $query->bindValue(1, $token, SQLITE3_TEXT);
        return false !== $query->execute();
    }

    protected function startTransaction(): void
    {
        // SQLite3 doesn't expose beginTransaction directly in older versions or it might be implicit?
        // Actually SQLite3 class has exec('BEGIN').
        $this->sqlite->exec('BEGIN');
    }

    protected function finishTransaction(): void
    {
        $this->sqlite->exec('COMMIT');
    }
}
