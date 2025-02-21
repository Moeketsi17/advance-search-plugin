<?php
/**
 * Plugin Name: Advanced Trend Alert Search
 * Description: Robust search filter for Trend Alert post type with enhanced security and performance
 * Version: 2.2
 * Author: Wetpaint Advertising
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Security check

class Trend_Alert_Search {
    public function __construct() {
        add_shortcode( 'trend_alert_search', [ $this, 'render_search_form' ] );
        add_action( 'pre_get_posts', [ $this, 'filter_search_results' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_styles' ] );
        add_filter( 'posts_search', [ $this, 'custom_trend_alert_search' ], 10, 2 );
    }

    /**
     * Renders the search form for Trend Alerts.
     */
    public function render_search_form() {
        ob_start();
        ?>
        <form method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>" role="search">
            <?php wp_nonce_field( 'trend_alert_search', 'trend_alert_nonce' ); ?>
            <input type="hidden" name="post_type" value="trend-alert" />
            <input type="hidden" name="trend_alert_search_active" value="1" /> <!-- Hidden input to track shortcode search -->
            <input 
                type="text" 
                name="s" 
                placeholder="Search Trend Alerts" 
                value="<?php echo esc_attr( get_search_query() ); ?>" 
                required 
                aria-label="Search Trend Alerts"
            />
            <button type="submit">Search</button>
        </form>
        <?php
        return ob_get_clean();
    }

    /**
     * Filters search results to only include 'trend-alert' post type when using the shortcode.
     */
    public function filter_search_results( $query ) {
        if ( ! is_admin() && $query->is_main_query() && $query->is_search() ) {
            // Check if the shortcode search is active
            if ( isset( $_GET['trend_alert_search_active'] ) && $_GET['trend_alert_search_active'] == '1' ) {
                // Verify nonce for security
                if ( ! isset( $_GET['trend_alert_nonce'] ) || 
                     ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['trend_alert_nonce'] ) ), 'trend_alert_search' ) ) {
                    return;
                }

                $query->set( 'post_type', 'trend-alert' );
                $query->set( 'posts_per_page', 10 ); // Pagination
            }
        }
    }

    /**
     * Customizes the search query for trend alerts when using the shortcode.
     */
    public function custom_trend_alert_search( $search, $query ) {
        global $wpdb;

        if ( empty( $search ) || ! $query->is_main_query() || ! $query->is_search() ) {
            return $search;
        }

        // Ensure the search is coming from the shortcode
        if ( ! isset( $_GET['trend_alert_search_active'] ) || $_GET['trend_alert_search_active'] != '1' ) {
            return $search; // Exit and use default search behavior
        }

        $search_term = $query->get( 's' );

        if ( ! empty( $search_term ) ) {
            $search_term = '%' . $wpdb->esc_like( $search_term ) . '%';

            $search = $wpdb->prepare(
                " AND ( {$wpdb->posts}.post_title LIKE %s 
                    OR {$wpdb->posts}.post_content LIKE %s ) 
                  AND {$wpdb->posts}.post_type = %s 
                  AND {$wpdb->posts}.post_status = 'publish'",
                $search_term, 
                $search_term, 
                'trend-alert' // Ensure this matches your post type registration
            );
        }

        return $search;
    }

    /**
     * Enqueues the styles for the search form.
     */
    public function enqueue_styles() {
        wp_enqueue_style( 
            'trend-alert-search', 
            plugin_dir_url( __FILE__ ) . 'css/style.css', 
            [], 
            '2.2', 
            'all' 
        );
    }
}

new Trend_Alert_Search();
