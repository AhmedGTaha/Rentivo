<?php
/**
 * Field wrapper.
 *
 * Every form control in Rentivo renders through this component so labels,
 * hints, error messages and aria wiring are identical everywhere.
 *
 * @var string      $name
 * @var string      $label
 * @var string      $type        text|email|tel|number|date|datetime-local|search|textarea|select|color
 * @var mixed       $value
 * @var array       $errors      Field name => message
 * @var string|null $hint
 * @var bool        $required
 * @var array       $options     For select: value => label
 * @var string|null $placeholder
 * @var string|null $prefix      Input group prefix text, e.g. "BHD"
 * @var array       $attributes
 */

$name = $name ?? '';
$label = $label ?? '';
$type = $type ?? 'text';
$value = $value ?? '';
$errors = $errors ?? [];
$hint = $hint ?? null;
$required = $required ?? false;
$options = $options ?? [];
$placeholder = $placeholder ?? null;
$prefix = $prefix ?? null;
$attributes = $attributes ?? [];

$id = $attributes['id'] ?? 'field-' . preg_replace('/[^a-z0-9]+/i', '-', $name);
unset($attributes['id']);

$error = $errors[$name] ?? null;
$describedBy = [];

if ($hint !== null) {
    $describedBy[] = $id . '-hint';
}

if ($error !== null) {
    $describedBy[] = $id . '-error';
}

$controlAttributes = $attributes + [
    'id'               => $id,
    'name'             => $name,
    'placeholder'      => $placeholder,
    'required'         => $required ?: null,
    'aria-invalid'     => $error !== null ? 'true' : null,
    'aria-describedby' => $describedBy === [] ? null : implode(' ', $describedBy),
];
?>
<div class="field">
    <label class="field__label" for="<?= e($id) ?>">
        <?= e($label) ?>
        <?php if (!$required): ?><span class="field__optional">Optional</span><?php endif; ?>
    </label>

    <?php if ($type === 'textarea'): ?>
        <textarea class="textarea"<?= attributes($controlAttributes) ?>><?= e($value) ?></textarea>
    <?php elseif ($type === 'select'): ?>
        <select class="select"<?= attributes($controlAttributes) ?>>
            <?php foreach ($options as $optionValue => $optionLabel): ?>
                <option value="<?= e($optionValue) ?>"
                    <?= (string) $optionValue === (string) $value ? 'selected' : '' ?>>
                    <?= e($optionLabel) ?>
                </option>
            <?php endforeach; ?>
        </select>
    <?php elseif ($prefix !== null): ?>
        <div class="input-group">
            <span class="input-group__addon"><?= e($prefix) ?></span>
            <input class="input" type="<?= e($type) ?>" value="<?= e($value) ?>"<?= attributes($controlAttributes) ?>>
        </div>
    <?php else: ?>
        <input class="input" type="<?= e($type) ?>" value="<?= e($value) ?>"<?= attributes($controlAttributes) ?>>
    <?php endif; ?>

    <?php if ($hint !== null): ?>
        <p class="field__hint" id="<?= e($id) ?>-hint"><?= e($hint) ?></p>
    <?php endif; ?>

    <?php if ($error !== null): ?>
        <p class="field__error" id="<?= e($id) ?>-error">
            <?= component('primitives/icon', ['name' => 'alert', 'size' => 14]) ?>
            <span><?= e($error) ?></span>
        </p>
    <?php endif; ?>
</div>
