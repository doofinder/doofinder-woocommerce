<?php use Doofinder\WC\Transient_Log; ?>

<style>
    p.submit {
        display: none;
    }
</style>

<p><em><?php

_e( 'This section is for developers.', 'woocommerce-doofinder' ); ?></em></p>

<pre class="doofinder-log"><?php

$log = Transient_Log::get_log();
if ( $log && is_array( $log ) ) {
    foreach ( $log as $entry ):
        ?><div class="entry"><?php
            print_r( $entry );
        ?></div><?php
    endforeach;
}

?></pre>

<?php

return array();
