<?php

require_once('./inc/config.php');

// Get database handle.
$dbh = $dbh = new PDO(
    $db_string,
    $db_user,
    $db_pass
); 
$dbh->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
?>
