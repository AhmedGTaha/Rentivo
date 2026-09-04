<?php
/**
 * Toggle switch. Semantically a checkbox presented as a switch.
 *
 * @var string $name
 * @var string $label
 * @var bool   $checked
 * @var string $value
 */

$name = $name ?? '';
$label = $label ?? '';
$checked = $checked ?? false;
$value = $value ?? '1';
?>
<label class="toggle">
    <input type="checkbox" name="<?= e($name) ?>" value="<?= e($value) ?>" role="switch"
        <?= $checked ? 'checked aria-checked="true"' : 'aria-checked="false"' ?>>
    <span class="toggle__track" aria-hidden="true"></span>
    <span class="toggle__label"><?= e($label) ?></span>
</label>
