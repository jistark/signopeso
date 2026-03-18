<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! is_search() ) {
    return;
}

$search_query = get_search_query();
$found_posts  = $GLOBALS['wp_query']->found_posts ?? 0;
$current_sort = isset( $_GET['orderby'] ) && in_array( $_GET['orderby'], array( 'date', 'relevance' ), true )
    ? sanitize_text_field( $_GET['orderby'] )
    : 'relevance';

$base_url = home_url( '/' );
?>
<div class="sp-search-header<?php echo $found_posts === 0 ? ' sp-search-header--empty' : ''; ?>">
    <h1 class="sp-search-header__title">
        <?php if ( $found_posts > 0 ) : ?>
            <?php echo esc_html( $found_posts ); ?> <?php echo $found_posts === 1 ? 'resultado' : 'resultados'; ?> para
            <span class="sp-search-header__query">&laquo;<?php echo esc_html( $search_query ); ?>&raquo;</span>
        <?php else : ?>
            Sin resultados para
            <span class="sp-search-header__query">&laquo;<?php echo esc_html( $search_query ); ?>&raquo;</span>
        <?php endif; ?>
    </h1>

    <?php if ( $found_posts > 0 ) : ?>
    <div class="sp-search-header__sort">
        <a href="<?php echo esc_url( add_query_arg( array( 's' => $search_query, 'orderby' => 'relevance' ), $base_url ) ); ?>"
           class="<?php echo 'relevance' === $current_sort ? 'is-active' : ''; ?>">Relevancia</a>
        <span class="sp-search-header__sep">&middot;</span>
        <a href="<?php echo esc_url( add_query_arg( array( 's' => $search_query, 'orderby' => 'date' ), $base_url ) ); ?>"
           class="<?php echo 'date' === $current_sort ? 'is-active' : ''; ?>">Fecha</a>
    </div>
    <?php else : ?>
    <p class="sp-search-header__hint">Intenta con otros términos o explora las secciones.</p>
    <?php endif; ?>
</div>
<?php
