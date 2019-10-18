<?php

/* Copyright (C) 2006-2019 Tobias Leupold <tobias.leupold@gmx.de>

   This file is part of the b8 package

   This program is free software; you can redistribute it and/or modify it
   under the terms of the GNU Lesser General Public License as published by
   the Free Software Foundation in version 2.1 of the License.

   This program is distributed in the hope that it will be useful, but
   WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
   or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Lesser General Public
   License for more details.

   You should have received a copy of the GNU Lesser General Public License
   along with this program; if not, write to the Free Software Foundation,
   Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307, USA.
*/

/**
 * Abstract base class for storage backends
 *
 * @license LGPL 2.1
 * @package b8
 * @author Tobias Leupold <tobias.leupold@gmx.de>
 */

namespace b8\storage;

abstract class storage_base
{
    const INTERNALS_TEXTS     = 'b8*texts';
    const INTERNALS_DBVERSION = 'b8*dbversion';

    protected $degenerator = null;

    /**
     * Sets up the backend
     *
     * @access public
     * @param array The configuration for the respective backend
     */
    abstract protected function setup_backend(array $config);

    /**
     * Does the actual interaction with the database when fetching data
     *
     * @access protected
     * @param array $tokens List of token names to fetch
     * @return mixed Returns an array of the returned data in the format array(token => data)
               or an empty array if there was no data.
     */
    abstract protected function fetch_token_data(array $tokens);

    /**
     * Stores a new token to the database
     *
     * @access protected
     * @param string $token The token's name
     * @param array $count The ham and spam counters [ 'count_ham' => int, 'count_spam' => int ]
     * @return bool true on success or false on failure
     */
    abstract protected function add_token(string $token, array $count);

    /**
     * Updates an existing token
     *
     * @access protected
     * @param string $token The token's name
     * @param array $count The ham and spam counters [ 'count_ham' => int, 'count_spam' => int ]
     * @return bool true on success or false on failure
     */
    abstract protected function update_token(string $token, array $count);

    /**
     * Removes a token from the database
     *
     * @access protected
     * @param string $token The token's name
     * @return bool true on success or false on failure
     */
    abstract protected function delete_token(string $token);

    /**
     * Starts a transaction (if the underlying database supports/needs this)
     *
     * @access protected
     * @return void
     */
    abstract protected function start_transaction();

    /**
     * Finishes a transaction (if the underlying database supports/needs this)
     *
     * @access protected
     * @return void
     */
    abstract protected function finish_transaction();

    /**
     * Passes the degenerator to the instance and calls the backend setup
     *
     * @access public
     * @param array The respective backen's configuration
     * @param object The degenerator to use
     * @return void
     */
    public function __construct(array $config, object $degenerator)
    {
        $this->degenerator = $degenerator;
        $this->setup_backend($config);

        $internals = $this->get_internals();
        if (! isset($internals['dbversion']) || $internals['dbversion'] !== \b8\b8::DBVERSION) {
            throw new Exception('b8_storage_base: The connected database is not a b8 v'
                                . \b8\b8::DBVERSION . ' database.');
        }
    }

    /**
     * Get the database's internal variables.
     *
     * @access public
     * @return array Returns an array of all internals.
     */
    public function get_internals()
    {
        $internals = $this->fetch_token_data([ self::INTERNALS_TEXTS,
                                               self::INTERNALS_DBVERSION ]);

        // Just in case this is called by check_database() and it's not yet clear if we actually
        // have a b8 database
        $texts_ham = null;
        $texts_spam = null;
        $dbversion = null;
        if(isset($internals[self::INTERNALS_TEXTS]['count_ham'])) {
            $texts_ham = (int) $internals[self::INTERNALS_TEXTS]['count_ham'];
        }
        if(isset($internals[self::INTERNALS_TEXTS]['count_spam'])) {
            $texts_spam = (int) $internals[self::INTERNALS_TEXTS]['count_spam'];
        }
        if(isset($internals[self::INTERNALS_DBVERSION]['count_ham'])) {
            $dbversion = (int) $internals[self::INTERNALS_DBVERSION]['count_ham'];
        }

        return [ 'texts_ham'  => $texts_ham,
                 'texts_spam' => $texts_spam,
                 'dbversion'  => $dbversion ];
    }

    /**
     * Get all data about a list of tokens from the database.
     *
     * @access public
     * @param array The tokens list
     * @return mixed Returns False on failure, otherwise returns array of returned data
               in the format [ 'tokens'      => [ token => count ],
                               'degenerates' => [ token => [ degenerate => count ] ] ].
     */
    public function get(array $tokens)
    {
        // First we see what we have in the database
        $token_data = $this->fetch_token_data($tokens);

        // Check if we have to degenerate some tokens
        $missing_tokens = array();
        foreach ($tokens as $token) {
            if (! isset($token_data[$token])) {
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

            $token_data = array_merge($token_data, $this->fetch_token_data($degenerates_list));
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
                foreach ($this->degenerator->degenerates[$token] as $degenerate) {
                    if (isset($token_data[$degenerate]) === true) {
                        // A degenertaed version of the token way found in the database
                        $return_data_degenerates[$token][$degenerate] = $token_data[$degenerate];
                    }
                }
            }
        }

        // Now, all token data directly found in the database is in $return_data_tokens  and all
        // data for degenerated versions is in $return_data_degenerates, so
        return [ 'tokens'      => $return_data_tokens,
                 'degenerates' => $return_data_degenerates ];
    }

    /**
     * Stores or deletes a list of tokens from the given category.
     *
     * @access public
     * @param array The tokens list
     * @param string Either \b8\b8::HAM or \b8\b8::SPAM
     * @param string Either \b8\b8::LEARN or \b8\b8::UNLEARN
     * @return void
     */
    public function process_text(array $tokens, string $category, string $action)
    {
        // No matter what we do, we first have to check what data we have.

        // First get the internals, including the ham texts and spam texts counter
        $internals = $this->get_internals();
        // Then, fetch all data for all tokens we have
        $token_data = $this->fetch_token_data(array_keys($tokens));

        $this->start_transaction();

        // Process all tokens to learn/unlearn
        foreach ($tokens as $token => $count) {
            if (isset($token_data[$token])) {
                // We already have this token, so update it's data

                // Get the existing data
                $count_ham  = $token_data[$token]['count_ham'];
                $count_spam = $token_data[$token]['count_spam'];

                // Increase or decrease the right counter
                if ($action === \b8\b8::LEARN) {
                    if ($category === \b8\b8::HAM) {
                        $count_ham += $count;
                    } elseif ($category === \b8\b8::SPAM) {
                        $count_spam += $count;
                    }
                } elseif ($action == \b8\b8::UNLEARN) {
                    if ($category === \b8\b8::HAM) {
                        $count_ham -= $count;
                    } elseif ($category === \b8\b8::SPAM) {
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
                    $this->update_token($token, [ 'count_ham' => $count_ham,
                                                  'count_spam' => $count_spam ]);
                } else {
                    $this->delete_token($token);
                }
            } else {
                // We don't have the token. If we unlearn a text, we can't delete it as we don't
                // have it anyway, so just do something if we learn a text
                if ($action === \b8\b8::LEARN) {
                    if ($category === \b8\b8::HAM) {
                        $this->add_token($token, [ 'count_ham' => $count,
                                                   'count_spam' => 0 ]);
                    } elseif ($category === \b8\b8::SPAM) {
                        $this->add_token($token, [ 'count_ham' => 0,
                                                   'count_spam' => $count ]);
                    }
                }
            }
        }

        // Now, all token have been processed, so let's update the right text
        if ($action === \b8\b8::LEARN) {
            if ($category === \b8\b8::HAM) {
                $internals['texts_ham']++;
            } elseif ($category === \b8\b8::SPAM) {
                $internals['texts_spam']++;
            }
        } elseif ($action == \b8\b8::UNLEARN) {
            if ($category === \b8\b8::HAM) {
                if ($internals['texts_ham'] > 0) {
                    $internals['texts_ham']--;
                }
            } elseif ($category === \b8\b8::SPAM) {
                if ($internals['texts_spam'] > 0) {
                    $internals['texts_spam']--;
                }
            }
        }

        $this->update_token(self::INTERNALS_TEXTS, [ 'count_ham'  => $internals['texts_ham'],
                                                     'count_spam' => $internals['texts_spam'] ]);

        $this->finish_transaction();
    }

}
