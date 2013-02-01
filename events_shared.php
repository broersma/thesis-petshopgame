<?php
// Start session.
session_start();

// Get database handle.
require_once('db.php');

// Get the $user_id for the current user.
$sql = 'SELECT rowid FROM user WHERE session_id = :session_id;';
$sth = $dbh->prepare($sql);
$sth->execute(array(':session_id' => session_id()));
$user_id = $sth->fetchColumn();
if ( $user_id === false ) {
    // Create a new user
    $sql = "INSERT INTO user (session_id, last_activity) VALUES (:session_id, UTC_TIMESTAMP);";
    $sth = $dbh->prepare($sql);
    
    // Wrap lastInsertId in a transaction to be sure we get the right value.
    $dbh->beginTransaction();
    $sth->execute(array(':session_id' => session_id()));
    $user_id = $dbh->lastInsertId();
    $dbh->commit();    
} else {
    // Update the user's last activity.
    $sql = "UPDATE user SET last_activity = UTC_TIMESTAMP WHERE rowid = :user_id;";
    $sth = $dbh->prepare($sql);
    $sth->execute(array(':user_id' => $user_id));
}

// Post event to event queue.
function postEvent($user_id, $event) {
    global $dbh;

    // Insert into database.
    $sql = "INSERT INTO event (user_id, json) VALUES (:user_id, :json);";
    $sth = $dbh->prepare($sql);
    $sth->execute(array(':user_id' => $user_id, ':json' => json_encode($event)));
}
?>
