    </div>
</main>

<?php
$currentScript = str_replace('\\', '/', (string) ($_SERVER['PHP_SELF'] ?? ''));
$onRolePage = str_contains($currentScript, '/shared/')
    || str_contains($currentScript, '/admin-only/')
    || str_contains($currentScript, '/staff-only/');
$assetBase = $onRolePage ? '../' : '';
$extraJs = $extraJs ?? [];
?>
<?php if (empty($hideFooter)): ?>
    <footer class="app-footer border-top py-3 bg-white">
        <div class="container d-flex justify-content-between align-items-center flex-wrap gap-2">
            <small class="text-muted">San Enrique LGU Scholarship Records Management System</small>
            <small class="text-muted">Municipality of San Enrique, Negros Occidental</small>
        </div>
    </footer>
<?php endif; ?>

<div class="modal fade modal-se" id="crudConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <div class="modal-se-title-wrap">
                    <span class="modal-se-icon is-info" id="crudConfirmIcon">
                        <i class="fa-solid fa-circle-info"></i>
                    </span>
                    <div>
                        <h5 class="modal-title mb-0" id="crudConfirmTitle">Confirm Action</h5>
                        <small class="text-muted" id="crudConfirmSubtitle">San Enrique LGU Scholarship</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-2">
                <p class="mb-0" id="crudConfirmMessage">Proceed with this action?</p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" id="crudConfirmCancel">Cancel</button>
                <button type="button" class="btn btn-primary" id="crudConfirmSubmit">Continue</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e($assetBase) ?>assets/js/main.js"></script>
<?php foreach ($extraJs as $jsFile): ?>
    <script src="<?= e((string) $jsFile) ?>"></script>
<?php endforeach; ?>
</body>
</html>
