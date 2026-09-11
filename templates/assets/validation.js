/**
 * Custom on-blur field validation, replacing the browser's native
 * constraint-validation popups. Rules mirror utilities/ValidationManager.php
 * exactly (required, email, phone_pk, password strength) so client and
 * server never disagree about what's valid — the server call in
 * CustomerManager::register() remains the authoritative check regardless.
 * Applies to any <form novalidate> on the page.
 */
(function () {
    var COMMON_PASSWORD_PATTERNS = /(12345|password|qwerty|abc123|123456789)/i;
    var PASSWORD_MAX_LENGTH = 128;

    function closestField(el) {
        return el.closest('.field');
    }

    function setError(el, message) {
        var field = closestField(el);
        if (!field) {
            return;
        }

        // The .error div always exists in the markup (reserves its own
        // space via min-height) — only its text ever changes, so showing
        // or clearing an error never shifts anything else on the page.
        var errorEl = field.querySelector('.error');
        if (!errorEl) {
            return;
        }

        errorEl.textContent = message || '';
    }

    function isValidPakistaniPhone(value) {
        var normalized = value.replace(/[\s-]/g, '');
        return /^(?:\+92|0092|0)3\d{9}$/.test(normalized);
    }

    function isValidEmail(value) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
    }

    function passwordErrors(value, minLength, email) {
        var errors = [];

        if (minLength && value.length < minLength) {
            errors.push('Must be least ' + minLength + ' characters.');
        }

        if (value.length > PASSWORD_MAX_LENGTH) {
            errors.push('The password is too long.');
        }

        if (COMMON_PASSWORD_PATTERNS.test(value)) {
            errors.push('Avoid using common or easily guessable passwords.');
        }

        var emailLocal = email ? email.split('@')[0].toLowerCase() : '';
        if (emailLocal && value.toLowerCase().indexOf(emailLocal) !== -1) {
            errors.push('Password cannot contain your email.');
        }

        return errors;
    }

    function validateField(el) {
        var value = el.value.trim();

        if (el.hasAttribute('required') && value === '') {
            setError(el, 'Field is required.');
            return false;
        }

        if (el.type === 'email' && value !== '' && !isValidEmail(value)) {
            setError(el, 'Enter a valid email address.');
            return false;
        }

        if (el.type === 'tel' && value !== '' && !isValidPakistaniPhone(value)) {
            setError(el, 'Enter a valid Pakistani number, e.g. 03001234567 or +923001234567.');
            return false;
        }

        if (el.type === 'password' && value !== '') {
            var minLength = el.hasAttribute('minlength') ? parseInt(el.getAttribute('minlength'), 10) : 0;
            var form = el.closest('form');
            var emailField = form ? form.querySelector('input[type="email"]') : null;
            var errors = passwordErrors(value, minLength, emailField ? emailField.value.trim() : '');

            if (errors.length) {
                setError(el, errors[0]);
                return false;
            }
        }

        setError(el, null);
        return true;
    }

    function fieldLoader(el) {
        var wrap = el.closest('.input-wrap');
        if (!wrap) {
            return null;
        }

        var loaderEl = wrap.querySelector('.field-loader');
        if (!loaderEl) {
            loaderEl = document.createElement('span');
            loaderEl.className = 'field-loader';
            loaderEl.hidden = true;
            loaderEl.innerHTML = '<span class="loader" aria-hidden="true"></span>';
            wrap.appendChild(loaderEl);
        }

        return loaderEl;
    }

    function checkPwned(el) {
        var checkedValue = el.value;
        var loaderEl = fieldLoader(el);
        if (loaderEl) {
            loaderEl.hidden = false;
        }

        fetch('/check-password', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'password=' + encodeURIComponent(checkedValue),
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                // The user may have already typed something else by the time
                // this resolves — a stale response must not overwrite it.
                if (el.value !== checkedValue) {
                    return;
                }

                if (data.pwned) {
                    setError(el, 'This password has appeared in a known data breach. Consider choosing a different one.');
                }
            })
            .catch(function () {
                // Fail silently, same as the server's fail-open behavior — an
                // unreachable HIBP must never block or alarm the user here.
            })
            .finally(function () {
                if (loaderEl) {
                    loaderEl.hidden = true;
                }
            });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('form[novalidate]').forEach(function (form) {
            var fields = form.querySelectorAll('input, select');

            fields.forEach(function (field) {
                field.addEventListener('blur', function () {
                    var isValid = validateField(field);

                    if (isValid && field.hasAttribute('data-check-pwned') && field.value.trim() !== '') {
                        checkPwned(field);
                    }
                });
            });

            form.addEventListener('submit', function (e) {
                var valid = true;
                var firstInvalid = null;

                fields.forEach(function (field) {
                    if (!validateField(field)) {
                        valid = false;
                        firstInvalid = firstInvalid || field;
                    }
                });

                if (!valid) {
                    e.preventDefault();
                    if (firstInvalid) {
                        firstInvalid.focus();
                    }
                    if (window.Toast) {
                        window.Toast.show('Please fix all required fields.', 'error');
                    }
                }
            });
        });
    });
})();
