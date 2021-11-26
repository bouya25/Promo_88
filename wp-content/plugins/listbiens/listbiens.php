<?php
/*
Plugin Name: Liste Biens
Version: 1.0.0
*/
function register_script_biens()
{
    wp_register_style('new_style_biens', plugins_url('style.css', __FILE__), false, '1.0.0', 'all');
    wp_enqueue_style('new_style_biens');
}

// use the registered jquery and style above
add_action('wp_enqueue_scripts', 'register_script_biens');

function listbiens()
{
    $args = array(
        'post_type' => 'biens',
        'orderby' => 'post_date',
        'order' => 'DESC',
    );

    // 2. On exécute la WP Query
    $my_query = new WP_Query($args);
    // 3. On lance la boucle !

    if ($my_query->have_posts()) {
        $biens = "";
        while ($my_query->have_posts()) : $my_query->the_post();

            $titreBiens = get_the_title();
            $prixBiens = get_field('prix');
            $surfaceBiens = get_field('surface');
            $image = get_field('image_du_bien');

            $biens .= "<div class='biens'><h4>" . $titreBiens . "</h4>
            <img src='" . $image['url'] . "' alt='" . $image['filename'] . "'>
            <p class='price'> Prix : " . $prixBiens . "</p>
            <p class='surface'> Surface : " . $surfaceBiens . "</p></div>";

        endwhile;
    }
    wp_reset_postdata();
    return "<div class='liste-biens'><h3> Liste des biens :  </h3>" . $biens . "</div>";
}
add_shortcode('listeBiens', 'listbiens');
