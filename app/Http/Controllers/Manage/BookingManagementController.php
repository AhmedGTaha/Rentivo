<?php

declare(strict_types=1);

namespace Rentivo\Http\Controllers\Manage;

use Rentivo\Auth\SessionAuth;
use Rentivo\Components\View;
use Rentivo\Http\HttpException;
use Rentivo\Http\Request;
use Rentivo\Http\Response;
use Rentivo\Repositories\ActivityLogRepository;
use Rentivo\Repositories\BookingRepository;
use Rentivo\Repositories\CarRepository;
use Rentivo\Repositories\DocumentRepository;
use Rentivo\Repositories\OrganizationCustomerRepository;
use Rentivo\Repositories\RentalRepository;
use Rentivo\Security\Authorization;
use Rentivo\Security\OrganizationContext;
use Rentivo\Security\Permissions;
use Rentivo\Services\BookingException;
use Rentivo\Services\BookingService;
use Rentivo\Services\BookingStatus;
use Rentivo\Services\RentalService;
use Rentivo\Support\DateTimeHelper;
use Rentivo\Support\Flash;

/**
 * Organization booking operations: review, decisions, pickup and return.
 *
 * The visible actions on the detail page and the server-side guards come from
 * the same permission checks, so hiding a button is never the only defence.
 */
final class BookingManagementController extends ManageController
{
    public function __construct(
        View $view,
        SessionAuth $auth,
        Authorization $authorization,
        private BookingRepository $bookings,
        private BookingService $bookingService,
        private RentalService $rentals,
        private RentalRepository $rentalRepository,
        private CarRepository $cars,
        private DocumentRepository $documents,
        private OrganizationCustomerRepository $customers,
        private ActivityLogRepository $activity
    ) {
        parent::__construct($view, $auth, $authorization);
    }

    /** GET /manage/{org}/bookings */
    public function index(Request $request): Response
    {
        $context = $this->organization($request);
        $context->authorize(Permissions::BOOKINGS_VIEW);

        $filters = $this->listFilters($request);

        $total = $this->bookings->countForOrganization($context->organizationId(), $filters);
        $pagination = $this->paginate($request, $total, 15);

        return $this->renderManage($context, 'manage/bookings/index', [
            'title'         => 'Bookings',
            'manageSection' => 'bookings',
            'bookings'      => $this->bookings->listForOrganization($context->organizationId(), $filters, $pagination),
            'total'         => $total,
            'pagination'    => $pagination,
            'filters'       => $filters,
            'statusCounts'  => $this->bookings->statusCounts($context->organizationId()),
            'statusOptions' => BookingStatus::options(),
            'carOptions'    => $this->cars->simpleListForOrganization($context->organizationId()),
        ]);
    }

    /** GET /manage/{org}/bookings/{reference} */
    public function show(Request $request): Response
    {
        $context = $this->organization($request);
        $context->authorize(Permissions::BOOKINGS_VIEW);

        $booking = $this->requireBooking($context, $request);
        $bookingId = (int) $booking['id'];

        $rental = $this->rentalRepository->findByBooking($bookingId, $context->organizationId());

        // Documents are only surfaced to staff who may view them.
        $documents = $context->can(Permissions::DOCUMENTS_VIEW)
            ? $this->documents->listForCustomer($context->organizationId(), (int) $booking['user_id'])
            : [];

        return $this->renderManage($context, 'manage/bookings/detail', [
            'title'          => 'Booking ' . $booking['reference'],
            'manageSection'  => 'bookings',
            'booking'        => $booking,
            'rental'         => $rental,
            'inspectionImages' => $rental === null ? [] : $this->rentalRepository->inspectionImages((int) $rental['id']),
            'documents'      => $documents,
            'customer'       => $this->customers->find($context->organizationId(), (int) $booking['user_id']),
            'bookingSummary' => $this->customers->bookingSummary($context->organizationId(), (int) $booking['user_id']),
            'timeline'       => $this->activity->forEntity($context->organizationId(), 'booking', $bookingId),
            'allowedTransitions' => BookingStatus::allowedTransitions((string) $booking['status']),
            'paymentStatuses' => BookingStatus::paymentStatuses(),
            'isOverdue'      => $this->isOverdue($booking),
        ]);
    }

    /** POST /manage/{org}/bookings/{reference}/confirm */
    public function confirm(Request $request): Response
    {
        return $this->action($request, function (OrganizationContext $context, string $reference): string {
            $this->bookingService->confirm($context, $reference);

            return 'Booking ' . $reference . ' confirmed.';
        });
    }

    /** POST /manage/{org}/bookings/{reference}/reject */
    public function reject(Request $request): Response
    {
        return $this->action($request, function (OrganizationContext $context, string $reference) use ($request): string {
            $this->bookingService->reject($context, $reference, $this->nullableString($request, 'reason', 500));

            return 'Booking ' . $reference . ' rejected.';
        });
    }

    /** POST /manage/{org}/bookings/{reference}/cancel */
    public function cancel(Request $request): Response
    {
        return $this->action($request, function (OrganizationContext $context, string $reference) use ($request): string {
            $this->bookingService->cancelByStaff($context, $reference, $this->nullableString($request, 'reason', 500));

            return 'Booking ' . $reference . ' cancelled.';
        });
    }

    /** POST /manage/{org}/bookings/{reference}/ready */
    public function markReady(Request $request): Response
    {
        return $this->action($request, function (OrganizationContext $context, string $reference): string {
            $this->bookingService->markReadyForPickup($context, $reference);

            return 'Booking ' . $reference . ' is ready for pickup.';
        });
    }

    /** POST /manage/{org}/bookings/{reference}/no-show */
    public function markNoShow(Request $request): Response
    {
        return $this->action($request, function (OrganizationContext $context, string $reference) use ($request): string {
            $this->bookingService->markNoShow($context, $reference, $this->nullableString($request, 'reason', 500));

            return 'Booking ' . $reference . ' marked as a no-show.';
        });
    }

    /** POST /manage/{org}/bookings/{reference}/payment */
    public function updatePayment(Request $request): Response
    {
        return $this->action($request, function (OrganizationContext $context, string $reference) use ($request): string {
            $this->bookingService->updatePaymentStatus($context, $reference, (string) $request->input('payment_status'));

            return 'Payment status updated.';
        });
    }

    /** POST /manage/{org}/bookings/{reference}/notes */
    public function updateNotes(Request $request): Response
    {
        return $this->action($request, function (OrganizationContext $context, string $reference) use ($request): string {
            $this->bookingService->updateAdminNotes($context, $reference, $this->nullableString($request, 'admin_notes', 5000));

            return 'Internal notes saved.';
        });
    }

    // -----------------------------------------------------------------
    // Pickup and return
    // -----------------------------------------------------------------

    /** GET /manage/{org}/bookings/{reference}/checkout */
    public function checkoutForm(Request $request): Response
    {
        $context = $this->organization($request);
        $context->authorize(Permissions::BOOKINGS_CHECKOUT);

        $booking = $this->requireBooking($context, $request);

        return $this->renderManage($context, 'manage/bookings/checkout', [
            'title'         => 'Pickup — ' . $booking['reference'],
            'manageSection' => 'bookings',
            'booking'       => $booking,
            'car'           => $this->cars->findInOrganization((int) $booking['car_id'], $context->organizationId()),
        ]);
    }

    /** POST /manage/{org}/bookings/{reference}/checkout */
    public function checkout(Request $request): Response
    {
        $context = $this->organization($request);
        $reference = (string) $request->route('reference');

        $validator = $this->validate($request, [
            'mileage'         => 'required|int|min_value:0',
            'fuel_percentage' => 'required|int|min_value:0|max_value:100',
            'condition'       => 'nullable|max:2000',
        ], [
            'mileage'         => 'Odometer reading',
            'fuel_percentage' => 'Fuel level',
            'condition'       => 'Condition notes',
        ]);

        $redirect = $this->manageUrl($context, 'bookings/' . rawurlencode($reference) . '/checkout');

        if ($validator->fails()) {
            return $this->redirectWithErrors($redirect, $validator->errors(), $request->body());
        }

        try {
            $this->rentals->checkout(
                $context,
                $reference,
                [
                    'mileage'         => (int) $validator->value('mileage'),
                    'fuel_percentage' => (int) $validator->value('fuel_percentage'),
                    'condition'       => $validator->value('condition'),
                ],
                $request->fileList('inspection_images')
            );

            Flash::success('Pickup completed. The rental is now active.');
        } catch (BookingException $e) {
            Flash::error($e->getMessage());

            return $this->redirect($redirect);
        }

        return $this->redirect($this->manageUrl($context, 'bookings/' . rawurlencode($reference)));
    }

    /** GET /manage/{org}/bookings/{reference}/return */
    public function returnForm(Request $request): Response
    {
        $context = $this->organization($request);
        $context->authorize(Permissions::BOOKINGS_COMPLETE_RETURN);

        $booking = $this->requireBooking($context, $request);
        $rental = $this->rentalRepository->findByBooking((int) $booking['id'], $context->organizationId());

        if ($rental === null) {
            Flash::error('This booking has no rental to return.');

            return $this->redirect($this->manageUrl($context, 'bookings/' . rawurlencode((string) $booking['reference'])));
        }

        return $this->renderManage($context, 'manage/bookings/return', [
            'title'         => 'Return — ' . $booking['reference'],
            'manageSection' => 'bookings',
            'booking'       => $booking,
            'rental'        => $rental,
            'isOverdue'     => $this->isOverdue($booking),
        ]);
    }

    /** POST /manage/{org}/bookings/{reference}/return */
    public function completeReturn(Request $request): Response
    {
        $context = $this->organization($request);
        $reference = (string) $request->route('reference');

        $validator = $this->validate($request, [
            'mileage'            => 'required|int|min_value:0',
            'fuel_percentage'    => 'required|int|min_value:0|max_value:100',
            'condition'          => 'nullable|max:2000',
            'damage_notes'       => 'nullable|max:2000',
            'additional_charges' => 'nullable|money',
            'final_car_status'   => 'required|in:available,maintenance',
        ], [
            'mileage'            => 'Odometer reading',
            'fuel_percentage'    => 'Fuel level',
            'additional_charges' => 'Additional charges',
            'final_car_status'   => 'Car status after return',
        ]);

        $redirect = $this->manageUrl($context, 'bookings/' . rawurlencode($reference) . '/return');

        if ($validator->fails()) {
            return $this->redirectWithErrors($redirect, $validator->errors(), $request->body());
        }

        try {
            $this->rentals->completeReturn(
                $context,
                $reference,
                [
                    'mileage'                 => (int) $validator->value('mileage'),
                    'fuel_percentage'         => (int) $validator->value('fuel_percentage'),
                    'condition'               => $validator->value('condition'),
                    'damage_notes'            => $validator->value('damage_notes'),
                    'additional_charges_fils' => (int) ($validator->value('additional_charges') ?? 0),
                    'final_car_status'        => (string) $validator->value('final_car_status'),
                ],
                $request->fileList('inspection_images')
            );

            Flash::success('Return completed and the booking is now closed.');
        } catch (BookingException $e) {
            Flash::error($e->getMessage());

            return $this->redirect($redirect);
        }

        return $this->redirect($this->manageUrl($context, 'bookings/' . rawurlencode($reference)));
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    /**
     * Wraps a booking action with consistent error handling and redirect.
     *
     * @param callable(OrganizationContext,string):string $handler Returns the success message.
     */
    private function action(Request $request, callable $handler): Response
    {
        $context = $this->organization($request);
        $reference = (string) $request->route('reference');

        try {
            Flash::success($handler($context, $reference));
        } catch (BookingException $e) {
            Flash::error($e->getMessage());

            // Surface the specific conflicting bookings, not just a message.
            foreach ($e->conflicts() as $conflict) {
                Flash::warning(sprintf(
                    'Conflict with %s (%s → %s).',
                    $conflict['reference'],
                    datetime_display((string) $conflict['pickup_at']),
                    datetime_display((string) $conflict['return_at'])
                ));
            }
        }

        return $this->redirect($this->manageUrl($context, 'bookings/' . rawurlencode($reference)));
    }

    /** @return array<string,mixed> */
    private function requireBooking(OrganizationContext $context, Request $request): array
    {
        $booking = $this->bookings->findInOrganization(
            (string) $request->route('reference'),
            $context->organizationId()
        );

        if ($booking === null) {
            throw HttpException::notFound('Booking not found in this organization.');
        }

        return $booking;
    }

    /** @return array<string,mixed> */
    private function listFilters(Request $request): array
    {
        $status = $request->queryParam('status');
        $payment = $request->queryParam('payment_status');

        $from = DateTimeHelper::fromInput((string) $request->queryParam('from', ''));
        $to = DateTimeHelper::fromInput((string) $request->queryParam('to', ''));

        return [
            'search'         => $this->search($request),
            'status'         => is_string($status) && BookingStatus::exists($status) ? $status : null,
            'payment_status' => is_string($payment) && in_array($payment, BookingStatus::paymentStatuses(), true)
                ? $payment
                : null,
            'car_id'         => (int) $request->queryParam('car_id', 0),
            'user_id'        => (int) $request->queryParam('user_id', 0),
            'from'           => $from === null ? null : DateTimeHelper::toDb($from),
            'to'             => $to === null ? null : DateTimeHelper::toDb($to),
            'overdue'        => $request->queryParam('overdue') === '1',
        ];
    }

    /** Overdue is derived from the data, never stored as a status. */
    private function isOverdue(array $booking): bool
    {
        return (string) $booking['status'] === BookingStatus::ACTIVE
            && (string) $booking['return_at'] < DateTimeHelper::nowDb();
    }
}
