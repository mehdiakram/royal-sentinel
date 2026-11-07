/**
 * Royal Sentinel Admin JS
 * Dark Mode Toggle + Global Admin Features
 */
(function() {
    'use strict';

    // =====================
    // Dark Mode
    // =====================
    var DM_KEY = 'rs_dark_mode';

    function applyDarkMode(enabled) {
        if (enabled) {
            document.body.classList.add('rs-dark-mode');
            var btn = document.getElementById('rs-dm-toggle');
            if (btn) btn.textContent = '☀️';
        } else {
            document.body.classList.remove('rs-dark-mode');
            var btn = document.getElementById('rs-dm-toggle');
            if (btn) btn.textContent = '🌙';
        }
    }

    function initDarkMode() {
        // Inject floating button
        var btn = document.createElement('button');
        btn.id = 'rs-dm-toggle';
        btn.className = 'rs-dark-mode-toggle';
        btn.title = 'Toggle Dark Mode';
        btn.textContent = '🌙';
        document.body.appendChild(btn);

        // Load saved preference
        var saved = localStorage.getItem(DM_KEY);
        if (saved === '1') {
            applyDarkMode(true);
        }

        // Toggle on click
        btn.addEventListener('click', function() {
            var isDark = document.body.classList.contains('rs-dark-mode');
            applyDarkMode(!isDark);
            localStorage.setItem(DM_KEY, isDark ? '0' : '1');
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDarkMode);
    } else {
        initDarkMode();
    }

})();
