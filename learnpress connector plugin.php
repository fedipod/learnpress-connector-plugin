<?php
/*
 * Plugin Name: LearnPress Connector Plugin
 * Plugin URI: https://github.com/fedipod/learnpress-connector-plugin
 * Description: Getting LearnPress Learning Data Into WordPress Post.
 * Author: NI YUNHAO
 * Author URI: https://21te495.daiichi-koudai.com
 * Version: 4.1.2
 * Require_LP_Version: 4.0.0
 */


// Create admin menu
function learnpress_import_menu() {
    add_menu_page(
        'LearnPress Import', // Page title
        'LearnPress Import', // Menu title
        'manage_options',    // Capability
        'learnpress-import', // Menu slug
        'learnpress_import_page' // Callback function
    );
}
add_action('admin_menu', 'learnpress_import_menu');

// Display admin page
function learnpress_import_page() {
    ?>
    <div class="wrap">
        <h1>LearnPress Data Import</h1>
        <form method="post" action="">
            <?php wp_nonce_field('learnpress_import_action', 'learnpress_import_nonce'); ?>
            <input type="hidden" name="learnpress_import_action" value="import_data">
            <?php submit_button('Import Data'); ?>
        </form>
        <?php
        if (isset($_POST['learnpress_import_action']) && $_POST['learnpress_import_action'] == 'import_data') {
            if (!check_admin_referer('learnpress_import_action', 'learnpress_import_nonce')) {
                wp_die('Security check failed.');
            }
            learnpress_import_data();
        }
        ?>
    </div>
    <?php
}

// Import data function
function learnpress_import_data() {
    global $wpdb;

    // Query user course data
    $results = $wpdb->get_results("
        SELECT ui.user_id, ui.item_id as course_id, ui.status, ui.graduation, uir.result
        FROM {$wpdb->prefix}learnpress_user_items ui
        LEFT JOIN {$wpdb->prefix}learnpress_user_item_results uir ON ui.user_item_id = uir.user_item_id
        WHERE ui.item_type = 'lp_course'
    ");

    // Check if any data is returned
    if (empty($results)) {
        echo '<div class="notice notice-warning is-dismissible"><p>No data available for import.</p></div>';
        return;
    }

    foreach ($results as $row) {
        // Get user information
        $user_info = get_userdata($row->user_id);
        if (!$user_info) {
            continue;
        }
        $user_name = $user_info->user_login;

        // Get course information
        $course = get_post($row->course_id);
        if (!$course) {
            continue;
        }
        $course_title = $course->post_title;

        // Process course result
        $result = maybe_unserialize($row->result);
        $score = isset($result['result']) ? $result['result'] : 'N/A';

        // Create new WordPress post content
        $post_content = "User {$user_name} completed the course: {$course_title}.\n";
        $post_content .= "Status: {$row->status}\n";
        $post_content .= "Graduation: {$row->graduation}\n";
        $post_content .= "Score: {$score}";

        // Create new WordPress post
        $new_post = array(
            'post_title'    => 'LearnPress Learning Data',
            'post_content'  => $post_content,
            'post_status'   => 'publish',
            'post_author'   => $row->user_id,
            'post_category' => array(1)  // Default category
        );

        // Insert post into WordPress
        wp_insert_post($new_post);
    }

    echo '<div class="notice notice-success is-dismissible"><p>Data imported successfully.</p></div>';
}
?>
