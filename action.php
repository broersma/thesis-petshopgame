<?php

// Add event to show up in log.
function addActionToLog($conversation_id, $user_id, $shopkeepLine, $customerLine='') {
    global $dbh;
    
    $line = $shopkeepLine;
    if ( !empty($customerLine) ) {
        $line .= '|' . $customerLine;    
    }

    // Insert into database.
    $sql = "INSERT INTO log (conversation_id, user_id, is_event, timestamp, line) VALUES (:conversation_id, :user_id, 1, UTC_TIMESTAMP, :line)";        
    $sth = $dbh->prepare($sql);                    
    $sth->execute(array(':conversation_id' => $conversation_id, ':user_id' => $user_id, ':line' => $line));
}

if ( $_SERVER['REQUEST_METHOD'] == 'POST' ) {

    // Set up database and get $user_id for current user.
    require_once('events_shared.php');
    require_once('inc/config_events.php');
    
    // Deactivate conversation with AFK users.
    $sql = 'UPDATE conversation
                JOIN user AS shopkeep ON shopkeep_id = shopkeep.rowid
                LEFT OUTER JOIN user AS customer ON customer_id = customer.rowid
            SET active = 0, timed_out = 1
            WHERE active
              AND NOT customer_left
              AND shopkeep.last_activity < UTC_TIMESTAMP - INTERVAL :seconds SECOND
              AND (customer_id IS NULL OR customer.last_activity < UTC_TIMESTAMP - INTERVAL :seconds SECOND);';
    $sth = $dbh->prepare($sql);
    $sth->execute(array(':seconds'=>$events_afkTime));

    // Get the $conversation_id. $other_user_id and $other_user_ready for the current user.
    // Select a conversation we are either in, or which we can join.
    $sql = 'SELECT rowid, shopkeep_id, shopkeep_ready,
                   customer_id, customer_ready, animal_given,
                   animal_paid, customer_left
            FROM conversation
            WHERE active
              AND ((shopkeep_id = :user_id AND shopkeep_eval = 0)
                   OR (customer_id = :user_id AND customer_eval = 0)
                   OR (shopkeep_id != :user_id AND customer_id IS NULL
                       # Make sure we do not join a conversation with someone we played our last (=ORDER BY rowid DESC LIMIT 1) game with.
                       AND IFNULL(shopkeep_id != (SELECT IF(shopkeep_id=:user_id,customer_id,shopkeep_id) AS other_user_id
                                                    FROM conversation
                                                   WHERE (shopkeep_id = :user_id AND shopkeep_eval = 1)
                                                      OR (customer_id = :user_id AND customer_eval = 1)
                                                ORDER BY rowid DESC LIMIT 1),
                                  TRUE)
                      )
                  ) LIMIT 1;';
    $sth = $dbh->prepare($sql);
    $sth->execute(array(':user_id' => $user_id));

    $conversation = $sth->fetch();
    $conversation_id = $conversation['rowid'];
    $user_ready = false;
    $other_user_id = null;
    $other_user_ready = false;
    if ( $conversation === false ) {
        // Start new conversation.
        $sql = "INSERT INTO conversation (timestamp_a, shopkeep_id) VALUES (UTC_TIMESTAMP, :user_id);";
        $sth = $dbh->prepare($sql);
        
        // Wrap lastInsertId in a transaction to be sure we get the right value.
        $dbh->beginTransaction();
        $sth->execute(array(':user_id' => $user_id));
        $conversation_id = $dbh->lastInsertId();
        $dbh->commit();
        
    } elseif ( empty($conversation['customer_id']) && $conversation['shopkeep_id'] != $user_id ) {
        // Join conversation.
        $sql = "UPDATE conversation SET timestamp_b = UTC_TIMESTAMP, customer_id = :user_id WHERE active AND customer_id IS NULL AND rowid = :conversation_id;";
        $sth = $dbh->prepare($sql);
        $sth->execute(array(':user_id' => $user_id, ':conversation_id' => $conversation_id));

        // Dispatch event to other user, who becomes the shopkeeper and starts playing.
        $other_user_id = $conversation['shopkeep_id'];
        $other_user_ready = $conversation['shopkeep_ready'];
        $event = array('event_name' => 'show_slideshow', 'role' => 'shopkeep');
        postEvent($other_user_id, $event);
                
        // Add the customer enters the store event. Make it random whose id is coupled to this, as it decides who gets to start the game.
        addActionToLog($conversation_id, ( rand(0, 1) ? $user_id : $other_user_id ), 'A customer enters the pet shop.', 'You enter the pet shop.');
        
    } else {
        // Already in a conversation with someone.        
        if ( $user_id == $conversation['shopkeep_id'] ) {
            $user_ready = $conversation['shopkeep_ready'];
            $other_user_id = $conversation['customer_id'];
            $other_user_ready = $conversation['customer_ready'];
        } else {
            $user_ready = $conversation['customer_ready'];
            $other_user_id = $conversation['shopkeep_id'];
            $other_user_ready = $conversation['shopkeep_ready'];
        }
    }

    ////////////////////////////////////////////////////////////////////////////

    if ( isset($_POST['action']) ) {
                
        // Test if the player is the shopkeep.
        $is_shopkeep = ($user_id == $conversation['shopkeep_id']);
                            
        // Test if it is this player's turn.
        $sql = 'SELECT log.user_id FROM log, conversation WHERE conversation.active AND log.conversation_id = :conversation_id ORDER BY timestamp DESC LIMIT 1;';
        $sth = $dbh->prepare($sql);
        $sth->execute(array(':conversation_id' => $conversation['rowid']));
        $last_user_id = $sth->fetchColumn();
        $is_turn = ($last_user_id!==false?$last_user_id != $user_id:$is_shopkeep);
                        
        switch ( $_POST['action'] ) {

            // User loaded the page.
            case 'page_loaded':
                // Dispatch event to current user.
                if ( empty($other_user_id) ) {
                    $event = array('event_name' => 'waiting_for_other_player');
                    postEvent($user_id, $event);
                } else {
                    // Test if game is running.
                    if ( $conversation['customer_left'] ) {                        
                        $event = array('event_name' => 'go_to_evaluation');
                        postEvent($user_id, $event);                   
                    
                    } else {
                        $event = null;
                        if ( $user_ready ) {
                            if ( $other_user_ready ) {
                                postEvent($user_id, array('event_name' => 'start_game', 'role' => ($is_shopkeep?'shopkeep':'customer'),
                                                          'turn' => $is_turn, 'animal_given' => (bool)$conversation['animal_given'],
                                                          'animal_paid' => (bool)$conversation['animal_paid'] ));
                            } else {
                                // NOTE The order of these events is important.
                                postEvent($user_id, array('event_name' => 'show_slideshow', 'role' => ($is_shopkeep?'shopkeep':'customer')));
                                postEvent($user_id, array('event_name' => 'waiting_for_other_player' ));
                            }
                        } else {
                            postEvent($user_id, array('event_name' => 'show_slideshow', 'role' => ($is_shopkeep?'shopkeep':'customer') ));
                        }
                    }
                }
                break;

            // User has seen the intro.
            case 'proceed':
                
                $sql = '';
                if ( $is_shopkeep ) {
                    $sql = "UPDATE conversation SET shopkeep_ready = 1 WHERE conversation.active AND conversation.rowid = :conversation_id;";
                } else {
                    $sql = "UPDATE conversation SET customer_ready = 1 WHERE conversation.active AND conversation.rowid = :conversation_id;";
                }
                $sth = $dbh->prepare($sql);
                $sth->execute(array(':conversation_id' => $conversation_id));
                
                if ( $other_user_ready ) {
                    
                    // Send events to user.
                    $event = array('event_name' => 'start_game', 'role' => ($is_shopkeep?'shopkeep':'customer'), 'turn' => $is_turn, 'animal_given' => (bool)$conversation['animal_given'], 'animal_paid' => (bool)$conversation['animal_paid'] );
                    postEvent($user_id, $event);
                       
                    // Send events to other user.
                    $event = array('event_name' => 'start_game', 'role' => (!$is_shopkeep?'shopkeep':'customer'), 'turn' => !$is_turn, 'animal_given' => (bool)$conversation['animal_given'], 'animal_paid' => (bool)$conversation['animal_paid'] );
                    postEvent($other_user_id, $event);

                } else {
                    postEvent($user_id, array('event_name' => 'waiting_for_other_player' ));
                }
                break;

            // User send a new chat.
            case 'new_chat':            
                if ( $is_turn && isset($_POST['line']) ) { 
                    // Insert into database.
                    $sql = "INSERT INTO log (conversation_id, user_id, timestamp, line) VALUES (:conversation_id, :user_id, UTC_TIMESTAMP, :line)";        
                    $sth = $dbh->prepare($sql);                    
                    $sth->execute(array(':conversation_id' => $conversation_id, ':user_id' => $user_id, ':line' => $_POST['line']));

                    // Send event to other user.
                    $event = array('event_name' => 'new_log', 'animal_given' => (bool)$conversation['animal_given'], 'animal_paid' => (bool)$conversation['animal_paid']);
                    if ( !empty($other_user_id) ) {
                        postEvent($other_user_id, $event);       
                    }
                }
                break;
                
            // Player actions.
            case 'give_animal':
                if ( $is_turn && $user_id === $conversation['shopkeep_id'] ) {
                    // Update conversation.
                    $sql = "UPDATE conversation SET animal_given = 1 WHERE conversation.active AND conversation.rowid = :conversation_id;";
                    $sth = $dbh->prepare($sql);
                    $sth->execute(array(':conversation_id' => $conversation_id));
                    
                    addActionToLog($conversation_id, $user_id, 'You give the animal to the customer.', 'The shopkeep gives the animal to you.');
                
                    // Send event to other user.
                    $event = array('event_name' => 'new_log', 'animal_given' => true, 'animal_paid' => (bool)$conversation['animal_paid']);
                    if ( !empty($other_user_id) ) {
                        postEvent($other_user_id, $event);
                    }
                }
                break;
            case 'pay':
                if ( $is_turn && $user_id === $conversation['customer_id'] ) {
                    // Update conversation.
                    $sql = "UPDATE conversation SET animal_paid = 1 WHERE conversation.active AND conversation.rowid = :conversation_id;";
                    $sth = $dbh->prepare($sql);
                    $sth->execute(array(':conversation_id' => $conversation_id));
                    
                    addActionToLog($conversation_id, $user_id, 'The customer pays for the animal.', 'You pay the shopkeep for the animal.');
                
                    // Send event to other user.
                    $event = array('event_name' => 'new_log', 'animal_given' => (bool)$conversation['animal_given'], 'animal_paid' => true);
                    if ( !empty($other_user_id) ) {
                        postEvent($other_user_id, $event);
                    }
                }
                break;
            case 'leave':
                if ( $is_turn && $user_id === $conversation['customer_id'] ) {
                    addActionToLog($conversation_id, $user_id, 'The customer leaves the store.', 'You leave the store.');
                    
                    // Update conversation.
                    $sql = "UPDATE conversation SET customer_left = 1 WHERE conversation.active AND conversation.rowid = :conversation_id;";
                    $sth = $dbh->prepare($sql);
                    $sth->execute(array(':conversation_id' => $conversation_id));                    
                    
                    // Send event to other user.
                    if ( !empty($other_user_id) ) {
                        $event = array('event_name' => 'new_log', 'animal_given' => (bool)$conversation['animal_given'], 'animal_paid' => (bool)$conversation['animal_paid']);
                        postEvent($other_user_id, $event);
                        
                        $event = array('event_name' => 'go_to_evaluation');
                        postEvent($other_user_id, $event);
                        
                        $event = array('event_name' => 'go_to_evaluation');
                        postEvent($user_id, $event);
                    }
                }
                break;
        }
    }
}
?>
