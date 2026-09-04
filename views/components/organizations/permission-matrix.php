<?php
/**
 * Permission matrix.
 *
 * Renders the closed permission catalogue as grouped checkboxes. Only keys
 * that exist in Permissions::groups() can be displayed, and the server filters
 * the submitted keys through the same list, so a tampered form cannot grant
 * anything outside the catalogue.
 *
 * @var array $permissionGroups  Permissions::groups()
 * @var array $assigned          Currently held keys
 * @var bool  $readonly          Render as read-only summary badges
 */

$permissionGroups = $permissionGroups ?? [];
$assigned = $assigned ?? [];
$readonly = $readonly ?? false;
?>
<div class="permission-matrix">
    <?php foreach ($permissionGroups as $group): ?>
        <fieldset class="permission-group">
            <div class="permission-group__header">
                <legend class="permission-group__title"><?= e($group['label']) ?></legend>
                <?php if (!$readonly): ?>
                    <span class="text-xs text-muted">
                        <?php
                        $selected = count(array_intersect(array_keys($group['permissions']), $assigned));
                        echo (int) $selected . ' of ' . count($group['permissions']);
                        ?>
                    </span>
                <?php endif; ?>
            </div>

            <?php if ($readonly): ?>
                <div class="permission-summary">
                    <?php
                    $granted = array_intersect(array_keys($group['permissions']), $assigned);
                    ?>
                    <?php if ($granted === []): ?>
                        <span class="text-xs text-muted">No permissions in this area.</span>
                    <?php else: ?>
                        <?php foreach ($granted as $key): ?>
                            <?= component('primitives/badge', [
                                'label' => $group['permissions'][$key],
                                'tone'  => 'neutral',
                                'small' => true,
                            ]) ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="permission-group__options">
                    <?php foreach ($group['permissions'] as $key => $label): ?>
                        <?= component('forms/checkbox', [
                            'name'    => 'permissions[]',
                            'value'   => $key,
                            'label'   => $label,
                            'hint'    => $key,
                            'checked' => in_array($key, $assigned, true),
                            'card'    => true,
                        ]) ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </fieldset>
    <?php endforeach; ?>
</div>
