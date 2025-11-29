<?php

// SPDX-FileCopyrightText: 2006-2022 Tobias Leupold <tl at stonemx dot de>
//
// SPDX-License-Identifier: LGPL-3.0-or-later

declare(strict_types=1);

namespace B8\storage;

use B8\B8;

/**
 * A Berkeley DB (DBA) storage backend
 *
 * @package B8
 */

class DBA extends StorageBase
{
    /**
     * @var resource|false
     */
    private $db;

    /**
     * We should create the table if it doesn't exist and fill it with the internals.
     *
     * @return bool True on success (the database was initialized), false otherwise
     */
    protected function initialize(): bool
    {
        // Use the existing connection
        if (!is_resource($this->db)) {
            return false;
        }

        // Storing the necessary internal variables.
        $internals = ['b8*dbversion' => '3', 'b8*texts' => '0 0'];
        foreach ($internals as $key => $value) {
            if (dba_insert($key, $value, $this->db) === false) {
                return false;
            }
        }
        return true;
    }

    public function isInitialized(): bool
    {
        // Trying to read data from the database
        if ($this->db) {
            return dba_fetch(B8::INTERNALS_DBVERSION, $this->db) !== false;
        }
        return false;
    }

    public function isUpToDate(): bool
    {
        return intval(dba_fetch(B8::INTERNALS_DBVERSION, $this->db)) === B8::DBVERSION;
    }


    protected function setupBackend(array $config)
    {
        if (
            !isset($config['resource'])
            || !is_resource($config['resource'])
            || get_resource_type($config['resource']) !== 'dba'
        ) {
            throw new \Exception(DBA::class . ": No valid DBA resource passed");
        }
        $this->db = $config['resource'];
    }

    protected function fetchTokenData(array $tokens): array
    {
        $data = [];

        foreach ($tokens as $token) {
            // Try to the raw data in the format "count_ham count_spam"
            $count = dba_fetch($token, $this->db);

            if ($count !== false) {
                // Split the data by space characters
                $split_data = explode(' ', $count);

                // As an internal variable may have just one single value, we have to check for this
                $count_ham = isset($split_data[0]) ? (int) $split_data[0] : null;
                $count_spam = isset($split_data[1]) ? (int) $split_data[1] : null;

                // Append the parsed data
                $data[$token] = [
                    B8::KEY_COUNT_HAM => $count_ham,
                    B8::KEY_COUNT_SPAM => $count_spam
                ];
            }
        }

        return $data;
    }

    private function assembleCountValue(array $count): string
    {
        // Assemble the count data string
        $count_value = $count[B8::KEY_COUNT_HAM] . ' ' . $count[B8::KEY_COUNT_SPAM];
        // Remove whitespace from data of the internal variables
        return rtrim($count_value);
    }

    protected function addToken(string $token, array $count): bool
    {
        return dba_insert($token, $this->assembleCountValue($count), $this->db);
    }

    protected function updateToken(string $token, array $count): bool
    {
        return dba_replace($token, $this->assembleCountValue($count), $this->db);
    }

    protected function deleteToken(string $token): bool
    {
        return dba_delete($token, $this->db);
    }

    protected function startTransaction(): void
    {
        if (function_exists('dba_sync')) {
            dba_sync($this->db);
        }
    }

    protected function finishTransaction(): void
    {
        return;
    }
}
