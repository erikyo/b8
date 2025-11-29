<?php

// SPDX-FileCopyrightText: 2006-2022 Tobias Leupold <tl at stonemx dot de>
//
// SPDX-License-Identifier: LGPL-3.0-or-later

/**
 * An enhanced degenerator that handles both single tokens and n-grams
 *
 * @package b8
 */

namespace B8\degenerator;

use Exception;

class Enhanced
{
    public array $config = [
        'multibyte' => true,
        'encoding'  => 'UTF-8',
        'degenerate_ngrams' => true  // New option
    ];

    public array $degenerates = [];

    /**
     * Constructs the enhanced degenerator.
     *
     * @access public
     *
     * @param array $config The configuration: [ 'multibyte' => bool,
     *   'encoding' => string,
     *   'degenerate_ngrams' => bool
     * ]
     *
     * @throws Exception If an unknown configuration key is provided
     */
    public function __construct(array $config)
    {
        // Validate config data
        foreach ($config as $name => $value) {
            switch ($name) {
                case 'multibyte':
                case 'degenerate_ngrams':
                    $this->config[$name] = (bool) $value;
                    break;
                case 'encoding':
                    $this->config[$name] = (string) $value;
                    break;
                default:
                    throw new Exception(self::class . ": Unknown configuration key: "
                                         . "\"$name\"");
            }
        }
    }

    /**
     * Generates a list of "degenerated" words for a list of words.
     * Now handles both unigrams and n-grams.
     *
     * @access public
     * @param array $words The words to degenerate
     * @return array An array containing an array of degenerated tokens for each token
     */
    public function degenerate(array $words): array
    {
        $degenerates = [];

        foreach ($words as $word) {
            // Detect if this is an n-gram (contains space)
            if ($this->isNgram($word)) {
                $degenerates[$word] = $this->degenerateNgram($word);
            } else {
                $degenerates[$word] = $this->degenerateWord($word);
            }
        }

        return $degenerates;
    }

    /**
     * Checks if a token is an n-gram (contains spaces)
     *
     * @access private
     * @param string $token The token to check
     * @return bool True if token is an n-gram
     */
    private function isNgram(string $token): bool
    {
        return strpos($token, ' ') !== false;
    }

    /**
     * Degenerates an n-gram by degenerating each component word
     * and combining them in various ways.
     *
     * @access private
     * @param string $ngram The n-gram to degenerate
     * @return array An array of degenerated n-grams
     */
    private function degenerateNgram(string $ngram): array
    {
        // Check cache first
        if (isset($this->degenerates[$ngram])) {
            return $this->degenerates[$ngram];
        }

        // If n-gram degeneration is disabled, return empty array
        if ($this->config['degenerate_ngrams'] === false) {
            $this->degenerates[$ngram] = [];
            return [];
        }

        // Split n-gram into individual words
        $words = explode(' ', $ngram);
        $degenerated_components = [];

        // Degenerate each word individually
        foreach ($words as $word) {
            $degenerated = $this->degenerateWord($word);
            // Include the original word too
            array_unshift($degenerated, $word);
            $degenerated_components[] = $degenerated;
        }

        // Generate combinations of degenerated forms
        $ngram_degenerates = $this->combineDegeneratedWords($degenerated_components);

        // Remove duplicates and the original n-gram
        $ngram_degenerates = $this->deleteDuplicates($ngram, $ngram_degenerates);

        // Cache the result
        $this->degenerates[$ngram] = $ngram_degenerates;

        return $ngram_degenerates;
    }

    /**
     * Combines degenerated word variations into n-gram variations.
     * For example: ["buy", "Buy"] x ["cheap", "Cheap"] = ["buy cheap", "Buy cheap", "buy Cheap", "Buy Cheap"]
     * But we limit combinations to avoid explosion.
     *
     * @access private
     * @param array $components Array of arrays, each containing degenerated forms of a word
     * @return array Array of combined n-grams
     */
    private function combineDegeneratedWords(array $components): array
    {
        // Start with the first word's variations
        $combinations = array_map(function ($word) {
            return [$word];
        }, $components[0]);

        // Add each subsequent word
        for ($i = 1; $i < count($components); $i++) {
            $new_combinations = [];

            foreach ($combinations as $partial) {
                // Only combine with the most important variations to avoid explosion
                // Take original + first 2 degenerates (usually lowercase, uppercase, first-cap)
                $variations_to_use = array_slice($components[$i], 0, 3);

                foreach ($variations_to_use as $word) {
                    $new_partial = $partial;
                    $new_partial[] = $word;
                    $new_combinations[] = $new_partial;
                }
            }

            $combinations = $new_combinations;

            // Limit total combinations to prevent explosion
            if (count($combinations) > 50) {
                $combinations = array_slice($combinations, 0, 50);
                break;
            }
        }

        // Convert arrays back to space-separated strings
        return array_map(function ($combo) {
            return implode(' ', $combo);
        }, $combinations);
    }

    /**
     * Builds a list of "degenerated" versions of a single word.
     * This is the original logic from standard degenerator.
     *
     * @access private
     * @param string $word The word
     * @return array An array of degenerated words
     */
    private function degenerateWord(string $word): array
    {
        // Check for any stored words so the process doesn't have to repeat
        if (isset($this->degenerates[$word])) {
            return $this->degenerates[$word];
        }

        // Create different versions of upper and lower case
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
                     . mb_substr(
                         $lower,
                         1,
                         mb_strlen($word, $this->config['encoding']),
                         $this->config['encoding']
                     );
        }

        // Add the versions
        $upper_lower   = [];
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
                    $tmp          = preg_replace('/([!?])+$/', '$1', $alt_word);
                    $degenerate[] = $tmp;
                }

                $tmp          = preg_replace('/([!?])+$/', '', $alt_word);
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

        // Check each version
        foreach ($list as $alt_word) {
            if ($alt_word != $word && !in_array($alt_word, $list_processed)) {
                array_push($list_processed, $alt_word);
            }
        }

        return $list_processed;
    }

    /**
     * Gets statistics about cached degenerates
     *
     * @access public
     * @return array Statistics about the degenerate cache
     */
    public function getCacheStats(): array
    {
        $unigram_count = 0;
        $ngram_count = 0;

        foreach (array_keys($this->degenerates) as $token) {
            if ($this->isNgram($token)) {
                $ngram_count++;
            } else {
                $unigram_count++;
            }
        }

        return [
            'total_cached'   => count($this->degenerates),
            'unigrams'       => $unigram_count,
            'ngrams'         => $ngram_count,
            'memory_usage'   => memory_get_usage(true)
        ];
    }

    /**
     * Clears the degenerate cache
     *
     * @access public
     * @return void
     */
    public function clearCache()
    {
        $this->degenerates = [];
    }
}
