<?php

namespace Pagup\BetterRobots\Core;

final class AgentControl {
    private const EXTERNAL_URL = 'https://wpagentcontrol.com/';

    public static function is_active() : bool {
        return defined( 'PAC_VERSION' ) || class_exists( '\\Pagup\\AgentControl\\Plugin', false );
    }

    public static function url() : string {
        return self::is_active()
            ? admin_url( 'admin.php?page=pagup-agent-control' )
            : self::EXTERNAL_URL;
    }

    /**
     * Return translated plugin-row labels for the JSON-localized admin UI locales.
     *
     * @return array{0: string, 1: string}
     */
    public static function row_meta_labels() : array {
        $locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();

        return array(
            self::ecosystem_label_for_locale( $locale ),
            __( 'Agent Control', 'better-robots-txt' ),
        );
    }

    public static function ecosystem_label_for_locale( string $locale ) : string {
        $catalog = self::row_meta_catalog();
        $labels = isset( $catalog['locales'] ) && is_array( $catalog['locales'] )
            ? $catalog['locales']
            : array();
        $fallbacks = isset( $catalog['languageFallbacks'] ) && is_array( $catalog['languageFallbacks'] )
            ? $catalog['languageFallbacks']
            : array();
        $normalized = str_replace( '-', '_', trim( $locale ) );

        if ( isset( $labels[$normalized] ) && is_string( $labels[$normalized] ) ) {
            return $labels[$normalized];
        }

        $language = strtolower( (string) strtok( $normalized, '_' ) );
        $fallback_locale = isset( $fallbacks[$language] ) && is_string( $fallbacks[$language] )
            ? $fallbacks[$language]
            : '';

        if ( '' !== $fallback_locale && isset( $labels[$fallback_locale] ) && is_string( $labels[$fallback_locale] ) ) {
            return $labels[$fallback_locale];
        }

        return __( 'Part of the Pagup ecosystem', 'better-robots-txt' );
    }

    /**
     * @return array<string, mixed>
     */
    private static function row_meta_catalog() : array {
        static $catalog = null;

        if ( is_array( $catalog ) ) {
            return $catalog;
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads an immutable bundled catalog.
        $contents = file_get_contents( __DIR__ . '/agent-control-row-meta.json' );
        $decoded = is_string( $contents ) ? json_decode( $contents, true ) : null;
        $catalog = is_array( $decoded ) ? $decoded : array();

        return $catalog;
    }
}
