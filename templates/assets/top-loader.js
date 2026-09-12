/**
 * TopLoader — thin progress bar across the top of the page during any
 * navigation, à la NProgress / ncs-portal's NextTopLoader. This app is
 * plain server-rendered multi-page navigation, not a client router, so
 * there's no explicit "navigation finished" event to hook — beforeunload
 * covers every case that matters (link clicks, form submits, back/
 * forward, manual refresh) and the bar is simply destroyed when the
 * browser actually unloads the page, which is exactly the desired end
 * state. Never sets event.returnValue, so it can't trigger the
 * browser's "leave site?" confirmation dialog.
 */
(function () {
    var bar = null;
    var width = 0;
    var timer = null;

    function start() {
        bar = bar || document.getElementById('top-loader');
        if (!bar) {
            return;
        }

        width = 0;
        bar.style.width = '0%';
        bar.classList.add('top-loader-active');

        clearInterval(timer);
        timer = setInterval(function () {
            // Creep toward 99%, slowing as it approaches — never reaches
            // 100% under its own steam, since nothing here ever calls it
            // done. Fast (25%-of-remaining-distance, every 50ms) so a
            // quick page load still visually races to near-completion
            // instead of getting caught at a low percentage when the
            // page unloads out from under it — only a genuinely slow
            // load lingers, still climbing, near the top.
            width += (99 - width) * 0.25;
            bar.style.width = width + '%';
        }, 50);
    }

    window.addEventListener('beforeunload', start);

    document.addEventListener('DOMContentLoaded', function () {
        bar = document.getElementById('top-loader');
    });
})();
