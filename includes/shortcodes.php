<?php
if ( ! defined( 'WPINC' ) ) { die; }

/**
 * Main Shortcode for displaying a Course, Unit, Lesson, or Quiz.
 * FIXED: Now checks for course_id from $_GET to support navigation from [ahovn_all_courses]
 */
function ahovn_lms_course_shortcode($atts) {
    $atts = shortcode_atts(['id' => 0], $atts, 'ahovn_lms_course');
    $course_id = intval($atts['id']);
    
    // FIXED: Check URL GET parameter for course_id (from [ahovn_all_courses] clicks)
    if (empty($course_id) && isset($_GET['course_id'])) {
        $course_id = intval($_GET['course_id']);
    }
    
    if (!$course_id || get_post_type($course_id) !== 'ahovn_lms_course') {
        return '<p>Lỗi: Không tìm thấy khóa học.</p>';
    }

    $view_item_id = isset($_GET['view_item']) ? intval($_GET['view_item']) : null;
    $view_item_type = isset($_GET['item_type']) ? sanitize_text_field($_GET['item_type']) : null;
    $show_unit_id = isset($_GET['show_unit']) ? intval($_GET['show_unit']) : null;

    if ($view_item_id && $view_item_type) {
        return ahovn_lms_render_single_item_view($view_item_id, $view_item_type);
    }
    if ($show_unit_id) {
        return ahovn_lms_render_unit_view($show_unit_id, $course_id);
    }
    return ahovn_lms_render_course_grid_view($course_id);
}

/**
 * Renders the view for a single Lesson or Quiz.
 */
function ahovn_lms_render_single_item_view($item_id, $item_type) {
    $item_post = get_post($item_id);
    if (!$item_post) return '<p>Lỗi: Không tìm thấy mục này.</p>';

    // Get Course ID for tracking
    $course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
    $tracking_attrs = '';
    if ($course_id) {
        $tracking_attrs = sprintf(' data-track-course-id="%d" data-track-title="%s" ', $course_id, esc_attr($item_post->post_title));
    }

    ob_start();
    // Added tracking attributes to wrapper
    echo '<div class="ahovn-lms-single-item-view"' . $tracking_attrs . '>';
    $back_link = remove_query_arg(['view_item', 'item_type', 'item_key', 'show_flashcard']);
    echo '<a href="' . esc_url($back_link) . '" class="ahovn-lms-back-link">&larr; Quay lại danh sách</a>';

    if ($item_type === 'lesson') {
        echo '<div class="ahovn-lms-lesson-content">' . apply_filters('the_content', $item_post->post_content) . '</div>';
        echo ahovn_lms_render_lesson_navigation($item_id);
    } elseif ($item_type === 'quiz') {
        $show_flashcard_from_url = isset($_GET['show_flashcard']) && $_GET['show_flashcard'] === 'true';
        $item_key = $_GET['item_key'] ?? 'NO';
        $course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
        
        echo do_shortcode('[ahoquizper_test id="' . $item_id . '" email_key="' . esc_attr($item_key) . '" course_id="' . $course_id . '"]');
        
        if ($show_flashcard_from_url) {
            echo '<div class="ahovn-lms-flashcard-link-wrapper">';
            echo '<h3>Học với Flashcard</h3>';
            echo do_shortcode('[ahoquizper_flashcard id="' . $item_id . '"]');
            echo '</div>';
        }
    }
    echo '</div>';
    return ob_get_clean();
}

/**
 * Renders the grid view for items inside a Unit.
 */
function ahovn_lms_render_unit_view($unit_id, $course_id) {
    $unit_items_meta = get_post_meta($unit_id, '_ahovn_lms_unit_items', true);
    if (empty($unit_items_meta) || !is_array($unit_items_meta)) { return '<p>Chương này chưa có nội dung.</p>'; }

    $unit_items = [];
    foreach ($unit_items_meta as $item_data) {
        if ($item_post = get_post($item_data['id'])) {
            $item_data['post_title'] = $item_post->post_title;
            $unit_items[] = $item_data;
        }
    }
    usort($unit_items, fn($a, $b) => strnatcmp($a['post_title'], $b['post_title']));

    $current_page_url = get_permalink();
    // If we are on a standalone unit page, get_permalink() might be the unit link.
    // If we are inside a course, it is the course link.
    
    // Added tracking attributes
    $tracking_attrs = '';
    if ($course_id) {
        $tracking_attrs = sprintf(' data-track-course-id="%d" data-track-title="%s" ', $course_id, esc_attr(get_the_title($unit_id)));
    }

    ob_start();
    ?>
    <!-- Wrapper with tracking -->
    <div class="ahovn-lms-unit-view" <?php echo $tracking_attrs; ?>>
        <?php if($course_id > 0): // Only show back link if inside a course context ?>
            <a href="<?php echo esc_url($current_page_url); ?>" class="ahovn-lms-back-link">&larr; Quay lại khóa học</a>
        <?php endif; ?>
        
        <h2><?php echo get_the_title($unit_id); ?></h2>
        <div class="ahovn-lms-course-grid">
            <?php
            foreach ($unit_items as $index => $item_data) {
                $item_args = [
                    'view_item' => $item_data['id'],
                    'item_type' => $item_data['type'],
                    'course_id' => $course_id,
                ];
                // Only add show_unit param if we are in course context. 
                // In standalone unit, the current URL is already the unit URL.
                if ($course_id > 0) {
                     $item_args['show_unit'] = $unit_id;
                }

                if ($item_data['type'] === 'quiz' && isset($item_data['settings'])) {
                   $item_args['item_key'] = $item_data['settings']['email_key'] ?? 'NO';
                   $item_args['show_flashcard'] = !empty($item_data['settings']['show_flashcard']) ? 'true' : 'false';
                }
                $item_permalink = add_query_arg($item_args, $current_page_url);
                echo ahovn_lms_render_grid_item($item_data, $item_permalink, $index + 1);
            }
            ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Renders the main grid view for a Course.
 */
function ahovn_lms_render_course_grid_view($course_id) {
    $course_content_meta = get_post_meta($course_id, '_ahovn_lms_course_content', true);
    if (empty($course_content_meta)) { return '<p>Khóa học này chưa có nội dung.</p>'; }

    $course_items = [];
    foreach ($course_content_meta as $item_data) {
        if ($item_post = get_post($item_data['id'])) {
            $item_data['post_title'] = $item_post->post_title;
            $course_items[] = $item_data;
        }
    }
    usort($course_items, fn($a, $b) => strnatcmp($a['post_title'], $b['post_title']));
    
    $current_page_url = get_permalink();
    ob_start();
    ?>
    <!-- Added data-course-id attribute -->
    <div class="ahovn-lms-course-wrapper" data-course-id="<?php echo esc_attr($course_id); ?>">
        <div class="ahovn-lms-course-grid">
        <?php 
        foreach ($course_items as $index => $item_data) {
            $item_id = $item_data['id'];
            $item_type = $item_data['type'];
            $item_permalink = '#';

            if ($item_type === 'unit') {
                $item_permalink = add_query_arg(['show_unit' => $item_id, 'course_id' => $course_id], $current_page_url);
            } else {
                 $args = [ 'view_item' => $item_id, 'item_type' => $item_type, 'course_id' => $course_id ];
                 if ($item_type === 'quiz' && isset($item_data['settings'])) {
                     $args['item_key'] = $item_data['settings']['email_key'] ?? 'NO';
                     $args['show_flashcard'] = !empty($item_data['settings']['show_flashcard']) ? 'true' : 'false';
                 }
                 $item_permalink = add_query_arg($args, $current_page_url);
            }
            echo ahovn_lms_render_grid_item($item_data, $item_permalink, $index + 1);
        }
        ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Helper function to render the navigation buttons for a lesson.
 */
function ahovn_lms_render_lesson_navigation($current_item_id) {
    $next_item_data = ahovn_lms_get_next_item($current_item_id);
    ob_start();
    ?>
    <div id="ahovn-lms-results-navigation" style="display: flex;">
        <a href="<?php echo esc_url(remove_query_arg(['view_item', 'item_type'])); ?>" id="ahovn-lms-redo-btn" class="button">Hoàn thành & Quay lại</a>
        <?php if ($next_item_data): 
            // FIX: Ensure we use the correct permalink base. 
            // If in unit mode, use the unit link. If in course mode, use course link.
            $base_link = get_permalink();
            $next_item_url = add_query_arg($next_item_data, $base_link); 
        ?>
            <a href="<?php echo esc_url($next_item_url); ?>" id="ahovn-lms-next-item-btn" class="button">Hoàn thành & Bài tiếp theo</a>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Helper function to find the next item in a course/unit.
 */
function ahovn_lms_get_next_item($current_item_id) {
    $course_id = isset($_REQUEST['course_id']) ? intval($_REQUEST['course_id']) : 0;
    $unit_id = isset($_REQUEST['show_unit']) ? intval($_REQUEST['show_unit']) : 0;
    
    // FIX 1: If no course_id, check if we are in a standalone Unit by Post Type
    // This allows finding the next item when viewing a Unit directly
    if (!$course_id && get_post_type() === 'ahovn_lms_unit') {
        $unit_id = get_the_ID();
    }
    
    // If still no unit_id and no course_id, we can't determine next item
    if (!$course_id && !$unit_id) return null;

    $items_list = ($unit_id) ? get_post_meta($unit_id, '_ahovn_lms_unit_items', true) : get_post_meta($course_id, '_ahovn_lms_course_content', true);
    
    if (empty($items_list) || !is_array($items_list)) return null;
    $sorted_list = [];
    foreach ($items_list as $item) { if ($post = get_post($item['id'])) { $item['post_title'] = $post->post_title; $sorted_list[] = $item; } }
    usort($sorted_list, fn($a, $b) => strnatcmp($a['post_title'], $b['post_title']));
    $current_item_index = -1;
    foreach ($sorted_list as $index => $item) { if ($item['id'] == $current_item_id) { $current_item_index = $index; break; } }

    if ($current_item_index !== -1 && isset($sorted_list[$current_item_index + 1])) {
        $next_item = $sorted_list[$current_item_index + 1];
        $next_item_data = [];
        
        if ($course_id) { $next_item_data['course_id'] = $course_id; }
        
        // Only add 'show_unit' if we are actually in a Course context looking at a unit
        if ($unit_id && $course_id) { $next_item_data['show_unit'] = $unit_id; }
        
        if ($next_item['type'] === 'unit') { 
            unset($next_item_data['show_unit']); $next_item_data['show_unit'] = $next_item['id'];
        } else { 
            $next_item_data['view_item'] = $next_item['id']; 
            $next_item_data['item_type'] = $next_item['type']; 
        }
        
        if ($next_item['type'] === 'quiz') {
            $settings_found = false;
            // Check settings in item data directly
            if (isset($next_item['settings'])) {
                $next_item_data['item_key'] = $next_item['settings']['email_key'] ?? 'NO';
                $next_item_data['show_flashcard'] = !empty($next_item['settings']['show_flashcard']) ? 'true' : 'false';
                $settings_found = true;
            }
            // Fallback to course data if needed
            if (!$settings_found && $course_id) {
                $course_content_meta = get_post_meta($course_id, '_ahovn_lms_course_content', true);
                if(is_array($course_content_meta)) {
                    foreach($course_content_meta as $course_item) {
                        if (isset($course_item['id']) && $course_item['id'] == $next_item['id']) {
                            $next_item_data['item_key'] = $course_item['settings']['email_key'] ?? 'NO';
                            $next_item_data['show_flashcard'] = !empty($course_item['settings']['show_flashcard']) ? 'true' : 'false';
                            break;
                        }
                    }
                }
            }
        }
        return $next_item_data;
    }
    return null;
}

/**
 * Helper function to render a single grid item.
 */
function ahovn_lms_render_grid_item($item_data, $permalink, $sequential_index) {
    $item_id = $item_data['id']; $item_type = $item_data['type']; $item_post_title = $item_data['post_title'] ?? '';
    $item_class = 'course-grid-item-' . esc_attr($item_type); $item_title_display = esc_html(str_replace('_', ' ', $item_post_title));
    $number = str_pad($sequential_index, 2, '0', STR_PAD_LEFT);
    if (preg_match('/_?(\d+)$/', $item_post_title, $matches)) { $number = str_pad($matches[1], 2, '0', STR_PAD_LEFT); }
    return sprintf('<a href="%s" class="course-grid-item %s" data-item-id="%s"><div class="course-grid-item-icon"><span>%s</span></div><div class="course-grid-item-title">%s</div></a>', esc_url($permalink), esc_attr($item_class), esc_attr($item_id), $number, $item_title_display);
}

add_action('init', function() {
    add_shortcode('ahovn_lms_course', 'ahovn_lms_course_shortcode');
    add_shortcode('ahoquizper_test', 'ahoquizper_test_shortcode');
    add_shortcode('ahoquizper_flashcard', 'ahoquizper_flashcard_shortcode');
});

/**
 * Helper function to render media list HTML (Support Audio/Image)
 */
function ahovn_lms_render_item_media($media_items) {
    if (empty($media_items) || !is_array($media_items)) return '';
    
    $html = '<div class="quiz-media-gallery">';
    foreach($media_items as $url) {
        $ext = pathinfo($url, PATHINFO_EXTENSION);
        $ext = strtolower($ext);
        
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])) {
            $html .= '<div class="quiz-media-item media-image"><img src="' . esc_url($url) . '" alt="Quiz Media" style="max-width:100%; height:auto; display:block; margin: 10px auto; border-radius: 5px;"></div>';
        } elseif (in_array($ext, ['mp3', 'wav', 'ogg'])) {
             $html .= '<div class="quiz-media-item media-audio" style="margin: 10px 0;">' . do_shortcode('[audio src="' . esc_url($url) . '"]') . '</div>';
        }
    }
    $html .= '</div>';
    return $html;
}

/**
 * Quiz Shortcode
 * --- ORDER UPDATED: Extension -> Media -> Flashcard ---
 * --- UPDATED: Render Multiple Media (Audio/Image) ---
 * --- FIX: Always use standalone mode (show all mondai at once) ---
 */
function ahoquizper_test_shortcode($atts) {
    $atts = shortcode_atts([
        'id' => 0, 
        'email_key' => 'NO', 
        'course_id' => 0, 
        'show_flashcard' => 'false',
        'is_standalone' => 'false'
    ], $atts, 'ahoquizper_test');

    $post_id = intval($atts['id']); 
    $email_key = $atts['email_key']; 
    if ($email_key === -1 || $email_key === '') $email_key = 'NO'; 

    $course_id = intval($atts['course_id']);
    $show_flashcard = filter_var($atts['show_flashcard'], FILTER_VALIDATE_BOOLEAN);
    
    // FIX: Always use standalone mode to show all mondai at once
    $is_standalone = true;

    $is_free_mode = (strtoupper($email_key) === 'FREE'); 

    if (!$post_id || !get_post($post_id)) { return '<p>Lỗi: Bài thi không hợp lệ hoặc thiếu thông số.</p>'; }
    $topics = get_post_meta($post_id, '_ahoquizper_topics', true);
    if (empty($topics) || !is_array($topics)) { return '<p>Bài thi này chưa có nội dung.</p>'; }
    
    foreach ($topics as $t_key => $topic) {
        if (!isset($topic['items']) || !is_array($topic['items'])) continue;
        $quizzes_only = [];

        $topics[$t_key]['no_random_questions'] = !empty($topic['no_random_questions']);

        foreach ($topic['items'] as $i_key => $item) {
            $item_type = $item['type'] ?? 'quiz';
            if ($item_type === 'quiz') {
                $question_content = $item['question'] ?? '';
                
                // --- NEW: Render Media Items ---
                $media_items = [];
                // Check new array format
                if (isset($item['media_items']) && is_array($item['media_items'])) {
                    $media_items = $item['media_items'];
                } elseif (!empty($item['audio_url'])) { 
                    // Fallback to old single audio
                    $media_items[] = $item['audio_url']; 
                }

                if (!empty($media_items)) {
                    $question_content .= ahovn_lms_render_item_media($media_items);
                }
                // ------------------------------

                $topic['items'][$i_key]['question'] = apply_filters('the_content', $question_content);
                $topic['items'][$i_key]['no_random_options'] = !empty($item['no_random_options']);
                $quizzes_only[] = $topic['items'][$i_key];
            } else if ($item_type === 'lesson') {
                $topic['items'][$i_key]['processed_content'] = apply_filters('the_content', do_shortcode($item['content'] ?? ''));
                unset($topic['items'][$i_key]['content']);
            }
        }
        $topics[$t_key]['items'] = $topic['items']; $topics[$t_key]['quizzes'] = $quizzes_only;
    }

    // --- FIX 2: Prepare next item data for JS ---
    // This allows the "Next" button on the results screen (rendered by JS) to work
    $next_item_data = ahovn_lms_get_next_item($post_id);
    $next_item_url_params = [];
    if ($next_item_data) {
        // We only need the parameters, not the full URL, because JS will append them
        $next_item_url_params = $next_item_data;
    }

    ob_start();
    ?>
    <div class="ahoquizper-wrapper">
        <div id="ahoquizper-quiz" data-post-id="<?php echo esc_attr($post_id); ?>" data-email-key="<?php echo esc_attr($email_key); ?>" data-course-id="<?php echo esc_attr($course_id); ?>">
            
            <?php if (!is_user_logged_in()): ?>
            <div id="login-prompt-wrapper">
                <a href="<?php echo wp_login_url(get_permalink()); ?>" class="button login-prompt-button">ĐĂNG NHẬP</a>
                <span>để lưu tiến trình học, xếp hạng bài làm...</span>
            </div>
            <?php endif; ?>

            <h1 class="quiz-title"><?php echo get_the_title($post_id); ?></h1>
            <div id="ahovn-lms-leaderboard-container">
                <?php echo ahovn_lms_get_leaderboard_html($post_id); ?>
            </div>
            <div id="quiz-content-wrapper"></div>
            <div id="ahovn-lms-quiz-navigation"><button id="ahovn-lms-next-topic-btn">Mondai tiếp theo (Bỏ qua)</button><button id="ahovn-lms-submit-btn">Nộp bài</button></div>
            <div id="ahovn-lms-results-navigation"></div>
        </div>
        
        <!-- Pass next_item_data to JS -->
        <script type="text/javascript">
            var ahoquizper_quiz_data = <?php echo wp_json_encode([
                'topics' => $topics, 
                'is_free_mode' => $is_free_mode, 
                'is_standalone' => $is_standalone, 
                'is_user_logged_in' => is_user_logged_in(),
            ]); ?>;
        </script>
        
        <?php 
        // 1. Extension Content (Move to top)
        $extension_content = get_post_meta($post_id, '_ahoquizper_extension_content', true);
        if ($extension_content) {
            echo '<div class="ahovn-lms-extension-wrapper">';
            echo '<div class="extension-header"><span class="dashicons dashicons-lightbulb"></span> Mở rộng</div>';
            echo '<div class="extension-content">' . do_shortcode(wpautop($extension_content)) . '</div>';
            echo '</div>';
        }

        // 2. Media (Move to middle)
        $end_media_url = get_post_meta($post_id, '_ahoquizper_end_media_url', true);
        $end_media_start = get_post_meta($post_id, '_ahoquizper_end_media_start', true);
        $end_media_end = get_post_meta($post_id, '_ahoquizper_end_media_end', true);
        
        if ($end_media_url) {
            echo '<div class="ahovn-lms-end-media-wrapper">';
            if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i', $end_media_url, $matches)) {
                $video_id = $matches[1];
                $embed_url = "https://www.youtube.com/embed/" . esc_attr($video_id) . "?enablejsapi=1";
                if (!empty($end_media_start)) { $embed_url .= "&start=" . intval($end_media_start); }
                if (!empty($end_media_end)) { $embed_url .= "&end=" . intval($end_media_end); }
                echo '<div class="ahovn-lms-responsive-video"><iframe src="' . $embed_url . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div>';
            } else {
                echo '<div class="ahovn-lms-audio-player"><audio controls style="width: 100%;"><source src="' . esc_url($end_media_url) . '">Trình duyệt của bạn không hỗ trợ thẻ audio.</audio></div>';
            }
            echo '</div>';
        }

        // 3. Flashcard (Move to bottom)
        if ($show_flashcard):
            echo '<div class="ahovn-lms-flashcard-link-wrapper">';
            echo '<h3>Học với Flashcard</h3>';
            echo do_shortcode('[ahoquizper_flashcard id="' . $post_id . '"]');
            echo '</div>';
        endif; 
        ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Flashcard Shortcode
 * --- UPDATED: Added Heart Icon for Favorites & New Back Format ---
 * --- UPDATED: Support Image/Audio in Flashcard ---
 */
function ahoquizper_flashcard_shortcode($atts) {
    $atts = shortcode_atts(['id' => 0], $atts, 'ahoquizper_flashcard');
    $post_id = intval($atts['id']);
    if (!$post_id || get_post_type($post_id) !== 'ahoquizper_test') { return '<p>Lỗi: Không tìm thấy bài thi cho flashcard.</p>'; }
    $topics = get_post_meta($post_id, '_ahoquizper_topics', true);
    if (empty($topics)) return '<p>Bài thi này chưa có câu hỏi nào.</p>';
    
    $flashcards = [];
    $test_title = get_the_title($post_id); // Lấy tên bài thi để lưu vết

    foreach ($topics as $topic) {
        if (!empty($topic['items'])) {
            foreach ($topic['items'] as $item) {
                if (($item['type'] ?? 'quiz') === 'quiz' && isset($item['correct'])) {
                    $correct_option_index = intval($item['correct']);
                    if (isset($item['options'][$correct_option_index])) {
                        // Prepare Content
                        $front_content = $item['options'][$correct_option_index];
                        $question_raw  = $item['question'] ?? '';
                        
                        // --- NEW: Render Media Items for Flashcard ---
                        $media_items = [];
                        if (isset($item['media_items']) && is_array($item['media_items'])) {
                            $media_items = $item['media_items'];
                        } elseif (!empty($item['audio_url'])) { 
                            $media_items[] = $item['audio_url']; 
                        }
                        if (!empty($media_items)) {
                            $question_raw .= ahovn_lms_render_item_media($media_items);
                        }
                        // ---------------------------------------------

                        $question_line = apply_filters('the_content', $question_raw);
                        $question_line = str_replace(']]>', ']]&gt;', $question_line);

                        // Explanation logic
                        $explanation_raw  = $item['explanation'] ?? '';
                        $explanation_raw = str_replace(array("\r\n", "\r"), "\n", $explanation_raw);
                        $lines = explode("\n", $explanation_raw);
                        
                        $line1 = array_shift($lines);
                        $line1_formatted = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', esc_html($line1));

                        $rest_formatted = '';
                        if (!empty($lines)) {
                            $rest_parts = [];
                            foreach($lines as $l) {
                                if(trim($l) === '') continue; 
                                $rest_parts[] = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', esc_html($l));
                            }
                            $rest_formatted = implode("<br>", $rest_parts);
                        }

                        $back_content  = '<div class="flashcard-back-content">';
                        $back_content .= '<div class="flashcard-section-top">';
                        if (!empty($line1_formatted)) {
                            $back_content .= '<div class="flashcard-explanation-title">' . $line1_formatted . '</div>';
                        }
                        $back_content .= '<div class="flashcard-question">' . $question_line . '</div>';
                        $back_content .= '</div>';
                        if (!empty($rest_formatted)) {
                            $back_content .= '<div class="flashcard-divider"></div>';
                            $back_content .= '<div class="flashcard-explanation-detail">' . $rest_formatted . '</div>';
                        }
                        $back_content .= '</div>';

                        // Generate a unique ID for this card to track favorites
                        $card_id = $post_id . '_' . md5($front_content . $question_raw);

                        $flashcards[] = [
                            'id' => $card_id,
                            'test_title' => $test_title,
                            'front' => esc_html($front_content), 
                            'back'  => $back_content
                        ];
                    }
                }
            }
        }
    }

    if (empty($flashcards)) { return '<p>Không có câu hỏi nào có phần giải thích để tạo flashcard.</p>'; }
    
    ob_start(); ?>
    <div class="ahoquizper-wrapper">
        <div id="ahoquizper-flashcard-container" class="flashcard-container">
            <div class="flashcard">
                <div class="flashcard-inner">
                    <div class="flashcard-front">
                        <!-- Content Rendered by JS -->
                    </div>
                    <div class="flashcard-back">
                        <!-- Content Rendered by JS -->
                    </div>
                </div>
            </div>
            
            <!-- Heart Toggle Button (Floating inside container) -->
            <div id="flashcard-fav-btn" title="Lưu vào thẻ yêu thích">
                <i class="dashicons dashicons-heart"></i>
            </div>

            <div class="flashcard-navigation">
                <button id="flashcard-prev" title="Previous (←)">&larr;</button>
                <span id="flashcard-counter">1 / <?php echo count($flashcards); ?></span>
                <button id="flashcard-next" title="Next (→)">&rarr;</button>
                <button id="flashcard-shuffle" title="Xáo trộn (Random)" style="margin-left: 15px;">🔀</button>
            </div>
            
             <div class="flashcard-instructions">Phím tắt: Space=lật thẻ, ←/→＝Thẻ trước/Sau</div>
        </div>
        <script type="text/javascript">var ahoquizper_flashcard_data = <?php echo wp_json_encode($flashcards); ?>;</script>
    </div>
    <?php return ob_get_clean();
}
