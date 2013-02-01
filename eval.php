<?php
    session_start();
    
    // Get database handle.
    require_once('db.php');

    // Get the $user_id for the current user.
    $sql = 'SELECT rowid FROM user WHERE session_id = :session_id;';
    $sth = $dbh->prepare($sql);
    $sth->execute(array(':session_id' => session_id()));
    $user_id = $sth->fetchColumn();
    
    // Get the $conversation_id, $other_user_id and $other_user_ready for the current user.
    $sql = 'SELECT rowid, shopkeep_id, customer_id, animal_given, animal_paid
            FROM conversation
            WHERE customer_left
              AND active
              AND ((shopkeep_id = :user_id AND shopkeep_eval = 0)
                   OR (customer_id = :user_id AND customer_eval = 0));';
    $sth = $dbh->prepare($sql);
    $sth->execute(array(':user_id' => $user_id));
    $conversation = $sth->fetch();
    
    // Redirect to main page if we can't find a conversation to evaluate.
    if ( $user_id === false || $conversation === false ) {
        header('Location: index.php');
        exit;
    }
    
    // Load questionairre config to define Likert items.
    require_once('inc/quest.php');
    
    // Globals for form input processing.
    $gameInputCorrect = true;
    $generalInputCorrect = true;

    // Process form submit.
    if ( isset($_POST) && isset($_POST['submit']) && count($_POST) > 1 ) {
    
        // Test form input for current game.
        foreach ( $quest_gameItems as $item ) {
            $name = $item->name;
            if ( !isset($_POST[$name]) || !in_array($_POST[$name], array('1','2','3','4','5')) ) {
                $gameInputCorrect = false;
                break;
            }
        }
        
        if ( $_POST['submit'] == 'Submit questionnaire' ) {
            // Test form input for general questionnaire.
            foreach ( $quest_generalItems as $item ) {
                $name = $item->name;
                if ( !isset($_POST[$name]) || !in_array($_POST[$name], array('1','2','3','4','5')) ) {
                    $generalInputCorrect = false;
                    break;
                }
            }
        }
    
        // If the form input for current game was correct.
        if ( $gameInputCorrect && $generalInputCorrect ) {
    
            // Dynamically generate SQL for the update.
            $updateGameEvalFields = array();
            $updateGameEvalValues = array();
            foreach ( $quest_gameItems as $item ) {
                $name = $item->name;
                $updateGameEvalFields[] = "shopkeep_eval_$name = IF(shopkeep_id = :user_id, :$name, shopkeep_eval_$name)";
                $updateGameEvalFields[] = "customer_eval_$name = IF(customer_id = :user_id, :$name, customer_eval_$name)";
                $updateGameEvalValues[":$name"] = $_POST[$name];
            }
            $updateGameEvalFields = implode(',', $updateGameEvalFields);
    
            // Update user's questionnaire for the last conversation.
            $sql = "UPDATE conversation
                    SET shopkeep_eval = IF(shopkeep_id = :user_id, 1, shopkeep_eval),
                        customer_eval = IF(customer_id = :user_id, 1, customer_eval),
                        $updateGameEvalFields
                    WHERE active AND rowid = :conversation_id AND (shopkeep_id = :user_id OR customer_id = :user_id);";
            $sth = $dbh->prepare($sql);
            $sth->execute(array_merge(array(':conversation_id' => $conversation['rowid'],
                                            ':user_id' => $user_id),
                                            $updateGameEvalValues));

            if ( $_POST['submit'] == 'Play again!' ) {
                // Redirect to game page.
                header('Location: game.php');
                exit;
            } elseif ( $_POST['submit'] == 'Submit questionnaire' ) {
            
                // Dynamically generate SQL for the update.
                $updateGeneralEvalFields = array();
                $updateGeneralEvalValues = array();
                foreach ( $quest_generalItems as $item ) {
                    $name = $item->name;
                    $updateGeneralEvalFields[] = "eval_$name = :$name";
                    $updateGeneralEvalValues[":$name"] = $_POST[$name];
                }                    
                $updateGeneralEvalFields = implode(',', $updateGeneralEvalFields);
            
                // Update user's general questionnaire.
                $sql = "UPDATE user SET $updateGeneralEvalFields WHERE rowid = :user_id;";
                $sth = $dbh->prepare($sql);
                $sth->execute(array_merge(array(':user_id' => $user_id),
                                                $updateGeneralEvalValues));
            
                // Remove session.
                session_unset();
                session_destroy();
                session_write_close();
                setcookie(session_name(),'',0,'/');
                session_regenerate_id(true);
                
                // Redirect to home page.
                header('Location: index.php');
                exit;
            }
        }
    }

    require_once('_/html/start.html');

    // Get total games played for the current user.
    $sql = 'SELECT COUNT(rowid)
            FROM conversation
            WHERE customer_left
              AND (shopkeep_id = :user_id OR customer_id = :user_id);';
    $sth = $dbh->prepare($sql);
    $sth->execute(array(':user_id' => $user_id));
    $totalGamesPlayed = $sth->fetchColumn();
    
    // Function to print as fieldset containing a Likert item.
    function likertItem($name, $statement, $required) {
        $required = $required ? 'required' : '';
        return "<fieldset>
          <legend>$statement</legend>
          <label> <input $required type=radio name=$name value=1>Strongly disagree</label>
          <label> <input $required type=radio name=$name value=2>Disagree</label>
          <label> <input $required type=radio name=$name value=3>Neither agree nor disagree</label>
          <label> <input $required type=radio name=$name value=4>Agree</label>
          <label> <input $required type=radio name=$name value=5>Strongly agree</label>
         </fieldset>";
    }
?>

<h1 class="balloon">Questionnaire</h1>
<p class="notification">Thanks for playing! Please fill out this short questionnaire. Select the answer to each statement which corresponds most closely to your desired response.</p>
 <?php if ( !$gameInputCorrect || !$generalInputCorrect ) { echo '<span class="error">There was an error in the input. Please fill out the form completely.</span>'; } ?>

<form id="user_eval" method="post">
     <div id="game_eval" class="fields">
         <h2 class="balloon">In the last game...</h2>
         <?php            
            shuffle($quest_gameItems);            
            foreach ( $quest_gameItems as $item ) {
                echo likertItem($item->name, $item->statement, true);
            }
         ?>
     </div>
     <div id="gen_eval" class="fields" style="display: none;">
         <h2 class="balloon">During the entire playing session (<?php echo $totalGamesPlayed, ( $totalGamesPlayed == 1 ? ' conversation' : ' conversations' ); ?>)...</h2>
         <?php            
            shuffle($quest_generalItems);            
            foreach ( $quest_generalItems as $item ) {
                echo likertItem($item->name, $item->statement, false);
            }
         ?>   
     </div>
     <input type="submit" id="submit_eval" name="submit" value="Play again!">
     <input type="submit" id="goto_gen_eval" name="submit" value="I'm done playing">
</form>

<?php
    require_once('_/html/script.php');
?>

<script>
$(function () {        
    $("#user_eval").validate({
        errorPlacement: function(error, element) {
            error.appendTo("label[for="+$(element).attr('id')+"]");
        },
        errorElement: "em"
    });
    $('#user_eval').removeAttr('novalidate');
    
    $("#goto_gen_eval").click(function() {

        // Confirm player wants to go to general questionnaire.
        if ( !confirm("Are you sure you want to stop playing?") ) {
            return;
        }

        $("#gen_eval").show();
        $("#gen_eval input").attr('required','required');
        $("#submit_eval").val('Submit questionnaire');
        $(this).hide();
        return false;
    });
});
</script>

<?php
    require_once('_/html/end.html');
?>
