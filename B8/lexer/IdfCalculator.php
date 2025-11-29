<?php

// SPDX-FileCopyrightText: 2024
// SPDX-License-Identifier: LGPL-3.0-or-later

declare(strict_types=1);

/**
 * IDF (Inverse Document Frequency) calculator for TF-IDF weighting
 *
 * @package B8
 */

namespace B8\lexer;

use B8\B8;

class IdfCalculator
{
    const IDF_TOTAL_DOCS = 'idf*total_docs';
    const IDF_DOC_PREFIX = 'idf*doc_';

    private object $storage;  // Cache for document counts per token
    private int $total_documents = 0;
    /** @var array<string, int> */
    private array $document_counts = [];

    /**
     * Constructs the IDF calculator.
     *
     * @access public
     *
     * @param object $storage Reference to the storage backend
     *
     * @return void
     */
    public function __construct(object $storage)
    {
        $this->storage = $storage;
        $this->load_total_documents();
    }

    /**
     * Loads the total document count from storage.
     *
     * @access private
     */
    private function load_total_documents(): void
    {
        $result = $this->storage->get([self::IDF_TOTAL_DOCS]);
        $this->total_documents = 0;
        if (isset($result['tokens'][self::IDF_TOTAL_DOCS])) {
            $this->total_documents = (int) $result['tokens'][self::IDF_TOTAL_DOCS][B8::KEY_COUNT_HAM];
        }
    }

    /**
     * Updates IDF statistics when a new document is processed.
     * This should be called during training for each ham/spam email.
     *
     * @access public
     * @param  array $tokens Array of unique tokens in the document
     */
    public function update_document(array $tokens): void
    {
        // Increment total document count
        $this->total_documents++;
        $this->storage->set(self::IDF_TOTAL_DOCS, $this->total_documents);

        // Get unique tokens only (document frequency counts presence, not occurrences)
        $unique_tokens = array_unique(array_keys($tokens));

        // Update document count for each unique token
        foreach ($unique_tokens as $token) {
            if (substr($token, 0, 3) === 'b8*') {
                continue;  // Skip internal tokens
            }

            $key = self::IDF_DOC_PREFIX . $token;
            $r = $this->storage->fetchOne($key);
            if (!isset($r[$key])) {
                $this->storage->set($key, 1);
                $this->document_counts[$token] = 1;
            } else {
                $current_count = $r[$key][B8::KEY_COUNT_HAM];
                $new_count = (int) $current_count + 1;
                $this->storage->set($key, $new_count);
                $this->document_counts[$token] = $new_count;
            }
        }
    }

    /**
     * Batch calculate IDF for multiple tokens (more efficient).
     *
     * @access public
     * @param  array $tokens Array of tokens
     * @return array Associative array of token => IDF
     */
    public function getIdfBatch(array $tokens): array
    {
        $idf_values = [];

        foreach ($tokens as $token) {
            $idf_values[$token] = $this->getIdf($token);
        }

        return $idf_values;
    }

    /**
     * Calculates IDF for a token.
     * IDF = log(N / df) where N is total docs and df is document frequency
     *
     * @access public
     * @param  string $token The token to calculate IDF for
     * @return float The IDF value
     */
    public function getIdf(string $token): float
    {
        // Return default IDF if no documents yet
        if ($this->total_documents == 0) {
            return 1.0;
        }

        // Get document frequency from cache or storage
        if (!isset($this->document_counts[$token])) {
            $key = self::IDF_DOC_PREFIX . $token;
            $r = $this->storage->fetchOne($key);
            if (!isset($r[$key])) {
                $this->document_counts[$token] = 0;
            } else {
                $this->document_counts[$token] = (int) $r[$key][B8::KEY_COUNT_HAM];
            }
        }

        $doc_frequency = $this->document_counts[$token];

        // If token never seen, give it a small document frequency (smoothing)
        if ($doc_frequency == 0) {
            $doc_frequency = 1;
        }

        // Calculate IDF: log(N / df)
        // Add 1 to avoid division by zero and provide smoothing
        return log(($this->total_documents + 1) / ($doc_frequency + 1));
    }

    /**
     * Gets the total number of documents processed.
     *
     * @access public
     * @return int Total document count
     */
    public function get_total_documents(): int
    {
        return $this->total_documents;
    }

    /**
     * Resets all IDF statistics.
     *
     * @access public
     * @return void
     */
    public function reset(): void
    {
        // This would need to iterate through storage and delete all IDF entries
        // Implementation depends on storage backend capabilities
        $this->total_documents = 0;
        $this->document_counts = [];
        $this->storage->set(self::IDF_TOTAL_DOCS, 0);
    }
}
