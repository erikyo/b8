<?php

// SPDX-FileCopyrightText: 2006-2022 Tobias Leupold <tl at stonemx dot de>
//
// SPDX-License-Identifier: LGPL-3.0-or-later

/**
 * Abstract base class for storage backends
 *
 * @package B8
 */

declare(strict_types=1);

namespace B8\storage;

use B8\B8;
use Exception;

abstract class StorageBase
{
    /**
     * @var \B8\degenerator\Standard|\B8\degenerator\Enhanced
     */
    protected object $degenerator;

    /**
     * Check if the table is initialized
     *
     * @access public
     */
    abstract public function isInitialized(): bool;

    /**
     * Check if the table is up to date
     *
     * @access public
     */
    abstract public function isUpToDate(): bool;

    /**
     * Initialize the table
     *
     * @access protected
     */
    abstract protected function initialize(): bool;

    /**
     * Sets up the backend
     *
     * @access protected
     * @param array $config The configuration for the respective backend
     *
     * @throws Exception If the backend setup fails
     */
    abstract protected function setupBackend(array $config);

    /**
     * Does the actual interaction with the database when fetching data
     *
     * @access protected
     * @param array $tokens List of token names to fetch
     * @return array Returns an array of the returned data in the format array(token => data)
     *                or an empty array if there was no data.
     */
    abstract protected function fetchTokenData(array $tokens): array;

    /**
     * @access protected
     * @param string $token The token's name
     * @param array $count The ham and spam counters [ \B8\B8::KEY_COUNT_HAM => int,
                                                       \B8\B8::KEY_COUNT_SPAM => int ]
     * @return bool true on success or false on failure
     */
    abstract protected function addToken(string $token, array $count): bool;

    /**
     * Updates an existing token
     *
     * @access protected
     * @param string $token The token's name
     * @param array $count The ham and spam counters [ \B8\B8::KEY_COUNT_HAM => int,
                                                       \B8\B8::KEY_COUNT_SPAM => int ]
     * @return bool true on success or false on failure
     */
    abstract protected function updateToken(string $token, array $count): bool;

    /**
     * Removes a token from the database
     *
     * @access protected
     * @param string $token The token's name
     * @return bool true on success or false on failure
     */
    abstract protected function deleteToken(string $token): bool;

    /**
     * Starts a transaction (if the underlying database supports/needs this)
     *
     * @access protected
     * @return void
     */
    abstract protected function startTransaction(): void;

    /**
     * Finishes a transaction (if the underlying database supports/needs this)
     *
     * @access protected
     * @return void
     */
    abstract protected function finishTransaction(): void;

    /**
     * Passes the degenerator to the instance and calls the backend setup
     *
     * @access public
     *
     * @param $config array The respective backen's configuration
     * @param $degenerator object The degenerator to use
     *
     * @throws Exception If the connected database is not a B8 v
     * @return void
     */
    public function __construct(array $config, object $degenerator)
    {
        $this->degenerator = $degenerator;

        $this->setupBackend($config);

        if (!$this->isInitialized()) {
            if (!$this->initialize()) {
                if (!$this->isUpToDate()) {
                    throw new Exception(StorageBase::class . ': Unable  v' . (string) B8::DBVERSION . ' database.');
                }
            }
        }

        $internals = $this->getInternals();
        if ($internals[B8::KEY_DB_VERSION] !== B8::DBVERSION) {
            throw new Exception(
                StorageBase::class . ': The connected database is not a B8 v' . (string) B8::DBVERSION . ' database.'
            );
        }
    }

    /**
     * Get the database's internal variables.
     *
     * @access public
     * @return array Returns an array of all internals.
     */
    public function getInternals(): array
    {
        $internals = $this->fetchTokenData([B8::INTERNALS_TEXTS, B8::INTERNALS_DBVERSION]);

        // Just in case this is called by check_database() and it's not yet clear if we actually
        // have a B8 database
        $texts_ham = null;
        $texts_spam = null;
        $dbversion = null;
        if (isset($internals[B8::INTERNALS_TEXTS][B8::KEY_COUNT_HAM])) {
            $texts_ham = (int) $internals[B8::INTERNALS_TEXTS][B8::KEY_COUNT_HAM];
        }
        if (isset($internals[B8::INTERNALS_TEXTS][B8::KEY_COUNT_SPAM])) {
            $texts_spam = (int) $internals[B8::INTERNALS_TEXTS][B8::KEY_COUNT_SPAM];
        }
        if (isset($internals[B8::INTERNALS_DBVERSION][B8::KEY_COUNT_HAM])) {
            $dbversion = (int) $internals[B8::INTERNALS_DBVERSION][B8::KEY_COUNT_HAM];
        }

        return [
            B8::KEY_TEXTS_HAM => $texts_ham,
            B8::KEY_TEXTS_SPAM => $texts_spam,
            B8::KEY_DB_VERSION => $dbversion
        ];
    }

    /**
     * Get all data about a list of tokens from the database.
     *
     * @access public
     * @param $tokens array The token list
     * @return array Returns False on failure, otherwise returns array of returned data
               in the format [ 'tokens' => [ token => count ],
                               'degenerates' => [ token => [ degenerate => count ] ] ].
     */
    public function get(array $tokens): array
    {
        // First, we see what we have in the database
        $token_data = $this->fetchTokenData($tokens);

        // Check if we have to degenerate some tokens
        $missing_tokens = array();
        foreach ($tokens as $token) {
            if (!isset($token_data[$token])) {
                $missing_tokens[] = $token;
            }
        }

        if (count($missing_tokens) > 0) {
            // We have to degenerate some tokens
            $degenerates_list = [];

            // Generate a list of degenerated tokens for the missing tokens ...
            $degenerates = $this->degenerator->degenerate($missing_tokens);

            // ... and look them up
            foreach ($degenerates as $token => $token_degenerates) {
                $degenerates_list = array_merge($degenerates_list, $token_degenerates);
            }

            $token_data = array_merge($token_data, $this->fetchTokenData($degenerates_list));
        }

        // Here, we have all available data in $token_data.

        $return_data_tokens = [];
        $return_data_degenerates = [];

        foreach ($tokens as $token) {
            if (isset($token_data[$token])) {
                // The token was found in the database
                $return_data_tokens[$token] = $token_data[$token];
            } else {
                // The token was not found, so we look if we can return data for degenerated tokens
                $degenerates = $this->degenerator->degenerates[$token];
                foreach ($degenerates as $degenerate) {
                    if (isset($token_data[$degenerate])) {
                        // A degenerated version of the token way found in the database
                        $return_data_degenerates[$token][$degenerate] = $token_data[$degenerate];
                    }
                }
            }
        }

        // Now, all token data directly found in the database is in $return_data_tokens  and all
        // data for degenerated versions is in $return_data_degenerates, so
        return [
            'tokens' => $return_data_tokens,
            'degenerates' => $return_data_degenerates
        ];
    }

    public function set(string $token, ?int $val1 = null, ?int $val2 = null): void
    {
        $count = [B8::KEY_COUNT_HAM => $val1, B8::KEY_COUNT_SPAM => $val2];
        $data = $this->fetchTokenData([$token]);
        if (isset($data[$token])) {
            $this->updateToken($token, $count);
        } else {
            $this->addToken($token, $count);
        }
    }

    public function fetchOne(string $token): array
    {
        return $this->fetchTokenData([$token]);
    }

    /**
     * Stores or deletes a list of tokens from the given category.
     *
     * @access public
     * @param $tokens array The token list
     * @param $category string Either \B8\B8::HAM or \B8\B8::SPAM
     * @param $action string Either \B8\B8::LEARN or \B8\B8::UNLEARN
     * @return void
     */
    public function processText(array $tokens, string $category, string $action): void
    {
        // No matter what we do, we first have to check what data we have.

        // First get the internals, including the ham texts and spam texts counter
        $internals = $this->getInternals();
        // Then, fetch all data for all tokens we have
        $token_data = $this->fetchTokenData(array_keys($tokens));

        $this->startTransaction();

        // Process all tokens to learn/unlearn
        foreach ($tokens as $token => $count) {
            if (isset($token_data[$token])) {
                // We already have this token, so update it's data

                // Get the existing data
                $count_ham = $token_data[$token][B8::KEY_COUNT_HAM];
                $count_spam = $token_data[$token][B8::KEY_COUNT_SPAM];

                // Increase or decrease the right counter
                if ($action === B8::LEARN) {
                    if ($category === B8::HAM) {
                        $count_ham += $count;
                    } elseif ($category === B8::SPAM) {
                        $count_spam += $count;
                    }
                } elseif ($action == B8::UNLEARN) {
                    if ($category === B8::HAM) {
                        $count_ham -= $count;
                    } elseif ($category === B8::SPAM) {
                        $count_spam -= $count;
                    }
                }

                // We don't want to have negative values
                if ($count_ham < 0) {
                    $count_ham = 0;
                }
                if ($count_spam < 0) {
                    $count_spam = 0;
                }

                // Now let's see if we have to update or delete the token
                if ($count_ham != 0 or $count_spam != 0) {
                    $this->updateToken($token, [
                        B8::KEY_COUNT_HAM => $count_ham,
                        B8::KEY_COUNT_SPAM => $count_spam
                    ]);
                } else {
                    $this->deleteToken($token);
                }
            } else {
                // We don't have the token. If we unlearn a text, we can't delete it as we don't
                // have it anyway, so just do something if we learn a text
                if ($action === B8::LEARN) {
                    if ($category === B8::HAM) {
                        $this->addToken($token, [
                            B8::KEY_COUNT_HAM => $count,
                            B8::KEY_COUNT_SPAM => 0
                        ]);
                    } elseif ($category === B8::SPAM) {
                        $this->addToken($token, [
                            B8::KEY_COUNT_HAM => 0,
                            B8::KEY_COUNT_SPAM => $count
                        ]);
                    }
                }
            }
        }

        // Now, all tokens have been processed, so let's update the right text
        if ($action === B8::LEARN) {
            if ($category === B8::HAM) {
                $internals[B8::KEY_TEXTS_HAM]++;
            } elseif ($category === B8::SPAM) {
                $internals[B8::KEY_TEXTS_SPAM]++;
            }
        } elseif ($action === B8::UNLEARN) {
            if ($category === B8::HAM) {
                if ($internals[B8::KEY_TEXTS_HAM] > 0) {
                    $internals[B8::KEY_TEXTS_HAM]--;
                }
            } elseif ($category === B8::SPAM) {
                if ($internals[B8::KEY_TEXTS_SPAM] > 0) {
                    $internals[B8::KEY_TEXTS_SPAM]--;
                }
            }
        }

        $this->updateToken(
            B8::INTERNALS_TEXTS,
            [
                B8::KEY_COUNT_HAM => $internals[B8::KEY_TEXTS_HAM],
                B8::KEY_COUNT_SPAM => $internals[B8::KEY_TEXTS_SPAM]
            ]
        );

        $this->finishTransaction();
    }
}
