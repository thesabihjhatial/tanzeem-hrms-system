/**
 * A single, reusable confirm modal (see components/modal.twig, included
 * once in base.twig). Promise-based so callers can just await the
 * user's choice: Modal.confirm('Are you sure?').then(function (ok) { ... }).
 */
window.Modal = (function () {
    function elements() {
        return {
            overlay: document.getElementById('modal-overlay'),
            title: document.getElementById('modal-title'),
            message: document.getElementById('modal-message'),
            confirmBtn: document.getElementById('modal-confirm'),
            cancelBtn: document.getElementById('modal-cancel'),
        };
    }

    function confirm(message, options) {
        options = options || {};
        var el = elements();
        if (!el.overlay) {
            return Promise.resolve(window.confirm(message));
        }

        el.title.textContent = options.title || 'Are you sure?';
        el.message.textContent = message;
        el.confirmBtn.textContent = options.confirmText || 'Confirm';
        el.cancelBtn.textContent = options.cancelText || 'Cancel';
        el.overlay.hidden = false;
        requestAnimationFrame(function () {
            el.overlay.classList.add('modal-visible');
        });

        return new Promise(function (resolve) {
            function close(result) {
                el.overlay.classList.remove('modal-visible');

                // Matches toast.js's own dismiss delay, so every overlay in
                // the app fades/slides out over the same 200ms.
                setTimeout(function () {
                    el.overlay.hidden = true;
                }, 200);

                el.confirmBtn.removeEventListener('click', onConfirm);
                el.cancelBtn.removeEventListener('click', onCancel);
                el.overlay.removeEventListener('click', onOverlayClick);
                document.removeEventListener('keydown', onKeydown);
                resolve(result);
            }

            function onConfirm() {
                close(true);
            }

            function onCancel() {
                close(false);
            }

            function onOverlayClick(e) {
                if (e.target === el.overlay) {
                    close(false);
                }
            }

            function onKeydown(e) {
                if (e.key === 'Escape') {
                    close(false);
                }
            }

            el.confirmBtn.addEventListener('click', onConfirm);
            el.cancelBtn.addEventListener('click', onCancel);
            el.overlay.addEventListener('click', onOverlayClick);
            document.addEventListener('keydown', onKeydown);
            el.confirmBtn.focus();
        });
    }

    return { confirm: confirm };
})();
