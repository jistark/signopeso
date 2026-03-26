<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$recent = new WP_Query( array(
    'posts_per_page' => 3,
    'post_status'    => 'publish',
    'no_found_rows'  => true,
) );

if ( ! $recent->have_posts() ) {
    return;
}
?>
<div class="sp-recirculation-lite">
    <div class="sp-section-label">quizás te interese</div>
    <?php
    while ( $recent->have_posts() ) :
        $recent->the_post();
        $card_block = new WP_Block(
            array(
                'blockName'    => 'sp/post-card',
                'attrs'        => array(),
                'innerBlocks'  => array(),
                'innerHTML'    => '',
                'innerContent' => array(),
            ),
            array( 'postId' => get_the_ID() )
        );
        echo $card_block->render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    endwhile;
    wp_reset_postdata();
    ?>
</div>
<?php
