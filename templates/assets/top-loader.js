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
            // Creep toward 90%, slowing as it approaches — never reaches
            // 100% under its own steam, since nothing here ever calls it done.
            width += (90 - width) * 0.1;
            bar.style.width = width + '%';
        }, 200);
    }

    window.addEventListener('beforeunload', start);

    document.addEventListener('DOMContentLoaded', function () {
        bar = document.getElementById('top-loader');
    });
})();
