window.Toast = (function () {

    var DISMISS_AFTER_MS = 5000;
    var container = null;

    function ensureContainer() {

        if (!container) {
        
            container = document.createElement('div');
            container.className = 'toast-container';
            container.setAttribute('role', 'status');
            container.setAttribute('aria-live', 'polite');
            document.body.appendChild(container);
        
        }

        return container;
    
    }

    function dismiss(el) {
    
        el.classList.remove('toast-visible');
    
        setTimeout(function () {
    
            el.remove();
    
        }, 200);
    
    }

    function show(message, type) {

        var el = document.createElement('div');
        el.className = 'toast toast-' + (type || 'info');
        el.textContent = message;
        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'toast-close';
        close.setAttribute('aria-label', 'Dismiss');
        close.textContent = '×';

        close.addEventListener('click', function () {
        
            dismiss(el);
        
        });
        
        el.appendChild(close);

        ensureContainer().appendChild(el);
        
        requestAnimationFrame(function () {
        
            el.classList.add('toast-visible');
        
        });

        var timer = setTimeout(function () {

            dismiss(el);
        
        }, DISMISS_AFTER_MS);

        el.addEventListener('mouseenter', function () {
        
            clearTimeout(timer);
        
        });
    
    }

    return { show: show };

})();
