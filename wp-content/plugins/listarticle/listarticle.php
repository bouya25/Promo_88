<?php
/*
Plugin Name: Liste Article
Version: 1.0.0
*/
function register_script_article()
{
    wp_register_style('new_style_article', plugins_url('style.css', __FILE__), false, '1.0.0', 'all');
    wp_enqueue_style('new_style_article');
}

// use the registered jquery and style above
add_action('wp_enqueue_scripts', 'register_script_article');

function LTA()
{
    $args = array(
        'post_type' => 'post',
        'posts_per_page' => '3',
        'orderby' => 'date',
        'order' => 'DESC',
    );

    // 2. On exécute la WP Query
    $my_query = new WP_Query($args);
    // 3. On lance la boucle !

    if ($my_query->have_posts()) {
        $article = "";
        while ($my_query->have_posts()) : $my_query->the_post();


            $articleTitle = get_the_title();
            $articleTime = get_the_date();
            $article .= "<div class='last-article'>
                            <h3>" . $articleTitle .
                "</h3><p>" . $articleTime . "</p></div>";
        endwhile;
    }

    // 4. On réinitialise à la requête principale (important)

    wp_reset_postdata();
    return "<h3 class='last-three-article_title'>Nos 3 dernières news</h3>
    <div class='last-three-article'>" . $article . "</div>";
}
add_shortcode('L3A', 'LTA');
