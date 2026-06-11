jQuery(document).ready(function($) {
    // Filter Courses - FIXED version
    $('input[name="course-category-filter"]').on('change', function() {
        const selectedCategory = $(this).val();
        
        if (selectedCategory === 'all') {
            $('.all-courses-item').fadeIn(300);
        } else {
            $('.all-courses-item').each(function() {
                const $item = $(this);
                const categories = $item.data('categories');
                
                // Check if the selected category is in the item's categories
                if (categories && categories.includes(selectedCategory)) {
                    $item.fadeIn(300);
                } else {
                    $item.fadeOut(300);
                }
            });
        }
    });
    
    // Rank Popup
    const $modal = $('#ahovn-rank-popup-modal');
    const $trigger = $('#ahovn-rank-popup-trigger');
    const $close = $('.close-modal');
    const $contentContainer = $('#rank-content-container');
    
    $trigger.on('click', function() {
        $modal.fadeIn(300);
        loadRankData('week');
    });
    
    $close.on('click', function() {
        $modal.fadeOut(300);
    });
    
    $(window).on('click', function(event) {
        if ($(event.target).is($modal)) {
            $modal.fadeOut(300);
        }
    });
    
    // Rank Tab Switching
    $('.rank-tab-btn').on('click', function() {
        $('.rank-tab-btn').removeClass('active');
        $(this).addClass('active');
        
        const period = $(this).data('period');
        loadRankData(period);
    });
    
    // Load Rank Data via AJAX
    function loadRankData(period) {
        $.ajax({
            url: ahovn_lms_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ahovn_lms_load_rank_data',
                nonce: ahovn_lms_ajax.nonce,
                period: period
            },
            beforeSend: function() {
                $contentContainer.html('<p style="text-align:center;">Đang tải...</p>');
            },
            success: function(response) {
                if (response.success) {
                    $contentContainer.html(response.data);
                } else {
                    $contentContainer.html('<p style="text-align:center; color:red;">Lỗi tải dữ liệu</p>');
                }
            },
            error: function() {
                $contentContainer.html('<p style="text-align:center; color:red;">Lỗi kết nối</p>');
            }
        });
    }
    
    // FIXED: Handle course card clicks from all-courses grid
    // Ensures course_id parameter is appended to the course permalink
    $(document).on('click', '.all-courses-item', function(e) {
        const $card = $(this);
        const courseId = $card.data('id');
        let href = $card.attr('href');
        
        // Only modify href if we have both courseId and href
        if (href && courseId) {
            // Check if course_id parameter already exists in the URL
            if (href.indexOf('course_id=') === -1) {
                // Determine which separator to use (? or &)
                const separator = href.indexOf('?') === -1 ? '?' : '&';
                // Append the course_id parameter
                href = href + separator + 'course_id=' + encodeURIComponent(courseId);
                // Update the href attribute
                $card.attr('href', href);
            }
        }
        
        // Let the default link behavior proceed (browser navigation)
    });
});
