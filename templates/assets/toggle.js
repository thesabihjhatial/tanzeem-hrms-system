/**
 * Generic segmented-toggle behavior (see components/toggle.twig). Keeps
 * the hidden input's value and each button's active state in sync, and
 * dispatches "toggle:change" on the hidden input so a page can react to
 * the choice without this file needing to know what that reaction is.
 */
(function () {
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.toggle').forEach(function (toggle) {
            var hidden = document.getElementById('field_' + toggle.dataset.toggleName);
            if (!hidden) {
                return;
            }

            toggle.querySelectorAll('.toggle-option').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    toggle.querySelectorAll('.toggle-option').forEach(function (b) {
                        b.classList.remove('active');
                    });
                    btn.classList.add('active');
                    hidden.value = btn.dataset.value;
                    hidden.dispatchEvent(new CustomEvent('toggle:change', { detail: { value: btn.dataset.value } }));
                });
            });
        });
    });
})();
