<?php
/*
Plugin Name: WD Export/Import Posts to CSV
Plugin URI:  https://example.com/wd-export-import-posts-to-csv
Description: A plugin to export and import WordPress blog posts to/from a CSV file using WD abbreviations.
Version:     1.2
Author:      Warf Designs LLC
Author URI:  https://warfdesigns.com
License:     GPL2
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Define plugin constants.
define('WD_YOUR_PLUGIN_VERSION', '1.0.0');
define('WD_YOUR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WD_YOUR_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include necessary files.
require_once WD_YOUR_PLUGIN_DIR . 'includes/class-wd-your-plugin.php';
require_once WD_YOUR_PLUGIN_DIR . 'includes/class-wd-serial-key-validator.php';

// Initialize the plugin.
function wd_your_plugin_init() {
    WD_Your_Plugin::init();
}
add_action('plugins_loaded', 'wd_your_plugin_init');

// Hook to add the export/import functionality to the admin menu
add_action( 'admin_menu', 'wd_add_menu' );

/**
 * Add a new submenu under Tools.
 */
function wd_add_menu() {
    add_management_page(
        'WD Export/Import Posts',      // Page title
        'WD Export/Import Posts',      // Menu title
        'manage_options',              // Capability
        'wd-export-import-posts',      // Menu slug
        'wd_export_import_page'        // Callback function
    );
}

/**
 * Render the export/import page in the WordPress admin.
 */
function wd_export_import_page() {
    ?>
    <div class="wrap">
        <h1>WD Export/Import Posts</h1>
        
        <?php wd_display_admin_notices(); ?>
        
        <!-- Export Section -->
        <h2>Export Posts to CSV</h2>
        <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>">
            <?php
                wp_nonce_field( 'wd_export_posts_nonce', 'wd_export_posts_nonce_field' );
                ?>
            <input type="hidden" name="action" value="wd_export_posts" />
            <?php submit_button( 'Export Posts', 'primary', 'wd_export_submit' ); ?>
        </form>

        <hr />

        <!-- Import Section -->
        <h2>Import Posts from CSV</h2>
        <form method="post" enctype="multipart/form-data" action="<?php echo admin_url( 'admin-post.php' ); ?>">
            <?php
                wp_nonce_field( 'wd_import_posts_nonce', 'wd_import_posts_nonce_field' );
                ?>
            <input type="hidden" name="action" value="wd_import_posts" />
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="wd_import_file">CSV File</label></th>
                    <td><input type="file" name="wd_import_file" id="wd_import_file" accept=".csv" required /></td>
                </tr>
            </table>
            <?php submit_button( 'Import Posts', 'secondary', 'wd_import_submit' ); ?>
        </form>
    </div>
    <?php
}

/**
 * Display admin notices based on query parameters.
 */
function wd_display_admin_notices() {
    if ( isset( $_GET['export'] ) && $_GET['export'] === 'success' ) {
        echo '<div class="notice notice-success is-dismissible"><p>Posts exported successfully.</p></div>';
    }

    if ( isset( $_GET['import'] ) ) {
        $import_status = sanitize_text_field( $_GET['import'] );
        if ( $import_status === 'success' ) {
            echo '<div class="notice notice-success is-dismissible"><p>Posts imported successfully.</p></div>';
        } elseif ( $import_status === 'error' ) {
            echo '<div class="notice notice-error is-dismissible"><p>There was an error importing the posts. Please check the CSV file format and try again.</p></div>';
        }
    }
}

// Handle Export Action
add_action( 'admin_post_wd_export_posts', 'wd_handle_export_posts' );

/**
 * Handle exporting posts to CSV.
 */
function wd_handle_export_posts() {
    // Verify nonce
    if ( ! isset( $_POST['wd_export_posts_nonce_field'] ) || ! wp_verify_nonce( $_POST['wd_export_posts_nonce_field'], 'wd_export_posts_nonce' ) ) {
        wp_die( 'Nonce verification failed.' );
    }

    // Check user capabilities
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'You do not have sufficient permissions to export posts.' );
    }

    // Fetch all published posts
    $posts = get_posts( array(
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'numberposts'    => -1,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ) );

    if ( empty( $posts ) ) {
        wp_redirect( add_query_arg( array( 'page' => 'wd-export-import-posts', 'export' => 'success' ), admin_url( 'tools.php' ) ) );
        exit;
    }

    // Set CSV headers
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename=wd-posts-export-' . date( 'Y-m-d' ) . '.csv' );

    // Open output stream
    $output = fopen( 'php://output', 'w' );

    if ( $output === false ) {
        wp_die( 'Failed to open output stream.' );
    }

    // Output CSV column headings
    fputcsv( $output, array( 'ID', 'Title', 'Content', 'Date', 'Author' ) );

    // Loop through each post and output to CSV
    foreach ( $posts as $post ) {
        $author = get_the_author_meta( 'display_name', $post->post_author );
        fputcsv( $output, array(
            $post->ID,
            $post->post_title,
            $post->post_content,
            $post->post_date,
            $author,
        ) );
    }

    fclose( $output );
    exit;
}

// Handle Import Action
add_action( 'admin_post_wd_import_posts', 'wd_handle_import_posts' );

/**
 * Handle importing posts from CSV.
 */
function wd_handle_import_posts() {
    // Verify nonce
    if ( ! isset( $_POST['wd_import_posts_nonce_field'] ) || ! wp_verify_nonce( $_POST['wd_import_posts_nonce_field'], 'wd_import_posts_nonce' ) ) {
        wp_die( 'Nonce verification failed.' );
    }

    // Check user capabilities
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'You do not have sufficient permissions to import posts.' );
    }

    // Check if file is uploaded without errors
    if ( ! isset( $_FILES['wd_import_file'] ) || $_FILES['wd_import_file']['error'] !== UPLOAD_ERR_OK ) {
        wp_redirect( add_query_arg( array( 'page' => 'wd-export-import-posts', 'import' => 'error' ), admin_url( 'tools.php' ) ) );
        exit;
    }

    $file = $_FILES['wd_import_file']['tmp_name'];

    // Validate file type
    $file_type = wp_check_filetype( $_FILES['wd_import_file']['name'] );
    if ( $file_type['ext'] !== 'csv' ) {
        wp_redirect( add_query_arg( array( 'page' => 'wd-export-import-posts', 'import' => 'error' ), admin_url( 'tools.php' ) ) );
        exit;
    }

    // Open the CSV file
    if ( ( $handle = fopen( $file, 'r' ) ) === false ) {
        wp_redirect( add_query_arg( array( 'page' => 'wd-export-import-posts', 'import' => 'error' ), admin_url( 'tools.php' ) ) );
        exit;
    }

    $header = fgetcsv( $handle ); // Read the header row

    // Validate header columns
    $expected_headers = array( 'ID', 'Title', 'Content', 'Date', 'Author' );
    if ( $header === false || array_map( 'trim', $header ) !== $expected_headers ) {
        fclose( $handle );
        wp_redirect( add_query_arg( array( 'page' => 'wd-export-import-posts', 'import' => 'error' ), admin_url( 'tools.php' ) ) );
        exit;
    }

    $imported = 0;
    $errors   = 0;

    // Loop through each row in the CSV
    while ( ( $row = fgetcsv( $handle ) ) !== false ) {
        // Skip empty rows
        if ( count( $row ) < 5 || empty( $row[1] ) ) {
            $errors++;
            continue;
        }

        // Sanitize and assign variables
        $title   = sanitize_text_field( $row[1] );
        $content = wp_kses_post( $row[2] );
        $date    = sanitize_text_field( $row[3] );
        $author  = sanitize_text_field( $row[4] );

        // Validate date format
        $date_timestamp = strtotime( $date );
        if ( $date_timestamp === false ) {
            $errors++;
            continue;
        }
        $formatted_date = date( 'Y-m-d H:i:s', $date_timestamp );

        // Get author ID
        $author_id = wd_get_author_by_display_name( $author );
        if ( $author_id === false ) {
            $author_id = get_current_user_id(); // Assign to current user if author not found
        }

        // Prepare post data
        $post_data = array(
            'post_title'    => $title,
            'post_content'  => $content,
            'post_date'     => $formatted_date,
            'post_status'   => 'publish',
            'post_author'   => $author_id,
            'post_type'     => 'post',
        );

        // Insert the post
        $post_id = wp_insert_post( $post_data, true );

        if ( is_wp_error( $post_id ) ) {
            $errors++;
            continue;
        }

        $imported++;
    }

    fclose( $handle );

    // Prepare redirect URL with status
    if ( $imported > 0 && $errors === 0 ) {
        $redirect_url = add_query_arg( array( 'page' => 'wd-export-import-posts', 'import' => 'success' ), admin_url( 'tools.php' ) );
    } elseif ( $imported > 0 && $errors > 0 ) {
        $redirect_url = add_query_arg( array( 'page' => 'wd-export-import-posts', 'import' => 'partial' ), admin_url( 'tools.php' ) );
    } else {
        $redirect_url = add_query_arg( array( 'page' => 'wd-export-import-posts', 'import' => 'error' ), admin_url( 'tools.php' ) );
    }

    wp_redirect( $redirect_url );
    exit;
}

/**
 * Get user ID by display name.
 *
 * @param string $display_name The display name of the user.
 * @return int|false The user ID or false if not found.
 */
function wd_get_author_by_display_name( $display_name ) {
    $user = get_user_by( 'display_name', $display_name );
    if ( $user ) {
        return $user->ID;
    }

    // Attempt to find user by login as a fallback
    $user = get_user_by( 'login', $display_name );
    if ( $user ) {
        return $user->ID;
    }

    // Return false if user not found
    return false;
}
