<?php
/**
 * Search field with a leading icon.
 *
 * @var string $name
 * @var string $value
 * @var string $placeholder
 * @var string $label       Accessible label; visually hidden.
 * @var array  $attributes
 */

$name = $name ?? 'q';
$value = $value ?? '';
$placeholder = $placeholder ?? 'Search';
$label = $label ?? 'Search';
$attributes = $attributes ?? [];

$id = 'search-' . preg_replace('/[^a-z0-9]+/i', '-', $name);
?>
<div class="search-field">
    <label class="visually-hidden" for="<?= e($id) ?>"><?= e($label) ?></label>
    <?= component('primitives/icon', ['name' => 'search', 'class' => 'search-field__icon', 'size' => 18]) ?>
    <input class="input" type="search" id="<?= e($id) ?>" name="<?= e($name) ?>"
           value="<?= e($value) ?>" placeholder="<?= e($placeholder) ?>"
           autocomplete="off"<?= attributes($attributes) ?>>
</div>
