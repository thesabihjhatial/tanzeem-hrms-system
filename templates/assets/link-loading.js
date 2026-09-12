/**
 * Makes navigation links idempotent, the same way button-loading.js does
 * for form submits: the first click swaps the link's text for its
 * data-redirect-text and disables further clicks until the browser
 * navigates away. Applies to any <a data-redirect-text="...">.
 */
(function () {
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('a[data-redirect-text]').forEach(function (link) {
            link.addEventListener('click', function (e) {
                if (link.getAttribute('aria-disabled') === 'true') {
                    e.preventDefault();
                    return;
                }

                // Opt-in confirmation, same data-confirm pattern and Modal
                // used by button-loading.js — the first click always blocks
                // navigation and awaits the modal; the re-fired click (via
                // link.click() below) carries the confirmed flag through.
                if (link.dataset.confirm && !link.dataset.confirmed) {
                    e.preventDefault();

                    window.Modal.confirm(link.dataset.confirm, { title: link.dataset.confirmTitle }).then(function (ok) {
                        if (!ok) {
                            return;
                        }

                        link.dataset.confirmed = '1';
                        link.click();
                        delete link.dataset.confirmed;
                    });

                    return;
                }

                link.dataset.originalText = link.textContent;
                link.textContent = link.dataset.redirectText;
                link.setAttribute('aria-disabled', 'true');
                link.classList.add('link-loading');
            });
        });
    });
})();
