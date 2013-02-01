<?php 

// Load config for database.
require_once('../inc/config.php');
// Load questionairre config to define Likert items.
require_once('../inc/quest.php');

// Dynamically generate SQL for the table create.
$createGameEvalFields = array();
foreach ( $quest_gameItems as $item ) {
    $name = $item->name;
    $createGameEvalFields[] = "shopkeep_eval_$name INT DEFAULT NULL,customer_eval_$name INT DEFAULT NULL";
}
$createGameEvalFields = implode(',', $createGameEvalFields);
$createGeneralEvalFields = array();
foreach ( $quest_generalItems as $item ) {
    $name = $item->name;
    $createGeneralEvalFields[] = "eval_$name INT DEFAULT NULL";
}
$createGeneralEvalFields = implode(',', $createGeneralEvalFields);

$dbh = $dbh = new PDO(
    $db_string,
    $db_user,
    $db_pass
);
// Set warning so the DROP TABLE queries won't ever screw things up.
$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
                                                                         
$dbh->query("DROP TABLE conversation;");    
$dbh->query("DROP TABLE user;");
$dbh->query("DROP TABLE log;");
$dbh->query("DROP TABLE event;");

$dbh->query("CREATE TABLE conversation (rowid INT(11) PRIMARY KEY AUTO_INCREMENT, active INT DEFAULT 1, timed_out INT DEFAULT 0,
    timestamp_a TEXT, shopkeep_id INT, shopkeep_ready INT DEFAULT 0, shopkeep_eval INT DEFAULT 0,
    timestamp_b TEXT, customer_id INT, customer_ready INT DEFAULT 0, customer_eval INT DEFAULT 0,
    animal_given INT DEFAULT 0, animal_paid INT DEFAULT 0, customer_left INT DEFAULT 0,
    $createGameEvalFields);");   
$dbh->query("CREATE TABLE user (rowid INT(11) PRIMARY KEY AUTO_INCREMENT, session_id VARCHAR(40), last_activity TIMESTAMP DEFAULT 0, $createGeneralEvalFields);");
$dbh->query("CREATE TABLE log (rowid INT(11) PRIMARY KEY AUTO_INCREMENT, conversation_id INT, user_id INT, is_event INT DEFAULT 0, timestamp TEXT, line TEXT);");
$dbh->query("CREATE TABLE event (rowid INT(11) PRIMARY KEY AUTO_INCREMENT, user_id INT, json TEXT, sent INT DEFAULT 0);");

?>
