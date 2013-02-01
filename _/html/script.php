<!-- Grab Google CDN's jQuery. fall back to local if necessary -->
<script src="//ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js"></script>
<script>window.jQuery || document.write("<script src='_/js/jquery-1.7.1.min.js'>\x3C/script>")</script>

<!-- Grab Microsft CDN's jQuery.validate. fall back to local if necessary -->
<script src="//ajax.aspnetcdn.com/ajax/jquery.validate/1.9/jquery.validate.min.js"></script>
<script>jQuery().validate || document.write("<script src='_/js/jquery.validate-1.9.0.min.js'>\x3C/script>")</script>

<!-- this is where we put our custom functions -->
<script src="_/js/functions.js"></script>

<?php require_once('inc/config.php'); if ( $site_env == 'prd' ) { ?>

<!-- Asynchronous google analytics; this is the official snippet.-->
<script>

  var _gaq = _gaq || [];
  _gaq.push(['_setAccount', 'UA-30710869-1']);
  _gaq.push(['_trackPageview']);

  (function() {
    var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
    ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
    var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
  })();

</script>

<?php } ?>
