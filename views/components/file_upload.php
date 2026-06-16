<?php
/**
 * Component — file upload field (reused by phase 5 comment attachments).
 *
 * Optional vars set by the including view:
 *   $fileInputName (string)      input name, default 'attachment'
 *   $fileError     (string|null) lang key for an inline error to display
 *
 * Allowed types/size come from docs/SECURITY_AUTH.md. The accept attribute is a
 * UX hint only — the authoritative validation is server-side in TicketController.
 */
$fileInputName = isset($fileInputName) && $fileInputName !== '' ? $fileInputName : 'attachment';
$fileError     = isset($fileError) ? $fileError : null;
?>
<div class="form-group">
    <label class="form-label" for="<?php echo e($fileInputName); ?>"><?php echo e(t('label_attachment')); ?></label>
    <input class="form-control" type="file" id="<?php echo e($fileInputName); ?>"
           name="<?php echo e($fileInputName); ?>"
           accept=".jpg,.jpeg,.png,.pdf,.docx,.xlsx">
    <p class="form-hint"><?php echo e(t('file_upload_hint')); ?></p>
    <?php if ($fileError !== null): ?>
        <p class="form-error"><?php echo e(t($fileError)); ?></p>
    <?php endif; ?>
</div>
