<?php

namespace Doofinder\WP;

class Admin_Notices
{
    public static function init()
    {
        add_action('admin_notices', function () {
            $notices = self::get_custom_notices();
            if (!empty($notices)) {
                foreach ($notices as $notice_id => $notice) {
                    if (isset($notice['is_custom']) && $notice['is_custom']) {
                        self::render_custom_notice($notice['html']);
                    } else {
                        self::render_notice($notice_id, $notice);
                    }
                }
            }
        });
    }

    public static function add_notice($notice_name, $title, $message, $type = 'info', $extra = null, $classes = '', $dismissible = false)
    {
        $current_notices = self::get_custom_notices();
        $current_notices[$notice_name] = [
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'extra' => $extra,
            'classes' => $classes,
            'dismissible' => $dismissible
        ];
        update_option('doofinder_for_wp_notices', $current_notices);
    }


    public static function add_custom_notice($notice_name, $html)
    {
        $current_notices = self::get_custom_notices();
        $current_notices[$notice_name] = [
            'is_custom' => true,
            'html' => $html
        ];
        update_option('doofinder_for_wp_notices', $current_notices);
    }

    public static function remove_notice($id)
    {
        if (!self::is_notice_active($id)) {
            return;
        }

        $current_notices = self::get_custom_notices();
        if (array_key_exists($id, $current_notices)) {
            unset($current_notices[$id]);
            update_option('doofinder_for_wp_notices', $current_notices);
        }
    }


    public static function get_custom_notices()
    {
        return get_option('doofinder_for_wp_notices', []);
    }

    public static function render_notice($notice_id, $notice)
    {

        $classes = "wordpress-message df-notice migration-complete " . $notice['classes']
?>
        <div class="notice notice-<?php echo $notice['type'] ?> <?php echo ($notice['dismissible']) ? "is-dismissible" : ""; ?>">
            <div id=" <?php echo $notice_id; ?>" class="<?php echo $classes; ?>">
                <div class="df-notice-row">
                    <div class="df-notice-col logo">
                        <figure class="logo" style="width:5rem;height:auto;float:left;margin:.5em 0;margin-right:0.75rem;">
                            <img src="<?php echo Doofinder_For_WordPress::plugin_url() . 'assets/svg/imagotipo1.svg'; ?>" />
                        </figure>
                    </div>
                    <div class="df-notice-col content">
                        <?php
                        if (!empty($notice['title'])) :
                        ?>
                            <h3><?php echo $notice['title'] ?></h3>
                        <?php
                        endif;
                        ?>
                        <p>
                            <?php echo $notice['message'] ?>
                        </p>
                    </div>
                    <?php
                    if (!empty($notice['extra'])) :
                    ?>
                        <div class="df-notice-col extra align-center">
                            <?php echo $notice['extra'] ?>
                        </div>
                    <?php
                    endif;
                    ?>
                </div>
            </div>
        </div>
<?php
    }

    public static function render_custom_notice($notice_html)
    {
        echo $notice_html;
    }

    public static function is_notice_active($notice_name)
    {
        $current_notices = self::get_custom_notices();
        return array_key_exists($notice_name, $current_notices);
    }
}
