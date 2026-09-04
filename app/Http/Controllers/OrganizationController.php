<?php

declare(strict_types=1);

namespace Rentivo\Http\Controllers;

use Rentivo\Auth\SessionAuth;
use Rentivo\Components\View;
use Rentivo\Http\Request;
use Rentivo\Http\Response;
use Rentivo\Security\Authorization;
use Rentivo\Security\RateLimiter;
use Rentivo\Services\OrganizationService;
use Rentivo\Support\Flash;

/**
 * Organization creation. Any authenticated user may create one and becomes
 * its admin.
 */
final class OrganizationController extends Controller
{
    public function __construct(
        View $view,
        SessionAuth $auth,
        Authorization $authorization,
        private OrganizationService $organizations,
        private RateLimiter $rateLimiter
    ) {
        parent::__construct($view, $auth, $authorization);
    }

    /** GET /organizations/create */
    public function create(Request $request): Response
    {
        $user = $this->requireUser();

        return $this->render('account/organization-create', [
            'title'          => 'Create an organization',
            'user'           => $user,
            'accountSection' => 'organizations',
        ], 'account');
    }

    /** POST /organizations */
    public function store(Request $request): Response
    {
        $user = $this->requireUser();
        $userId = (int) $user['id'];

        $this->rateLimiter->enforce(
            'organization_create:' . $userId,
            5,
            3600,
            'You have created several organizations recently. Please try again later.'
        );

        $validator = $this->validate($request, [
            'name'          => 'required|max:191|min:2',
            'contact_email' => 'required|email|max:191',
            'phone'         => 'required|phone|max:32',
            'description'   => 'nullable|max:2000',
            'address'       => 'nullable|max:255',
            'primary_color' => 'nullable|hex_color',
            'rental_terms'  => 'nullable|max:5000',
        ], [
            'name'          => 'Organization name',
            'contact_email' => 'Contact email',
            'phone'         => 'Phone number',
            'primary_color' => 'Brand colour',
            'rental_terms'  => 'Rental terms',
        ]);

        if ($validator->fails()) {
            return $this->redirectWithErrors('/organizations/create', $validator->errors(), $request->body());
        }

        $organization = $this->organizations->create([
            'name'          => (string) $validator->value('name'),
            'contact_email' => (string) $validator->value('contact_email'),
            'phone'         => (string) $validator->value('phone'),
            'description'   => $validator->value('description'),
            'address'       => $validator->value('address'),
            'primary_color' => $validator->value('primary_color') ?? '#111111',
            'rental_terms'  => $validator->value('rental_terms'),
        ], $userId);

        Flash::success($organization['name'] . ' is ready. Add your locations and first car to start renting.');

        return $this->redirect('/manage/' . $organization['slug']);
    }
}
