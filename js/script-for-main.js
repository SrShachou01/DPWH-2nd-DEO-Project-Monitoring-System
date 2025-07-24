// script-for-main.js

document.addEventListener('DOMContentLoaded', function() {
    // Function to toggle the section based on the checkbox
    function toggleSection(checkboxId, sectionId) {
        const checkbox = document.getElementById(checkboxId);
        const section = document.getElementById(sectionId);
        
        checkbox.addEventListener('change', function() {
            if (checkbox.checked) {
                section.removeAttribute('disabled');
                section.querySelectorAll('input, textarea').forEach(function(input) {
                    input.removeAttribute('disabled');
                });
            } else {
                section.setAttribute('disabled', true);
                section.querySelectorAll('input, textarea').forEach(function(input) {
                    input.setAttribute('disabled', true);
                });
            }
        });
    }

    // Enable sections based on the checkbox
    // Ensure that the section IDs correspond to the actual IDs in edit-project.php
    toggleSection('enable_cws', 'cws');
    toggleSection('enable_cwr', 'cwr');
    toggleSection('enable_cte', 'cte');
    toggleSection('enable_mtsr', 'mtsr');
    toggleSection('enable_vo', 'vo');
    toggleSection('enable_cm', 'cm');
    toggleSection('enable_iom', 'iom');
    toggleSection('enable_fc', 'fc');
});
