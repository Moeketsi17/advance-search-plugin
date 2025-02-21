<?php
/**
 * Plugin Name: Advanced Customizable Search
 * Description: Customizable search filter for various post types with enhanced settings and form management.
 * Version: 2.5
 * Author: Wetpaint Advertising
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Security check

class Advanced_Customizable_Search {

    const SETTINGS_GROUP = 'acs_settings';
    const SETTINGS_SECTION = 'acs_settings_section';
    const POST_TYPES_OPTION = 'acs_post_types';
    const SEARCH_FORM_POST_TYPE = 'custom_search_form';

    public function __construct() {
        // Register Search Form Post Type
        add_action( 'init', [ $this, 'register_search_form_post_type' ] );

        // Admin menu and settings
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'add_meta_boxes', [ $this, 'add_search_form_meta_boxes' ] );
        add_action( 'save_post', [ $this, 'save_search_form_meta_data' ] );
        add_filter( 'manage_' . self::SEARCH_FORM_POST_TYPE . '_posts_columns', [ $this, 'set_custom_search_form_columns' ] );
        add_action( 'manage_' . self::SEARCH_FORM_POST_TYPE . '_posts_custom_column', [ $this, 'custom_search_form_column' ], 10, 2 );

        add_shortcode( 'customizable_search_form', [ $this, 'render_search_form_shortcode' ] );
        add_action( 'pre_get_posts', [ $this, 'filter_search_results' ] );
        add_filter( 'posts_search', [ $this, 'custom_search' ], 10, 2 );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_styles' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_styles' ] );
    }

   /**
     * Enqueues admin styles.
     *
     * @param string $hook The current admin page.
     *
     * @return void
     */
    public function admin_enqueue_styles( $hook ) {
        if ( 'toplevel_page_customizable-search-settings' === $hook || 'custom_search_form' === get_post_type() ) {
            wp_enqueue_style(
                'customizable-search-admin',
                plugin_dir_url( __FILE__ ) . 'css/admin_style.css',
                [],
                '2.5',
                'all'
            );
        }
    }
    /**
     * Registers the 'custom_search_form' post type.
     *
     * @return void
     */
    public function register_search_form_post_type() {
        $labels = array(
            'name'               => _x( 'Search Forms', 'post type general name', 'customizable-search' ),
            'singular_name'      => _x( 'Search Form', 'post type singular name', 'customizable-search' ),
            'menu_name'          => _x( 'Search Forms', 'admin menu', 'customizable-search' ),
            'name_admin_bar'     => _x( 'Search Form', 'add new on admin bar', 'customizable-search' ),
            'add_new'            => _x( 'Add New', 'search form', 'customizable-search' ),
            'add_new_item'       => __( 'Add New Search Form', 'customizable-search' ),
            'new_item'           => __( 'New Search Form', 'customizable-search' ),
            'edit_item'          => __( 'Edit Search Form', 'customizable-search' ),
            'view_item'          => __( 'View Search Form', 'customizable-search' ),
            'all_items'          => __( 'All Search Forms', 'customizable-search' ),
            'search_items'       => __( 'Search Search Forms', 'customizable-search' ),
            'parent_item_colon'  => __( 'Parent Search Forms:', 'customizable-search' ),
            'not_found'          => __( 'No search forms found.', 'customizable-search' ),
            'not_found_in_trash' => __( 'No search forms found in Trash.', 'customizable-search' ),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => 'customizable-search-settings', // Show under your main settings menu
            'query_var'          => false,
            'rewrite'            => false,
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => null,
            'supports'           => array( 'title' ),
            'show_in_rest'       => false, // Disable Gutenberg editor if not needed
        );

        register_post_type( self::SEARCH_FORM_POST_TYPE, $args );
    }

    /**
     * Adds meta boxes to the search form edit page.
     */
    public function add_search_form_meta_boxes() {
        add_meta_box(
            'search_form_settings',
            'Search Form Settings',
            [ $this, 'render_search_form_settings_meta_box' ],
            self::SEARCH_FORM_POST_TYPE,
            'normal',
            'default'
        );
    }

    /**
     * Renders the search form settings meta box.
     *
     * @param WP_Post $post The post object.
     *
     * @return void
     */
    public function render_search_form_settings_meta_box( $post ) {
        wp_nonce_field( 'search_form_settings', 'search_form_settings_nonce' );

        $selected_post_types = get_post_meta( $post->ID, '_selected_post_types', true );
        if ( ! is_array( $selected_post_types ) ) {
            $selected_post_types = [];
        }
        $post_types = get_post_types( [ 'public' => true ], 'objects' );

        ?>
        <p>
            <label for="selected_post_types"><?php esc_html_e( 'Select Post Types:', 'customizable-search' ); ?></label><br>
            <select name="selected_post_types[]" id="selected_post_types" multiple style="width:100%;">
                <?php foreach ( $post_types as $post_type ) : ?>
                    <option value="<?php echo esc_attr( $post_type->name ); ?>" <?php selected( in_array( $post_type->name, $selected_post_types, true ) ); ?>>
                        <?php echo esc_html( $post_type->label ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <br>
            <small><?php esc_html_e( 'Choose the post types to include in this search form.', 'customizable-search' ); ?></small>
        </p>
        <?php
    }

    /**
     * Saves the search form meta data.
     *
     * @param int $post_id The post ID.
     *
     * @return void
     */
    public function save_search_form_meta_data( $post_id ) {
        if ( ! isset( $_POST['search_form_settings_nonce'] ) || ! wp_verify_nonce( $_POST['search_form_settings_nonce'], 'search_form_settings' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        if ( isset( $_POST['selected_post_types'] ) && is_array( $_POST['selected_post_types'] ) ) {
            $selected_post_types = array_map( 'sanitize_text_field', $_POST['selected_post_types'] );
            update_post_meta( $post_id, '_selected_post_types', $selected_post_types );
        } else {
            delete_post_meta( $post_id, '_selected_post_types' );
        }
    }

     /**
     * Sets the custom columns for the 'custom_search_form' post type list table.
     *
     * @param array $columns An array of column names.
     *
     * @return array An updated array of column names including 'shortcode'.
     */
    public function set_custom_search_form_columns( $columns ) {
        unset( $columns['date'] ); // Remove date column

        $columns['shortcode'] = __( 'Shortcode', 'customizable-search' );
        $columns['date'] = __( 'Date', 'customizable-search' ); // Add date column back
        return $columns;
    }

        /**
     * Populates the custom columns for the 'custom_search_form' post type list table.
     *
     * @param string $column The name of the column to display.
     * @param int    $post_id The ID of the post being displayed.
     *
     * @return void
     */
    public function custom_search_form_column( $column, $post_id ) {
        switch ( $column ) {
            case 'shortcode':
                $shortcode = '[customizable_search_form id="' . esc_attr( $post_id ) . '"]';
                echo '<input type="text" style="width:100%; padding:2px;" readonly value="' . esc_attr( $shortcode ) . '" onclick="this.select();">';
                break;

        }
    }

    /**
     * Adds the admin menu.
     */
    public function add_admin_menu() {
        add_menu_page(
            'Customizable Search Settings',
            'Customizable Search',
            'manage_options',
            'customizable-search-settings',
            [ $this, 'render_settings_page' ],
            'dashicons-search',
            20
        );
    }

    /**
     * Registers the settings.
     */
    public function register_settings() {
        register_setting( self::SETTINGS_GROUP, self::POST_TYPES_OPTION, [ $this, 'sanitize_post_types' ] );

        add_settings_section(
            self::SETTINGS_SECTION,
            'Search Settings',
            [ $this, 'render_settings_section' ],
            'customizable-search-settings'
        );

        add_settings_field(
            'acs_post_types',
            'General Post Type Selection',
            [ $this, 'render_post_types_field' ],
            'customizable-search-settings',
            self::SETTINGS_SECTION
        );
    }

     /**
     * Sanitize post types.
     *
     * @param array $input The array of post types to sanitize.
     *
     * @return array The sanitized array of post types.
     */
    public function sanitize_post_types( $input ) {
        $sanitized_post_types = [];
        $available_post_types = get_post_types( [ 'public' => true ] ); // Get all public post types

        if ( is_array( $input ) ) {
            foreach ( $input as $post_type ) {
                if ( in_array( $post_type, $available_post_types, true ) ) {
                    $sanitized_post_types[] = sanitize_text_field( $post_type );
                }
            }
        }

        return $sanitized_post_types;
    }


    /**
     * Renders the settings section description.
     */
    public function render_settings_section() {
        echo '<p>Choose which post types to include in the search.</p>';
    }

    /**
     * Renders the post types field.
     */
    public function render_post_types_field() {
        $selected_post_types = get_option( self::POST_TYPES_OPTION, [] );
        $post_types = get_post_types( [ 'public' => true ], 'objects' );
        ?>
        <select name="<?php echo self::POST_TYPES_OPTION; ?>[]" multiple="multiple" style="width:300px;">
            <?php
            foreach ( $post_types as $post_type ) {
                ?>
                <option value="<?php echo esc_attr( $post_type->name ); ?>" <?php selected( in_array( $post_type->name, $selected_post_types, true ) ); ?>>
                    <?php echo esc_html( $post_type->label ); ?>
                </option>
                <?php
            }
            ?>
        </select>
        <p class="description">Select the post types to include in the search for the general search form.</p>
        <?php
    }

    /**
     * Renders the settings page.
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>Customizable Search Settings</h1>
            <p>Here, you can configure the default post types used by the search.  For more specific search forms, create a new "Search Form" entry in the admin menu.</p>
            <form method="post" action="options.php">
                <?php
                settings_fields( self::SETTINGS_GROUP );
                do_settings_sections( 'customizable-search-settings' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
 * Renders the search form shortcode.
 *
 * @param array $atts The array of attributes.
 *
 * @return string The search form.
 */
public function render_search_form_shortcode( $atts = [] ) {
    $atts = shortcode_atts(
        [
            'id' => '', // ID of the custom_search_form post
        ],
        $atts,
        'customizable_search_form'
    );

    $form_id = intval( $atts['id'] );

    if ( empty( $form_id ) ) {
        return '<p>Error: Search form ID is required.</p>';
    }

    // Get current search term if any
    $search_term = isset($_GET['custom_search_term']) ? esc_attr(sanitize_text_field($_GET['custom_search_term'])) : '';

    ob_start();
    ?>
    <form method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>" role="search" class="customizable-search-form">
        <?php wp_nonce_field( 'customizable_search', 'customizable_search_nonce' ); ?>
        <input type="hidden" name="customizable_search_active" value="<?php echo esc_attr( $form_id ); ?>" />
        <!-- We need the s parameter to trigger WordPress search -->
        <input type="hidden" name="s" value="search-trigger" />
        <input
            type="text"
            name="custom_search_term"
            placeholder="Search"
            value="<?php echo $search_term; ?>"
            required
            aria-label="Search"
        />
        <button type="submit">Search</button>
    </form>
    <?php
    return ob_get_clean();
}

    /**
 * Filters search results.
 */
public function filter_search_results( $query ) {
    if ( ! is_admin() && $query->is_main_query() && $query->is_search() ) {
        if ( isset( $_GET['customizable_search_active'] ) && isset( $_GET['customizable_search_nonce'] ) && 
            wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['customizable_search_nonce'] ) ), 'customizable_search' ) ) {
            
            // Handle general search
            if ( $_GET['customizable_search_active'] == '1' ) {
                $post_types = get_option( self::POST_TYPES_OPTION, ['post'] ); // Default to 'post' if nothing is selected
                $query->set( 'post_type', $post_types );
            } else { // Handle specific search forms
                $form_id = intval( $_GET['customizable_search_active'] );
                if ( ! empty( $form_id ) ) {
                    $selected_post_types = get_post_meta( $form_id, '_selected_post_types', true );
                    if ( ! is_array( $selected_post_types ) || empty( $selected_post_types ) ) {
                        $selected_post_types = ['post']; // Default to post if nothing selected
                    }
                    $query->set( 'post_type', $selected_post_types );
                }
            }
            
            // Set posts per page
            $query->set( 'posts_per_page', 10 );
            
            // If we have a custom search term, use it for the search
            if ( isset( $_GET['custom_search_term'] ) && ! empty( $_GET['custom_search_term'] ) ) {
                // We'll handle the actual search in custom_search()
                // Just setting a flag here that our custom search is active
                $query->set( 'custom_search_active', true );
            }
        }
    }
}

    /**
     * Customizes the search query.
     */
    public function custom_search( $search, $query ) {
        global $wpdb;

        if ( empty( $search ) || ! $query->is_main_query() || ! $query->is_search() ) {
            return $search;
        }

        if ( ! isset( $_GET['customizable_search_active'] ) ) {
            return $search;
        }

        $search_term = isset($_GET['custom_search_term']) ? sanitize_text_field($_GET['custom_search_term']) : '';

        // Prepare the search term for the SQL query
        $search_term_like = '%' . $wpdb->esc_like( $search_term ) . '%';

        //Handle general search
        if($_GET['customizable_search_active'] == '1'){
             $selected_post_types = get_option( self::POST_TYPES_OPTION, ['post'] ); // Default to 'post' if nothing is selected
        } else { //Handle specific search forms
           $form_id = intval( $_GET['customizable_search_active'] );
           $selected_post_types = get_post_meta( $form_id, '_selected_post_types', true );
           if ( ! is_array( $selected_post_types ) ) {
                $selected_post_types = [];
           }
        }

        if ( ! empty( $search_term ) ) {
            // Construct the SQL query to search within post titles and content
            $post_type_conditions = '';
            $post_types_count = count( $selected_post_types );

            if ( $post_types_count > 0 ) {
                $post_type_conditions = " AND {$wpdb->posts}.post_type IN ('" . implode( "', '", array_map( 'esc_sql', $selected_post_types ) ) . "')";
            }

            // Simplified search query
            $search = "AND ( {$wpdb->posts}.post_title LIKE '$search_term_like' OR {$wpdb->posts}.post_content LIKE '$search_term_like' )" . $post_type_conditions . " AND {$wpdb->posts}.post_status = 'publish'";

            // Debugging output
            error_log('Search term: ' . $search_term);
            error_log('Search term LIKE: ' . $search_term_like);
            error_log('Constructed search query: ' . $search);

        } else {
            // If there's no search term, return an empty search string.
            $search = "AND 1=0";
        }

        return $search;
    }

    /**
     * Enqueues the styles.
     */
    public function enqueue_styles() {
        wp_enqueue_style(
            'customizable-search',
            plugin_dir_url( __FILE__ ) . 'css/style.css',
            [],
            '2.5',
            'all'
        );
    }
}

new Advanced_Customizable_Search();