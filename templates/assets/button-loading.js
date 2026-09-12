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

        // A disabled control is excluded when the browser builds the
        // submitted form data — and that exclusion is checked AFTER the
        // submit event runs. So disabling a submitter button that itself
        // carries name="…" value="…" (e.g. one button per pricing card)
        // would silently drop that field from the POST body. Preserve it
        // via a hidden input before disabling, so the visible button can
        // still go inert for the loading state without losing its value.
        if (loading && button.name && button.form) {
            var carrier = document.createElement('input');
            carrier.type = 'hidden';
            carrier.name = button.name;
            carrier.value = button.value;
            button.form.appendChild(carrier);
        }

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

                // A form with several submit buttons (e.g. one per pricing
                // card) must load the one actually clicked, not just the
                // first in the DOM — e.submitter (native to SubmitEvent)
                // gives the exact button; older browsers fall back to the
                // first, which is still correct for every single-button form.
                var button = e.submitter || form.querySelector('button[type="submit"]');
                if (!button || button.disabled) {
                    return;
                }

                // Opt-in confirmation for a submit button whose action is
                // worth a second thought (e.g. one pricing card per plan
                // choice) — set via data-confirm="message" (and optionally
                // data-confirm-title) on the button. The modal is async, so
                // the first pass always blocks the real submit and re-fires
                // it via requestSubmit() only if the user confirms — the
                // confirmed flag skips the modal on that second pass.
                if (button.dataset.confirm && !button.dataset.confirmed) {
                    e.preventDefault();

                    window.Modal.confirm(button.dataset.confirm, { title: button.dataset.confirmTitle }).then(function (ok) {
                        if (!ok) {
                            return;
                        }

                        button.dataset.confirmed = '1';
                        form.requestSubmit(button);
                        delete button.dataset.confirmed;
                    });

                    return;
                }

                var allSubmitButtons = form.querySelectorAll('button[type="submit"]');
                allSubmitButtons.forEach(function (other) {
                    if (other !== button) {
                        other.disabled = true;
                    }
                });

                set(button, true);
            });
        });
    });

    return { set: set };
})();
