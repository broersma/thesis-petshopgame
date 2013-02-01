<?php

if ( $_SERVER['REQUEST_METHOD'] == 'GET' ) {
    
    // Set up database and get $user_id for current user.
    require_once('events_shared.php');
    require_once('inc/config_events.php');

    // Configuration options for Comet script.
    $script_time = $events_scriptTime;
    $response_time = $events_responseTime;

    // Ignore user abort so we can handle it (not doing that at the moment, though).
    ignore_user_abort(true);
    set_time_limit(0);

    // ob_end_flush is required for flush to work.
    ob_end_flush();

    // Close session so we won't block.
    session_write_close();

    // Select unsent events.
    $firedEvents = array();
    $sthSelect = $dbh->prepare('SELECT json FROM event WHERE user_id = :user_id AND NOT sent;');
    $sthSelect->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    // Prepare to mark unsent events as sent.
    $sthUpdate = $dbh->prepare('UPDATE event SET sent = 1 WHERE user_id = :user_id AND NOT sent;');
    $sthUpdate->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    // Prepare to find if the other player is AFK.
    $sthUpdateAfk = $dbh->prepare('UPDATE conversation
                                   LEFT OUTER JOIN user ON user.rowid = IF(shopkeep_id=:user_id, customer_id, shopkeep_id)
                                   SET active = 0, timed_out = 1
                                   WHERE active
                                     AND NOT customer_left
                                     AND (customer_id = :user_id OR shopkeep_id = :user_id)
                                     AND user.last_activity < UTC_TIMESTAMP - INTERVAL :seconds SECOND;');
    $sthUpdateAfk->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $sthUpdateAfk->bindValue(':seconds', $events_afkTime, PDO::PARAM_INT);
    
    $loops = $script_time / $response_time; // number of loops
    while ( $loops-- && !connection_aborted() ) {

        // Check other player AFK.
        $sthUpdateAfk->execute();
        if ( $sthUpdateAfk->rowCount() > 0 ) {
            $event = array('event_name' => 'other_player_afk');
            postEvent($user_id, $event);
        }

        // Check events.
        $sthSelect->execute();
        $firedEvents = $sthSelect->fetchAll(PDO::FETCH_COLUMN, 0);
        if ( !empty($firedEvents) ) {
            $sthUpdate->execute();
            break;
        }

        // Sleep for $response_time microseconds.
        usleep($response_time);

        // To check if browser is still alive using connection_aborted we need to send some output and flush it.
        echo " ";
        flush();
    }

    // Return events since the script was called as JSON.
    if ( !connection_aborted() ) {
        exit('[' . implode(',', $firedEvents) . ']');
    }
}
?>
