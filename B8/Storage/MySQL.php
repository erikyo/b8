<?php

// SPDX-FileCopyrightText: 2009 Oliver Lillie <ollie@buggedcom.co.uk>
// SPDX-FileCopyrightText: 2006-2021 Tobias Leupold <tl at stonemx dot de>
//
// SPDX-License-Identifier: LGPL-3.0-or-later

declare(strict_types=1);

namespace B8\Storage;

use B8\B8;
use Exception;
use mysqli;

/**
 * A MySQL storage backend
 *
 * @package B8
 */

class MySQL extends StorageBase
{
    private mysqli $mysql;
    private string $table;

    /**
     * We should create the table if it doesn't exist and fill it with the internals.
     *
     * @return bool True on success (table created and initialized), false on the other cases
     */
    protected function initialize(): bool
    {
        // Create the table if it doesn't exist
        $r = $this->mysql->query("CREATE TABLE IF NOT EXISTS `" . $this->table . "` (
            `token` varchar(190) character set utf8mb4 collate utf8mb4_bin NOT NULL,
            `count_ham` int unsigned default NULL,
            `count_spam` int unsigned default NULL,
            PRIMARY KEY (`token`)
        ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;");

        if ($r === false) {
            return false;
        }

        // If the table was created, then insert the internals
        $stmt = $this->mysql->prepare(
            "INSERT INTO " . $this->table . " (`token`, `count_ham`) VALUES ('b8*dbversion', '3')"
        );
        if ($stmt) {
            $stmt->execute();
        }

        $stmt = $this->mysql->prepare(
            "INSERT INTO " . $this->table . " (`token`, `count_ham`, `count_spam`) VALUES ('b8*texts', '0', '0');"
        );
        if ($stmt) {
            $stmt->execute();
        }

        return true;
    }

    public function isInitialized(): bool
    {
        try {
            $result = $this->mysql->query("SELECT * FROM " . $this->table . " LIMIT 1");
            return $result !== false;
        } catch (Exception $e) {
            return false;
        }
    }

    public function isUpToDate(): bool
    {
        return intval($this->mysql->query(
            "SELECT * FROM " . $this->table . " WHERE token = 'b8*dbversion'"
        )) === B8::DBVERSION;
    }

    protected function setupBackend(array $config)
    {
        if (
            !isset($config['resource'])
            || !$config['resource'] instanceof mysqli
        ) {
            throw new Exception(MySQL::class . ": No valid mysqli object passed");
        }
        $this->mysql = $config['resource'];

        if (!isset($config['table'])) {
            throw new Exception(MySQL::class . ": No B8 wordlist table name passed");
        }
        $this->table = (string) $config['table'];
    }

    protected function fetchTokenData(array $tokens): array
    {
        $data = [];

        if (empty($tokens)) {
            return $data;
        }

        $escaped = [];
        foreach ($tokens as $token) {
            $escaped[] = $this->mysql->real_escape_string($token);
        }

        // prepared statements with parameter binding
        $placeholders = implode(',', array_fill(0, count($tokens), '?'));
        $query = "SELECT token, count_ham, count_spam FROM {$this->table} WHERE token IN ($placeholders)";
        $result = $this->mysql->query($query);

        if ($result) {
            while ($row = $result->fetch_row()) {
                $data[$row[0]] = [
                    B8::KEY_COUNT_HAM => (int) $row[1],
                    B8::KEY_COUNT_SPAM => (int) $row[2]
                ];
            }
            $result->free_result();
        }

        return $data;
    }

    protected function addToken(string $token, array $count): bool
    {
        $stmt = $this->mysql->prepare('INSERT INTO ' . $this->table . '(token, count_ham, count_spam) VALUES(?, ?, ?)');
        if (!$stmt) {
            return false;
        }

        $ham = (int) $count[B8::KEY_COUNT_HAM];
        $spam = (int) $count[B8::KEY_COUNT_SPAM];

        $stmt->bind_param('sii', $token, $ham, $spam);
        $result = $stmt->execute();
        $stmt->close();

        return $result;
    }

    protected function updateToken(string $token, array $count): bool
    {
        $stmt = $this->mysql->prepare('UPDATE ' . $this->table . ' SET count_ham = ?, count_spam = ? WHERE token = ?');
        if (!$stmt) {
            return false;
        }

        $ham = (int) $count[B8::KEY_COUNT_HAM];
        $spam = (int) $count[B8::KEY_COUNT_SPAM];

        $stmt->bind_param('iis', $ham, $spam, $token);
        $result = $stmt->execute();
        $stmt->close();

        return $result;
    }

    protected function deleteToken(string $token): bool
    {
        $stmt = $this->mysql->prepare('DELETE FROM ' . $this->table . ' WHERE token = ?');
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('s', $token);
        $result = $stmt->execute();
        $stmt->close();

        return $result;
    }

    protected function startTransaction(): void
    {
        $this->mysql->begin_transaction();
    }

    protected function finishTransaction(): void
    {
        $this->mysql->commit();
    }
}
