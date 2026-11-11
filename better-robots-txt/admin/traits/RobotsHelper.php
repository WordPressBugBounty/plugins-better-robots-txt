<?php
namespace Pagup\BetterRobots\Traits;

trait RobotsHelper 
{
    public function default_options() {
        $options = array(
            'user_agents' => "User-agent: *\n"
                ."Allow: /wp-admin/admin-ajax.php\n"
                ."Allow: /*/*.css\n"
                ."Allow: /*/*.js\n"
                ."Disallow: /wp-admin/\n"
                ."Disallow: /wp-includes/\n"
                ."Disallow: /readme.html\n"
                ."Disallow: /license.txt\n"
                ."Disallow: /xmlrpc.php\n"
                ."Disallow: /wp-login.php\n"
                ."Disallow: /wp-register.php\n"
                ."Disallow: */disclaimer/*\n"
                ."Disallow: *?attachment_id=\n"
                ."Disallow: /privacy-policy\n",
            'remove_settings' => false
        );
        update_option( 'robots_txt', $options );
        return $options;
    }

    public function agents()
    {
        return array ( 
            array (
                "name" => "Google Bot",
                "agent" => "Googlebot",
                "slug" => "googlebot",
                "path" => "/",
                "dir" => "root",
                "define" => ""
            ),
            array (
                "name" => "Google Images",
                "agent" => "Googlebot-Image",
                "slug" => "google_images",
                "path" => "/wp-content/uploads/",
                "dir" => "media directory",
                "define" => ""
            ),
            array (
                "name" => "Google Media Partners",
                "agent" => "Mediapartners-Google",
                "slug" => "mediapartners_google",
                "path" => "/",
                "dir" => "root",
                "define" => ""
            ),
            array (
                "name" => "Google AdsBot",
                "agent" => "AdsBot-Google",
                "slug" => "google_adsbot",
                "path" => "/",
                "dir" => "root",
                "define" => ""
            ),
            array (
                "name" => "Google Mobile",
                "agent" => "AdsBot-Google-Mobile",
                "slug" => "google_mobile",
                "path" => "/",
                "dir" => "root",
                "define" => ""
            ),
            array (
                "name" => "Bing Bot",
                "agent" => "Bingbot",
                "slug" => "bingbot",
                "path" => "/",
                "dir" => "root",
                "define" => "(Microsoft Search Engine)"
            ),
            array (
                "name" => "MSN Bot",
                "agent" => "Msnbot",
                "slug" => "msnbot",
                "path" => "/",
                "dir" => "root",
                "define" => "(Microsoft Search Engine)"
            ),
            array (
                "name" => "MSNBot Media",
                "agent" => "msnbot-media",
                "slug" => "msnbot-media",
                "path" => "/wp-content/uploads/",
                "dir" => "media directory",
                "define" => ""
            ),
            array (
                "name" => "Apple bot",
                "agent" => "Applebot",
                "slug" => "applebot",
                "path" => "/",
                "dir" => "root",
                "define" => "(Used for Siri and Spotlight Suggestions)"
            ),
            array (
                "name" => "Yandex Bot",
                "agent" => "Yandex",
                "slug" => "yandexbot",
                "path" => "/",
                "dir" => "root",
                "define" => "(Search Engine in Russia)"
            ),
            array (
                "name" => "Yandex Images",
                "agent" => "YandexImages",
                "slug" => "yandeximages",
                "path" => "/wp-content/uploads/",
                "dir" => "media directory",
                "define" => ""
            ),
            array (
                "name" => "Yahoo Search (Slurp bot)",
                "agent" => "Slurp",
                "slug" => "slurp",
                "path" => "/",
                "dir" => "root",
                "define" => ""
            ),
            array (
                "name" => "DuckDuckGo Bot",
                "agent" => "DuckDuckBot",
                "slug" => "duckduckbot",
                "path" => "/",
                "dir" => "root",
                "define" => ""
            ),
            array (
                "name" => "Qwant",
                "agent" => "Qwantify",
                "slug" => "qwantify",
                "path" => "/",
                "dir" => "root",
                "define" => ""
            )
        );

    }

    public function chinese_bots($val)
    {
        return "User-agent: Baiduspider\n"
                ."$val: /\n"
                ."User-agent: Baiduspider/2.0\n"
                ."$val: /\n"
                ."User-agent: Baiduspider-video\n"
                ."$val: /\n"
                ."User-agent: Baiduspider-image\n"
                ."$val: /\n"
                ."User-agent: Sogou spider\n"
                ."$val: /\n"
                ."User-agent: Sogou web spider\n"
                ."$val: /\n"
                ."User-agent: Sosospider\n"
                ."$val: /\n"
                ."User-agent: Sosospider+\n"
                ."$val: /\n"
                ."User-agent: Sosospider/2.0\n"
                ."$val: /\n"
                ."User-agent: yodao\n"
                ."$val: /\n"
                ."User-agent: youdao\n"
                ."$val: /\n"
                ."User-agent: YoudaoBot\n"
                ."$val: /\n"
                ."User-agent: YoudaoBot/1.0\n"
                ."$val: /\n\n";
    }

    public function feed_protector()
    {
        return "Disallow: /feed/\n"
                ."Disallow: /feed/$\n"
                ."Disallow: /comments/feed\n"
                ."Disallow: /trackback/\n"
                ."Disallow: */?author=*\n"
                ."Disallow: */author/*\n"
                ."Disallow: /author*\n"
                ."Disallow: /author/\n"
                ."Disallow: */comments$\n"
                ."Disallow: */feed\n"
                ."Disallow: */feed$\n"
                ."Disallow: */trackback\n"
                ."Disallow: */trackback$\n"
                ."Disallow: /?feed=\n"
                ."Disallow: /wp-comments\n"
                ."Disallow: /wp-feed\n"
                ."Disallow: /wp-trackback\n"
                ."Disallow: */replytocom=\n\n";
    }

    public function bad_bots()
    {
        return array(
            "GiftGhostBot",
            "Seznam",
            "PaperLiBot",
            "Genieo ",
            "Dataprovider/6.101",
            "DataproviderSiteExplorer",
            "Dazoobot/1.0",
            "Diffbot",
            "DomainStatsBot/1.0",
            "dubaiindex",
            "eCommerceBot",
            "ExpertSearchSpider",
            "Feedbin",
            "Fetch/2.0a",
            "FFbot/1.0",
            "focusbot/1.1",
            "HuaweiSymantecSpider",
            "HuaweiSymantecSpider/1.0",
            "JobdiggerSpider",
            "LemurWebCrawler",
            "LipperheyLinkExplorer",
            "LSSRocketCrawler/1.0",
            "LYT.SRv1.5",
            "MiaDev/0.0.1",
            "Najdi.si/3.1",
            "BountiiBot",
            "Experibot_v1",
            "bixocrawler",
            "bixocrawler TestCrawler",
            "Crawler4j",
            "Crowsnest/0.5",
            "CukBot",
            "Dataprovider/6.92",
            "DBLBot/1.0",
            "Diffbot/0.1",
            "Digg Deeper/v1",
            "discobot/1.0",
            "discobot/1.1",
            "discobot/2.0",
            "discoverybot/2.0",
            "Dlvr.it/1.0",
            "DomainStatsBot/1.0",
            "drupact/0.7",
            "Ezooms/1.0  ",
            "fastbot crawler beta 2.0  ",
            "fastbot crawler beta 4.0  ",
            "feedly social",
            "Feedly/1.0  ",
            "FeedlyBot/1.0  ",
            "Feedspot  ",
            "Feedspotbot/1.0",
            "Clickagy Intelligence Bot v2",
            "classbot",
            "CISPA Vulnerability Notification",
            "CirrusExplorer/1.1",
            "Checksem/Nutch-1.10",
            "CatchBot/5.0",
            "CatchBot/3.0",
            "CatchBot/2.0",
            "CatchBot/1.0",
            "CamontSpider/1.0",
            "Buzzbot/1.0",
            "Buzzbot",
            "BusinessSeek.biz_Spider",
            "BUbiNG",
            "008/0.85",
            "008/0.83",
            "008/0.71",
            "^Nail",
            "FyberSpider/1.3",
            "findlinks/1.1.6-beta5",
            "g2reader-bot/1.0",
            "findlinks/1.1.6-beta6",
            "findlinks/2.0",
            "findlinks/2.0.1",
            "findlinks/2.0.2",
            "findlinks/2.0.4",
            "findlinks/2.0.5",
            "findlinks/2.0.9",
            "findlinks/2.1",
            "findlinks/2.1.5",
            "findlinks/2.1.3",
            "findlinks/2.2",
            "findlinks/2.5",
            "findlinks/2.6",
            "FFbot/1.0",
            "findlinks/1.0",
            "findlinks/1.1.3-beta8",
            "findlinks/1.1.3-beta9",
            "findlinks/1.1.4-beta7",
            "findlinks/1.1.6-beta1",
            "findlinks/1.1.6-beta1 Yacy",
            "findlinks/1.1.6-beta2",
            "findlinks/1.1.6-beta3",
            "findlinks/1.1.6-beta4",
            "bixo",
            "bixolabs/1.0",
            "Crawlera/1.10.2",
            "Dataprovider Site Explorer"
        );
    }

    public function bad_bots_chatgpt()
    {
        return array(
            "ia_archiver",
            "archive.org_bot",
            "SiteExplorer",
            "spbot",
            "WBSearchBot",
            "linkdexbot",
            "Screaming Frog SEO Spider",
            "netEstate NE Crawler",
            "Moreover",
            "sentibot",
            "Aboundexbot",
            "proximic",
            "oBot",
            "meanpathbot",
            "Nutch",
            "TurnitinBot",
            "ZoominfoBot",
            "ZmEu",
            "grapeshot",
            "python-requests",
            "Go-http-client",
            "Apache-HttpClient",
            "libwww-perl",
            "curl",
            "wget"
        );
    }

    public function backlinks_pro()
    {
        return array(
            "AhrefsBot",
            "Alexibot",
            "MJ12bot",
            "SurveyBot",
            "Xenu's",
            "Xenu's Link Sleuth 1.1c",
            "rogerbot",
            "SemrushBot",
            "SemrushBot-SA",
            "SemrushBot-BA",
            "SemrushBot-SI",
            "SemrushBot-SWA",
            "SemrushBot-CT",
            "SemrushBot-BM",
            "DotBot/1.1",
            "DotBot",
        );
    }
    
    public function woo_crawlers()
    {
        return "Disallow: /cart/\n"
        ."Disallow: /checkout/\n"
        ."Disallow: /my-account/\n"
        ."Disallow: /*?orderby=price\n"
        ."Disallow: /*?orderby=rating\n"
        ."Disallow: /*?orderby=date\n"
        ."Disallow: /*?orderby=price-desc\n"
        ."Disallow: /*?orderby=popularity\n"
        ."Disallow: /*?filter\n"
        ."Disallow: /*?orderby=title\n"
        ."Disallow: /*?orderby=desc\n"
        ."Disallow: /*?filter\n"
        ."Disallow: /*add-to-cart=*\n"
        ."Disallow: /*add_to_wishlist=*\n"
        ."Disallow: /*?paged=&count=*\n"
        ."Disallow: /*?count=*\n\n";
    }

    public function crawl_budget()
    {
        return "Disallow: /search/\n"
        ."Disallow: *?s=*\n"
        ."Disallow: *?p=*\n"
        ."Disallow: *&p=*\n"
        ."Disallow: *&preview=*\n"
        ."Disallow: /search\n\n";
    }

    public function facebook_bots($val)
    {
        return "User-agent: facebookexternalhit/1.0\n"
        ."$val: /\n"
        ."User-agent: facebookexternalhit/1.1\n"
        ."$val: /\n"
        ."User-agent: facebookplatform/1.0\n"
        ."$val: /\n"
        ."User-agent: Facebot/1.0\n"
        ."$val: /\n"
        ."User-agent: Visionutils/0.2\n"
        ."$val: /\n"
        ."User-agent: datagnionbot\n"
        ."$val: /\n\n";
    }

    public function twitter_bots($val)
    {
        return "User-agent: Twitterbot\n"
        ."$val: /\n\n";
    }
    
    public function linkedin_bots($val)
    {
        return "User-agent: LinkedInBot/1.0\n"
        ."$val: /\n\n";
    }
    
    public function pinterest_bots($val)
    {
        return "User-agent: Pinterest/0.1\n"
        ."$val: /\n"
        ."User-agent: Pinterest/0.2\n"
        ."$val: /\n\n";
    }

    public function post_metas()
    {
        // Individual post disallow rules.
        // A stored rt_disallow value only means "rule enabled" (non-empty, as before): it is never
        // printed. Each rule is rebuilt from the permalink of a published, publicly viewable post.
        global $wpdb;

        // Upper bound on the posts read for the public robots.txt, lowest post IDs first.
        $limit = (int) apply_filters( 'better_robots_txt_post_rules_limit', 1000 );

        if ( $limit < 1 ) {
            return;
        }

        $post_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT pm.post_id FROM $wpdb->postmeta pm INNER JOIN $wpdb->posts p ON p.ID = pm.post_id WHERE pm.meta_key = %s AND pm.meta_value NOT IN ('', '0') AND p.post_status = %s ORDER BY pm.post_id ASC LIMIT %d",
                'rt_disallow',
                'publish',
                $limit
            )
        );

        if ( empty( $post_ids ) || !is_array( $post_ids ) ) {
            return;
        }

        $post_ids = array_map( 'intval', $post_ids );

        if ( function_exists( '_prime_post_caches' ) ) {
            _prime_post_caches( $post_ids, false, true );
        }

        $rules = array();

        foreach ( $post_ids as $post_id ) {
            $rule = $this->post_rule_path( $post_id );

            if ( '' !== $rule ) {
                $rules[$rule] = true;
            }
        }

        if (!empty($rules)){
            return array_keys( $rules );
        }
    }

    /**
     * Robots.txt rule of one post, rebuilt from its own permalink.
     *
     * @param int $post_id Post ID.
     * @return string Rule path, or '' when the post must not contribute a rule.
     */
    private function post_rule_path( $post_id )
    {
        $post = get_post( $post_id );

        if ( !$post || 'publish' !== $post->post_status ) {
            return '';
        }

        // The flag counts under the exact key only. The SQL comparison follows the database
        // collation (case- and accent-insensitive), so a look-alike key would match there.
        if ( !array_filter( (array) get_post_meta( $post->ID, 'rt_disallow' ) ) ) {
            return '';
        }

        // is_post_publicly_viewable() needs WordPress 5.7. The post is published, so on older
        // versions it is viewable exactly when its post type is.
        $viewable = function_exists( 'is_post_publicly_viewable' )
            ? is_post_publicly_viewable( $post )
            : is_post_type_viewable( $post->post_type );

        if ( !$viewable ) {
            return '';
        }

        $permalink = get_permalink( $post );

        // parse_url() rewrites control characters instead of rejecting them.
        if ( !is_string( $permalink ) || preg_match( '/[\x00-\x20\x7F]/', $permalink ) ) {
            return '';
        }

        $url  = wp_parse_url( $permalink );
        $home = wp_parse_url( home_url( '/' ) );

        if ( !is_array( $url ) ) {
            return '';
        }

        // Only URLs of this site: a permalink that names a host must name the home host.
        if ( isset( $url['host'] ) && ( empty( $home['host'] ) || strtolower( $url['host'] ) !== strtolower( $home['host'] ) ) ) {
            return '';
        }

        $path      = isset( $url['path'] ) ? $url['path'] : '/';
        $query     = isset( $url['query'] ) ? $url['query'] : '';
        $home_path = isset( $home['path'] ) ? $home['path'] : '/';

        // The home URL itself would disallow the whole site.
        if ( '' === $query && rtrim( $path, '/' ) === rtrim( $home_path, '/' ) ) {
            return '';
        }

        $rule = ( '' === $query ) ? $path : $path . '?' . $query;

        // Robots.txt paths are ASCII: percent-encode raw non-ASCII bytes.
        $rule = preg_replace_callback( '/[\x80-\xFF]/', function ( $byte ) {
            return rawurlencode( $byte[0] );
        }, $rule );

        // A trailing slash ends the path segment. Without it, or with a query string, anchor the
        // rule with "$" (RFC 9309) so that it cannot prefix-match unrelated URLs.
        if ( '' !== $query || '/' !== substr( $path, -1 ) ) {
            $rule .= '$';
        }

        return $this->is_post_rule_path( $rule ) ? $rule : '';
    }

    /**
     * Whether a per-post rule can be printed on one "Disallow:" line.
     *
     * Starts with "/", printable ASCII only, no space, no "*" wildcard, no "#" comment marker,
     * "$" only as the final end-of-pattern anchor, bounded length.
     *
     * @param mixed $rule Candidate rule path.
     * @return bool
     */
    private function is_post_rule_path( $rule )
    {
        return is_string( $rule )
            && strlen( $rule ) <= 2048
            && 1 === preg_match( '/\A\/[\x21\x22\x25-\x29\x2B-\x7E]+\$?\z/', $rule );
    }

    public function network_rules($site)
    {
        return "User-agent: *\n"
            ."Allow: /{$site}/wp-admin/admin-ajax.php\n"
            ."Allow: /*/*.css\n"
            ."Allow: /*/*.js\n"
            ."Disallow: /{$site}/wp-admin/\n"
            ."Disallow: /{$site}/wp-includes/\n"
            ."Disallow: /{$site}/readme.html\n"
            ."Disallow: /{$site}/license.txt\n"
            ."Disallow: /{$site}/xmlrpc.php\n"
            ."Disallow: /{$site}/wp-login.php\n"
            ."Disallow: /{$site}/wp-register.php\n"
            ."Disallow: /{$site}/*/disclaimer/*\n"
            ."Disallow: /{$site}/*?attachment_id=\n"
            ."Disallow: /{$site}/privacy-policy\n\n";
    }

    public function network_woo($site)
    {
        return "Disallow: /{$site}/cart/\n"
        ."Disallow: /{$site}/checkout/\n"
        ."Disallow: /{$site}/my-account/\n"
        ."Disallow: /{$site}/*?orderby=price\n"
        ."Disallow: /{$site}/*?orderby=rating\n"
        ."Disallow: /{$site}/*?orderby=date\n"
        ."Disallow: /{$site}/*?orderby=price-desc\n"
        ."Disallow: /{$site}/*?orderby=popularity\n"
        ."Disallow: /{$site}/*?filter\n"
        ."Disallow: /{$site}/*add-to-cart=*\n\n";
    }

    public function image_crawlability($val)
    {
        return "User-agent: *\n"
                ."$val: /*.png*\n"
                ."$val: /*.jpg*\n"
                ."$val: /*.gif*\n"
                ."$val: /*.webp*\n\n";
    }
    
}

