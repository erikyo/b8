<?php

/* SPDX-FileCopyrightText: none

   SPDX-License-Identifier: CC0-1.0
*/

/***************************************************************
 * This is an example script demonstrating how b8 can be used. *
 ***************************************************************/

/******************************
// Use this code block if you want to use Berkeley DB (you should ;-).

// First create an empty database (e. g. using the install/setup_berkeleydb.php script)
// and move it here, so that the following resource creation will work:
$db = dba_open('wordlist.db', 'w', 'db4');

// Tell b8 to use the above Berkeley DB
$config_b8      = [ 'storage'  => 'dba' ];
$config_storage = [ 'resource' => $db ];
*******************************/

/*******************************
// Use this code block if you want to use a MySQL table

// Be sure to provide appropriate access data and have the respective table set up
// (e. g. run the SQL to be found in install/setup_mysql.sql)
$mysql = new mysqli('localhost', 'user', 'pass', 'database');

// Tell b8 to use the above MySQL resource
$config_b8      = [ 'storage'  => 'mysql' ];
$config_storage = [ 'resource' => $mysql,
                    'table'    => 'b8_wordlist' ];
*******************************/

// We use the default lexer settings
$config_lexer = [];

// We use the default degenerator configuration
$config_degenerator = [];
// If you don't have PHP's mbstring module, set 'multibyte' to false:
//$config_degenerator = [ 'multibyte' => false ];

/**************************
 * Here starts the script *
 **************************/

$time_start = null;

function microtimeFloat()
{
    list($usec, $sec) = explode(' ', microtime());
    return (float) $usec + (float) $sec;
}

// Output a nicely colored rating

function formatRating($rating)
{
    if ($rating === false) {
        return '<span style="color:red">could not calculate spaminess</span>';
    }

    $red   = floor(255 * $rating);
    $green = floor(255 * (1 - $rating));
    return "<span style=\"color:rgb($red, $green, 0);\"><b>" . sprintf("%5f", $rating)
           . "</b></span>";
}

echo <<<END
<?xml version="1.0" encoding="UTF-8"?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN"
   "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">

<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">

<head>

<title>example b8 interface</title>

<meta http-equiv="content-type" content="text/html; charset=UTF-8" />

<meta name="dc.creator" content="Tobias Leupold" />
<meta name="dc.rights" content="Copyright (c) by Tobias Leupold" />

</head>

<body>

<div>

<h1>example b8 interface</h1>


END;

if (! isset($config_b8)) {
    echo "<p style=\"color:red;\"><b>Please adjust the settings in this file first!</b></p>\n\n";
    echo "\n\n</div>\n\n</body>\n\n</html>";
    exit();
}

$postedText = '';

if (isset($_POST['action']) and $_POST['text'] ==  '') {
    echo "<p style=\"color:red;\"><b>Please type in a text!</b></p>\n\n";
} elseif(isset($_POST['action']) and $_POST['text'] != '') {
    $time_start = microtimeFloat();

    // Include the b8 code
    require __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'b8'
            . DIRECTORY_SEPARATOR . 'b8.php';

    # Create a new b8 instance
    try {
        $b8 = new b8\b8($config_b8, $config_storage, $config_lexer, $config_degenerator);
    }
    catch(Exception $e) {
        echo "<b>example:</b> Could not initialize b8.<br/>\n";
        echo "<b>Error message:</b> ", $e->getMessage();
        echo "\n\n</div>\n\n</body>\n\n</html>";
        exit();
    }

    $text = stripslashes($_POST['text']);
    $postedText = htmlentities($text, ENT_QUOTES, 'UTF-8');

    switch($_POST['action']) {
        case 'Classify':
            echo '<p><b>Spaminess: ' . formatRating($b8->classify($text)) . "</b></p>\n";
            break;

        case 'Save as Spam':
            $ratingBefore = $b8->classify($text);
            $b8->learn($text, b8\b8::SPAM);
            $ratingAfter = $b8->classify($text);

            echo "<p>Saved the text as Spam</p>\n\n";
            echo "<div><table>\n";
            echo '<tr><td>Classification before learning:</td><td>' . formatRating($ratingBefore)
                 . "</td></tr>\n";
            echo '<tr><td>Classification after learning:</td><td>'  . formatRating($ratingAfter)
                 . "</td></tr>\n";
            echo "</table></div>\n\n";

            break;

        case 'Save as Ham':
            $ratingBefore = $b8->classify($text);
            $b8->learn($text, b8\b8::HAM);
            $ratingAfter = $b8->classify($text);

            echo "<p>Saved the text as Ham</p>\n\n";

            echo "<div><table>\n";
            echo '<tr><td>Classification before learning:</td><td>' . formatRating($ratingBefore)
                 . "</td></tr>\n";
            echo '<tr><td>Classification after learning:</td><td>'  . formatRating($ratingAfter)
                 . "</td></tr>\n";
            echo "</table></div>\n\n";

            break;

        case 'Delete from Spam':
            $b8->unlearn($text, b8\b8::SPAM);
            echo "<p style=\"color:green\">Deleted the text from Spam</p>\n\n";
            break;

        case 'Delete from Ham':
            $b8->unlearn($text, b8\b8::HAM);
            echo "<p style=\"color:green\">Deleted the text from Ham</p>\n\n";
            break;

    }

    $mem_used      = round(memory_get_usage() / 1048576, 5);
    $peak_mem_used = round(memory_get_peak_usage() / 1048576, 5);
    $time_taken    = round(microtimeFloat() - $time_start, 5);
}

echo <<<END
<div>
<form action="{$_SERVER['PHP_SELF']}" method="post">
<div>
<textarea name="text" cols="50" rows="16">$postedText</textarea>
</div>
<table>
<tr>
<td><input type="submit" name="action" value="Classify" /></td>
</tr>
<tr>
<td><input type="submit" name="action" value="Save as Spam" /></td>
<td><input type="submit" name="action" value="Save as Ham" /></td>
</tr>
<tr>
<td><input type="submit" name="action" value="Delete from Spam" /></td>
<td><input type="submit" name="action" value="Delete from Ham" /></td>
</tr>
</table>
</form>
</div>

</div>

END;

if($time_start !== null) {
echo <<<END
<div>
<table border="0">
<tr><td>Memory used:     </td><td>$mem_used&thinsp;MB</td></tr>
<tr><td>Peak memory used:</td><td>$peak_mem_used&thinsp;MB</td></tr>
<tr><td>Time taken:      </td><td>$time_taken&thinsp;sec</td></tr>
</table>
</div>

END;
}

?>

</body>

</html>
