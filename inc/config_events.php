<?php
    $events_scriptTime = 10000000; //TODO set to 60s: 60000000; // Maximum time in microseconds each request to this script should last.
    $events_responseTime =2000000; // Worst case response time in microseconds. Average case will be half of this.
    $events_afkTime = 20; //TODO set to 120; Time in seconds before a player is considered AFK. Should be more than ($events_scriptTime + timeout for get_events in functions.js).
?>
