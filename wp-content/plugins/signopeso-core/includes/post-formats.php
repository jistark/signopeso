<?php
/**
 * Post Formats Taxonomy (sp_formato)
 *
 * Registers the custom taxonomy, populates default terms, renders radio-button
 * meta box, saves the selected term, and defaults new posts to "corto".
 *
 * @package SignoPeso
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ---------------------------------------------------------------------------
// 1. Register taxonomy
// ---------------------------------------------------------------------------

/**
 * Registers the sp_formato taxonomy on the post post-type.
 */
function sp_register_formato_taxonomy(): void {
    $labels = [
        'name'                       => _x( 'Formatos', 'taxonomy general name', 'signopeso' ),
        'singular_name'              => _x( 'Formato', 'taxonomy singular name', 'signopeso' ),
        'search_items'               => __( 'Buscar formatos', 'signopeso' ),
        'all_items'                  => __( 'Todos los formatos', 'signopeso' ),
        'edit_item'                  => __( 'Editar formato', 'signopeso' ),
        'update_item'                => __( 'Actualizar formato', 'signopeso' ),
        'add_new_item'               => __( 'Añadir nuevo formato', 'signopeso' ),
        'new_item_name'              => __( 'Nuevo nombre de formato', 'signopeso' ),
        'menu_name'                  => __( 'Formatos', 'signopeso' ),
        'not_found'                  => __( 'No se encontraron formatos.', 'signopeso' ),
        'no_terms'                   => __( 'Sin formatos', 'signopeso' ),
        'items_list_navigation'      => __( 'Navegación de la lista de formatos', 'signopeso' ),
        'items_list'                 => __( 'Lista de formatos', 'signopeso' ),
        'back_to_items'              => __( '← Volver a formatos', 'signopeso' ),
        'item_link'                  => _x( 'Enlace de formato', 'navigation link block title', 'signopeso' ),
        'item_link_description'      => _x( 'Un enlace a un formato.', 'navigation link block description', 'signopeso' ),
    ];

    $args = [
        'labels'            => $labels,
        'hierarchical'      => false,
        'public'            => true,
        'show_ui'           => true,
        'show_in_menu'      => true,
        'show_in_nav_menus' => true,
        'show_in_rest'      => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => [ 'slug' => 'formato' ],
        'meta_box_cb'       => 'sp_formato_meta_box',
    ];

    register_taxonomy( 'sp_formato', [ 'post' ], $args );
}
add_action( 'init', 'sp_register_formato_taxonomy' );

// ---------------------------------------------------------------------------
// 2. Populate default terms
// ---------------------------------------------------------------------------

/**
 * Inserts the four canonical formato terms.
 * Safe to call multiple times — wp_insert_term() is idempotent when the term
 * already exists (returns WP_Error with "term_exists" code).
 */
function sp_populate_formato_terms(): void {
    $terms = [
        [
            'name' => 'Corto',
            'slug' => 'corto',
            'desc' => __( 'Pieza breve: nota rápida, observación o reflexión corta.', 'signopeso' ),
        ],
        [
            'name' => 'Enlace',
            'slug' => 'enlace',
            'desc' => __( 'Enlace externo comentado.', 'signopeso' ),
        ],
        [
            'name' => 'Largo',
            'slug' => 'largo',
            'desc' => __( 'Artículo o ensayo de largo aliento.', 'signopeso' ),
        ],
        [
            'name' => 'Cobertura',
            'slug' => 'cobertura',
            'desc' => __( 'Seguimiento periodístico de un tema en curso.', 'signopeso' ),
        ],
    ];

    foreach ( $terms as $term ) {
        if ( ! term_exists( $term['slug'], 'sp_formato' ) ) {
            wp_insert_term(
                $term['name'],
                'sp_formato',
                [
                    'slug'        => $term['slug'],
                    'description' => $term['desc'],
                ]
            );
        }
    }
}

/**
 * On init (priority 20, after taxonomy registration), populates terms once and
 * stores a flag so the check only runs once per site.
 */
function sp_maybe_populate_formato_terms(): void {
    if ( get_option( 'sp_formato_terms_populated' ) ) {
        return;
    }

    sp_populate_formato_terms();
    update_option( 'sp_formato_terms_populated', true, autoload: false );
}
add_action( 'init', 'sp_maybe_populate_formato_terms', 20 );

// ---------------------------------------------------------------------------
// 3. Meta box — radio buttons
// ---------------------------------------------------------------------------

/**
 * Renders the sp_formato meta box with radio buttons instead of checkboxes.
 *
 * @param WP_Post $post Current post object.
 */
function sp_formato_meta_box( WP_Post $post ): void {
    $terms = get_terms(
        [
            'taxonomy'   => 'sp_formato',
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ]
    );

    if ( is_wp_error( $terms ) || empty( $terms ) ) {
        echo '<p>' . esc_html__( 'No hay formatos disponibles.', 'signopeso' ) . '</p>';
        return;
    }

    // Determine the currently assigned term (fall back to "corto").
    $assigned = wp_get_post_terms( $post->ID, 'sp_formato', [ 'fields' => 'slugs' ] );
    $current  = ( ! is_wp_error( $assigned ) && ! empty( $assigned ) )
        ? $assigned[0]
        : 'corto';

    wp_nonce_field( 'sp_formato_save', 'sp_formato_nonce' );

    echo '<div class="sp-formato-meta-box" style="padding:4px 0;">';
    foreach ( $terms as $term ) {
        $checked = checked( $current, $term->slug, false );
        printf(
            '<label style="display:block;margin-bottom:6px;cursor:pointer;">'
            . '<input type="radio" name="sp_formato" value="%1$s" %2$s style="margin-right:6px;">'
            . '%3$s'
            . '</label>',
            esc_attr( $term->slug ),
            $checked,
            esc_html( $term->name )
        );
    }
    echo '</div>';
}

// ---------------------------------------------------------------------------
// 4. Save meta box value
// ---------------------------------------------------------------------------

/**
 * Persists the selected formato term when a post is saved.
 *
 * @param int $post_id Post ID being saved.
 */
function sp_save_formato( int $post_id ): void {
    // Nonce check.
    if (
        ! isset( $_POST['sp_formato_nonce'] )
        || ! wp_verify_nonce( sanitize_key( $_POST['sp_formato_nonce'] ), 'sp_formato_save' )
    ) {
        return;
    }

    // Autosave bail-out.
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    // Capability check.
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    $slug = isset( $_POST['sp_formato'] )
        ? sanitize_key( $_POST['sp_formato'] )
        : 'corto';

    // Validate that the submitted slug is a real term.
    $term = get_term_by( 'slug', $slug, 'sp_formato' );
    if ( ! $term ) {
        $slug = 'corto';
        $term = get_term_by( 'slug', $slug, 'sp_formato' );
    }

    if ( $term ) {
        wp_set_post_terms( $post_id, [ $term->term_id ], 'sp_formato', false );
    }
}
add_action( 'save_post', 'sp_save_formato' );

// ---------------------------------------------------------------------------
// 5. Default formato for new posts
// ---------------------------------------------------------------------------

/**
 * Assigns "corto" as the default formato for brand-new posts that have no
 * formato term yet.
 *
 * @param int $post_id Post ID just inserted.
 */
function sp_default_formato( int $post_id ): void {
    // Only act on truly new posts (auto-draft created by the block editor).
    if ( get_post_status( $post_id ) !== 'auto-draft' ) {
        return;
    }

    $existing = wp_get_post_terms( $post_id, 'sp_formato', [ 'fields' => 'ids' ] );
    if ( ! is_wp_error( $existing ) && ! empty( $existing ) ) {
        return;
    }

    $term = get_term_by( 'slug', 'corto', 'sp_formato' );
    if ( $term ) {
        wp_set_post_terms( $post_id, [ $term->term_id ], 'sp_formato', false );
    }
}
add_action( 'wp_insert_post', 'sp_default_formato' );
