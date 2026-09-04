<?php

declare(strict_types=1);

namespace Rentivo\Http\Controllers\Manage;

use Rentivo\Auth\SessionAuth;
use Rentivo\Components\View;
use Rentivo\Http\HttpException;
use Rentivo\Http\Request;
use Rentivo\Http\Response;
use Rentivo\Repositories\BookingRepository;
use Rentivo\Repositories\DocumentRepository;
use Rentivo\Repositories\OrganizationCustomerRepository;
use Rentivo\Security\Authorization;
use Rentivo\Security\Permissions;
use Rentivo\Services\AuditService;
use Rentivo\Support\Flash;
use Rentivo\Support\Pagination;

/**
 * Organization customers.
 *
 * The customer relationship is created by the first booking with the agency,
 * and internal notes recorded here are never shown to the customer.
 */
final class CustomerManagementController extends ManageController
{
    public function __construct(
        View $view,
        SessionAuth $auth,
        Authorization $authorization,
        private OrganizationCustomerRepository $customers,
        private BookingRepository $bookings,
        private DocumentRepository $documents,
        private AuditService $audit
    ) {
        parent::__construct($view, $auth, $authorization);
    }

    /** GET /manage/{org}/customers */
    public function index(Request $request): Response
    {
        $context = $this->organization($request);
        $context->authorize(Permissions::CUSTOMERS_VIEW);

        $search = $this->search($request);

        $total = $this->customers->countForOrganization($context->organizationId(), $search);
        $pagination = $this->paginate($request, $total, 15);

        return $this->renderManage($context, 'manage/customers/index', [
            'title'         => 'Customers',
            'manageSection' => 'customers',
            'customers'     => $this->customers->listForOrganization($context->organizationId(), $search, $pagination),
            'total'         => $total,
            'search'        => $search ?? '',
            'pagination'    => $pagination,
        ]);
    }

    /** GET /manage/{org}/customers/{id} */
    public function show(Request $request): Response
    {
        $context = $this->organization($request);
        $context->authorize(Permissions::CUSTOMERS_VIEW);

        $customer = $this->customers->findInOrganization(
            $request->routeInt('id'),
            $context->organizationId()
        );

        if ($customer === null) {
            throw HttpException::notFound('Customer not found in this organization.');
        }

        $userId = (int) $customer['user_id'];

        $bookingPagination = new Pagination(1, 10, 0);
        $filters = ['user_id' => $userId];
        $bookingTotal = $this->bookings->countForOrganization($context->organizationId(), $filters);
        $bookingPagination = new Pagination(1, 10, $bookingTotal);

        return $this->renderManage($context, 'manage/customers/detail', [
            'title'         => (string) $customer['name'],
            'manageSection' => 'customers',
            'customer'      => $customer,
            'summary'       => $this->customers->bookingSummary($context->organizationId(), $userId),
            'bookings'      => $this->bookings->listForOrganization($context->organizationId(), $filters, $bookingPagination),
            'documents'     => $context->can(Permissions::DOCUMENTS_VIEW)
                ? $this->documents->listForCustomer($context->organizationId(), $userId)
                : [],
        ]);
    }

    /** POST /manage/{org}/customers/{id}/notes */
    public function updateNotes(Request $request): Response
    {
        $context = $this->organization($request);
        $context->authorize(Permissions::CUSTOMERS_EDIT_NOTES);

        $customerId = $request->routeInt('id');

        $customer = $this->customers->findInOrganization($customerId, $context->organizationId());

        if ($customer === null) {
            throw HttpException::notFound('Customer not found in this organization.');
        }

        $this->customers->updateNotes(
            $customerId,
            $context->organizationId(),
            $this->nullableString($request, 'internal_notes', 5000)
        );

        $this->audit->record(
            $context->organizationId(),
            $context->userId(),
            AuditService::CUSTOMER_NOTES_UPDATED,
            'organization_customer',
            $customerId,
            ['customer_user_id' => (int) $customer['user_id']]
        );

        Flash::success('Internal notes saved.');

        return $this->redirect($this->manageUrl($context, 'customers/' . $customerId));
    }
}
