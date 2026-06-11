<?php
if ( ! defined( 'WPINC' ) ) { die; }

/**
 * Shortcode: [ahovn_all_courses]
 * Displays all courses in a filterable grid with categories
 */
function ahovn_lms_all_courses_shortcode($atts) {
    $atts = shortcode_atts(['categories' => ''], $atts, 'ahovn_all_courses');
    
    wp_enqueue_style('ahovn-lms-all-courses-style', 
        AHOVNLMS_PRO_URL . 'public/css/all-courses-style.css', [], AHOVNLMS_PRO_VERSION);
    wp_enqueue_script('ahovn-lms-all-courses-script', 
        AHOVNLMS_PRO_URL . 'public/js/all-courses-script.js', ['jquery'], AHOVNLMS_PRO_VERSION, true);
    
    $courses = get_posts([
        'post_type' => 'ahovn_lms_course',
        'numberposts' => -1,
        'post_status' => 'publish',
        'orderby' => 'title',
        'order' => 'ASC'
    ]);
    
    $categories = get_terms([
        'taxonomy' => 'ahovn_course_category',
        'hide_empty' => false
    ]);
    
    ob_start();
    ?>
    <div class="ahovn-all-courses-wrapper">
        <!-- Header with Filter and Rank Button -->
        <div class="all-courses-header">
            <div class="courses-header-left">
                <button class="rank-btn" id="ahovn-rank-popup-trigger" title="Top 10 students">
                    <span class="dashicons dashicons-chart-bar"></span> Top 10
                </button>
            </div>
            
            <div class="courses-header-right">
                <div class="course-filter">
                    <div class="filter-options">
                        <label class="filter-checkbox">
                            <input type="radio" name="course-category-filter" value="all" checked>
                            All
                        </label>
                        <?php foreach($categories as $cat): ?>
                            <label class="filter-checkbox">
                                <input type="radio" name="course-category-filter" value="<?php echo esc_attr($cat->slug); ?>" data-term-id="<?php echo esc_attr($cat->term_id); ?>">
                                <?php echo esc_html($cat->name); ?> (<?php echo $cat->count; ?>)
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Courses Grid -->
        <div class="ahovn-all-courses-grid">
            <?php
            if (empty($courses)) {
                echo '<p class="no-courses">No courses available.</p>';
            } else {
                foreach($courses as $course) {
                    $course_id = $course->ID;
                    $icon = ahovn_lms_get_course_icon($course_id);
                    $course_link = get_permalink($course_id);
                    
                    // Get course categories
                    $course_categories = wp_get_post_terms($course_id, 'ahovn_course_category', ['fields' => 'all']);
                    
                    // Build category classes and data attributes
                    $category_classes = [];
                    $category_slugs = [];
                    foreach ($course_categories as $cat) {
                        $category_classes[] = 'cat-' . esc_attr($cat->slug);
                        $category_slugs[] = esc_attr($cat->slug);
                    }
                    $category_class = !empty($category_classes) ? implode(' ', $category_classes) : 'uncategorized';
                    
                    // Get course stats - Count quizzes from course content
                    $quiz_count = 0;
                    
                    // Get course content items
                    $course_content = get_post_meta($course_id, '_ahovn_lms_course_content', true);
                    if (is_array($course_content)) {
                        foreach ($course_content as $item) {
                            if (isset($item['type']) && $item['type'] === 'quiz') {
                                $quiz_count++;
                            } elseif (isset($item['type']) && $item['type'] === 'unit') {
                                // Count quizzes inside units
                                $unit_id = $item['id'];
                                $unit_items = get_post_meta($unit_id, '_ahovn_lms_unit_items', true);
                                if (is_array($unit_items)) {
                                    foreach ($unit_items as $unit_item) {
                                        if (isset($unit_item['type']) && $unit_item['type'] === 'quiz') {
                                            $quiz_count++;
                                        }
                                    }
                                }
                            }
                        }
                    }
                    ?>
                    <a href="<?php echo esc_url($course_link); ?>" 
                       class="ahovn-course-card all-courses-item <?php echo esc_attr($category_class); ?>"
                       data-id="<?php echo esc_attr($course_id); ?>"
                       data-categories="<?php echo esc_attr(implode(' ', $category_slugs)); ?>">
                        <div class="course-icon-wrapper">
                            <span class="course-icon"><?php echo $icon; ?></span>
                        </div>
                        <h3 class="course-title"><?php echo esc_html($course->post_title); ?></h3>
                        <p class="course-meta">
                            <span class="quiz-count"><?php echo $quiz_count; ?> quiz</span>
                        </p>
                    </a>
                    <?php
                }
            }
            ?>
        </div>
    </div>
    
    <!-- Rank Popup Modal -->
    <div id="ahovn-rank-popup-modal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h2>Top 10</h2>
            
            <div class="rank-tabs">
                <button class="rank-tab-btn active" data-period="week">This Week</button>
                <button class="rank-tab-btn" data-period="month">This Month</button>
                <button class="rank-tab-btn" data-period="year">This Year</button>
            </div>
            
            <div id="rank-content-container">
                <!-- Content loaded by AJAX -->
            </div>
        </div>
    </div>
    
    <?php
    return ob_get_clean();
}
add_shortcode('ahovn_all_courses', 'ahovn_lms_all_courses_shortcode');
