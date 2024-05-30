<?php
    if ( !isset( $installation_id ) || !isset( $region ) || !isset( $language_code ) ) {
        die();
    }
    $installation_id = htmlspecialchars( $installation_id, ENT_QUOTES, "UTF-8" );
    $region          = htmlspecialchars( $region, ENT_QUOTES, "UTF-8" );
    $language_code   = htmlspecialchars( $language_code, ENT_QUOTES, "UTF-8" );
?>
<?php if ( ! empty( $language_code ) ): ?>
<script>
  (function(w, k) {w[k] = window[k] || function () { (window[k].q = window[k].q || []).push(arguments) }})(window, "doofinderApp")

  doofinderApp("config", "language", "<?php echo $language_code; ?>")
</script>
<?php endif; ?>
<script src="https://<?php echo $region; ?>-config.doofinder.com/2.x/<?php echo $installation_id; ?>.js" async></script>
