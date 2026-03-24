// public/assets/js/alerts.js

document.addEventListener("DOMContentLoaded", () => {
    const appFlashes = Array.isArray(window.appFlashes) ? window.appFlashes : [];
    
    // ---------------------------------------------------------
    // 1. AUTO-TRIGGER ALERTS BASED ON URL PARAMETERS
    // ---------------------------------------------------------
    // This looks at the web address. If it sees "?success=1", it fires a popup.
    const urlParams = new URLSearchParams(window.location.search);
    
    if (urlParams.has('success')) {
        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: 'Action completed successfully.',
            confirmButtonColor: '#004085'
        }).then(() => {
            // Clean up the URL so the popup doesn't show again if they refresh
            window.history.replaceState(null, null, window.location.pathname);
        });
    }

    if (urlParams.has('error')) {
        Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: 'Something went wrong. Please try again.',
            confirmButtonColor: '#dc3545'
        });
    }

    appFlashes.forEach((flash) => {
        const icon = flash.type === 'success' ? 'success' : 'error';
        const title = flash.type === 'success' ? 'Success!' : 'Notice';

        Swal.fire({
            icon,
            title,
            text: flash.message || 'Please review the latest system message.',
            confirmButtonColor: icon === 'success' ? '#004085' : '#dc3545'
        });
    });

    // ---------------------------------------------------------
    // 2. CONFIRMATION POPUPS FOR DANGEROUS ACTIONS
    // ---------------------------------------------------------
    // Add the class 'confirm-action' to any button that deletes or rejects something
    const confirmButtons = document.querySelectorAll('.confirm-action');

    confirmButtons.forEach(button => {
        button.addEventListener('click', function(event) {
            event.preventDefault(); // Stop the form or link from executing immediately
            
            const form = this.closest('form'); // Find the form this button belongs to
            const actionText = this.getAttribute('data-action') || 'do this';

            Swal.fire({
                title: 'Are you sure?',
                text: `You are about to ${actionText}. You cannot undo this action.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, proceed!'
            }).then((result) => {
                if (result.isConfirmed) {
                    if (form) {
                        form.submit(); // If they say yes, submit the form to PHP
                    } else {
                        window.location.href = this.href; // Or follow the link
                    }
                }
            });
        });
    });

    // ---------------------------------------------------------
    // 3. DOCUMENT REJECTION PROMPT (Staff UI)
    // ---------------------------------------------------------
    // Special popup that asks the staff to type a reason for rejecting a document
    const rejectDocButtons = document.querySelectorAll('.btn-reject-doc');

    rejectDocButtons.forEach(button => {
        button.addEventListener('click', function(event) {
            event.preventDefault();
            const form = this.closest('form');
            
            Swal.fire({
                title: 'Reject Document',
                input: 'textarea',
                inputLabel: 'Reason for rejection (This will be sent to the student via SMS)',
                inputPlaceholder: 'e.g., The document is blurry...',
                inputAttributes: {
                    'aria-label': 'Reason for rejection'
                },
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                confirmButtonText: 'Reject Document',
                preConfirm: (remarks) => {
                    if (!remarks) {
                        Swal.showValidationMessage('You must provide a reason for rejection.');
                    }
                    return remarks;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Create a hidden input to pass the remarks to PHP
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'rejection_remarks';
                    hiddenInput.value = result.value;
                    form.appendChild(hiddenInput);
                    
                    // Set the status to Rejected and submit
                    form.querySelector('input[name="status"]').value = 'Rejected';
                    form.submit();
                }
            });
        });
    });
});
