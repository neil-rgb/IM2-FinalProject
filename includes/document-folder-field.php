<div class="modal-field">
    <label for="<?= e($folderFieldId) ?>">Original paper folder <b>*</b></label>
    <select id="<?= e($folderFieldId) ?>" name="location_id" required
            aria-describedby="<?= e($folderFieldId) ?>-hint<?= isset($formErrors['location_id']) ? ' ' . e($folderFieldId) . '-error' : '' ?>"<?= isset($formErrors['location_id']) ? ' aria-invalid="true"' : '' ?>>
        <option value="">Choose a folder</option>
        <?php $hasMatchingFolders = false; ?>
        <?php foreach ($storageLocations as $location): ?>
            <?php
            $locationTypeId = (string) $location['type_id'];
            $matchesType = $folderTypeId === $locationTypeId;
            $hasMatchingFolders = $hasMatchingFolders || $matchesType;
            $selectedFolder = $matchesType && $formValues['location_id'] === (string) $location['location_id'];
            ?>
            <option value="<?= e($location['location_id']) ?>" data-type-id="<?= e($locationTypeId) ?>"<?= $selectedFolder ? ' selected' : '' ?>><?= e($location['cabinet']) ?> / <?= e($location['drawer']) ?> / <?= e($location['folder']) ?></option>
        <?php endforeach; ?>
    </select>
    <small id="<?= e($folderFieldId) ?>-hint" class="modal-field__hint" data-location-hint aria-live="polite"><?php if ($hasMatchingFolders): ?>Folders are filtered by document category.<?php elseif ($folderTypeId === ''): ?>Choose a document category first.<?php else: ?>No folders are configured for this category.<?php endif; ?></small>
    <?php if (isset($formErrors['location_id'])): ?>
        <small id="<?= e($folderFieldId) ?>-error"><?= e($formErrors['location_id']) ?></small>
    <?php endif; ?>
</div>
