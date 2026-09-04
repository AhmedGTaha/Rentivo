<?php
/**
 * File upload drop zone.
 *
 * Anything checked in the browser is convenience only; ImageService performs
 * the real MIME, decode and size validation server-side.
 *
 * @var string $name
 * @var string $title
 * @var string $hint
 * @var bool   $multiple
 * @var string $accept
 * @var array  $attributes
 */

$name = $name ?? 'file';
$title = $title ?? 'Choose a file or drag it here';
$hint = $hint ?? 'JPEG, PNG or WebP';
$multiple = $multiple ?? false;
$accept = $accept ?? 'image/jpeg,image/png,image/webp';
$attributes = $attributes ?? [];

$id = 'upload-' . preg_replace('/[^a-z0-9]+/i', '-', $name);
?>
<div class="file-upload" data-file-upload>
    <label class="visually-hidden" for="<?= e($id) ?>"><?= e($title) ?></label>
    <?= component('primitives/icon', ['name' => 'upload', 'class' => 'file-upload__icon', 'size' => 28]) ?>
    <span class="file-upload__title"><?= e($title) ?></span>
    <span class="file-upload__hint"><?= e($hint) ?></span>
    <span class="file-upload__selected" data-file-summary></span>
    <input type="file" id="<?= e($id) ?>" name="<?= e($multiple ? $name . '[]' : $name) ?>"
           accept="<?= e($accept) ?>" <?= $multiple ? 'multiple' : '' ?><?= attributes($attributes) ?>>
</div>
