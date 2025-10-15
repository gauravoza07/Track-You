jQuery(document).ready(function($) {
    // Add any interactive features here
    $('.uat-filters select').on('change', function() {
        $(this).closest('form').submit();
    });
}); 