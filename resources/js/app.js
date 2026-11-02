// HT-CGRF progressive enhancements (vanilla JS, no dependencies).

function initOtpGroup(group) {
    // The hidden carrier field lives in the same form; look it up from the form
    // scope so a future markup shuffle can never silently disable the flow.
    const shell = group.closest('form') ?? group;
    const hidden = shell.querySelector('input[type="hidden"][name="code"]');
    const boxes = Array.from(group.querySelectorAll('input[type="text"]'));

    if (!hidden || boxes.length === 0) {
        return;
    }

    const digitsOf = (value) => value.replace(/\D/g, '');

    const sync = () => {
        hidden.value = boxes.map((box) => digitsOf(box.value)).join('');
    };

    const distribute = (digits) => {
        boxes.forEach((box, index) => {
            box.value = digits.charAt(index);
        });
        sync();
    };

    const focusBox = (index) => {
        boxes[Math.min(boxes.length - 1, Math.max(0, index))]?.focus();
    };

    boxes.forEach((box, index) => {
        box.addEventListener('input', () => {
            const digits = digitsOf(box.value);

            if (digits.length > 1) {
                // Multi-digit entry: paste or browser OTP autofill into a box.
                distribute(digits);
                focusBox(digits.length);

                return;
            }

            box.value = digits;

            if (digits !== '' && index < boxes.length - 1) {
                focusBox(index + 1);
            }

            sync();
        });

        box.addEventListener('keydown', (event) => {
            if (event.key === 'Backspace') {
                if (box.value === '' && index > 0) {
                    event.preventDefault();
                    boxes[index - 1].value = '';
                    sync();
                    focusBox(index - 1);
                }

                return;
            }

            if (event.key === 'ArrowLeft' && index > 0) {
                event.preventDefault();
                focusBox(index - 1);
            } else if (event.key === 'ArrowRight' && index < boxes.length - 1) {
                event.preventDefault();
                focusBox(index + 1);
            }
        });

        box.addEventListener('focus', () => {
            box.select();
        });

        // Safety net: some setups (extensions, IMEs, autofill managers) can
        // swallow the `input` event. Advance on keyup only while the caret is
        // still on this box holding exactly one digit — when the `input`
        // handler already advanced, the active element has moved on and this
        // is a no-op, so it never double-jumps.
        box.addEventListener('keyup', (event) => {
            if (document.activeElement !== box || event.key.length !== 1) {
                return;
            }

            if (digitsOf(box.value).length === 1 && index < boxes.length - 1) {
                focusBox(index + 1);
            }
        });
    });

    // Development-only affordance: click the flashed dev code chip to fill the boxes.
    const devFill = group.closest('form')?.querySelector('[data-otp-fill]');

    if (devFill) {
        devFill.addEventListener('click', () => {
            distribute(digitsOf(devFill.getAttribute('data-otp-fill') ?? ''));
            focusBox(boxes.length - 1);
        });
    }

    const form = group.closest('form');

    if (form) {
        form.addEventListener('submit', sync);
    }

    sync();
}

const otpGroups = document.querySelectorAll('[data-otp-group]');
otpGroups.forEach(initOtpGroup);
