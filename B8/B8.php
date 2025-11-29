<?php

// SPDX-FileCopyrightText: 2009 Oliver Lillie <ollie@buggedcom.co.uk>
// SPDX-FileCopyrightText: 2006-2021 Tobias Leupold <tl at stonemx dot de>
//
// SPDX-License-Identifier: LGPL-3.0-or-later

/**
 * The B8 spam filter library with enhanced features
 *
 * @package B8
 */

declare(strict_types=1);

namespace B8;

use B8\Lexer\IdfCalculator;
use Exception;

class B8
{
    public const DBVERSION = 4;

    public const SPAM = 'spam';
    public const HAM = 'ham';
    public const LEARN = 'learn';
    public const UNLEARN = 'unlearn';

    public const CLASSIFIER_TEXT_MISSING = 'CLASSIFIER_TEXT_MISSING';

    public const TRAINER_TEXT_MISSING = 'TRAINER_TEXT_MISSING';
    public const TRAINER_CATEGORY_MISSING = 'TRAINER_CATEGORY_MISSING';
    public const TRAINER_CATEGORY_FAIL = 'TRAINER_CATEGORY_FAIL';

    public const INTERNALS_TEXTS = 'b8*texts';
    public const INTERNALS_DBVERSION = 'b8*dbversion';

    public const KEY_DB_VERSION = 'dbversion';
    public const KEY_COUNT_HAM = 'count_ham';
    public const KEY_COUNT_SPAM = 'count_spam';
    public const KEY_TEXTS_HAM = 'texts_ham';
    public const KEY_TEXTS_SPAM = 'texts_spam';

    private array $config = [
        'lexer' => 'standard',
        'degenerator' => 'standard',
        'storage' => 'dba',
        'use_relevant' => 15,
        'min_dev' => 0.2,
        'rob_s' => 0.3,
        'rob_x' => 0.5,
        // New enhanced features
        'use_tfidf' => false,
        'use_ngrams' => false,
        'degenerate_ngrams' => true
    ];

    private ?object $storage = null;
    private ?object $lexer = null;
    private ?object $degenerator = null;
    private ?array $token_data = null;
    private ?IdfCalculator $idf_calc = null;

    /**
     * Constructs B8 with enhanced feature support
     *
     * @access public
     *
     * @param $config             array B8's configuration: [ 'lexer'        => string,
     *                            'degenerator'  => string, 'storage'      => string,
     *                            'use_relevant' => int, 'min_dev'      => float,
     *                            'rob_s'        => float, 'rob_x'        => float,
     *                            'use_tfidf'    => bool, 'use_ngrams'   => bool ]
     * @param $config_storage     array The storage backend's config (depending on the backend used)
     * @param $config_lexer       array The lexer's config (depending on the lexer used)
     * @param $config_degenerator array The degenerator's config (depending on the degenerator used)
     *
     * @throws Exception If the storage backend is not found
     */
    public function __construct(
        array $config = [],
        array $config_storage = [],
        array $config_lexer = [],
        array $config_degenerator = []
    ) {
        // Validate config data
        foreach ($config as $name => $value) {
            switch ($name) {
                case 'min_dev':
                case 'rob_s':
                case 'rob_x':
                    $this->config[$name] = (float) $value;
                    break;
                case 'use_relevant':
                    $this->config[$name] = (int) $value;
                    break;
                case 'lexer':
                case 'degenerator':
                case 'storage':
                    $this->config[$name] = (string) $value;
                    break;
                case 'use_tfidf':
                case 'use_ngrams':
                case 'degenerate_ngrams':
                    $this->config[$name] = (bool) $value;
                    break;
                default:
                    throw new Exception(B8::class . ": Unknown configuration key: \"$name\"");
            }
        }

        // Set up the degenerator class
        $degenerator_class = $this->determineDegeneratorClass();
        $enhanced_degenerator_config = $this->prepareDegeneratorConfig($config_degenerator);
        $this->degenerator = new $degenerator_class($enhanced_degenerator_config);

        // Set up the storage backend first (needed for IDF calculator)
        $class = '\\B8\\Storage\\' . ucfirst($this->config['storage']);
        $this->storage = new $class($config_storage, $this->degenerator);

        // Initialize the IDF calculator if TF-IDF is enabled
        if ($this->config['use_tfidf'] === true) {
            $this->idf_calc = new \B8\Lexer\IdfCalculator($this->storage);
        }

        // Determine which lexer to use based on enhanced features
        $lexer_class = $this->determineLexerClass();

        // Prepare lexer configuration with enhanced features
        $enhanced_config = $this->prepareLexerConfig($config_lexer);

        // Set up the lexer class
        $this->lexer = new $lexer_class($enhanced_config);
    }

    /**
     * Determines which degenerator class to use based on configuration
     *
     * @access private
     * @return string The fully qualified degenerator class name
     */
    private function determineDegeneratorClass(): string
    {
        // If n-grams are enabled, use enhanced degenerator
        if ($this->config['use_ngrams'] === true) {
            $enhanced_class = '\\B8\\Degenerator\\Enhanced';
            if (class_exists($enhanced_class)) {
                return $enhanced_class;
            }

            // Log warning if enhanced degenerator requested but not available
            trigger_error(
                'Enhanced degenerator not available. Falling back to standard degenerator. ' .
                'N-gram degeneration will be limited.',
                E_USER_WARNING
            );
        }

        // Use configured degenerator (standard by default)
        return '\\B8\\Degenerator\\' . ucfirst($this->config['degenerator']);
    }

    /**
     * Prepares degenerator configuration including enhanced features
     *
     * @access private
     * @param array $config_degenerator User-provided degenerator configuration
     * @return array Complete degenerator configuration
     */
    private function prepareDegeneratorConfig(array $config_degenerator): array
    {
        // If using enhanced degenerator with n-grams, add n-gram specific config
        if ($this->config['use_ngrams'] === true) {
            $enhanced_config = [
                'degenerate_ngrams' => $this->config['degenerate_ngrams']
            ];

            // Merge with user configuration (user config takes precedence)
            return array_merge($enhanced_config, $config_degenerator);
        }

        return $config_degenerator;
    }

    /**
     * Determines which lexer class to use based on configuration
     *
     * @access private
     * @return string The fully qualified lexer class name
     */
    private function determineLexerClass(): string
    {
        // If enhanced features are requested, use enhanced lexer
        if ($this->config['use_tfidf'] === true || $this->config['use_ngrams'] === true) {
            // Check if enhanced lexer exists, otherwise fall back to standard
            $enhanced_class = '\\B8\\Lexer\\Enhanced';
            if (class_exists($enhanced_class)) {
                return $enhanced_class;
            }

            // Log warning if enhanced features requested but not available
            trigger_error(
                'Enhanced lexer not available. Falling back to standard lexer. ' .
                'TF-IDF and N-grams features will be disabled.',
                E_USER_WARNING
            );
        }

        // Use standard lexer
        return '\\B8\\Lexer\\' . ucfirst($this->config['lexer']);
    }

    /**
     * Prepares lexer configuration including enhanced features
     *
     * @access private
     *
     * @param array $config_lexer User-provided lexer configuration
     *
     * @return array Complete lexer configuration
     */
    private function prepareLexerConfig(array $config_lexer): array
    {
        // If using enhanced lexer, add enhanced features configuration
        if ($this->config['use_tfidf'] === true || $this->config['use_ngrams'] === true) {
            $enhanced_config = [
                'use_tfidf' => $this->config['use_tfidf'],
                'use_ngrams' => $this->config['use_ngrams']
            ];

            // Add IDF storage reference if TF-IDF is enabled
            if ($this->config['use_tfidf'] === true && $this->idf_calc !== null) {
                $enhanced_config['idf_storage'] = $this->idf_calc;
            }

            // Merge with user configuration (user config takes precedence)
            return array_merge($enhanced_config, $config_lexer);
        }

        return $config_lexer;
    }

    /**
     * Classifies a text
     *
     * @access public
     *
     * @param string|null $text The text to classify
     *
     * @return float|int|string float The rating between 0 (ham) and 1 (spam) or an error code
     */
    public function classify(?string $text = null)
    {
        // Let's first see if the user called the function correctly
        if ($text === null) {
            return B8::CLASSIFIER_TEXT_MISSING;
        }

        // Get the internal database variables, containing the number of ham and spam texts so the
        // spam probability can be calculated in relation to them
        $internals = $this->storage->getInternals();

        // Calculate the spaminess of all tokens

        // Get all tokens we want to rate
        $tokens = $this->lexer->getTokens($text);

        // Check if the lexer failed (if so, $tokens will be a lexer error code, if not, $tokens
        //  will be an array)
        if (!is_array($tokens)) {
            return $tokens;
        }

        // Fetch all available data for the token set from the database
        $this->token_data = $this->storage->get(array_keys($tokens));

        // Get TF-IDF weights if available
        $tfidf_weights = [];
        if ($this->config['use_tfidf'] && $this->lexer instanceof \B8\Lexer\Enhanced) {
            $tfidf_weights = $this->lexer->getAllTfidfWeights();
        }

        // Calculate the spaminess and importance for each token (or a degenerated form of it)

        $word_count = [];
        $rating = [];
        $importance = [];

        foreach ($tokens as $word => $count) {
            $word_count[$word] = $count;
            // Get base probability (not modified by TF-IDF)
            $rating[$word] = $this->getProbability($word, $internals);

            // Calculate importance, amplified by TF-IDF weight
            $base_importance = abs(0.5 - $rating[$word]);
            $weight = $tfidf_weights[$word] ?? 1.0;
            // TF-IDF weight amplifies importance (high TF-IDF = more important)
            $importance[$word] = $base_importance * $weight;
        }

        // Order by importance
        arsort($importance);

        // Get the most interesting tokens (use all if we have less than the given number)
        $relevant = [];
        for ($i = 0; $i < $this->config['use_relevant']; $i++) {
            if ($token = key($importance)) {
                // Important tokens remain

                // If the token's rating is relevant enough, use it
                if (abs(0.5 - $rating[$token]) > $this->config['min_dev']) {
                    // Tokens that appear more than once also count more than once
                    for ($x = 0; $x < $word_count[$token]; $x++) {
                        $relevant[] = $rating[$token];
                    }
                }
            } else {
                // We have fewer words as we want to use, so we already use what we have and can
                // break here
                break;
            }

            next($importance);
        }

        // Calculate the spaminess of the text (thanks to Mr. Robinson ;-)

        // We set both haminess and spaminess to 1 for the first multiplying
        $haminess = 1.0;
        $spaminess = 1.0;

        // Consider all relevant ratings
        foreach ($relevant as $value) {
            $haminess *= (1.0 - $value);
            $spaminess *= $value;
        }

        // If no token was good for calculation, we really don't know how to rate this text, so
        // we can return 0.5 without further calculations.
        if ($haminess == 1.0 && $spaminess == 1.0) {
            return 0.5;
        }

        // Calculate the combined rating

        // Get the number of relevant ratings
        $n = count($relevant);

        // The actual haminess and spaminess
        $haminess = 1 - pow($haminess, (1 / $n));
        $spaminess = 1 - pow($spaminess, (1 / $n));

        // Calculate the combined indicator
        $probability = ($haminess - $spaminess) / ($haminess + $spaminess);

        // We want a value between 0 and 1, not between -1 and +1, so ...
        // Alea iacta est
        return (1 + $probability) / 2;
    }

    /**
     * Calculate the spaminess of a single token also considering "degenerated" versions
     *
     * @access private
     *
     * @param string $word The word to rate
     * @param array $internals The "internals" array
     *
     * @return float The word's rating
     */
    private function getProbability(string $word, array $internals): float
    {
        // Let's see what we have!
        if (isset($this->token_data['tokens'][$word])) {
            // The token is in the database, so we can use it's data as-is and calculate the
            // spaminess of this token directly
            return $this->calculateProbability($this->token_data['tokens'][$word], $internals);
        }

        // The token was not found, so do we at least have similar words?
        if (isset($this->token_data['degenerates'][$word])) {
            // We found similar words, so calculate the spaminess for each one and choose the most
            // important one for the further calculation

            // The default rating is 0.5 simply saying nothing
            $rating = 0.5;

            foreach ($this->token_data['degenerates'][$word] as $count) {
                // Calculate the rating of the current degenerated token
                $rating_tmp = $this->calculateProbability($count, $internals);

                // Is it more important than the rating of another degenerated version?
                if (abs(0.5 - $rating_tmp) > abs(0.5 - $rating)) {
                    $rating = $rating_tmp;
                }
            }

            return $rating;
        } else {
            // The token is really unknown, so choose the default rating for completely unknown
            // tokens.
            //This strips down to the robX parameter, so we can be cheap out the freaky math
            // ;-)
            return $this->config['rob_x'];
        }
    }

    /**
     * Do the actual spaminess calculation of a single token
     *
     * @access private
     *
     * @param array $data The token's data [ \B8\B8::KEY_COUNT_HAM => int, \B8\B8::KEY_COUNT_SPAM => int ]
     * @param array $internals The "internals" array
     *
     * @return float The rating
     */
    private function calculateProbability(array $data, array $internals): float
    {
        // Calculate the basic probability as proposed by Mr. Graham

        // But: consider the number of ham and spam texts saved instead of the number of entries
        // where the token appeared to calculate a relative spaminess because we count tokens
        // appearing multiple times not just once but as often as they appear in the learned texts.

        $rel_ham = $data[B8::KEY_COUNT_HAM];
        $rel_spam = $data[B8::KEY_COUNT_SPAM];

        if ($internals[B8::KEY_TEXTS_HAM] > 0) {
            $rel_ham = $data[B8::KEY_COUNT_HAM] / $internals[B8::KEY_TEXTS_HAM];
        }

        if ($internals[B8::KEY_TEXTS_SPAM] > 0) {
            $rel_spam = $data[B8::KEY_COUNT_SPAM] / $internals[B8::KEY_TEXTS_SPAM];
        }

        $rating = $rel_spam / ($rel_ham + $rel_spam);

        // Calculate the better probability proposed by Mr. Robinson
        $all = $data[B8::KEY_COUNT_HAM] + $data[B8::KEY_COUNT_SPAM];
        return (($this->config['rob_s'] * $this->config['rob_x']) + ($all * $rating))
            / ($this->config['rob_s'] + $all);
    }

    /**
     * Check the validity of the category of a request
     *
     * @access private
     *
     * @param string $category The category
     *
     * @return bool True if the category is valid, false otherwise
     */
    private function checkCategory(string $category): bool
    {
        return $category === B8::HAM || $category === B8::SPAM;
    }

    /**
     * Learn a reference text
     *
     * @access public
     *
     * @param string|null $text The text to learn
     * @param string|null $category Either B8::SPAM or B8::HAM
     *
     * @return string|null void or an error code
     * @throws Exception If the lexer fails
     */
    public function learn(?string $text = null, ?string $category = null): ?string
    {
        // Let's first see if the user called the function correctly
        if ($text === null) {
            return B8::TRAINER_TEXT_MISSING;
        }
        if ($category === null) {
            return B8::TRAINER_CATEGORY_MISSING;
        }

        return $this->processText($text, $category, B8::LEARN);
    }

    /**
     * Unlearn a reference text
     *
     * @access public
     *
     * @param string|null $text The text to unlearn
     * @param string|null $category Either b8::SPAM or b8::HAM
     *
     * @return string|null void or an error code
     * @throws Exception If the lexer fails
     */
    public function unlearn(?string $text = null, ?string $category = null): ?string
    {
        // Let's first see if the user called the function correctly
        if ($text === null) {
            return B8::TRAINER_TEXT_MISSING;
        }
        if ($category === null) {
            return B8::TRAINER_CATEGORY_MISSING;
        }

        return $this->processText($text, $category, B8::UNLEARN);
    }

    /**
     * Does the actual interaction with the storage backend for learning or unlearning texts
     *
     * @access private
     *
     * @param $text string The text to process
     * @param $category string Either B8::SPAM or B8::HAM
     * @param $action string Either B8::LEARN or B8::UNLEARN
     *
     * @return string|null void or an error code
     * @throws Exception If the lexer fails
     */
    private function processText(string $text, string $category, string $action): ?string
    {
        // Look if the request is okay
        if (!$this->checkCategory($category)) {
            return B8::TRAINER_CATEGORY_FAIL;
        }

        // Get all tokens from $text
        // For training, we don't want TF-IDF weights, just raw counts
        // So we temporarily disable TF-IDF if using enhanced lexer
        $tokens = $this->getTrainingTokens($text);

        // Check if the lexer failed (if so, $tokens will be a lexer error code, if not, $tokens
        //  will be an array)
        if (!is_array($tokens)) {
            return $tokens;
        }

        // Update IDF statistics if TF-IDF is enabled and we're learning
        if (
            $this->config['use_tfidf'] === true
            && $this->idf_calc !== null
            && $action === B8::LEARN
        ) {
            $this->idf_calc->updateDocument($tokens);
        }

        // Pass the tokens and what to do with it to the storage backend
        $this->storage->processText($tokens, $category, $action);
        return null;
    }

    /**
     * Gets tokens for training purposes (without TF-IDF weighting)
     *
     * @access private
     *
     * @param $text string The text to tokenize
     *
     * @return mixed Array of tokens or error code
     *
     * @throws Exception If the lexer fails
     */
    private function getTrainingTokens(string $text)
    {
        // If using enhanced lexer with TF-IDF, we need to get raw tokens for training
        if ($this->config['use_tfidf'] === true && $this->lexer instanceof \B8\Lexer\Enhanced) {
            // Create a temporary lexer without TF-IDF for training
            $config = [
                'use_tfidf' => false,
                'use_ngrams' => $this->config['use_ngrams']
            ];

            // Merge with any custom lexer config
            // This is a simplified approach; in production, you'd want to preserve
            // all original lexer configuration
            $temp_lexer = new \B8\Lexer\Enhanced($config);
            return $temp_lexer->getTokens($text);
        }

        // For standard lexer or when TF-IDF is disabled, use normal tokenization
        return $this->lexer->getTokens($text);
    }

    /**
     * Gets the IDF calculator instance (useful for external access)
     *
     * @access public
     * @return IdfCalculator|null The IDF calculator instance or null if not initialized
     */
    public function getIdfCalculator(): ?IdfCalculator
    {
        return $this->idf_calc;
    }

    /**
     * Gets the degenerator instance (useful for debugging)
     *
     * @access public
     * @return object|null The degenerator instance
     */
    public function getDegenerator(): ?object
    {
        return $this->degenerator;
    }

    /**
     * Checks if enhanced features are enabled
     *
     * @access public
     * @return array Status of enhanced features [ 'tfidf' => bool, 'ngrams' => bool ]
     */
    public function getEnhancedFeaturesStatus(): array
    {
        return [
            'tfidf' => $this->config['use_tfidf'],
            'ngrams' => $this->config['use_ngrams'],
            'lexer' => get_class($this->lexer)
        ];
    }
}
