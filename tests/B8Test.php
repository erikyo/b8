<?php

namespace CF7_AntiSpam\Tests;

use B8\B8;
use PHPUnit\Framework\TestCase;

/**
 * Test case for the b8 classification library.
 */
class B8Test extends TestCase
{
    /** @var string[] Paths of DB files to delete after the test */
    private $filesToClean = [];

    private $test_texts = [
        'ham' => "spam discussion",
        'ham2' => "I've finalized the detailed plan for the next phase",
        'spam' => "spam
            Your email address was randomly selected in our weekly draw to receive a guaranteed CASH Prize of $5000!
            This is NOT a joke spam. You MUST claim your prize within 24 hours or it will expire and be passed to the next winner.
            To proceed and collect your massive reward, simply click the secured link below and enter your personal details:
            [Link non sicuro o sospetto: https://tinyurl.com/claim-your-reward-now]
            DO NOT DELAY! Our agents are waiting to confirm your winnings. We only require a small processing fee of $4.99 paid upfront to release the full amount.
            Thank you for your immediate attention.",
        'spam2' => "spam spam spam spam spam spam spam spam Are you currently looking for a digital marketing company that actually gets you leads?
            I’m offering free Google Search Intent leads for your niche (up to 500)—I got this list and wanted to see if you wanted to be a free tester, no cost, no catch. Maybe you can give me a nice review if you like it.
            Would you be open to a quick 5-minute Google Meet call to discuss?",
        'spam3' => "I'm offering a cheap and quick spam way for you to reach millions of website owners just like my message reached you now. It's simple, you give me your ad text and I blast it with my special AI enabled software to either millions of random sites or targets that you select. Check out my site below for details or to have a live chat with me now.
            Go ahead and reach out now! I mean you already know this works since you've read my message this far right?",
    ];

    /**
     * This method is executed before each test.
     */
    protected function setUp(): void
    {
        // Reset the array of files to clean
        $this->filesToClean = [];
    }

    /**
     * This method is executed after each test to clean up the DB files.
     */
    protected function tearDown(): void
    {
        foreach ($this->filesToClean as $file) {
            // We must make sure to delete all files that dba may create.
            // Often 'dba' (especially with handler 'db4' or 'gdbm') creates multiple files.
            // We use a wildcard for safety, even if 'flatfile' creates only one.
            foreach (glob($file) as $actualFile) {
                if (file_exists($actualFile)) {
                    @unlink($actualFile);
                }
            }
        }
    }

    /**
     * Helper to obtain a unique temporary DB file path.
     */
    private function getTestDbPath(string $name, $ext = 'sql'): string
    {
        // Create a file in a temporary directory
        $path = sprintf(
            "%s%sb8_test_%s_%s.%s",
            sys_get_temp_dir(),
            DIRECTORY_SEPARATOR,
            $name,
            uniqid(),
            $ext
        );


        // Register the file for cleaning
        $this->filesToClean[] = $path;

        return $path;
    }

    /**
     * Test basic configuration and classification.
     */
    public function testBasicClassification()
    {
        if (!extension_loaded('sqlite3')) {
            $this->markTestSkipped('The sqlite3 extension is not loaded.');
        }

        $dbPath = $this->getTestDbPath('basic', 'sqlite');

        // To create a database, we could simply create a new SQLite3 object
        $sqlite = new \SQLite3($dbPath);

        $b8 = new B8(['storage' => 'sqlite'], ['resource' => $sqlite], ['min_size' => 3]);

        // Assume that b8::HAM exists as the counterpart of b8::SPAM
        $spamText = "Buy cheap viagra now replica watches online casino";
        $hamText = "Hello John, are you available for our meeting tomorrow?";

        // 1. Training
        $b8->learn($spamText, B8::SPAM);
        $b8->learn($hamText, B8::HAM);

        // 2. Classification
        $spamProbability = $b8->classify($spamText);
        $hamProbability = $b8->classify($hamText);

        // 3. Assertions
        $this->assertGreaterThan(0.85, $spamProbability, "The SPAM text should have a high probability of being spam.");
        $this->assertLessThan(0.15, $hamProbability, "The HAM text should have a low probability of being spam.");

        // Test of a new text
        $newSpam = "cheap watches and casino deals";
        $newHam = "see you tomorrow at the meeting";

        $this->assertGreaterThan(0.7, $b8->classify($newSpam), "New spam not correctly classified.");
        $this->assertLessThan(0.3, $b8->classify($newHam), "New ham not correctly classified.");
    }

    /**
     * Test advanced configuration and inspection methods.
     */
    public function testAdvancedConfigurationAndFeatures()
    {
        $dbPath = $this->getTestDbPath('advanced', 'sqlite');

        // To create a database we could simply create a new SQLite3 object
        $sqlite = new \SQLite3($dbPath);

        // Advanced configuration from documentation
        $b8 = new B8([
            'storage' => 'sqlite',
            'use_tfidf' => true,
            'use_ngrams' => true
        ], ['resource' => $sqlite], [
            'min_size' => 3,
            'max_size' => 50,
            'max_ngram_size' => 2
        ]);

        // === Inspection Methods Test ===

        // 1. Check features status
        $status = $b8->getEnhancedFeaturesStatus();

        $this->assertIsArray($status);
        $this->assertTrue($status['tfidf'], "TF-IDF (use_tfidf) should be active.");
        $this->assertTrue($status['ngrams'], "N-grams (use_ngrams) should be active.");
        $this->assertStringContainsString('Enhanced', $status['lexer'], "The lexer should be 'enhanced'.");

        // 2. Check the IDF calculator
        // We must first “learn” something to have some documents
        $newHam = $this->test_texts['ham'];
        $newHam2 = $this->test_texts['ham2'];
        $newSpam = $this->test_texts['spam'];
        $b8->learn($newHam, B8::HAM);
        $b8->learn($newHam2, B8::HAM);
        $b8->learn($newSpam, B8::SPAM);

        $idf = $b8->getIdfCalculator();

        // Check that the object is of the correct type (assuming the namespace)
        $this->assertInstanceOf(\B8\lexer\IdfCalculator::class, $idf);

        // Check the total number of documents
        $this->assertEquals(3, $idf->get_total_documents(), "The total number of documents should be 3.");

        // Learn more documents
        $b8->learn($this->test_texts['spam2'], B8::SPAM);
        $b8->learn($this->test_texts['spam3'], B8::SPAM);

        // then check if the total number of documents was updated
        $this->assertGreaterThan(4, $idf->get_total_documents(), "The total number of documents should be 5.");

        // Check again the classification for a text
        $spamProbability = $b8->classify("I've finalized the detailed plan for the next phase");
        $spamProbability2 = $b8->classify("Get your prize within 24 hours for free!");
        $this->assertLessThan(0.5, $spamProbability, "New spam not correctly classified.");
        $this->assertGreaterThan(0.5, $spamProbability2, "New spam not correctly classified.");

        // Check the IDF for a token
        $idf2 = $idf->getIdf("discussion");
        $idf3 = $idf->getIdf("spam");
        $this->assertGreaterThan(0.5, $idf2, "The IDF for 'spam' should be 1.");
        $this->assertLessThan(0.5, $idf3, "The IDF for 'spam' should be 1.");
    }
    /**
     * Test TF-IDF correctness.
     */
    public function testTfidfCorrectness()
    {
        $dbPath = $this->getTestDbPath('tfidf', 'sqlite');
        $sqlite = new \SQLite3($dbPath);

        $b8 = new B8([
            'storage' => 'sqlite',
            'use_tfidf' => true,
            'use_ngrams' => false // Disable n-grams for simpler IDF testing first
        ], ['resource' => $sqlite], [
            'min_size' => 3
        ]);

        // 1. Train with controlled data
        // "common" appears in all docs
        // "rare" appears in only 1 doc
        $b8->learn("common word one", B8::HAM);
        $b8->learn("common word two", B8::HAM);
        $b8->learn("common rare three", B8::SPAM);

        $idf = $b8->getIdfCalculator();

        // 2. Verify IDF values
        // Total docs = 3
        // "common": df = 3. IDF = log((3+1)/(3+1)) = log(1) = 0
        // "rare": df = 1. IDF = log((3+1)/(1+1)) = log(2) ~= 0.693

        $idfCommon = $idf->getIdf("common");
        $idfRare = $idf->getIdf("rare");

        $this->assertEquals(0.0, $idfCommon, "IDF for common word should be 0");
        $this->assertGreaterThan(0.6, $idfRare, "IDF for rare word should be > 0.6");

        // 3. Verify Token Weights in Lexer
        // The lexer should return RAW COUNTS in getTokens()
        // and TF-IDF weights separately in getAllTfidfWeights()

        $config = [
            'use_tfidf' => true,
            'idf_storage' => $idf,
            'min_size' => 3,
            'use_ngrams' => false
        ];
        $lexer = new \B8\lexer\Enhanced($config);

        $tokens = $lexer->getTokens("common rare");
        $weights = $lexer->getAllTfidfWeights();

        // Tokens should be RAW COUNTS (both appear once)
        $this->assertEquals(1, $tokens['common'], "Token count for 'common' should be 1 (raw count)");
        $this->assertEquals(1, $tokens['rare'], "Token count for 'rare' should be 1 (raw count)");

        // Weights should reflect TF-IDF
        // "common" has IDF=0, so weight should be 0
        // "rare" has IDF>0, so weight should be > 0
        $this->assertEquals(0.0, $weights['common'], "TF-IDF weight for common word should be 0");
        $this->assertGreaterThan(0, $weights['rare'], "TF-IDF weight for rare word should be > 0");
    }
}
