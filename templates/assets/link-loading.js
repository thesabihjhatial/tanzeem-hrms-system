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

                link.dataset.originalText = link.textContent;
                link.textContent = link.dataset.redirectText;
                link.setAttribute('aria-disabled', 'true');
                link.classList.add('link-loading');
            });
        });
    });
})();
