<?php
namespace ChocChop\Core;

class RecipeManager {

    const OPTION_KEY = 'choc_chop_site_recipes';

    public static function get_recipe( string $domain ): ?array {
        if ( empty( $domain ) ) {
            return null;
        }
        $recipes = self::get_all();
        return $recipes[ $domain ] ?? null;
    }

    public static function get_all(): array {
        $raw = get_option( self::OPTION_KEY, '{}' );
        if ( is_string( $raw ) ) {
            $decoded = json_decode( $raw, true );
            return is_array( $decoded ) ? $decoded : [];
        }
        return is_array( $raw ) ? $raw : [];
    }

    public static function save_recipe( string $domain, array $recipe ): void {
        if ( empty( $domain ) ) {
            return;
        }
        $recipes = self::get_all();
        $recipes[ $domain ] = $recipe;
        update_option( self::OPTION_KEY, wp_json_encode( $recipes, JSON_UNESCAPED_UNICODE ) );
    }

    public static function delete_recipe( string $domain ): void {
        $recipes = self::get_all();
        unset( $recipes[ $domain ] );
        update_option( self::OPTION_KEY, wp_json_encode( $recipes, JSON_UNESCAPED_UNICODE ) );
    }

    public static function url_to_domain( string $url ): string {
        $host = wp_parse_url( $url, PHP_URL_HOST );
        if ( ! $host ) {
            return '';
        }
        return preg_replace( '/^www\./i', '', $host );
    }

    public static function css_to_xpath( string $css ): string {
        $css = trim( $css );

        // #id
        if ( preg_match( '/^#([\w-]+)$/', $css, $m ) ) {
            return '//*[@id="' . $m[1] . '"]';
        }

        // tag#id
        if ( preg_match( '/^(\w+)#([\w-]+)$/', $css, $m ) ) {
            return '//' . $m[1] . '[@id="' . $m[2] . '"]';
        }

        // .class (may have multiple: .foo.bar)
        if ( preg_match( '/^\.([\w.-]+)$/', $css, $m ) ) {
            $classes = explode( '.', $m[1] );
            $conditions = array_map( [ self::class, 'class_condition' ], $classes );
            return '//*[' . implode( ' and ', $conditions ) . ']';
        }

        // tag.class
        if ( preg_match( '/^(\w+)\.([\w.-]+)$/', $css, $m ) ) {
            $tag = $m[1];
            $classes = explode( '.', $m[2] );
            $conditions = array_map( [ self::class, 'class_condition' ], $classes );
            return '//' . $tag . '[' . implode( ' and ', $conditions ) . ']';
        }

        // Plain tag
        if ( preg_match( '/^(\w+)$/', $css ) ) {
            return '//' . $css;
        }

        // Fallback: treat as-is (user may have entered XPath directly).
        return $css;
    }

    private static function class_condition( string $class_name ): string {
        return 'contains(concat(" ",normalize-space(@class)," ")," ' . $class_name . ' ")';
    }

    public static function learn( string $domain, array $scrape_context ): void {
        if ( empty( $domain ) ) {
            return;
        }

        $existing = self::get_recipe( $domain );

        // Don't overwrite manually locked recipes.
        if ( $existing && ! empty( $existing['manual_override'] ) ) {
            $existing['success_count'] = ( $existing['success_count'] ?? 0 ) + 1;
            self::save_recipe( $domain, $existing );
            return;
        }

        $recipe = $existing ?? [];

        if ( ! empty( $scrape_context['content_selector'] ) ) {
            $recipe['content_selector'] = $scrape_context['content_selector'];
        }

        if ( ! empty( $scrape_context['strip_selectors'] ) ) {
            $current = $recipe['strip_selectors'] ?? [];
            $recipe['strip_selectors'] = array_values( array_unique( array_merge( $current, $scrape_context['strip_selectors'] ) ) );
        }

        if ( ! empty( $scrape_context['strip_text'] ) ) {
            $current = $recipe['strip_text'] ?? [];
            $recipe['strip_text'] = array_values( array_unique( array_merge( $current, $scrape_context['strip_text'] ) ) );
        }

        $recipe['learned_at']    = current_time( 'mysql' );
        $recipe['success_count'] = ( $recipe['success_count'] ?? 0 ) + 1;

        if ( ! isset( $recipe['manual_override'] ) ) {
            $recipe['manual_override'] = false;
        }

        self::save_recipe( $domain, $recipe );
    }
}
