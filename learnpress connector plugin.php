<?php
/*
 * Plugin Name: LearnPress Connector Plugin
 * Plugin URI: https://github.com/fedipod/learnpress-connector-plugin
 * Description: Getting LearnPress Learning Data Into WordPress Post.
 * Author: NI YUNHAO
 * Author URI: https://21te495.daiichi-koudai.com
 * Version: 0.1.2
 */

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

// 获取 LearnPress 数据并插入到帖子中
function learnpress_to_post() {
    // 获取所有课程数据
    $courses = learn_press_get_courses(array('post_status' => 'publish')); // 假设这是一个可以获取所有课程数据的函数

    foreach ($courses as $course) {
        // 检查是否已经有对应的帖子
        $existing_post = get_posts(array(
            'post_type' => 'post',
            'meta_key' => '_learnpress_course_id',
            'meta_value' => $course->ID,
            'post_status' => 'publish',
        ));

        if (!$existing_post) {
            // 创建新的帖子
            $post_data = array(
                'post_title'    => $course->post_title,
                'post_content'  => $course->post_content,
                'post_status'   => 'publish',
                'post_author'   => get_current_user_id(),
                'post_type'     => 'post',
                'meta_input'    => array(
                    '_learnpress_course_id' => $course->ID,
                ),
            );

            // 插入帖子
            wp_insert_post($post_data);
        }
    }
}

// 在管理菜单中添加一个按钮来执行数据集成操作
function add_learnpress_to_post_menu() {
    add_menu_page(
        'LearnPress Connector', // 页面标题
        'LearnPress Connector', // 菜单标题
        'manage_options', // 能力
        'learnpress-connector', // 菜单slug
        'learnpress_connector_page', // 回调函数
        'dashicons-admin-tools', // 图标
        100 // 位置
    );
}
add_action('admin_menu', 'add_learnpress_to_post_menu');

// 输出插件页面内容
function learnpress_connector_page() {
    ?>
    <div class="wrap">
        <h1>LearnPress 数据集成</h1>
        <form method="post" action="">
            <input type="hidden" name="learnpress_import" value="1">
            <?php submit_button('导入 LearnPress 数据到帖子'); ?>
        </form>
    </div>
    <?php
    if (isset($_POST['learnpress_import'])) {
        learnpress_to_post();
        echo '<div class="updated notice"><p>LearnPress 数据已成功导入到帖子中。</p></div>';
    }
}
?>
