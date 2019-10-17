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
 * A Berkeley DB (DBA) storage backend
 *
 * @license LGPL 2.1
 * @package b8
 * @author Tobias Leupold <tobias.leupold@gmx.de>
 */

class b8_storage_dba extends b8_storage_base
{

    private $db = null;

    /**
     * Sets up the backend
     *
     * @access public
     * @param array $config: 'resource' => a DBA resource
     */
    protected function setup_backend($config)
    {
        if (! isset($config['resource']) || get_resource_type($config['resource']) !== 'dba') {
            throw new Exception("b8_storage_dba: No valid DBA resource passed");
        }
        $this->db = $config['resource'];
    }

    /**
     * Does the actual interaction with the database when fetching data.
     *
     * @access protected
     * @param array $tokens
     * @return mixed Returns an array of the returned data in the format array(token => data)
               or an empty array if there was no data.
     */
    protected function fetch_token_data($tokens)
    {
        $data = array();

        foreach ($tokens as $token) {
            // Try to the raw data in the format "count_ham count_spam lastseen"
            $count = dba_fetch($token, $this->db);

            if ($count !== false) {
                # Split the data by space characters
                $split_data = explode(' ', $count);

                // As the internal variables just have one single value, we have to check for this
                $count_ham  = null;
                $count_spam = null;
                if (isset($split_data[0])) {
                    $count_ham  = (int) $split_data[0];
                }
                if (isset($split_data[1])) {
                    $count_spam = (int) $split_data[1];
                }

                // Append the parsed data
                $data[$token] = [ 'count_ham'  => $count_ham,
                                  'count_spam' => $count_spam ];
            }
        }

        return $data;
    }

    /**
     * Translates a count array to a count data string
     *
     * @access private
     * @param array ('count_ham' => int, 'count_spam' => int)
     * @return string The translated array
     */
    private function assemble_count_value($count)
    {
        // Assemble the count data string
        $count_value = "{$count['count_ham']} {$count['count_spam']}";
        // Remove whitespace from data of the internal variables
        return(rtrim($count_value));
    }

    /**
     * Store a token to the database.
     *
     * @access protected
     * @param string $token
     * @param string $count
     * @return bool true on success or false on failure
     */
    protected function add_token($token, $count)
    {
        return dba_insert($token, $this->assemble_count_value($count), $this->db);
    }

    /**
     * Update an existing token.
     *
     * @access protected
     * @param string $token
     * @param string $count
     * @return bool true on success or false on failure
     */
    protected function update_token($token, $count)
    {
        return dba_replace($token, $this->assemble_count_value($count), $this->db);
    }

    /**
     * Remove a token from the database.
     *
     * @access protected
     * @param string $token
     * @return bool true on success or false on failure
     */
    protected function delete_token($token)
    {
        return dba_delete($token, $this->db);
    }

    /**
     * Does nothing. DBA doesn't need this.
     *
     * @access protected
     * @return void
     */
    protected function start_transaction()
    {
        return;
    }

    /**
     * Does nothing. DBA doesn't need this.
     *
     * @access protected
     * @return void
     */
    protected function finish_transaction()
    {
        return;
    }

}
