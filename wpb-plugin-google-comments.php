<?php
/*
Plugin Name:  WPB Google Comments
Plugin URI:   
Description:  A plugin to integrate Google Comments into your WordPress site. Use [google_comments] shortcode.
Version:      1.5
Author:       Black Ghost
Author URI:   https://github.com/BlackGhost0051
License:      GPL2
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
*/

if( !defined('ABSPATH') ){
    exit;
}

function get_data_from_base(){
    global $wpdb;
    $table_name = $wpdb->prefix . 'google_comments';

    $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id DESC");


    if (empty($results)) {
        return "<div class='google-comments-wrapper wrapper'>No reviews available.</div>";
    }

    $output = "<div class='google-comments-wrapper wrapper'>";

    foreach ($results as $review) {
        $rating = intval($review->rating);
        $stars = str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
        $short_text = mb_strlen($review->review_text) > 30
            ? mb_substr($review->review_text, 0, 30) . '...'
            : $review->review_text;


        $output .= "<div class='google-comment'>";

        $output .= "<div class='img-wrapper'>";
        $output .= "<img src='" . esc_html($review->profile_photo_url) . "' loading='lazy'>";
        $output .= "</div>";


        $output .= "<p class='comment-text'>" . esc_html($short_text) . "</p>";

        $output .= "<h3 class='comment-author'>" . esc_html($review->author_name) . "</h3>";
        $output .= "<p class='comment-time'>" . date("F j, Y, g:i a", $review->review_time) . "</p>";

        $output .= "<p class='comment-rating'><span class='stars'>{$stars}</span></p>";


        $output .= "<div class='read-more-wrapper'>";
        $output .= "<a href='" . esc_html($review->author_url) . "' target='_blank'>Read more</a>";
        $output .= "</div>";

        $output .= "</div>";
    }

    $output .= "</div>";

    return $output;

}

function make_request_and_save_to_base(){
    global $wpdb;
    $table_name = $wpdb->prefix . 'google_comments';

    // Places API url
    $place_id = '';     // Enter PLACE ID
    $key = '';          // Enter key ( Places API key )
    $url = 'https://maps.googleapis.com/maps/api/place/details/json?place_id=' . $place_id . '&key=' . $key;


    $response = @file_get_contents($url);
    if ($response === false) {
        return;
    }


    $data = json_decode($response, true);


    if ($data && isset($data['result']['reviews'])) {
        $wpdb->query("TRUNCATE TABLE $table_name");

        $now = current_time('mysql');

        foreach ($data['result']['reviews'] as $review) {
            $wpdb->insert(
                $table_name,
                [
                    'author_name'  => $review['author_name'],
                    'author_url'   => $review['author_url'],
                    'profile_photo_url'   => $review['profile_photo_url'],
                    'rating'       => $review['rating'],
                    'review_text'  => $review['text'],
                    'review_time'  => $review['time'],
                    'last_updated' => $now
                ],
                ['%s', '%s', '%s', '%d', '%s', '%d', '%s']
            );
        }
    }
}

function check_verify_timer(){
    global $wpdb;
    $table_name = $wpdb->prefix . 'google_comments';

    $charset_collate = $wpdb->get_charset_collate();


    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id INT NOT NULL AUTO_INCREMENT,
        author_name TEXT NOT NULL,
        author_url TEXT NOT NULL,
        profile_photo_url TEXT NOT NULL,
        rating INT NOT NULL,
        review_text TEXT NOT NULL,
        review_time INT NOT NULL,
        last_updated DATETIME NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";




    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);


    $last_update = $wpdb->get_var("SELECT last_updated FROM $table_name ORDER BY id DESC LIMIT 1");


    /// time
    if (!$last_update || strtotime($last_update) < strtotime('-7 days')) {
        make_request_and_save_to_base();
    }

}





add_shortcode('google_comments', 'get_data_from_base');
add_action('init', 'check_verify_timer');




function google_comments_save_enqueue_styles() {
    wp_enqueue_style('google-comments-save-style', plugin_dir_url(__FILE__) . 'style.css');
}
add_action('wp_enqueue_scripts', 'google_comments_save_enqueue_styles');
?>
