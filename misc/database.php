<?php 

require_once('../inc/config.php');
$dbh = $dbh = new PDO(
    $db_string,
    $db_user,
    $db_pass
); 
$dbh->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function showRow($values, $element='td') {
    
    echo '<tr>';
    foreach ( $values as $value ) {
        echo '<',$element,'>',$value,'</',$element,'>';
    }
    echo '</tr>';

}
             
function showTable($tableName) {

    global $dbh;
       
    echo '<h1>',$tableName,'</h1>';
    
    $rows = $dbh->query('SELECT * FROM ' . $tableName . ';');

    echo '<table id="', $tableName,'">';
    if ( $rows->rowCount() > 0 ) {
        
        // Show header row.
        $firstRow = $rows->fetch();
        showRow(array_keys($firstRow), 'th');
        showRow($firstRow);
    
        foreach ($rows as $row) // prints invalid resource
        {           
          showRow($row);
        }
    }
    echo '</table>';
}

       
showTable('conversation');
showTable('user');
showTable('log');
showTable('event');

?>
