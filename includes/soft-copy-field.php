<div class="modal-field soft-copy-field">
    <label for="document-soft-copy">Soft copy <span>(optional)</span></label>
    <?php if (isset($currentSoftCopyName)): ?>
        <small class="modal-field__hint">Current file: <?= e($currentSoftCopyName) ?>. Choose a new file to replace it.</small>
    <?php endif; ?>
    <input type="hidden" name="MAX_FILE_SIZE" value="<?= DOCUMENT_SOFT_COPY_MAX_BYTES ?>">
    <input type="file" name="soft_copy" id="document-soft-copy" accept=".pdf,.jpg,.jpeg,.png"
           aria-describedby="document-soft-copy-hint<?= isset($formErrors['soft_copy']) ? ' document-soft-copy-error' : '' ?>"<?= isset($formErrors['soft_copy']) ? ' aria-invalid="true"' : '' ?>>
    <small id="document-soft-copy-hint" class="modal-field__hint">PDF, JPG, or PNG. Up to 10 MB.</small>
    <?php if (isset($formErrors['soft_copy'])): ?>
        <small id="document-soft-copy-error"><?= e($formErrors['soft_copy']) ?></small>
    <?php endif; ?>
</div>
