.. SPDX-FileCopyrightText: 2006-2022 Tobias Leupold <tl at stonemx dot de>

SPDX-License-Identifier: CC-BY-SA-4.0

.. |br| raw:: html

   <br />

.. |contact_email| raw:: html

    &#116;&#111;&#98;&#105;&#97;&#115;&nbsp;&#46;&nbsp;&#108;&#101;&#117;&#112;&#111;&#108;&#100;&nbsp;&#97;&#116;&nbsp;&#103;&#109;&#120;&nbsp;&#46;&nbsp;&#100;&#101;

.. meta::
:viewport: width=device-width, initial-scale=1

.. section-numbering::

==========
b8: readme
==========

:Author: Tobias Leupold
:Homepage: https://nasauber.de/opensource/b8/ 
:Contact: |contact_email|
:Date: @LASTCHANGE@

.. contents:: Table of Contents

Description of b8
=================

What is b8?
-----------

b8 is a spam filter implemented in `PHP <http://www.php.net/ >`__. It is intended to keep your weblog or guestbook spam-free. The filter can be used anywhere in your PHP code and tells you whether a text is spam or not, using statistical text analysis. What it does is: you give b8 a text and it returns a value between 0 and 1, saying it's ham when it's near 0 and saying it's spam when it's near 1. See `How does it work?`_ for details about this. |br|
To be able to do this, b8 first has to learn some spam and some ham (non-spam) texts. If it makes mistakes when classifying unknown texts or the result is not distinct enough, b8 can be told what the text actually is, getting better with each learned text.

b8 is a statistical spam filter. I'm not a mathematician, but as far as I can grasp it, the math used in b8 has not much to do with Bayes' theorem itself. So I call it a *statistical* spam filter, not a *Bayesian* one. Principally, It's a program like `Bogofilter <http://bogofilter.sourceforge.net/ >`__ or `SpamBayes <http://spambayes.sourceforge.net/ >`__, but it is not intended to classify emails. Therefore, the way b8 works is slightly different from email spam filters. See `What's different?`_ if you're interested in the details.

**NEW: Enhanced Features** - b8 now supports TF-IDF weighting and N-gram analysis to significantly improve classification accuracy on modern spam patterns. These features are optional and backward-compatible with existing databases.

An example of what we're talking about here:

At the moment of this writing (November 2012), b8 has, since December 2006, classified 26,869 guestbook entries and weblog comments on my homepage. 145 were ham. 76 spam texts (0.28 %) have been falsely rated as ham (false negatives) and I had to remove them manually. Only one single ham message has been falsely classified as spam (false positive) back in June 2010, but – in defense of b8 – this was the very first English ham text I got. Previously, each and every of the 15,024 English texts posted have been spam. Texts with Chinese, Japanese or Cyrillic content (all spam either) did not appear until 2011. |br|
This results in a sensitivity of 99.7 % (the probability that a spam text will actually be rated as spam) and a specifity of 99.3 % (the probability that a ham text will actually be rated as ham) for my homepage. Before the one false positive, of course, the specifity has been 100 % ;-)

How does it work?
-----------------

In principle, b8 uses the math and technique described in Gary Robinson's articles "A Statistical Approach to the Spam Problem" [#statisticalapproach]_ and "Spam Detection" [#spamdetection]_. The "degeneration" method Paul Graham proposed in "Better Bayesian Filtering" [#betterbayesian]_ has also been implemented.

**Enhanced Algorithm**: When TF-IDF is enabled, tokens are weighted by their importance in the document relative to the training corpus. N-grams (2-3 word phrases) capture contextual patterns that single words miss. These weights are applied during classification but training always uses raw counts to maintain statistical validity.

b8 cuts the text to classify to pieces, extracting stuff like email addresses, links and HTML tags and of course normal words. For each such token, it calculates a single probability for a text containing it being spam, based on what the filter has learned so far. When the token has not been seen before, b8 tries to find similar ones using "degeneration" and uses the most relevant value found. If really nothing is found, b8 assumes a default rating for this token for the further calculations. |br|
Then, b8 takes the most relevant values (which have a rating far from 0.5, which would mean we don't know what it is) and calculates the combined probability that the whole text is spam.

What do I need for it?
----------------------

Not much! You just need PHP (at least **7.4+** with `mbstring` extension recommended) and a database to store the wordlist.

The probably most efficient way to store the wordlist is using a `Berkeley DB <http://oracle.com/technetwork/products/berkeleydb/downloads/index.html >`_. This also has been the original approach, and b8 comes with an appropriate storage backend. Additional backends are provided for `MySQL <http://mysql.com/ >`_ (mysqli) and `SQLite3 <https://sqlite.org/ >`_.

It should be quite trivial to create a storage backend for whatever database you want to use. Simply write a class extending ``\B8\storage\StorageBase`` which implements the abstract functions listed there.

What's different?
-----------------

b8 has been designed to classify forum posts, weblog comments or guestbook entries, not emails. For this reason, it uses a slightly different technique than most of the other statistical spam filters out there use.

My experience was that spam entries on my weblog or guestbook were often quite short, sometimes just something like "123abc" as text and a link to a suspect homepage. Some spam bots don't even made a difference between e. g. the "name" and "text" fields and posted their text as email address, for example. Considering this, b8 just takes one string to classify, making no difference between "headers" and "text". |br|
The other thing is that most statistical spam filters count one token one time, no matter how often it appears in the text (as Paul Graham describes it in [#planforspam]_). b8 does count how often a token has been seen and learns resp. considers this. Why this? Because a text containing one link (no matter where it points to, just indicated by a "\h\t\t\p\:\/\/" or a "www.") might not be spam, but a text containing 20 links might be.

This means that b8 might be good for classifying weblog comments, guestbook entries or forum posts (I really think it is ;-) – but very likely, it will work quite poor when being used for something else like classifying emails. At least with the default lexer. But as said above, for this task, there are lots of very good filters out there to choose from.

**NEW**: Modern spam often uses phrase patterns rather than single words. The optional N-gram feature captures these patterns while TF-IDF weighting ensures important tokens have more influence on the final rating.

Update from prior versions
==========================

If this is a new b8 installation, read on at the `Installation`_ section!

Update from bayes-php version 0.2.1 or earlier
----------------------------------------------

Please first follow the database update instructions of the bayes-php-0.3 release if you update from a version prior to bayes-php-0.3, then read on.

Update from bayes-php version 0.3 to any pre-0.5 version of b8
--------------------------------------------------------------

Version 0.5 introduced some changes. Here they are. Please read `Update from b8 0.5.*`_ for how to update your database.

If you use SQLite: Sorry, at the moment, there's no SQLite backend for b8. You can probably create a dump of your database and insert it into a MySQL table.

b8's lexer has been partially re-written. It should now be able to handle all kind of non-latin-1 input, like Cyrillic, Chinese or Japanese texts. Caused by this fact, much more tokens will be recognized when classifying such texts. Therefore, you could get different results in b8's ratings, even if the same database is used and although the math is still the same.

b8 0.5 introduced two constants that can be used in the ``learn()`` and ``unlearn()`` functions: ``b8::HAM`` and ``b8::SPAM``. The literal values "ham" and "spam" can still be used anyway.

Update from b8 0.5.*
--------------------

The lexer and the degenerator can now be configured via an additional config array. If you want to use the new lexer and/or degenerator config, read through the `Configuration`_ section.

The database format has changed. There's an update script for DBA and MySQL databases which can be found in the directory ``update/``. Simply edit the configuration array inside the respective script and run it. A new database with the current structure (v3) will be created. When the update was okay, simply replace your current database with the new one or change your configuration in a way that the new database will be used by b8.

The ``validate()`` functions have been removed in favor of throwing exceptions when something goes wrong instantiating b8. So if you set up b8 like this

::

    $b8 = new b8($config_b8, $config_storage);

    $started_up = $b8->validate();

    if($started_up !== true) {
        echo "Error: ", $started_up;
        do_something();
    }

you will have to change your code to something like this:

::

    try {
        $b8 = new b8\b8($config_b8, $config_storage, $config_lexer, $config_degenerator);
    }
    catch(Exception $e) {
        echo "Error: ", $e->getMessage();
        do_something();
    }

When an error occurs while instantiating b8, the object will simply not be created.

Update from b8 0.6.*
--------------------

**Major Enhancement Release**

Enhanced Features
--------------------

This version introduces **optional TF-IDF weighting and N-gram analysis** for significantly improved classification accuracy. These features are backward-compatible and can be enabled gradually.

Key changes:

* **Database version 4**: Updated internal schema for enhanced features
* **New enhanced lexer** (\ ``\B8\lexer\Enhanced``\ ): Supports TF-IDF and N-grams
* **New enhanced degenerator** (\ ``\B8\degenerator\Enhanced``\ ): Handles N-gram degeneration
* **Performance optimizations**: Batch IDF calculations, prepared statement caching, optimized N-gram generation
* **Critical bug fixes**: DBA initialization, MySQL SQL injection vulnerability, token data access

Migration steps:

1. **Database upgrade required**: Run the update script in ``update/`` to migrate from v3 to v4
2. **Configuration update**: Add new optional parameters (see `Configuration`_ section)
3. **Namespace usage**: All code now uses strict namespaces (PHP 7.4+ required)
4. **Backward compatibility**: Existing code will continue to work; enhanced features are opt-in

Installation
============

Installing b8 on your server is quite easy. You just have to provide the needed files. To do this, you could just upload the whole ``b8`` subdirectory to the base directory of your homepage. It contains the filter itself and all needed backend classes. The other directories (``doc``, ``example`` and ``install``) are not used by b8.

That's it ;-)

Configuration
=============

The configuration is passed as arrays when instantiating a new b8 object. Four arrays can be passed to b8. One containing b8's base configuration, one for the storage backend, one for the lexer and one for the degenerator. |br|
You can have a look at ``example/index.php`` to see how this can be done. `Using b8 in your scripts`_ also shows example code showing how b8 can be included in a PHP script.

Not all values have to be set. When some values are missing, the default ones will be used. If you do use the default settings, you don't have to pass them to b8. But of course, if you want to set something in e.g. the fourth config array, but not in the third, you will have to pass an empty ``array()`` or ``[]`` as third parameter anyway.

b8's base configuration
-----------------------

All these values can be set in the "config_b8" array (the first parameter) passed to b8.

These are some basic settings telling b8 which backend classes to use:

    **storage**
        This defines which storage backend will be used to save b8's wordlist. It's the name of the class in the ``\B8\storage`` namespace. b8 comes with three backends: ``dba`` (Berkeley DB), ``mysql`` (MySQLi), and ``sqlite`` (SQLite3). Default: ``dba`` (string).

    **lexer**
        The lexer class to be used. Options: ``standard`` (default) or ``enhanced`` (with TF-IDF/N-gram support). Default: ``standard`` (string).

    **degenerator**
        The degenerator class to be used. Options: ``standard`` (default) or ``enhanced`` (supports N-gram degeneration). Default: ``standard`` (string).

The following settings influence the mathematical internals of b8. If you want to experiment, feel free to play around with them; but be warned: wrong settings of these values will result in poor performance or could even "short-circuit" the filter. Leave these values as they are unless you know what you are doing.

The "Statistical discussion about b8" [#b8statistic]_ shows why the default values are the default ones.

    **use_relevant**
        This tells b8 how many tokens should be used to calculate the spamminess of a text. The default setting is ``15`` (integer). This seems to be a quite reasonable value. When using too many tokens, the filter will fail on texts filled with useless stuff or with passages from a newspaper, etc. not being very spammish. |br|
        The tokens counted multiple times (see above) are added in addition to this value. They don't replace other interesting tokens.

    **min_dev**
        This defines a minimum deviation from 0.5 that a token's rating must have to be considered when calculating the spamminess. Tokens with a rating closer to 0.5 than this value will simply be skipped. |br|
        If you don't want to use this feature, set this to ``0``. Defaults to ``0.2`` (float). Read [#b8statistic]_ before increasing this.

    **rob_x**
        This is Gary Robinson's *x* constant (cf. [#spamdetection]_). A completely unknown token will be rated with the value of ``rob_x``. The default ``0.5`` (float) seems to be quite reasonable, as we can't say if a token that also can't be rated by degeneration is good or bad. |br|
        If you receive much more spam than ham or vice versa, you could change this setting accordingly.

    **rob_s**
        This is Gary Robinson's *s* constant. This is essentially the probability that the *rob_x* value is correct for a completely unknown token. It will also shift the probability of rarely seen tokens towards this value. The default is ``0.3`` (float) |br|
        See [#spamdetection]_ for a closer description of the *s* constant and read [#b8statistic]_ for specific information about this constant in b8's algorithms.

    **use_tfidf** (NEW)
        Enable TF-IDF weighting for enhanced classification accuracy. Requires ``enhanced`` lexer. Default: ``false`` (boolean).

    **use_ngrams** (NEW)
        Enable N-gram analysis (2-3 word phrases) for better pattern detection. Requires ``enhanced`` lexer. Default: ``false`` (boolean).

    **degenerate_ngrams** (NEW)
        Enable degeneration of N-grams when using enhanced degenerator. Default: ``true`` (boolean).

Configuration of the storage backend
------------------------------------

The used storage backend itself defines what it wants to have passed in it's configuration array. The three example backends have this configuration:

The Berkeley DB (DBA) backend
`````````````````````````````
**resource**
    The DBA resource to use (to be set up via e.g. ``$db = dba_open('wordlist.db', 'w', 'db4');``).

The (example) MySQL backend
```````````````````````````

**resource**
    The mysqli object to use (to be created via e.g. ``$mysql = new mysqli('localhost', 'user', 'pass', 'database');``).

**table**
    The table containing b8's wordlist.

The (example) SQLite3 backend
`````````````````````````````
**resource**
The SQLite3 object to use (to be created via e.g. ``$sqlite = new SQLite3('wordlist.db');``).

**table** (optional)
The table name. Defaults to ``b8_wordlist`` if not specified.

Configuration of the lexer
--------------------------

The lexer disassembles the text we want to analyze to single words ("tokens"). The way it does this can be customized.

All the following values can be set in the "config_lexer" array (the third parameter) passed to b8.

Standard lexer options
--------------------
**min_size**
    The minimal length for a token to be considered. Defaults to ``3`` (integer).

**max_size**
    The maximal length for a token to be considered. Defaults to ``30`` (integer).

**allow_numbers**
    Should pure numbers also be considered? Defaults to ``false`` (boolean).

**get_uris**
    Look for URIs. Defaults to ``true`` (boolean).

**get_html**
    Extracts HTML tags. Defaults to ``true`` (boolean).

**get_bbcode**
    Extracts BBCode. Defaults to ``false`` (boolean).

Enhanced lexer options (NEW)
--------------------
When using ``enhanced`` lexer, these additional options are available:

**use_tfidf**
Enable TF-IDF weighting in this lexer instance. Default: ``false`` (boolean).

**use_ngrams**
Enable N-gram generation. Default: ``false`` (boolean).

**ngram_size**
Starting N-gram size (minimum 2). Default: ``2`` (integer).

**max_ngram_size**
Maximum N-gram size. Default: ``3`` (integer).

**max_ngram_length**
Maximum characters per N-gram. Default: ``60`` (integer).

**idf_storage**
Reference to IDF calculator instance (automatically set by b8 when TF-IDF is enabled).

Configuration of the degenerator
--------------------------------

Standard degenerator options
--------------------
**multibyte**
    Use multibyte operations when searching for degenerated versions. Defaults to ``true`` (boolean).

**encoding**
    The internal encoding for multibyte operations. Defaults to ``UTF-8`` (string).

Enhanced degenerator options (NEW)
--------------------
When using ``enhanced`` degenerator, these additional options are available:

**degenerate_ngrams**
Enable degeneration of N-gram tokens. Default: ``true`` (boolean).

**max_ngram_variants**
Maximum degenerated variants per N-gram (prevents combination explosion). Default: ``20`` (integer).

Using b8
========

Now, that everything is configured, you can start to use b8. A sample script that shows what can be done with the filter can be found in ``example/``. Using this script, you can test how all this works before integrating b8 in your own scripts.

Before you can start, you have to setup a database so that b8 can store a wordlist.

Setting up a new database
-------------------------

Setting up a new Berkeley DB
--------------------

There's a script that automates setting up a new Berkeley DB for b8. It is located at ``install/setup_berkeleydb.php``. Just run this script on your server and be sure that the directory containing it has the proper access rights set so that the server's HTTP server user or PHP user can create a new file in it (probably ``0777``). The script is quite self-explaining, just run it.

If you prefer to setup a new b8 Berkeley DB manually, just create an empty database and insert the following values:

::

    "b8*dbversion" => "4"
    "b8*texts"     => "0 0"

Setting up a new MySQL table
--------------------

The SQL file ``install/setup_mysql.sql`` contains both the ``CREATE`` statement for the wordlist table of b8 and the ``INSERT`` statements for adding the necessary internal variables.

Simply change the table name according to your needs (or leave it as it is ;-) and run the SQL to setup a MySQL b8 wordlist table.

Setting up a new SQLite table
--------------------

The SQL file ``install/setup_sqlite.sql`` contains the ``CREATE`` statement for SQLite3. Run it using your SQLite3 client or programmatically.

Using b8 in your scripts
--------------------

Just have a look at the example script ``example/index.php`` to see how you can include b8 in your scripts. Essentially, this strips down to:

::

    # Include b8's code (using composer or autoloader)
    require_once($path_to . 'vendor/autoload.php');

    # Do some configuration
    $config_b8 = [
        'storage' => 'dba',
        'lexer' => 'enhanced',        # NEW: Use enhanced features
        'degenerator' => 'enhanced',  # NEW: For N-gram support
        'use_tfidf' => true,          # NEW: Enable TF-IDF
        'use_ngrams' => true,         # NEW: Enable N-grams
        'use_relevant' => 15,
        'rob_s' => 0.3,
        'rob_x' => 0.5
    ];
    
    $config_storage = [ 'resource' => $db ];
    $config_lexer = [
        'min_size' => 3,
        'max_size' => 30,
        'use_tfidf' => true,    # Confirm enabling
        'use_ngrams' => true,   # Confirm enabling
        'max_ngram_size' => 3
    ];
    $config_degenerator = [
        'multibyte' => true,
        'degenerate_ngrams' => true
    ];

    # Create a new b8 instance
    try {
        $b8 = new \B8\B8($config_b8, $config_storage, $config_lexer, $config_degenerator);
    }
    catch(Exception $e) {
        echo "Error: ", $e->getMessage();
        do_something();
    }

b8 provides three functions in an object oriented way (called e.g. via ``$b8->classify($text)``):

**classify(string $text)**
    This function takes the text ``$text`` (string), calculates it's probability for being spam and returns it in the form of a value between 0 and 1 (float). |br|
    A value close to 0 says the text is more likely ham and a value close to 1 says the text is more likely spam. What to do with this value is *your* business ;-) See also `Tips on operation`_ below.

**learn(string $text, string $category)**
    This saves the text ``$text`` (string) in the category ``$category`` (b8 constant, either ``\B8\B8::HAM`` or ``\B8\B8::SPAM``).

**unlearn(string $text, string $category)**
    You don't need this function in normal operation. It just exists to delete a text from a category in which is has been stored accidentally before. It deletes the text ``$text`` (string) from the category ``$category`` (b8 constant, either ``\B8\B8::HAM`` or ``\B8\B8::SPAM``). |br|
    **Don't delete a spam text from ham after saving it in spam or vice versa, as long you don't have stored it accidentally in the wrong category before!** This will *not* improve performance, quite the opposite! The filter will always try to remove texts from the ham or spam data, even if they have never been stored there. The counters for tokens which are found will be decreased or the word will be deleted and the non-existing words will simply be ignored. But always, the text counter for the respective category will be decreased by 1 and will eventually reach 0. Consequently, the ham-spam texts proportion will become distorted, deteriorating the performance of b8's algorithms.

Tips on operation
=================

Before b8 can decide whether a text is spam or ham, you have to tell it what you consider as spam or ham. At least one learned spam or one learned ham text is needed to calculate anything. With nothing learned, b8 will rate everything with 0.5 (or whatever ``rob_x`` has been set to). To get good ratings, you need both learned ham and learned spam texts, the more the better. |br|
What's considered as ham or spam can be very different, depending on the operation site. On my homepage, practically each and every text posted in English or using non-latin-1 letters is spam. On an English or Russian homepage, this will be not the case. So I think it's not really meaningful to provide some "spam data" to start. Just train b8 with "your" spam and ham.

For the practical use, I advise to give the filter all data availible. E. g. name, email address, homepage and of course the text itself should be assembled in a variable (e.g. separated with an ``\n`` or just a space or tab after each block) and then be classified. The learning should also be done with all data availible. |br|
Saving the IP address is probably only meaningful for spam entries, because spammers often use the same IP address multiple times. In principle, you can leave out the IP of ham entries.

You can use b8 e.g. in a guestbook script and let it classify the text before saving it. Everyone has to decide which rating is necessary to classify a text as "spam", but a rating of >= 0.8 seems to be reasonable for me. If one expects the spam to be in another language that the ham entries or the spams are very short normally, one could also think about a limit of 0.7. |br|
The email filters out there mostly use > 0.9 or even > 0.99; but keep in mind that they have way more data to analyze in most of the cases. A guestbook entry may be quite short, especially when it's spam.

**Performance Tip**: When using enhanced features, consider setting a higher classification threshold (e.g., 0.85) as TF-IDF and N-grams provide higher confidence scores.

In my opinion, an autolearn function is very handy. I save spam messages with a rating higher than 0.7 but less than 0.9 automatically as spam. I don't do this with ham messages in an automated way to prevent the filter from saving a false negative as ham and then classifying and learning all the spam as ham when I'm on holidays ;-)

Learning spam or ham that has already been rated very high or low will not make spam detection better (as b8 already could classify the text correctly!) but probably only blow the database. So don't do that.

**NEW: Performance Optimization Tips**:
* **Batch operations**: When classifying multiple texts, reuse the b8 instance to benefit from IDF caching
* **Database tuning**: Add an index on ``count_ham`` and ``count_spam`` columns for large datasets
* **Memory usage**: The degenerator cache grows with unique tokens; consider calling ``clear_cache()`` periodically in long-running processes

Closing
=======

So … that's it. Thanks for using b8! If you find a bug or have an idea how to make b8 better, let me know. I'm also always looking forward to hear from people using b8 and I'm curious where it's used :-)

References
==========

.. [#planforspam] Paul Graham, *A Plan For Spam* (http://paulgraham.com/spam.html )
.. [#betterbayesian] Paul Graham, *Better Bayesian Filtering* (http://paulgraham.com/better.html )
.. [#spamdetection] Gary Robinson, *Spam Detection* (http://radio.weblogs.com/0101454/stories/2002/09/16/spamDetection.html )
.. [#statisticalapproach] *A Statistical Approach to the Spam Problem* (http://linuxjournal.com/article/6467 )
.. [#b8statistic] Tobias Leupold, *Statistical discussion about b8* (http://nasauber.de/opensource/b8/discussion/ )

Appendix
========

FAQ
---

What about TF-IDF and N-grams? How do they improve classification?
--------------------

TF-IDF (Term Frequency-Inverse Document Frequency) weights tokens by their importance in the document relative to the training corpus. This means:
* Common spam phrases like "click here" get higher weight if they appear frequently in spam but rarely in ham
* Common words like "the" get lower weight

N-grams capture contextual patterns that single words miss:
* "buy now" as a phrase is more meaningful than "buy" and "now" separately
* "free money" patterns are strong spam indicators

**Implementation note**: Training always uses raw counts to maintain Bayesian statistical validity. TF-IDF weights are applied only during classification as probability multipliers.

Do TF-IDF and N-grams require more memory?
--------------------

Yes, but optimizations minimize the impact:
* **IDF calculator**: Caches document frequencies for the current instance
* **N-grams**: Limited by ``max_ngram_size`` and ``max_ngram_length`` config options
* **Degenerator cache**: Enhanced version caches N-gram degenerates; call ``clear_cache()`` periodically in long-running processes

Typical memory increase: 10-30% depending on text length and N-gram settings. The accuracy gain far outweighs the memory cost.

Can I enable TF-IDF without N-grams (or vice versa)?
--------------------

Yes! They are independent features:

::

    # TF-IDF only
    'use_tfidf' => true, 'use_ngrams' => false
    
    # N-grams only
    'use_tfidf' => false, 'use_ngrams' => true
    
    # Both enabled (recommended for maximum accuracy)
    'use_tfidf' => true, 'use_ngrams' => true

TF-IDF works best with larger training corpora (1000+ documents). N-grams are effective even with smaller datasets.

What about more than two categories?
--------------------

I wrote b8 with the `KISS principle <http://en.wikipedia.org/wiki/KISS_principle >`__ in mind. For the "end-user", we have a class with almost no setup to do that can do three things: classify a text, learn a text and un-learn a text. Normally, there's no need to un-learn a text, so essentially, there are only two functions we need for the everyday use. |br|
This simplicity is only possible because b8 only knows two categories and tells you, in one float number between 0 and 1, if a given texts rather fits in the first or the second category. If we would support multiple categories, more work would have to be done and things would become more complicated. One would have to setup the categories, have another database layout (perhaps making it mandatory to have SQL) and one float number would not be sufficient to describe b8's output, so more code would be needed – even outside of b8.

All the code, the database layout and particularly the math is intended to do exactly one thing: distinguish between two categories. I think it would be a lot of work to change b8 so that it would support more than two categories. Probably, this is possible to do, but don't ask me in which way we would have to change the math to get multiple-category support ;-) |br|
Apart from this I do believe that most people using b8 don't want or need multiple categories. They just want to know if a text is spam or not, don't they? I do, at least ;-)

But let's think about the multiple-category thing. How would we calculate a rating for more than two categories? If we had a third one, let's call it "`Treet <http://en.wikipedia.org/wiki/Treet >`__", how would we calculate a rating? We could calculate three different ratings. One for "Ham", one for "Spam" and one for "Treet" and choose the highest one to tell the user what category fits best for the text. This could be done by using a small wrapper script using three instances of b8 as-is and three different databases, each containing texts being "Ham", "Spam", "Treet" and the respective counterparts. |br|
But here's the problem: if we have "Ham" and "Spam", "Spam" is the counterpart of "Ham". But what's the counterpart of "Spam" if we have more than one additional category? Where do the "Non-Ham", "Non-Spam" and "Non-Treet" texts come from?

Another approach, a direct calculation of more than two probabilities (the "Ham" probability is simply 1 minus the "Spam" probability, so we actually get two probabilities with the return value of b8) out of one database would require big changes in b8's structure and math.

There's a project called `PHPNaiveBayesianFilter <http://xhtml.net/scripts/PHPNaiveBayesianFilter >`__ which supports multiple categories by default. The author calls his software "Version 1.0", but I think this is the very first release, not a stable or mature one. The most recent change of that release dates back to 2003 according to the "changed" date of the files inside the zip archive, so probably, this project is dead or has never been alive and under active development at all. |br|
Actually, I played around with that code but the results weren't really good, so I decided to write my own spam filter from scratch back in early 2006 ;-)

All in all, there seems to be no easy way to implement multiple (meaning more than two) categories using b8's current code base and probably, b8 will never support more than two categories. Perhaps, a fork or a complete re-write would be better than implementing such a feature. Anyway, I don't close my mind to multiple categories in b8. Feel free to tell me how multiple categories could be implementented in b8 or how a multiple-category version using the same code base (sharing a common abstract class?) could be written.

What about a list with words to ignore?
--------------------

Some people suggested to introduce a list with words that b8 will simply ignore. Like "and", "or", "the", and so on. I don't think this is very meaningful.

First, it would just work for the particular language that has been stored in the list. Speaking of my homepage, most of my spam is English, almost all my ham is German. So I would have to maintain a list with the probably less interesting words for at least two languages. Additionally, I get spam in Chinese, Japanese or Cyrillic writing or something else I can't read as well. What word should be ignored in those texts? |br|
Second, why should we ever exclude words? Who tells us those words are *actually* meaningless? If a word appears both in ham and spam, it's rating will be near 0.5 and so, it won't be used for the final calculation anyway if a appropriate minimum deviation was set. So b8 will exclude it without having to maintain a blacklist. And think of this: if we excluded a word of which we only *think* it doesn't mean anything but it actually does appear more often in ham or spam, the results will get even worse.

So why should we care about things we do not have to care about? ;-)

**NEW**: TF-IDF automatically handles this by giving lower weight to common words and higher weight to distinctive ones, eliminating the need for manual ignore lists.

Why is it called "b8"?
--------------------

The initial name for the filter was (damn creative!) "bayes-php". There were two main reasons for searching another name: 1. "bayes-php" sucks. 2. the `PHP License <http://php.net/license/3_01.txt >`_ says the PHP guys do not like when the name of a script written in PHP contains the word "PHP". Read the `License FAQ <http://www.php.net/license/index.php#faq-lic >`_ for a reasonable argumentation about this.

Luckily, `Tobias Lang <http://langt.net/ >`_ proposed the new name "b8". And these are the reasons why I chose this name:

- "bayes-php" is a "b" followed by 8 letters.
- "b8" is short and handy. Additionally, there was no program with the name "b8" or "bate"
- The English verb "to bate" means "to decrease" – and that's what b8 does: it decreases the number of spam entries in your weblog or guestbook!
- "b8" just sounds way cooler than "bayes-php" ;-)

The database layout
--------------------

The database layout is quite simple. It's essentially just a key-value pair for everything stored. There are two "internal" variables stored as normal tokens. A lexer must not provide a token starting with ``b8*``, otherwise, we will probably get collisions. The internal tokens are:

**b8*dbversion**
    This indicates the database's version. Current version: **4**

**b8*texts**
    The number of ham and spam texts learned.

Each "normal" token is stored with it's literal name as the key and it's data as the value. The backends store the token's data in a different way. The DBA backend simply stores a string containing both values separated by a space character. The SQL backends store the counters in different columns.

A database query is always done by searching for a token's name, never for a count value.

**NEW for version 4**: When using TF-IDF, additional internal tokens are created with the prefix ``idf*`` to store document frequencies. These are automatically managed by the IDF calculator.