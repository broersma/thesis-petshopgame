<?php require_once('_/html/start.html'); ?>

<header>

<h1 class="balloon right front">The Pet Shop Game - a collective intelligence research project</h1>

</header>

<article>

<h2 class="balloon left front">Introduction</h2>

<p>
Computers have huge potential, but they can't talk as well as humans. This project is part of an effort to change that.
</p>

<p>The Pet Shop Game is a <em>two-player turn-based conversational role-playing game</em>. Every turn you either choose a role-specific action or speak an utterance. Everything the players do and say will be logged. In the end, all of of the data across all games will be combined and fed through advanced computer algorithms that will create a super-AI to control the world... err... to make science with.
</p>

<h2 class="balloon left front">How to play</h2>

<p>
When you start playing the Pet Shop Game you will be anonymously coupled with another player. Then you will be assigned a role (either <strong>customer</strong> or <strong>shopkeep</strong>) and you will receive a character background.
</p>

<p>
Now the game can truly start. On your turn you will be presented with a screen that looks like this:
</p>

<p style="text-align:center;">
<img src="_/img/example.png" alt="Example screen" />
</p>

<p>
In the top left, in the <span class="example purple">purple</span> area, are your <strong>actions</strong>. Directly below, in the <span class="example lightblue">lightblue</span> text balloon, you can create an utterance to <strong>speak</strong>. This is done by clicking the buttons in the top right <span class="example red">red</span> area, and thereby filling out the <span class="blank">blanks</span> in the text balloon. Both <strong>performing an action</strong> by clicking on its button and <strong>speaking an utterance</strong> by clicking the <em>Speak</em> button will end your current turn.
</p>

<p>
At the bottom of the screen are all previous messages. These come in three colours: <span class="example darkblue">darkblue</span> for your utterances, <span class="example darkgreen">green</span> for the other player's utterances, and <span class="example orange">orange</span> for actions.
</p>

<p>
 Regardless of having purchased a new pet, your current game is finished when the customer leaves the pet shop. Feel free, however, to play as many games as you want!
</p>

<h2 class="balloon left front">Ready to play?</h2>

<noscript><span class="error" style="border:1px solid red;margin-left: 40px;">JavaScript is disabled but it is required for playing the Pet Shop Game!</span></noscript>
<div class="balloon event front" style="text-align:center;width:150px;"><a href="game.php" style="text-decoration:none;"><div style="color:#fff;">Click here to play!<br/><sub>(JavaScript must be enabled)</sub></div></a></div>


</article>

<?php require_once('_/html/script.php'); ?>

<!-- page specific scripts: -->
<script>
</script>

<?php require_once('_/html/end.html'); ?>
