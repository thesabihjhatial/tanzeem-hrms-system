/**
 * Makes every form submit idempotent: on a successful (non-prevented)
 * submit, the button's own submit button disables itself and swaps its
 * label for a spinner until the browser navigates away. Runs after
 * validation.js's submit listener (script order in base.twig) so a
 * client-side validation failure — which calls preventDefault() — never
 * triggers the loading state on a form the user still needs to fix.
 */
window.ButtonLoader = (function () {
    function set(button, loading) {
        var label = button.querySelector('.btn-label');
        var loader = button.querySelector('.btn-loader');

        button.disabled = loading;
        button.classList.toggle('btn-loading', loading);

        if (label) {
            label.hidden = loading;
        }
        if (loader) {
            loader.hidden = !loading;
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('form').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                if (e.defaultPrevented) {
                    return;
                }

                var button = form.querySelector('button[type="submit"]');
                if (!button || button.disabled) {
                    return;
                }

                set(button, true);
            });
        });
    });

    return { set: set };
})();
