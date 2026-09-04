<?php
/**
 * Checkbox.
 *
 * @var string      $name
 * @var string      $label
 * @var mixed       $value
 * @var bool        $checked
 * @var string|null $hint
 * @var bool        $card   Renders as a bordered selectable card.
 * @var array       $attributes
 */

$name = $name ?? '';
$label = $label ?? '';
$value = $value ?? '1';
$checked = $checked ?? false;
$hint = $hint ?? null;
$card = $card ?? false;
$attributes = $attributes ?? [];
?>
<label class="<?= e(class_names(['checkbox' => true, 'checkbox--card' => $card])) ?>">
    <input type="checkbox" name="<?= e($name) ?>" value="<?= e($value) ?>"
        <?= $checked ? 'checked' : '' ?><?= attributes($attributes) ?>>
    <span>
        <span class="checkbox__label"><?= e($label) ?></span>
        <?php if ($hint !== null): ?>
            <span class="checkbox__hint"><?= e($hint) ?></span>
        <?php endif; ?>
    </span>
</label>
