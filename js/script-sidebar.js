$(document).ready(function() {
    // Initialize Bootstrap tooltips
    $('[data-toggle="tooltip"]').tooltip();

    // Sidebar Collapse Toggle
    $('#sidebarCollapse').on('click', function() {
        $('#sidebar').toggleClass('collapsed');
        $('#sidebar-strip').toggleClass('hidden');

        // Toggle the icon between bars and times
        $('#toggleIcon').toggleClass('fa-bars fa-times');

        // Update the tooltip title based on sidebar state
        if ($('#sidebar').hasClass('collapsed')) {
            $('#sidebarCollapse').attr('data-original-title', 'Expand Sidebar');
        } else {
            $('#sidebarCollapse').attr('data-original-title', 'Collapse Sidebar');
        }

        // Refresh tooltips to reflect changes
        $('#sidebarCollapse').tooltip('hide').tooltip('show');

        // Trigger reflow on the cards when sidebar state changes
        $('.cards-row').css('display', 'none'); // Temporarily hide cards
        $('.cards-row').css('display', 'flex'); // Re-enable flex to adjust layout
    });

    // Set initial state based on sidebar class
    if ($('#sidebar').hasClass('collapsed')) {
        $('#toggleIcon').removeClass('fa-times').addClass('fa-bars');
        $('#sidebar-strip').addClass('hidden');
        $('.cards-row').addClass('collapsed');
    } else {
        $('#toggleIcon').removeClass('fa-bars').addClass('fa-times');
        $('#sidebar-strip').removeClass('hidden');
    }
});
