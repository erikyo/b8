<?php

// SPDX-FileCopyrightText: 2009 Oliver Lillie <ollie@buggedcom.co.uk>
// SPDX-FileCopyrightText: 2006-2022 Tobias Leupold <tl at stonemx dot de>
//
// SPDX-License-Identifier: LGPL-3.0-or-later

/**
 * An enhanced lexer class with TF-IDF and N-grams support
 *
 * @package B8
 */

declare(strict_types=1);

namespace B8\Lexer;

class Enhanced
{
    private const LEXER_TEXT_EMPTY = 'LEXER_TEXT_EMPTY';
    private const LEXER_NO_TOKENS = 'b8*no_tokens';

    private array $config = [
        'min_size' => 3,
        'max_size' => 30,
        'get_uris' => true,
        'get_html' => true,
        'get_bbcode' => false,
        'allow_numbers' => false,
        // New options for enhanced features
        'use_tfidf' => true,
        'use_ngrams' => true,
        'ngram_size' => 2,  // 2 for bigrams, 3 for trigrams
        'max_ngram_size' => 3,  // Generate up to trigrams
        'idf_storage' => null  // Reference to IDF storage/calculator
    ];

    private ?array $tokens = null;
    private ?string $processed_text = null;
    private array $token_positions = [];  // Track positions for n-gram generation
    private array $tfidfWeights = [];

    // Regular expressions for token splitting
    private array $regexp = [
        'raw_split' => '/[\s,\.\/"\:;\|<>\-_\[\]{}\+=\)\(\*\&\^%]+/',
        'ip' => '/([A-Za-z0-9\_\-\.]+)/',
        'uris' => '/([A-Za-z0-9\_\-]*\.[A-Za-z0-9\_\-\.]+)/',
        'html' => '/(<.+?>)/',
        'bbcode' => '/(\[.+?\])/',
        'tagname' => '/(.+?)\s/',
        'numbers' => '/^[0-9]+$/'
    ];

    /**
     * Constructs the enhanced lexer.
     *
     * @access public
     *
     * @param array $config The configuration
     *
     * @return void
     * @throws \Exception
     */
    public function __construct(array $config)
    {
        // Validate config data
        foreach ($config as $name => $value) {
            switch ($name) {
                case 'min_size':
                case 'max_size':
                case 'ngram_size':
                case 'max_ngram_size':
                    $this->config[$name] = (int) $value;
                    break;
                case 'allow_numbers':
                case 'get_uris':
                case 'get_html':
                case 'get_bbcode':
                case 'use_tfidf':
                case 'use_ngrams':
                    $this->config[$name] = (bool) $value;
                    break;
                case 'idf_storage':
                    $this->config[$name] = $value;
                    break;
                default:
                    throw new \Exception(self::class . ": Unknown configuration key: \"$name\"");
            }
        }
    }

    /**
     * Splits a text to tokens with TF-IDF and N-grams support.
     *
     * @access public
     * @param  string $text The text to disassemble
     * @return string | array Returns a list of tokens or an error code
     */
    public function getTokens(string $text)
    {
        // Validate input
        if (empty($text)) {
            return self::LEXER_TEXT_EMPTY;
        }

        // Decode HTML entities
        $this->processed_text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

        // Reset token structures
        $this->tokens = [];
        $this->token_positions = [];
        $this->tfidfWeights = [];

        // Extract special tokens (URIs, HTML, BBCode)
        if ($this->config['get_uris'] === true) {
            $this->getUris($this->processed_text);
        }

        if ($this->config['get_html'] === true) {
            $this->getMarkup($this->processed_text, $this->regexp['html']);
        }

        if ($this->config['get_bbcode'] === true) {
            $this->getMarkup($this->processed_text, $this->regexp['bbcode']);
        }

        // Perform raw split and track positions
        $this->rawSplit($this->processed_text);

        // Generate N-grams if enabled
        if ($this->config['use_ngrams'] === true && count($this->token_positions) > 1) {
            $this->generateNgrams();
        }

        // Calculate TF-IDF weights but DON'T modify token counts
        if ($this->config['use_tfidf'] === true) {
            $this->calculateTfidfWeights();
        }

        // Ensure we have tokens
        if (count($this->tokens) == 0) {
            $this->tokens[self::LEXER_NO_TOKENS] = 1;
        }

        return $this->tokens;
    }

    /**
     * Extracts URIs from text.
     *
     * @access private
     * @param  string $text The text to process
     * @return void
     */
    private function getUris(string $text)
    {
        preg_match_all($this->regexp['uris'], $text, $raw_tokens);

        foreach ($raw_tokens[1] as $word) {
            // Remove trailing dot
            $word = rtrim($word, '.');

            // Add full URI as token
            $this->addToken($word, $word, false);

            // Also tokenize URI parts
            $this->rawSplit($word);
        }
    }

    /**
     * Adds a token to the list and optionally tracks its position.
     *
     * @access private
     *
     * @param string $token The token to add
     * @param string|null $word_to_remove Word to remove from processed text
     * @param bool $track_position Whether to track position for n-grams
     *
     * @return void
     */
    private function addToken(string $token, ?string $word_to_remove = null, bool $track_position = true)
    {
        // Validate token
        if (!$this->isValid($token)) {
            return;
        }

        // Normalize token (lowercase for consistency)
        $normalized_token = mb_strtolower($token, 'UTF-8');

        // Add to token list or increment counter
        if (!isset($this->tokens[$normalized_token])) {
            $this->tokens[$normalized_token] = 1;
        } else {
            $this->tokens[$normalized_token] += 1;
        }

        // Track position for n-gram generation
        if ($track_position && $this->config['use_ngrams']) {
            $this->token_positions[] = $normalized_token;
        }

        // Remove word from processed text if requested
        if ($word_to_remove !== null) {
            $this->processed_text = str_replace($word_to_remove, '', $this->processed_text);
        }
    }

    /**
     * Validates a token.
     *
     * @access private
     * @param  string $token The token string
     * @return bool Returns true if the token is valid
     */
    private function isValid(string $token): bool
    {
        // Prevent collision with internal variables
        if (substr($token, 0, 3) == 'b8*') {
            return false;
        }

        // Validate token size
        $len = strlen($token);
        if ($len < $this->config['min_size'] || $len > $this->config['max_size']) {
            return false;
        }

        // Exclude pure numbers if configured
        if (
            $this->config['allow_numbers'] === false
            && preg_match($this->regexp['numbers'], $token) > 0
        ) {
            return false;
        }

        return true;
    }

    /**
     * Performs raw text splitting into tokens.
     *
     * @access private
     * @param  string $text The text to split
     * @return void
     */
    private function rawSplit(string $text)
    {
        foreach (preg_split($this->regexp['raw_split'], $text) as $word) {
            // Add valid tokens and track positions
            $this->addToken($word);
        }
    }

    /**
     * Extracts HTML or BBCode markup.
     *
     * @access private
     * @param  string $text   The text to process
     * @param  string $regexp The regex pattern to use
     * @return void
     */
    private function getMarkup(string $text, string $regexp)
    {
        preg_match_all($regexp, $text, $raw_tokens);

        foreach ($raw_tokens[1] as $word) {
            $actual_word = $word;

            // Extract tag name if tag has parameters
            if (strpos($word, ' ') !== false) {
                preg_match($this->regexp['tagname'], $word, $match);
                $actual_word = $match[1];
                $word = "$actual_word..." . substr($word, -1);
            }

            // Add markup token (don't track position)
            $this->addToken($word, $actual_word, false);
        }
    }

    /**
     * Generates N-grams from token positions.
     *
     * @access private
     * @return void
     */
    private function generateNgrams()
    {
        $positions = $this->token_positions;
        $max_n = min($this->config['max_ngram_size'], count($positions));

        // Generate n-grams from size 2 up to max_ngram_size
        for ($n = 2; $n <= $max_n; $n++) {
            for ($i = 0; $i <= count($positions) - $n; $i++) {
                $ngram_tokens = array_slice($positions, $i, $n);

                // Create n-gram string with a separator
                $ngram = implode(' ', $ngram_tokens);

                // Validate n-gram length
                if (strlen($ngram) <= $this->config['max_size'] * $n) {
                    // Add n-gram to tokens
                    if (!isset($this->tokens[$ngram])) {
                        $this->tokens[$ngram] = 1;
                    } else {
                        $this->tokens[$ngram] += 1;
                    }
                }
            }
        }
    }

    /**
     * Calculate TF-IDF weights separately from token counts
     * @return void
     */
    private function calculateTfidfWeights(): void
    {
        if ($this->config['idf_storage'] === null) {
            $this->tfidfWeights = [];
            return;
        }

        $this->tfidfWeights = [];
        $total_tokens = array_sum($this->tokens);

        // Get IDF values in batch for all tokens
        $tokens_list = array_keys($this->tokens);
        // Check if getIdfBatch exists, otherwise fallback to loop
        if (method_exists($this->config['idf_storage'], 'getIdfBatch')) {
            $idf_values = $this->config['idf_storage']->getIdfBatch($tokens_list);
        } else {
            $idf_values = [];
            foreach ($tokens_list as $token) {
                $idf_values[$token] = $this->config['idf_storage']->getIdf($token);
            }
        }

        foreach ($this->tokens as $token => $count) {
            // Skip internal markers
            if (substr($token, 0, 3) === 'b8*') {
                $this->tfidfWeights[$token] = 1.0;
                continue;
            }

            // Calculate TF-IDF weight
            $tf = $count / $total_tokens;
            $idf = $idf_values[$token] ?? 1.0;
            $this->tfidfWeights[$token] = $tf * $idf;
        }
    }

    /**
     * Get TF-IDF weight for a specific token
     * @param string $token
     * @return float
     */
    public function getTfidfWeight(string $token): float
    {
        return $this->tfidfWeights[$token] ?? 1.0;
    }

    /**
     * Get all TF-IDF weights
     * @return array
     */
    public function getAllTfidfWeights(): array
    {
        return $this->tfidfWeights;
    }
}
