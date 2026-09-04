<?php

declare(strict_types=1);

namespace Rentivo\Http\Controllers\Manage;

use Rentivo\Auth\SessionAuth;
use Rentivo\Components\View;
use Rentivo\Http\Request;
use Rentivo\Http\Response;
use Rentivo\Security\Authorization;
use Rentivo\Services\OrganizationService;
use Rentivo\Services\UploadException;
use Rentivo\Support\Flash;

/**
 * Organization settings and branding. Admin-only.
 *
 * The brand colour is applied as an accent token; agencies cannot inject
 * markup, script or arbitrary CSS anywhere in the platform.
 */
final class SettingsController extends ManageController
{
    public function __construct(
        View $view,
        SessionAuth $auth,
        Authorization $authorization,
        private OrganizationService $organizations
    ) {
        parent::__construct($view, $auth, $authorization);
    }

    /** GET /manage/{org}/settings */
    public function edit(Request $request): Response
    {
        $context = $this->organization($request);
        $context->authorizeAdmin();

        return $this->renderManage($context, 'manage/settings', [
            'title'         => 'Settings',
            'manageSection' => 'settings',
        ]);
    }

    /** POST /manage/{org}/settings */
    public function update(Request $request): Response
    {
        $context = $this->organization($request);
        $context->authorizeAdmin();

        $validator = $this->validate($request, [
            'name'          => 'required|max:191|min:2',
            'contact_email' => 'required|email|max:191',
            'phone'         => 'required|phone|max:32',
            'description'   => 'nullable|max:2000',
            'address'       => 'nullable|max:255',
            'primary_color' => 'nullable|hex_color',
            'rental_terms'  => 'nullable|max:5000',
        ], [
            'contact_email' => 'Contact email',
            'primary_color' => 'Brand colour',
            'rental_terms'  => 'Rental terms',
        ]);

        $redirect = $this->manageUrl($context, 'settings');

        if ($validator->fails()) {
            return $this->redirectWithErrors($redirect, $validator->errors(), $request->body());
        }

        $this->organizations->update($context, [
            'name'          => (string) $validator->value('name'),
            'contact_email' => (string) $validator->value('contact_email'),
            'phone'         => (string) $validator->value('phone'),
            'description'   => $validator->value('description'),
            'address'       => $validator->value('address'),
            'primary_color' => $validator->value('primary_color') ?? '#111111',
            'rental_terms'  => $validator->value('rental_terms'),
            'is_active'     => $request->boolean('is_active') ? 1 : 0,
        ]);

        $logo = $request->file('logo');

        if (is_array($logo) && ($logo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $this->organizations->updateLogo($context, $logo);
            } catch (UploadException $e) {
                Flash::warning('Settings saved, but the logo was rejected: ' . $e->getMessage());

                return $this->redirect($redirect);
            }
        }

        Flash::success('Organization settings saved.');

        return $this->redirect($redirect);
    }
}
