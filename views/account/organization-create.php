<?php
/**
 * Organization creation.
 *
 * Any authenticated user may create an organization and becomes its admin.
 *
 * @var array $errors
 * @var array $old
 */

$errors = $errors ?? [];
$old = $old ?? [];
$currentUser = $currentUser ?? [];
?>

<div class="page-header">
    <div class="page-header__heading">
        <p class="eyebrow">For agencies</p>
        <h1 class="page-header__title">Create your organization</h1>
        <p class="page-header__description">
            You will become the administrator with full authority over this
            organization: its fleet, bookings, staff and settings.
        </p>
    </div>
</div>

<form class="card card--padded" method="post" action="/organizations" data-guard-submit>
    <?= csrf_field() ?>

    <fieldset class="fieldset">
        <legend class="fieldset__legend">Agency details</legend>

        <div class="form-grid form-grid--2">
            <?= component('forms/field', [
                'name'     => 'name',
                'label'    => 'Organization name',
                'value'    => $old['name'] ?? '',
                'required' => true,
                'errors'   => $errors,
                'hint'     => 'Your public storefront address is generated from this.',
            ]) ?>

            <?= component('forms/field', [
                'name'     => 'contact_email',
                'label'    => 'Contact email',
                'type'     => 'email',
                'value'    => $old['contact_email'] ?? ($currentUser['email'] ?? ''),
                'required' => true,
                'errors'   => $errors,
            ]) ?>

            <?= component('forms/field', [
                'name'     => 'phone',
                'label'    => 'Phone number',
                'type'     => 'tel',
                'value'    => $old['phone'] ?? '',
                'required' => true,
                'errors'   => $errors,
                'attributes' => ['placeholder' => '+973 1700 0000'],
            ]) ?>

            <?= component('forms/field', [
                'name'   => 'address',
                'label'  => 'Address',
                'value'  => $old['address'] ?? '',
                'errors' => $errors,
            ]) ?>

            <div class="form-grid__full">
                <?= component('forms/field', [
                    'name'        => 'description',
                    'label'       => 'Description',
                    'type'        => 'textarea',
                    'value'       => $old['description'] ?? '',
                    'errors'      => $errors,
                    'placeholder' => 'What makes your agency worth booking with?',
                ]) ?>
            </div>
        </div>
    </fieldset>

    <div class="divider"></div>

    <fieldset class="fieldset">
        <legend class="fieldset__legend">Branding and terms</legend>

        <div class="form-grid form-grid--2">
            <?= component('forms/field', [
                'name'   => 'primary_color',
                'label'  => 'Brand accent colour',
                'type'   => 'color',
                'value'  => $old['primary_color'] ?? '#111111',
                'hint'   => 'Used for small accents on your storefront only.',
                'errors' => $errors,
            ]) ?>

            <div class="form-grid__full">
                <?= component('forms/field', [
                    'name'        => 'rental_terms',
                    'label'       => 'Rental terms',
                    'type'        => 'textarea',
                    'value'       => $old['rental_terms'] ?? '',
                    'errors'      => $errors,
                    'hint'        => 'Shown to customers on your cars and bookings.',
                    'placeholder' => "Minimum driver age…\nDocuments required at pickup…\nFuel policy…",
                ]) ?>
            </div>
        </div>
    </fieldset>

    <div class="form-actions">
        <?= component('primitives/button', ['label' => 'Create organization', 'size' => 'lg']) ?>
        <?= component('primitives/button', [
            'label'   => 'Cancel',
            'href'    => '/account',
            'variant' => 'ghost',
        ]) ?>
    </div>
</form>
