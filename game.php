<?php
    session_start();
    require_once('_/html/start.html');
?>

<div id="debug"></div>
<div id="status"></div>
<div id="slideshow"></div>
<div id="game">
    <div id="input">
        <div id="action_input"class="balloon"> (ends turn)</div>
        <div id="speak_input" class="balloon"></div>
    </div>
    <div id="balloon_wrapper" class="balloon left">
        <div id="balloon"></div>
        <div id="balloon_buttons">
            <button id="clear" title="Clears the text balloon so you can start a new utterance." type="button"><img src="_/img/clear.png" alt="Clear" /> Clear text balloon</button>
            <button id="speak" title="Utter the complete sentences in the text balloon. This will end your turn." type="button"><img src="_/img/speak.png" alt="Speak" /> Speak (ends turn)</button>
        </div>
    </div> 
    <div id="chat"></div>
</div>

<?php
    require_once('_/html/script.php');
?>

<script type><?php require_once('inc/config.php'); if ( $site_env == 'prd' ) { echo 'window.onbeforeunload = function () { "use strict"; return "Reloading or leaving the page might end your current game."; };'; } ?></script>
<script type="text/javascript" src="game.js"></script>

<?php
    require_once('_/html/end.html');
?>
