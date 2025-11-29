<?php

// SPDX-FileCopyrightText: 2006-2022 Tobias Leupold <tl at stonemx dot de>
//
// SPDX-License-Identifier: LGPL-3.0-or-later

/**
 * A helper class to derive simplified tokens
 *
 * @package B8
 */

declare(strict_types=1);

namespace B8\degenerator;

use Exception as ExceptionAlias;

class Standard
{
    public array $config = [
        'multibyte' => true,
        'encoding' => 'UTF-8'
    ];

    public array $degenerates = [];

    /**
     * Constructs the degenerator.
     *
     * @access public
     *
     * @param array $config The configuration: [ 'multibyte' => bool,
     * 'encoding' => string ]
     *
     * @throws ExceptionAlias If an invalid configuration value is provided
     */
    public function __construct(array $config)
    {
        // Validate config data
        foreach ($config as $name => $value) {
            switch ($name) {
                case 'multibyte':
                    $this->config[$name] = (bool) $value;
                    break;
                case 'encoding':
                    $this->config[$name] = (string) $value;
                    break;
                default:
                    throw new ExceptionAlias(Standard::class . ": Unknown configuration key: "
                        . "\"$name\"");
            }
        }
    }

    /**
     * Generates a list of "degenerated" words for a list of words.
     *
     * @access public
     * @param array $words The words to degenerate
     * @return array An array containing an array of degenerated tokens for each token
     */
    public function degenerate(array $words): array
    {
        $degenerates = [];

        foreach ($words as $word) {
            $degenerates[$word] = $this->degenerateWord($word);
        }

        return $degenerates;
    }

    /**
     * Builds a list of "degenerated" versions of a word.
     *
     * @access private
     * @param string $word The word
     * @return array An array of degenerated words
     */
    private function degenerateWord(string $word): array
    {
        // Check for any stored words so the process doesn't have to repeat
        if (isset($this->degenerates[$word]) === true) {
            return $this->degenerates[$word];
        }

        // Create different versions of upper and lower case
        $lower = '';
        $upper = '';
        $first = '';

        if ($this->config['multibyte'] === false) {
            // The standard upper/lower versions
            $lower = strtolower($word);
            $upper = strtoupper($word);
            $first = substr($upper, 0, 1) . substr($lower, 1, strlen($word));
        } else {
            // The multibyte upper/lower versions
            $lower = mb_strtolower($word, $this->config['encoding']);
            $upper = mb_strtoupper($word, $this->config['encoding']);
            $first = mb_substr($upper, 0, 1, $this->config['encoding'])
                . mb_substr($lower, 1, mb_strlen($word, $this->config['encoding']), $this->config['encoding']);
        }

        // Add the versions
        $upper_lower = [];
        $upper_lower[] = $lower;
        $upper_lower[] = $upper;
        $upper_lower[] = $first;

        // Delete duplicate upper/lower versions
        $degenerate = $this->deleteDuplicates($word, $upper_lower);

        // Append the original word
        $degenerate[] = $word;

        // Degenerate all versions
        foreach ($degenerate as $alt_word) {
            // Look for stuff like !!! and ???
            if (preg_match('/[!?]$/', $alt_word) > 0) {
                // Add versions with different !s and ?s
                if (preg_match('/[!?]{2,}$/', $alt_word) > 0) {
                    $tmp = preg_replace('/([!?])+$/', '$1', $alt_word);
                    $degenerate[] = $tmp;
                }

                $tmp = preg_replace('/([!?])+$/', '', $alt_word);
                $degenerate[] = $tmp;
            }

            // Look for "..." at the end of the word
            $alt_word_int = $alt_word;
            while (preg_match('/[\.]$/', $alt_word_int) > 0) {
                $alt_word_int = substr($alt_word_int, 0, strlen($alt_word_int) - 1);
                $degenerate[] = $alt_word_int;
            }
        }

        // Some degenerates are the same as the original word. These don't have to be fetched, so we
        // create a new array with only new tokens
        $degenerate = $this->deleteDuplicates($word, $degenerate);

        // Store the list of degenerates for the token to prevent unnecessary re-processing
        $this->degenerates[$word] = $degenerate;

        return $degenerate;
    }

    /**
     * Remove duplicates from a list of degenerates of a word.
     *
     * @access private
     * @param string $word The word
     * @param array $list The list to process
     * @return array The list without duplicates
     */
    private function deleteDuplicates(string $word, array $list): array
    {
        $list_processed = [];

        // Check each upper/lower version
        foreach ($list as $alt_word) {
            if ($alt_word != $word) {
                $list_processed[] = $alt_word;
            }
        }

        return $list_processed;
    }
}
