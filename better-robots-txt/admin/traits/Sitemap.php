<?php

namespace Pagup\BetterRobots\Traits;

trait Sitemap
{
    public function yoast_sitemap() {
        $ch = curl_init( $this->yoast_sitemap_url );
        curl_setopt( $ch, CURLOPT_HEADER, true );
        curl_setopt( $ch, CURLOPT_NOBODY, true );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt( $ch, CURLOPT_TIMEOUT, 10 );
        $curl_output = curl_exec( $ch );
        //print curl_error($ch); // display error if curl is responding with 0
        $yoast_sitemap_header = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        curl_close( $ch );
        return $yoast_sitemap_header;
    }

    public function xml_sitemap() {
        $ch = curl_init( $this->xml_sitemap_url );
        curl_setopt( $ch, CURLOPT_HEADER, true );
        curl_setopt( $ch, CURLOPT_NOBODY, true );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt( $ch, CURLOPT_TIMEOUT, 10 );
        $curl_output = curl_exec( $ch );
        //print curl_error($ch); // display error if curl is responding with 0
        $xml_sitemap_header = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        curl_close( $ch );
        return $xml_sitemap_header;
    }

    public function sitemap_notification() {
        $has_sitemap_feature = false;
        if ( $has_sitemap_feature ) {
            // check if yoast is installed and sitemap is enabled
            if ( class_exists( 'WPSEO_Sitemaps' ) && $this->yoast_sitemap() == "200" ) {
                // yoast is working, sitemap added
                $sitemap_output = '<div class="rt-alert rt-success"><span class="closebtn">&times;</span>' . sprintf( wp_kses( __( '<a href="%s">Yoast Sitemap</a> is working. No need to add anything in sitemap field. You\'re good to go :)', 'better-robots-txt' ), array(
                    'a' => array(
                        'href' => array(),
                    ),
                ) ), esc_url( $this->yoast_sitemap_url ) ) . '</div>';
                // info box notification for different sitemap
                $sitemap_output .= '<div class="rt-alert  rt-square rt-info"><span class="closebtn">&times;</span>' . __( 'Want to use a different sitemap in robots.txt?', 'better-robots-txt' ) . '<br>' . sprintf( wp_kses( __( '1: Disable XML sitemaps from <a href="admin.php?page=wpseo_dashboard#top#features">Yoast Features</a>.', 'better-robots-txt' ), array(
                    'a' => array(
                        'href' => array(),
                    ),
                ) ) ) . '<br>' . sprintf( wp_kses( __( '2: Add your sitemap URL in field above. Check <a href="?page=better-robots-txt&tab=robot-faq">FAQ</a> for more info.', 'better-robots-txt' ), array(
                    'a' => array(
                        'href' => array(),
                    ),
                ) ) ) . '</div>';
            } elseif ( class_exists( 'WPSEO_Sitemaps' ) && $this->yoast_sitemap() == "404" ) {
                // yoast is enabled but sitemap is not
                $sitemap_output = '<div class="rt-alert rt-warning"><span class="closebtn">&times;</span>' . __( 'Yoast SEO is installed and working, but you need to enable XML sitemaps from', 'better-robots-txt' ) . ' <a href="' . home_url() . '/wp-admin/admin.php?page=wpseo_dashboard#top#features">' . __( 'Yoast Features', 'better-robots-txt' ) . '</a>.</div>';
            } elseif ( $this->xml_sitemap() == "200" ) {
                $sitemap_output = '<div class="rt-alert rt-success"><span class="closebtn">&times;</span>' . sprintf( wp_kses( __( '<a href="%s">XML Sitemap</a> is working. No need to add anything in sitemap field. You\'re good to go :)', 'better-robots-txt' ), array(
                    'a' => array(
                        'href' => array(),
                    ),
                ) ), esc_url( $this->xml_sitemap_url ) ) . '</div>';
                // info box notification for different sitemap
                $sitemap_output .= '<div class="rt-alert rt-square rt-info"><span class="closebtn">&times;</span>' . __( 'Want to use a different sitemap in robots.txt?', 'better-robots-txt' ) . '<br>' . sprintf( wp_kses( __( '1: Delete sitemap.xml file from WordPress root or disable plugin which is generating XML sitemap.', 'better-robots-txt' ), array(
                    'a' => array(
                        'href' => array(),
                    ),
                ) ) ) . '<br>' . sprintf( wp_kses( __( '2: Add your sitemap URL in field above. Check <a href="?page=better-robots-txt&tab=robot-faq">FAQ</a> for more info.', 'better-robots-txt' ), array(
                    'a' => array(
                        'href' => array(),
                    ),
                ) ) ) . '</div>';
            } else {
                //yoast/xml sitemap is not working
                $sitemap_output = '<div class="rt-alert rt-warning"><span class="closebtn">&times;</span>' . __( 'We recommend you to install Yoast SEO Plugin. Upload sitemap.xml to WordPress root directory or provide a URL to Sitemap', 'better-robots-txt' ) . '</div>';
            }
        } else {
            if ( class_exists( 'WPSEO_Sitemaps' ) && $this->yoast_sitemap() == "200" ) {
                // yoast is working, sitemap added
                $sitemap_output = '<div class="rt-alert rt-info"><span class="closebtn">&times;</span>' . sprintf( wp_kses( __( '<a href="%s">XML Sitemap</a> detected but not added.', 'better-robots-txt' ), array(
                    'a' => array(
                        'href' => array(),
                    ),
                ) ), esc_url( $this->yoast_sitemap_url ) ) . " " . $this->get_pro_link() . " " . __( 'sitemap feature', 'better-robots-txt' ) . '</div>';
            } elseif ( class_exists( 'WPSEO_Sitemaps' ) && $this->yoast_sitemap() == "404" ) {
                // yoast is enabled but sitemap is not
                $sitemap_output = '<div class="rt-alert rt-info"><span class="closebtn">&times;</span>' . __( 'Yoast SEO is installed.', 'better-robots-txt' ) . " " . $this->get_pro_link() . " " . __( 'sitemap feature', 'better-robots-txt' ) . '</div>';
            } elseif ( $this->xml_sitemap() == "200" ) {
                $sitemap_output = '<div class="rt-alert rt-info"><span class="closebtn">&times;</span>' . sprintf( wp_kses( __( '<a href="%s">XML Sitemap</a> detected but not added.', 'better-robots-txt' ), array(
                    'a' => array(
                        'href' => array(),
                    ),
                ) ), esc_url( $this->xml_sitemap_url ) ) . " " . $this->get_pro_link() . " " . __( 'sitemap feature', 'better-robots-txt' ) . '</div>';
            } else {
                //yoast is not installed/enabled
                $sitemap_output = '<div class="rt-alert rt-warning"><span class="closebtn">&times;</span>' . $this->get_pro_link() . " " . __( 'sitemap option', 'better-robots-txt' ) . '</div>';
            }
        }
        return $sitemap_output;
    }

}