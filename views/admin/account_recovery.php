<?php
require __DIR__ . '/../layouts/header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header('Location: ' . base_url('login'));
    exit;
}
?>

<div class="container-fluid bg-light" style="min-height: 100vh;">
    <div class="row">

        <?php require __DIR__ . '/../layouts/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4 app-page-shell">

            <section class="app-page-header">
                <div>
                    <span class="app-page-eyebrow">Recovery</span>
                    <h1 class="app-page-title"><i class="fa-solid fa-user-shield me-2"></i>Account Recovery Assistance</h1>
                </div>
            </section>

            <div class="row justify-content-center">
                <div class="col-xl-8">
                    <section class="app-surface">
                        <div class="app-surface-header">
                            <div>
                                <h2 class="app-surface-title">Manual Recovery Update</h2>
                            </div>
                        </div>
                        <div class="app-surface-body">
                            <div class="alert alert-warning border-0">
                                <strong>Identity verification required.</strong> Use this only after the applicant's identity has been confirmed by the LGU.
                            </div>

                            <form action="<?php echo htmlspecialchars(base_url('admin/account-recovery')); ?>" method="POST">
                                <input type="hidden" name="confirmed_user_id" id="confirmed_user_id" value="">

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="lookup_phone_number" class="form-label fw-bold">Current Mobile Number</label>
                                        <input type="tel" class="form-control" id="lookup_phone_number" name="lookup_phone_number"
                                            placeholder="09xxxxxxxxx" maxlength="11" inputmode="numeric" data-live-filter="digits">
                                    </div>

                                    <div class="col-md-6">
                                        <label for="lookup_email" class="form-label fw-bold">Current Applicant Email</label>
                                        <input type="email" class="form-control" id="lookup_email" name="lookup_email"
                                            placeholder="name@example.com" data-live-filter="email">
                                    </div>

                                    <div class="col-12">
                                        <div class="app-info-card" id="recovery-match-card">
                                            <div class="small text-muted mb-2">Matched Applicant</div>
                                            <div class="fw-semibold" id="recovery-match-name">Search by current mobile number or email first.</div>
                                            <div class="small text-muted mt-1" id="recovery-match-meta">No applicant match selected yet.</div>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <label for="new_phone_number" class="form-label fw-bold">Replacement Mobile Number</label>
                                        <input type="tel" class="form-control" id="new_phone_number" name="new_phone_number"
                                            placeholder="09xxxxxxxxx" maxlength="11" inputmode="numeric" data-live-filter="digits">
                                    </div>

                                    <div class="col-md-6">
                                        <label for="new_email" class="form-label fw-bold">Updated Applicant Email</label>
                                        <input type="email" class="form-control" id="new_email" name="new_email"
                                            placeholder="name@example.com" data-live-filter="email">
                                    </div>

                                    <div class="col-12">
                                        <label for="new_password" class="form-label fw-bold">Optional New Password</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" id="new_password" name="new_password" minlength="8">
                                            <button type="button" class="input-group-text bg-white password-toggle" data-target="new_password" aria-label="Show password">
                                                <i class="fa-solid fa-eye"></i>
                                            </button>
                                        </div>
                                        <div class="form-text">Leave blank if you only need to update the applicant's phone number or email.</div>
                                    </div>
                                </div>

                                <div class="app-actions-row mt-4">
                                    <button type="submit" class="btn btn-primary fw-bold px-4" id="recovery-submit-button" disabled>
                                        <i class="fa-solid fa-wrench me-2"></i>Update Account
                                    </button>
                                </div>
                            </form>
                        </div>
                    </section>
                </div>
            </div>

        </main>
    </div>
</div>

<script>
    document.querySelectorAll('.password-toggle').forEach(function (toggle) {
        toggle.addEventListener('click', function () {
            const input = document.getElementById(this.getAttribute('data-target'));
            const icon = this.querySelector('i');
            const isHidden = input.type === 'password';

            input.type = isHidden ? 'text' : 'password';
            icon.classList.toggle('fa-eye', !isHidden);
            icon.classList.toggle('fa-eye-slash', isHidden);
            this.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
        });
    });

    (function () {
        const phoneInput = document.getElementById('lookup_phone_number');
        const emailInput = document.getElementById('lookup_email');
        const hiddenUserId = document.getElementById('confirmed_user_id');
        const submitButton = document.getElementById('recovery-submit-button');
        const matchName = document.getElementById('recovery-match-name');
        const matchMeta = document.getElementById('recovery-match-meta');
        const lookupUrl = <?php echo json_encode(base_url('admin/account-recovery/lookup')); ?>;
        let lookupTimer = null;

        function setNoMatchState(message, meta) {
            hiddenUserId.value = '';
            submitButton.disabled = true;
            matchName.textContent = message;
            matchMeta.textContent = meta;
        }

        function setMatchState(match) {
            hiddenUserId.value = match.id || '';
            submitButton.disabled = !match.id;
            matchName.textContent = match.applicant_name || 'Matched applicant';
            const parts = [];
            if (match.phone_number) parts.push(match.phone_number);
            if (match.email) parts.push(match.email);
            if (match.school_name) parts.push(match.school_name);
            if (match.address_barangay) parts.push(match.address_barangay);
            matchMeta.textContent = parts.join(' | ') || 'Applicant details loaded.';
        }

        function runLookup() {
            const phone = phoneInput.value.trim();
            const email = emailInput.value.trim();

            if (!phone && !email) {
                setNoMatchState('Search by current mobile number or email first.', 'No applicant match selected yet.');
                return;
            }

            const url = new URL(lookupUrl, window.location.origin);
            if (phone) url.searchParams.set('lookup_phone_number', phone);
            if (email) url.searchParams.set('lookup_email', email);

            fetch(url.toString(), {
                headers: { 'Accept': 'application/json' }
            })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (data && data.match) {
                        setMatchState(data.match);
                    } else {
                        setNoMatchState('No matching applicant found.', data && data.message ? data.message : 'Check the current mobile number or email and try again.');
                    }
                })
                .catch(function () {
                    setNoMatchState('Unable to check the account right now.', 'Please try again in a moment.');
                });
        }

        function queueLookup() {
            window.clearTimeout(lookupTimer);
            lookupTimer = window.setTimeout(runLookup, 300);
        }

        phoneInput.addEventListener('input', queueLookup);
        emailInput.addEventListener('input', queueLookup);
    })();
</script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
