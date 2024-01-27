<?php

// SPDX-FileCopyrightText: none
//
// SPDX-License-Identifier: CC0-1.0

echo <<<END
<?xml version="1.0" encoding="UTF-8"?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN"
   "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">

<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">

<head>

<title>b8 Berkeley DB setup</title>

<meta http-equiv="content-type" content="text/html; charset=UTF-8"/>

<meta name="dc.creator" content="Tobias Leupold"/>
<meta name="dc.rights" content="Copyright (c) Tobias Leupold"/>

</head>

<body>

<div>

<h1>b8 Berkeley DB setup</h1>


END;

function setup_db()
{
    $dbfile = $_POST['dbfile'];
    $dbfile_escaped = htmlentities($dbfile);
    $dbfile_directory = $_SERVER['DOCUMENT_ROOT'] . dirname($_SERVER['PHP_SELF']);

    echo 'Checking database file name &hellip; ';
    if($dbfile == '') {
        echo "<span style=\"color:red;\">Please provide the name of the database file!</span><br/>\n";
        return false;
    }
    echo "$dbfile_escaped<br/>\n";

    echo "Touching/Creating $dbfile_escaped &hellip; ";
    if(touch($dbfile) === false) {
        echo "<span style=\"color:red;\">Failed to touch the database file. Please check the given filename and/or fix the permissions of $dbfile_directory.</span><br/>\n";
        return false;
    }
    echo "done<br/>\n";

    echo 'Setting file permissions to 0666 &hellip; ';
    if (chmod($dbfile, 0666) === false) {
        echo "<span style=\"color:red;\">Failed to change the permissions of $dbfile_directory$dbfile_escaped. Please adjust them manually.</span><br />\n";
        return false;
    }
    echo "done<br/>\n";

    echo 'Checking if the given file is empty &hellip; ';
    if (filesize($dbfile) > 0) {
        echo "<span style=\"color:red;\">$dbfile_directory$dbfile_escaped is not empty. Can't create a new database. Please delete/empty this file or give another filename.</span><br />\n";
        return false;
    }
    echo "it is<br/>\n";

    echo "Connecting to $dbfile_escaped &hellip; ";
    $db = dba_open($dbfile, "c", $_POST['handler']);
    if ($db === false) {
        echo "<span style=\"color:red;\">Could not connect to the database!</span><br/>\n";
        return false;
    }
    echo "done<br/>\n";

    echo 'Storing necessary internal variables &hellip; ';
    $internals = [ 'b8*dbversion' => '3',
                   'b8*texts'     => '0 0' ];
    foreach($internals as $key => $value) {
        if(dba_insert($key, $value, $db) === false) {
            echo "<span style=\"color:red;\">Failed to insert data!</span><br/>\n";
            return false;
        }
    }
    echo "done<br/>\n";

    echo 'Trying to read data from the database &hellip; ';
    $dbversion = dba_fetch('b8*dbversion', $db);
    if($dbversion != '3') {
        echo "<span style=\"color:red;\">Failed to read data!</span><br />\n";
        return false;
    }
    echo "success<br/>\n";

    dba_close($db);
    echo "</p>\n\n";
    echo "<p style=\"color:green;\">Successfully created a new b8 database!</p>\n\n";
    echo "<table>\n";
    echo "<tr><td>Filename:</td><td>$dbfile_directory$dbfile_escaped</td></tr>\n";
    echo "<tr><td>DBA handler:</td><td>{$_POST['handler']}</td><tr>\n";
    echo "</table>\n\n";
}

$failed = false;

if (isset($_POST['handler'])) {
    echo "<h2>Creating database</h2>\n\n";
    echo "<p>\n";
    $failed = ! setup_db();
    echo "</p>\n\n";
}

if($failed === true or ! isset($_POST['handler'])) {

echo <<<END
<form action="{$_SERVER['PHP_SELF']}" method="post">

<h2>DBA Handler</h2>

<p>
The following table shows all available DBA handlers. Please choose the "Berkeley DB" one.
</p>

<table>
<tr><td></td><td><b>Handler</b></td><td><b>Description</b></td></tr>

END;

foreach(dba_handlers(true) as $name => $version) {
    $checked = "";

    if (! isset($_POST['handler'])) {
        if (strpos($version, "Berkeley") !== false) {
            $checked = " checked=\"checked\"";
        }
    } else {
        if ($_POST['handler'] == $name) {
            $checked = " checked=\"checked\"";
        }
    }

    echo "<tr><td><input type=\"radio\" name=\"handler\" value=\"$name\"$checked /></td><td>$name</td><td>$version</td></tr>\n";
}

echo <<<END
</table>

<h2>Database file</h2>

<p>
Please give the name of the desired database file. It will be created in the directory where this script is located.
</p>

<p>
<input type="text" name="dbfile" value="wordlist.db" />
</p>

<p>
<input type="submit" value="Create the database" />
</p>

</form>


END;

}

?>

</div>

</body>

</html>
