// assets/js/script.js
document.addEventListener('DOMContentLoaded', function () {
    // Enable Bootstrap tooltips everywhere
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });

    // Add active class to current nav-link based on URL (simple version)
    const currentLocation = window.location.pathname;
    const navLinks = document.querySelectorAll('.user-profile-sidebar .list-group-item, .custom-navbar .nav-link:not(.dropdown-toggle)');
    navLinks.forEach(link => {
        // Check if the link's href (relative path) is part of the current location
        // This needs to be more robust for complex URLs or query strings
        if (link.getAttribute('href') !== '#' && currentLocation.includes(link.getAttribute('href').split('/').pop())) {
            // Check if the current link is more specific than an already active one
            let currentlyActive = document.querySelector('.user-profile-sidebar .list-group-item.active, .custom-navbar .nav-link.active:not(.dropdown-toggle)');
            if (currentlyActive && currentlyActive.getAttribute('href').length > link.getAttribute('href').length) {
                // Don't mark less specific link as active
            } else {
                 if(currentlyActive) currentlyActive.classList.remove('active');
                 link.classList.add('active');
            }
        } else {
            // link.classList.remove('active'); // Be careful, this might remove active from parent
        }
    });
     // Special case for dashboard
    if (currentLocation.endsWith('/dashboard.php') || currentLocation.endsWith('/')) {
        const dashboardLink = document.querySelector('a[href="dashboard.php"]');
        if (dashboardLink) {
             navLinks.forEach(l => l.classList.remove('active')); // Remove other active states
             dashboardLink.classList.add('active');
        }
    }

});