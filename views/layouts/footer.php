<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php $flashMessages = get_flash_messages(); ?>
<script>
window.appFlashes = <?php echo json_encode($flashMessages, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo htmlspecialchars(asset_url('js/navbar.js')); ?>"></script>
<script src="<?php echo htmlspecialchars(asset_url('js/live-validation.js')); ?>"></script>
<script src="<?php echo htmlspecialchars(asset_url('js/alerts.js')); ?>"></script>
</body>

</html>
