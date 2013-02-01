<?php 

// Get database handle.
require_once('db.php');

$sql = 'SELECT log.rowid, log.*, conversation.shopkeep_id
        FROM log, conversation, user
        WHERE log.conversation_id = conversation.rowid
          AND ((conversation.shopkeep_id = user.rowid AND NOT conversation.shopkeep_eval)
           OR  (conversation.customer_id = user.rowid AND NOT conversation.customer_eval))
          AND conversation.active
          AND user.session_id = :session_id
          ORDER BY timestamp DESC;';
$sth = $dbh->prepare($sql);

session_start();
session_write_close();

$sth->execute(array(':session_id' => session_id()));
$rows = $sth->fetchAll();

// Get user id.
$sql = 'SELECT rowid FROM user WHERE session_id = :session_id;';
$sth = $dbh->prepare($sql);
$sth->execute(array(':session_id' => session_id()));
$user_id = $sth->fetchColumn();


if ( $rows ) {
    foreach ($rows as $row)
    {
        $class = ($row['is_event'] ? 'event' : ($user_id ===$row['user_id']? 'left' : 'right'));
        $line = $row['line'];
        
        // Check if we need to split the line, in case of an event.
        $lines = explode('|', $line);
        if ( $row['is_event'] && count($lines) > 1 ) {
            $line = ($user_id === $row['shopkeep_id'] ? $lines[0] : $lines[1]);
        }
        
        echo '<div class="balloon ' . $class . '"><span class="line">' . $line . '</span></div>';
    }  
}

?>
