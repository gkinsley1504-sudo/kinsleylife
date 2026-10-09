<?php
// Preload LCP hero image on front page
add_action('wp_head', function () {
    if (is_front_page() || is_home()) {
        $image_url = 'https://kinsleylife.com/wp-content/uploads/2026/09/montserrat_islebaybeachbar_08.webp';
        echo '<link rel="preload" as="image" href="' . esc_url($image_url) . '" type="image/webp" fetchpriority="high">' . "\n";
		$image_url = 'https://kinsleylife.com/wp-content/uploads/2026/10/kinsleylife_logo_05.webp';
        echo '<link rel="preload" as="image" href="' . esc_url($image_url) . '" type="image/webp" fetchpriority="high">' . "\n";
    }
}, 1);

add_action( 'wp_head', 'optimize_google_fonts', 5 );
function optimize_google_fonts() {
    // 1. Preconnect to Google's main font domain
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
    
    // 2. Preconnect to Google's font storage domain (crossorigin is required here)
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
    
    // 3. Preload the Google Font CSS stylesheet (optional but recommended for speed)
    $font_url = 'https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;700&display=swap';
    echo '<link rel="preload" as="style" href="' . esc_url($font_url) . '">' . "\n";
    
    // 4. Actually load the stylesheet
    echo '<link rel="stylesheet" href="' . esc_url($font_url) . '">' . "\n";
}

// Add an overlay on travel posts of the location when it is the primary category
add_filter( 'post_thumbnail_html', 'kinsley_add_grid_location_badge', 10, 5 );
function kinsley_add_grid_location_badge( $html, $post_id, $post_thumbnail_id, $size, $attr ) {
    // Only run on the front end and if there is an image
    if ( is_admin() || empty( $html ) ) {
        return $html;
    }

    // --- NEW LOGIC: Check if post is in 'Travel' or a subcategory of 'Travel' ---
    $is_travel = false;
    $categories = get_the_category( $post_id );
    
    if ( ! empty( $categories ) ) {
        $travel_cat = get_category_by_slug( 'travel' );
        foreach ( $categories as $cat ) {
            // Check if category is 'travel' or explicitly a child of 'travel'
            if ( $cat->slug === 'travel' || ( $travel_cat && cat_is_ancestor_of( $travel_cat, $cat ) ) ) {
                $is_travel = true;
                break;
            }
        }
    }

    // If it's not a travel post, return the normal image without the badge
    if ( ! $is_travel ) {
        return $html;
    }
    // ----------------------------------------------------------------------------

    $location_name = '';

    // 1. Check for Yoast SEO Primary Category
    $yoast_primary_id = get_post_meta( $post_id, '_yoast_wpseo_primary_category', true );
    
    // 2. Check for Rank Math Primary Category
    $rank_math_primary_id = get_post_meta( $post_id, 'rank_math_primary_category', true );

    // Try to get the primary category from Yoast first, then Rank Math
    if ( $yoast_primary_id ) {
        $primary_cat = get_term( $yoast_primary_id, 'category' );
        if ( ! is_wp_error( $primary_cat ) && $primary_cat ) {
            $location_name = $primary_cat->name;
        }
    } elseif ( $rank_math_primary_id ) {
        $primary_cat = get_term( $rank_math_primary_id, 'category' );
        if ( ! is_wp_error( $primary_cat ) && $primary_cat ) {
            $location_name = $primary_cat->name;
        }
    } 
    
    // Fallback: If no primary category is set, grab the first available category
    if ( empty( $location_name ) ) {
        if ( ! empty( $categories ) ) {
            $location_name = $categories[0]->name;
        }
    }
    
    // If we successfully found a category name, build the badge and wrap the image
    if ( ! empty( $location_name ) ) {
        $location_name = esc_html( $location_name );
        $badge = '<span class="location-badge">' . $location_name . '</span>';
        
        $html = '<div class="location-badge-wrapper">' . $html . $badge . '</div>';
    }
    
    return $html;
}
// Use a custom search results page
add_action('template_redirect', 'enfold_custom_search_redirect');

function enfold_custom_search_redirect() {
    // Check if the current page is a search results page and a query exists
    if (is_search() && !empty($_GET['s'])) {
        // Redirect to the custom 'search-results' page, appending the search term
        wp_redirect(home_url('/search-results/?s=') . urlencode(get_query_var('s')));
        exit();
    }
}
// Apply the search keyword to Enfold builder elements on the custom search page
add_filter('avia_masonry_entries_query', 'enfold_custom_page_search_filter', 10, 2);
add_filter('avia_post_grid_query', 'enfold_custom_page_search_filter', 10, 2);
add_filter('avia_post_slide_query', 'enfold_custom_page_search_filter', 10, 2);
add_filter('avia_portfolio_query', 'enfold_custom_page_search_filter', 10, 2);

function enfold_custom_page_search_filter($query, $params) {
    // Check if we are on the custom search page and a search query exists in the URL
    if (is_page('search-results') && !empty($_GET['s'])) {
        // Force the Enfold element to filter its posts by the search term
        $query['s'] = sanitize_text_field($_GET['s']);
    }
    return $query;
}
// Create a shortcode to display the current search keyword
add_shortcode('search_keyword', 'enfold_display_search_keyword');

function enfold_display_search_keyword() {
    if (isset($_GET['s']) && !empty($_GET['s'])) {
        return esc_html(sanitize_text_field($_GET['s']));
    }
    return '';
}
// Redirect all category pages to the custom 'archive-results' page
add_action('template_redirect', 'enfold_custom_category_redirect');
function enfold_custom_category_redirect() {
    // If a visitor clicks a category link
    if (is_category() && !is_admin()) {
        $cat_obj = get_queried_object();
        if ($cat_obj && isset($cat_obj->slug)) {
            // Redirect to the custom page and attach the category slug to the URL
            wp_redirect(home_url('/archive-results/?custom_cat=') . $cat_obj->slug);
            exit();
        }
    }
}

// Filter Enfold builder elements on the custom page to show only that category
add_filter('avia_masonry_entries_query', 'enfold_custom_page_category_filter', 10, 2);
add_filter('avia_post_grid_query', 'enfold_custom_page_category_filter', 10, 2);
add_filter('avia_post_slide_query', 'enfold_custom_page_category_filter', 10, 2);
add_filter('avia_portfolio_query', 'enfold_custom_page_category_filter', 10, 2);

function enfold_custom_page_category_filter($query, $params) {
    // Check if we are on the archive-results page and a category slug exists in the URL
    if (is_page('archive-results') && !empty($_GET['custom_cat'])) {
        $cat_slug = sanitize_text_field($_GET['custom_cat']);
        
        // Force the grid to only pull posts from that specific category
        $query['tax_query'] = array(
            array(
                'taxonomy' => 'category',
                'field'    => 'slug',
                'terms'    => $cat_slug,
            ),
        );
    }
    return $query;
}
// Create a shortcode to display the proper category name on the custom archive page
add_shortcode('custom_category_title', 'enfold_dynamic_category_title');

function enfold_dynamic_category_title() {
    if (is_page('archive-results') && !empty($_GET['custom_cat'])) {
        $cat_slug = sanitize_text_field($_GET['custom_cat']);
        $category = get_category_by_slug($cat_slug);
        
        // If a valid category is found, output its formatted name
        if ($category) {
            return esc_html($category->name);
        }
    }
    // Fallback text just in case
    return 'Categories'; 
}
// Disable Enfold's AJAX instant search dropdown
add_filter('avf_frontend_search_form_param', 'kinsley_disable_ajax_search', 10, 1);
function kinsley_disable_ajax_search($params) {
    $params['ajax_disable'] = true;
    return $params;
}
