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
        errorEl.classList.remove('error--warning');
    }

    // The pwned-password check is a soft warning, not a blocking error —
    // ValidationManager::checkPasswordStrength() treats it the same way
    // server-side (a warning, never rejected). It renders in the same
    // .error div (so it lines up visually like every other field message)
    // but is marked so refreshSubmitState() never treats it as blocking.
    function setWarning(el, message) {
        var field = closestField(el);
        if (!field) {
            return;
        }

        var errorEl = field.querySelector('.error');
        if (!errorEl) {
            return;
        }

        errorEl.textContent = message || '';
        errorEl.classList.add('error--warning');
    }

    function isValidPakistaniPhone(value) {
        var normalized = value.replace(/[\s-]/g, '');
        if (!/^(?:\+92|0092|0)3\d{9}$/.test(normalized)) {
            return false;
        }
        return !isMonotonous(normalized.slice(-9));
    }

    function isMonotonous(value) {
        var stripped = value.replace(/\s/g, '').toLowerCase();
        return stripped !== '' && stripped.split('').every(function (ch) { return ch === stripped[0]; });
    }

    function isValidCnic(value) {
        var normalized = value.replace(/[\s-]/g, '');
        return /^\d{13}$/.test(normalized) && !isMonotonous(normalized);
    }

    function isSafeText(value) {
        return /^[a-zA-Z0-9\s.,'&-]+$/.test(value);
    }

    function isValidEmail(value) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
    }

    // Mirrors ValidationManager::DISPOSABLE_EMAIL_DOMAINS — keep both lists in sync.
    var DISPOSABLE_EMAIL_DOMAINS = [
        '10minutemail.com', '20minutemail.com', '33mail.com', 'dispostable.com',
        'emailondeck.com', 'fakeinbox.com', 'getairmail.com', 'getnada.com',
        'guerrillamail.com', 'guerrillamail.net', 'harakirimail.com', 'mailcatch.com',
        'maildrop.cc', 'mailinator.com', 'mailnesia.com', 'mintemail.com', 'moakt.com',
        'mytemp.email', 'sharklasers.com', 'spamgourmet.com', 'temp-mail.org',
        'tempail.com', 'tempinbox.com', 'tempmail.com', 'tempmailo.com',
        'throwawaymail.com', 'tmpmail.org', 'trashmail.com', 'yopmail.com',
    ];

    function isDisposableEmailDomain(value) {
        var domain = value.split('@')[1];
        return !!domain && DISPOSABLE_EMAIL_DOMAINS.indexOf(domain.toLowerCase().trim()) !== -1;
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

        if (el.type === 'email' && value !== '' && isDisposableEmailDomain(value)) {
            setError(el, 'Please use permanent email address, not a temporary one.');
            return false;
        }

        if (el.type === 'tel' && value !== '' && !isValidPakistaniPhone(value)) {
            setError(el, 'Enter a valid Pakistani number, e.g. 03001234567 or +923001234567.');
            return false;
        }

        if (el.name === 'cnic' && value !== '' && !isValidCnic(value)) {
            setError(el, 'Enter a valid 13-digit CNIC, e.g. 12345-1234567-1.');
            return false;
        }

        if (['name', 'company_name', 'city'].indexOf(el.name) !== -1 && value !== '') {
            if (!isSafeText(value)) {
                setError(el, 'Contains characters that aren\'t allowed.');
                return false;
            }

            if (!/[a-zA-Z]/.test(value)) {
                setError(el, 'Must contain least one letter.');
                return false;
            }

            if (isMonotonous(value)) {
                setError(el, 'Please enter a real value.');
                return false;
            }
        }

        if (el.type === 'password' && el.name !== 'confirm_password' && value !== '') {
            var minLength = el.hasAttribute('minlength') ? parseInt(el.getAttribute('minlength'), 10) : 0;
            var form = el.closest('form');
            var emailField = form ? form.querySelector('input[type="email"]') : null;
            var errors = passwordErrors(value, minLength, emailField ? emailField.value.trim() : '');

            if (errors.length) {
                setError(el, errors[0]);
                return false;
            }
        }

        if (el.name === 'confirm_password' && value !== '') {
            var passwordField = el.closest('form').querySelector('input[name="password"]');

            if (passwordField && value !== passwordField.value) {
                setError(el, 'The passwords do not match.');
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
                    setWarning(el, 'This password was found compromised. Consider using a different password.');
                }
            })
            .catch(function () {

            })
            .finally(function () {
                if (loaderEl) {
                    loaderEl.hidden = true;
                }
                refreshSubmitState(el.closest('form'));
            });
    }

    function checkEmailTaken(el) {
        var checkedValue = el.value;
        var loaderEl = fieldLoader(el);
        if (loaderEl) {
            loaderEl.hidden = false;
        }

        fetch('/check-email', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'email=' + encodeURIComponent(checkedValue),
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

                if (data.taken) {
                    setError(el, 'This email is already registered.');
                }
            })
            .catch(function () {

            })
            .finally(function () {
                if (loaderEl) {
                    loaderEl.hidden = true;
                }
                refreshSubmitState(el.closest('form'));
            });
    }

    function checkCnicTaken(el) {
        var checkedValue = el.value;
        var loaderEl = fieldLoader(el);
        if (loaderEl) {
            loaderEl.hidden = false;
        }

        fetch('/check-cnic', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'cnic=' + encodeURIComponent(checkedValue),
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (el.value !== checkedValue) {
                    return;
                }

                if (data.taken) {
                    setError(el, 'This CNIC is already registered.');
                }
            })
            .catch(function () {

            })
            .finally(function () {
                if (loaderEl) {
                    loaderEl.hidden = true;
                }
                refreshSubmitState(el.closest('form'));
            });
    }

    // Keeps the submit button disabled until every field is filled, has no
    // visible error, and no async check (pwned/email) is still in flight —
    // otherwise a user could submit while "This email is already
    // registered." is still showing on screen.
    function refreshSubmitState(form) {
        var submitButton = form.querySelector('button[type="submit"]');
        if (!submitButton) {
            return;
        }

        var fields = form.querySelectorAll('input[required], select[required]');
        var hasEmptyField = Array.prototype.some.call(fields, function (field) {
            return field.value.trim() === '';
        });

        // .error--warning (the pwned-password notice) is informational,
        // never blocking — ValidationManager::checkPasswordStrength()
        // treats it the same way server-side.
        var errorEls = form.querySelectorAll('.error:not(.error--warning)');
        var hasVisibleError = Array.prototype.some.call(errorEls, function (errorEl) {
            return errorEl.textContent.trim() !== '';
        });

        var loaderEls = form.querySelectorAll('.field-loader');
        var hasPendingCheck = Array.prototype.some.call(loaderEls, function (loaderEl) {
            return !loaderEl.hidden;
        });

        submitButton.disabled = hasEmptyField || hasVisibleError || hasPendingCheck;
    }

    // Six single-digit boxes (see data-otp-box in verify.twig) that feed
    // one hidden input#field_otp — that hidden field is what actually
    // gets submitted and validated, so the boxes are purely presentational.
    function bindOtpBoxes() {
        document.querySelectorAll('[data-otp-group]').forEach(function (group) {
            var boxes = Array.prototype.slice.call(group.querySelectorAll('[data-otp-box]'));
            var hidden = group.parentElement.querySelector('#field_otp');
            if (!hidden) {
                return;
            }

            function sync() {
                var digits = boxes.map(function (box) { return box.value; }).join('');
                hidden.value = digits.length === boxes.length ? digits : '';
                refreshSubmitState(hidden.closest('form'));
            }

            boxes.forEach(function (box, index) {
                box.addEventListener('input', function () {
                    box.value = box.value.replace(/\D/g, '').slice(-1);

                    if (box.value && index < boxes.length - 1) {
                        boxes[index + 1].focus();
                    }

                    sync();
                });

                box.addEventListener('keydown', function (e) {
                    if (e.key === 'Backspace' && !box.value && index > 0) {
                        boxes[index - 1].focus();
                        boxes[index - 1].value = '';
                        sync();
                    }
                });

                box.addEventListener('paste', function (e) {
                    var digits = (e.clipboardData.getData('text') || '').replace(/\D/g, '').split('');
                    if (!digits.length) {
                        return;
                    }

                    e.preventDefault();

                    boxes.forEach(function (box, i) {
                        box.value = digits[i] || '';
                    });

                    var nextEmpty = boxes.findIndex(function (box) { return !box.value; });
                    (nextEmpty === -1 ? boxes[boxes.length - 1] : boxes[nextEmpty]).focus();

                    sync();
                });

                // Only the last box's blur validates — validating on every
                // box as the user tabs through would show "Field is
                // required." before they've finished typing.
                if (index === boxes.length - 1) {
                    box.addEventListener('blur', function () {
                        validateField(hidden);
                    });
                }
            });
        });
    }

    function bindPasswordToggles() {
        document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
            button.addEventListener('click', function () {
                var input = button.closest('.input-wrap').querySelector('input');
                var eye = button.querySelector('.icon-eye');
                var eyeOff = button.querySelector('.icon-eye-off');
                var revealing = input.type === 'password';

                input.type = revealing ? 'text' : 'password';
                eye.classList.toggle('is-active', !revealing);
                eyeOff.classList.toggle('is-active', revealing);
                button.setAttribute('aria-label', revealing ? 'Hide password' : 'Show password');
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        bindPasswordToggles();
        bindOtpBoxes();

        document.querySelectorAll('form[novalidate]').forEach(function (form) {
            var fields = form.querySelectorAll('input, select');

            fields.forEach(function (field) {
                field.addEventListener('blur', function () {
                    var isValid = validateField(field);
                    refreshSubmitState(form);

                    if (isValid && field.hasAttribute('data-check-pwned') && field.value.trim() !== '') {
                        checkPwned(field);
                    }

                    if (isValid && field.hasAttribute('data-check-email') && field.value.trim() !== '') {
                        checkEmailTaken(field);
                    }

                    if (isValid && field.hasAttribute('data-check-cnic') && field.value.trim() !== '') {
                        checkCnicTaken(field);
                    }

                    refreshSubmitState(form);
                });

                field.addEventListener('input', function () {
                    refreshSubmitState(form);
                });
            });

            refreshSubmitState(form);

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
