<?php
/** Local, shared dismissal for the single Fy2Press discovery campaign. */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Pagup_Fy2Press_Discovery_V1', false ) ) {
    final class Pagup_Fy2Press_Discovery_V1 {
        private const CAMPAIGN = 'fy2press-discovery-v1';
        private static $parents = array();
        private static $rendered = false;

        public static function register( $page, $variant, $plugin_file, $extra_capability = '' ) {
            if ( empty( self::$parents ) ) {
                add_action( 'admin_post_pagup_fy2press_dismiss_v1', array( __CLASS__, 'dismiss' ) );
            }
            self::$parents[$page] = array(
                'variant' => $variant,
                'plugin_file' => $plugin_file,
                'extra_capability' => $extra_capability,
            );
        }

        private static function allowed( $page ) {
            if ( ! isset( self::$parents[$page] ) || ! is_admin() || is_network_admin()
                || ( function_exists( 'wp_doing_ajax' ) ? wp_doing_ajax() : ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST )
                || ! current_user_can( 'manage_options' ) ) {
                return false;
            }
            $extra = self::$parents[$page]['extra_capability'];
            return '' === $extra || current_user_can( $extra );
        }

        private static function key() {
            return 'pagup_promo_fy2press_v1_dismissed_' . get_current_blog_id();
        }

        private static function installed() {
            if ( defined( 'SHOPIPRESS_VERSION' ) || defined( 'FY2PRESS_VERSION' ) ) {
                return true;
            }
            if ( ! function_exists( 'get_plugins' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            // Includes inactive editions. Also recognize the legacy
            // ShopiPress entry point; folder renames are tolerated.
            foreach ( get_plugins() as $basename => $headers ) {
                if ( in_array( basename( $basename ), array( 'shopipress.php', 'fy2press.php' ), true )
                    && in_array( $headers['TextDomain'] ?? '', array( 'shopipress', 'fy2press' ), true ) ) {
                    return true;
                }
            }
            return false;
        }

        public static function render( $page ) {
            if ( self::$rendered || ! self::allowed( $page ) ) {
                return;
            }
            $screen = get_current_screen();
            if ( ! $screen || 'toplevel_page_' . $page !== $screen->id
                || get_user_meta( get_current_user_id(), self::key(), true ) || self::installed() ) {
                return;
            }
            $locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
            $language = strtolower( substr( $locale, 0, 2 ) );
            $language = in_array( $language, array( 'en', 'fr', 'es', 'de', 'it', 'nl', 'pt', 'ru' ), true ) ? $language : 'en';
            // Immutable local catalog, like the parent's existing admin labels.
            $catalog = json_decode( file_get_contents( __DIR__ . '/fy2press-copy.json' ), true );
            $copy = null;
            foreach ( $catalog['locales'][$language] ?? array() as $candidate ) {
                if ( $candidate['id'] === self::$parents[$page]['variant'] ) {
                    $copy = $candidate;
                    break;
                }
            }
            if ( ! $copy ) {
                return;
            }
            self::$rendered = true;
            $url = 'https://fy2press.com/' . ( 'fr' === $language ? 'fr-ca/' : ( 'es' === $language ? 'es/' : '' ) );
            $image = plugins_url( 'admin/assets/fy2press/f2-icon-256.png', self::$parents[$page]['plugin_file'] );
            ?>
            <style>
                .pagup-fy2press-card{box-sizing:border-box;max-width:1000px;margin:28px 20px 28px 0;padding:24px;border:1px solid #e5d9e4;border-radius:8px;background:#fff;color:#211b29;font:15px/1.6 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;overflow-wrap:anywhere}
                .pagup-fy2press-card *{box-sizing:border-box}
                .pagup-fy2press-brand{display:flex;gap:12px;align-items:center;color:#751445;font-weight:600}
                .pagup-fy2press-card h2{font-size:22px;line-height:1.35;margin:18px 0 10px;color:#211b29}
                .pagup-fy2press-card p{font-size:15px;line-height:1.6;margin:10px 0 20px}
                .pagup-fy2press-actions{display:flex;gap:14px;align-items:center;flex-wrap:wrap}
                .pagup-fy2press-card a{display:inline-block;max-width:100%;padding:12px 18px;background:#b80062;color:#fff;text-decoration:none;border-radius:5px;font-weight:600;line-height:1.5}
                .pagup-fy2press-card form{margin:0}
                .pagup-fy2press-card button{font:inherit;min-height:44px;padding:10px 2px;border:0;background:transparent;text-decoration:underline;color:#59515d;cursor:pointer;text-align:left;white-space:normal}
                .pagup-fy2press-card :is(a,button):focus-visible{outline:3px solid #005fcc;outline-offset:3px}
                @media(max-width:480px){.pagup-fy2press-card{margin:20px 10px 20px 0;padding:20px}.pagup-fy2press-card h2{font-size:21px}}
            </style>
            <aside class="pagup-fy2press-card" aria-labelledby="pagup-fy2press-title" data-fy2press-campaign="fy2press-discovery-v1">
                <div class="pagup-fy2press-brand"><img src="<?php echo esc_url( $image ); ?>" width="48" height="48" alt=""> <span>Fy2Press · PAGUP</span></div>
                <h2 id="pagup-fy2press-title"><?php echo esc_html( $copy['title'] ); ?></h2>
                <p><?php echo esc_html( $copy['body'] ); ?></p>
                <div class="pagup-fy2press-actions">
                    <a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $copy['cta'] ); ?></a>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <input type="hidden" name="action" value="pagup_fy2press_dismiss_v1">
                        <input type="hidden" name="campaign" value="fy2press-discovery-v1">
                        <input type="hidden" name="origin_page" value="<?php echo esc_attr( $page ); ?>">
                        <?php wp_nonce_field( self::CAMPAIGN . ':' . get_current_blog_id() . ':' . $page, 'fy2press_nonce', false ); ?>
                        <button type="submit"><?php echo esc_html( $copy['dismiss'] ); ?></button>
                    </form>
                </div>
            </aside>
            <?php
        }

        public static function dismiss() {
            $page = isset( $_POST['origin_page'] ) && is_string( $_POST['origin_page'] ) ? sanitize_key( wp_unslash( $_POST['origin_page'] ) ) : '';
            $campaign = isset( $_POST['campaign'] ) && is_string( $_POST['campaign'] ) ? sanitize_text_field( wp_unslash( $_POST['campaign'] ) ) : '';
            $nonce = isset( $_POST['fy2press_nonce'] ) && is_string( $_POST['fy2press_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['fy2press_nonce'] ) ) : '';
            $method = isset( $_SERVER['REQUEST_METHOD'] ) && is_string( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
            if ( 'POST' !== $method || self::CAMPAIGN !== $campaign
                || ! self::allowed( $page ) || ! get_current_user_id()
                || ! wp_verify_nonce( $nonce, self::CAMPAIGN . ':' . get_current_blog_id() . ':' . $page ) ) {
                wp_die( 'Invalid Fy2Press dismissal request.', '', array( 'response' => 403 ) );
            }
            update_user_meta( get_current_user_id(), self::key(), 1 );
            wp_safe_redirect( admin_url( 'admin.php?page=' . $page ) );
            exit;
        }
    }
}
