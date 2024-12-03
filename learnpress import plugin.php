<?php
/*
 * Plugin Name: LearnPress import Plugin
 * Description: Getting LearnPress Learning Data Into WordPress Post.
 * Author: NI YUNHAO
 * Version: 4.1.2
 * Require_LP_Version: 4.0.0
 */

// Create admin menu
function learnpress_import_menu() {
    add_menu_page(
        'LearnPress Import', // Page title
        'LearnPress Import', // Menu title
        'read',              // Capability (allow all logged-in users)
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
            // Remove the role check
            learnpress_import_data();
        }
        ?>
    </div>
    <?php
}

// Function to format time in a readable format
function format_time($seconds) {
    $years = floor($seconds / (365 * 24 * 3600));
    $seconds %= 365 * 24 * 3600;
    $days = floor($seconds / (24 * 3600));
    $seconds %= 24 * 3600;
    $hours = floor($seconds / 3600);
    $seconds %= 3600;
    $minutes = floor($seconds / 60);
    $seconds %= 60;

    $time_string = '';
    if ($years > 0) {
        $time_string .= $years . ' years ';
    }
    if ($days > 0) {
        $time_string .= $days . ' days ';
    }
    if ($hours > 0) {
        $time_string .= $hours . ' hours ';
    }
    if ($minutes > 0) {
        $time_string .= $minutes . ' minutes ';
    }
    if ($seconds > 0) {
        $time_string .= $seconds . ' seconds';
    }

    return trim($time_string);
}

// Function to get item type name
function get_item_type_name($item_type) {
    switch ($item_type) {
        case 'lp_lesson':
            return 'Lesson';
        case 'lp_quiz':
            return 'Quiz';
        default:
            return '';
    }
}

// Import data function
function learnpress_import_data() {
    global $wpdb;

    // Get current user ID
    $current_user_id = get_current_user_id();

    // Check if user is logged in
    if ($current_user_id == 0) {
        echo '<div class="notice notice-warning is-dismissible"><p>User not logged in.</p></div>';
        return;
    }

    // Get the table prefix
    $table_prefix = $wpdb->prefix;

    // Query user course data for the current user
    $results = $wpdb->get_results($wpdb->prepare("
        SELECT ui.user_id, ui.item_id as course_id, ui.status, ui.graduation, uir.result
        FROM {$table_prefix}learnpress_user_items ui
        LEFT JOIN {$table_prefix}learnpress_user_item_results uir ON ui.user_item_id = uir.user_item_id
        WHERE ui.item_type = 'lp_course' AND ui.user_id = %d
    ", $current_user_id));

    // Check if any data is returned
    if (empty($results)) {
        echo '<div class="notice notice-warning is-dismissible"><p>No data available for import.</p></div>';
        return;
    }

    foreach ($results as $row) {
        // Get course information
        $course = get_post($row->course_id);
        if (!$course) {
            continue;
        }
        $course_title = $course->post_title;

        // Process course result
        $result = maybe_unserialize($row->result);
        $score = isset($result['result']) ? $result['result'] : 'N/A';

        // Create new WordPress post content in HTML format
        $post_content = "<!-- wp:paragraph {\"className\":\"only-friends\"} -->";
        $post_content .= "<p class=\"only-friends\">Status: {$row->status}, Graduation: {$row->graduation}, Score: {$score}<br>";

        // Query section and item data
        $sections = $wpdb->get_results($wpdb->prepare("
            SELECT sec.section_id, sec.section_name, si.item_id, si.item_type, ui.start_time, ui.end_time, ui.graduation
            FROM {$table_prefix}learnpress_sections sec
            LEFT JOIN {$table_prefix}learnpress_section_items si ON sec.section_id = si.section_id
            LEFT JOIN {$table_prefix}learnpress_user_items ui ON si.item_id = ui.item_id AND ui.user_id = %d
            WHERE sec.section_course_id = %d
        ", $current_user_id, $row->course_id));

        if (!empty($sections)) {
            $completed_sections = "";
            $in_progress_sections = "";
            $uncompleted_sections = "";

            $completed_section_names = [];
            $in_progress_section_names = [];
            $uncompleted_section_names = [];

            foreach ($sections as $section) {
                $section_type = get_item_type_name($section->item_type);
                $start_time = $section->start_time ? $section->start_time : 'N/A';
                $end_time = $section->end_time ? $section->end_time : 'N/A';
                if ($start_time !== 'N/A' && $end_time !== 'N/A') {
                    $time_taken_seconds = strtotime($section->end_time) - strtotime($section->start_time);
                    $time_taken = format_time($time_taken_seconds);
                } else {
                    $time_taken = 'N/A';
                }

                // Get item name
                $item_name = get_the_title($section->item_id);

                $section_content = "";

                if ($start_time !== 'N/A' && $end_time !== 'N/A') {
                    if (!isset($completed_section_names[$section->section_id])) {
                        $section_content .= "{$section->section_name}<br>";
                        $completed_section_names[$section->section_id] = true;
                    }
                    $section_content .= "{$item_name}";
                    if ($section_type) {
                        $section_content .= ", Type: {$section_type}";
                    } else {
                        $section_content .= ", N/A";
                    }
                    $section_content .= ", Start Time: {$start_time}, End Time: {$end_time}, Time Taken: {$time_taken}";

                    // Display graduation status
                    if ($section->graduation == 'passed') {
                        $section_content .= ", Correct<br>";
                    } else {
                        $section_content .= ", Incorrect<br>";
                    }

                    $completed_sections .= $section_content;
                } elseif ($start_time !== 'N/A' && $end_time === 'N/A') {
                    if (!isset($in_progress_section_names[$section->section_id])) {
                        $section_content .= "{$section->section_name}<br>";
                        $in_progress_section_names[$section->section_id] = true;
                    }
                    $section_content .= "{$item_name}";
                    if ($section_type) {
                        $section_content .= ", Type: {$section_type}";
                    } else {
                        $section_content .= ", N/A";
                    }
                    $section_content .= ", Start Time: {$start_time}<br>";
                    $in_progress_sections .= $section_content;
                } else {
                    if (!isset($uncompleted_section_names[$section->section_id])) {
                        $section_content .= "{$section->section_name}<br>";
                        $uncompleted_section_names[$section->section_id] = true;
                    }
                    $section_content .= "{$item_name}";
                    if ($section_type) {
                        $section_content .= ", Type: {$section_type}";
                    } else {
                        $section_content .= "N/A";
                    }
                    $section_content .= "<br>";
                    $uncompleted_sections .= $section_content;
                }
            }

            if (!empty($completed_sections)) {
                $post_content .= "Completed Sections:<br>{$completed_sections}";
            }

            if (!empty($in_progress_sections)) {
                $post_content .= "In Progress Sections:<br>{$in_progress_sections}";
            }

            if (!empty($uncompleted_sections)) {
                $post_content .= "Uncompleted Sections:<br>{$uncompleted_sections}";
            }
        }
        $post_content .= "</p><!-- /wp:paragraph -->";

        // Create new WordPress post
        $new_post = array(
            'post_title'    => $course_title,
            'post_content'  => $post_content,
            'post_status'   => 'publish',
            'post_author'   => $current_user_id,
            'post_category' => array(1)  // Default category
        );

        // Insert post into WordPress
        wp_insert_post($new_post);
    }

    echo '<div class="notice notice-success is-dismissible"><p>Data imported successfully.</p></div>';
}
?>
