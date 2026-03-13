(function () {
    document.addEventListener('DOMContentLoaded', function () {
        const mobileInput = document.getElementById('phone');
        if (!mobileInput) {
            return;
        }

        const sanitize = function () {
            mobileInput.value = String(mobileInput.value || '').replace(/\D+/g, '').slice(0, 12);
        };

        mobileInput.addEventListener('input', sanitize);
        mobileInput.addEventListener('paste', function () {
            setTimeout(sanitize, 0);
        });
        mobileInput.addEventListener('keydown', function (event) {
            const allowedKeys = [
                'Backspace', 'Delete', 'Tab', 'ArrowLeft', 'ArrowRight', 'Home', 'End',
            ];
            if (allowedKeys.includes(event.key) || event.ctrlKey || event.metaKey) {
                return;
            }
            if (!/^\d$/.test(event.key)) {
                event.preventDefault();
            }
        });
    });
}());
