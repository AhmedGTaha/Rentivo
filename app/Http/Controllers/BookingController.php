<?php

declare(strict_types=1);

namespace Rentivo\Http\Controllers;

use DateTimeImmutable;
use Rentivo\Auth\SessionAuth;
use Rentivo\Components\View;
use Rentivo\Http\HttpException;
use Rentivo\Http\Request;
use Rentivo\Http\Response;
use Rentivo\Repositories\BookingRepository;
use Rentivo\Repositories\CarRepository;
use Rentivo\Repositories\LocationRepository;
use Rentivo\Repositories\UserRepository;
use Rentivo\Security\Authorization;
use Rentivo\Security\RateLimiter;
use Rentivo\Services\AvailabilityService;
use Rentivo\Services\BookingException;
use Rentivo\Services\BookingService;
use Rentivo\Services\PricingService;
use Rentivo\Support\DateTimeHelper;
use Rentivo\Support\Flash;

/**
 * Customer booking: checkout and submission.
 *
 * A guest may reach the checkout page. When they do, their whole selection —
 * car, agency, dates and locations — is stored in the session before they are
 * sent to Google, and they land back on this exact page afterwards rather
 * than on a generic dashboard.
 */
final class BookingController extends Controller
{
    public function __construct(
        View $view,
        SessionAuth $auth,
        Authorization $authorization,
        private CarRepository $cars,
        private LocationRepository $locations,
        private BookingRepository $bookings,
        private UserRepository $users,
        private BookingService $bookingService,
        private AvailabilityService $availability,
        private PricingService $pricing,
        private RateLimiter $rateLimiter
    ) {
        parent::__construct($view, $auth, $authorization);
    }

    /** GET /cars/{slug}/book */
    public function checkout(Request $request): Response
    {
        $car = $this->requireCar($request);

        $selection = $this->resolveSelection($request, $car);

        // A guest gets sent to Google, but their selection is preserved so the
        // callback returns them straight back here.
        if ($this->auth->guest()) {
            $this->auth->setIntendedBooking($selection);
            $this->auth->setIntendedUrl($this->checkoutUrl($car, $selection));

            Flash::info('Sign in with Google to complete your booking.');

            return $this->redirect('/login');
        }

        $userId = $this->requireUserId();
        $profile = $this->users->profileOrCreate($userId);

        $pickupAt = $selection['pickup_at'] === null ? null : DateTimeHelper::fromInput($selection['pickup_at']);
        $returnAt = $selection['return_at'] === null ? null : DateTimeHelper::fromInput($selection['return_at']);

        $quote = null;
        $availability = null;

        if ($pickupAt !== null && $returnAt !== null && $returnAt > $pickupAt) {
            $check = $this->availability->check($car, $pickupAt, $returnAt);

            $availability = $check;

            if ($check['available']) {
                $quote = $this->pricing->quote($pickupAt, $returnAt, (int) $car['daily_rate_fils']);
            }
        }

        return $this->render('account/booking-checkout', [
            'title'        => 'Complete your booking',
            'car'          => $car,
            'locations'    => $this->locations->publicForOrganization((int) $car['organization_id']),
            'selection'    => $selection,
            'quote'        => $quote,
            'availability' => $availability,
            'profile'      => $profile,
            'hasPhone'     => $this->users->hasPhone($userId),
            // Checkout lays out its own two-column container.
            'fullWidth'    => true,
        ]);
    }

    /** POST /cars/{slug}/book */
    public function store(Request $request): Response
    {
        $userId = $this->requireUserId();
        $car = $this->requireCar($request);

        $this->rateLimiter->enforce(
            'booking_submit:' . $userId,
            15,
            600,
            'You have submitted several bookings in a short time. Please wait a few minutes.'
        );

        // The phone number is a hard prerequisite for submitting a booking.
        if (!$this->users->hasPhone($userId)) {
            Flash::error('Please add a phone number to your profile before booking.');

            return $this->redirect('/account/profile?redirect=' . rawurlencode($request->path()));
        }

        $validator = $this->validate($request, [
            'pickup_at'          => 'required|datetime',
            'return_at'          => 'required|datetime',
            'pickup_location_id' => 'nullable|int',
            'return_location_id' => 'nullable|int',
            'customer_notes'     => 'nullable|max:1000',
        ], [
            'pickup_at'          => 'Pickup date and time',
            'return_at'          => 'Return date and time',
            'pickup_location_id' => 'Pickup location',
            'return_location_id' => 'Return location',
        ]);

        $pickupAt = $validator->value('pickup_at');
        $returnAt = $validator->value('return_at');

        if ($pickupAt instanceof DateTimeImmutable && $returnAt instanceof DateTimeImmutable && $returnAt <= $pickupAt) {
            $validator->addError('return_at', 'The return time must be after the pickup time.');
        }

        $organizationId = (int) $car['organization_id'];

        // Location ids are only accepted when they belong to this agency.
        $pickupLocationId = $this->validLocationId($validator->value('pickup_location_id'), $organizationId);
        $returnLocationId = $this->validLocationId($validator->value('return_location_id'), $organizationId);

        if ($validator->fails()) {
            return $this->redirectWithErrors(
                $this->checkoutUrlFromRequest($car, $request),
                $validator->errors(),
                $request->body()
            );
        }

        try {
            $booking = $this->bookingService->createForCustomer(
                $car,
                $userId,
                $pickupAt,
                $returnAt,
                $pickupLocationId,
                $returnLocationId,
                $validator->value('customer_notes')
            );
        } catch (BookingException $e) {
            Flash::error($e->getMessage());

            return $this->redirect($this->checkoutUrlFromRequest($car, $request));
        }

        $user = $this->auth->user() ?? [];
        $this->bookingService->announceCreation($booking, (string) ($user['name'] ?? 'A customer'));

        // The selection has served its purpose.
        $this->auth->pullIntendedBooking();

        Flash::success('Booking ' . $booking['reference'] . ' submitted. The agency will confirm shortly.');

        return $this->redirect('/account/bookings/' . $booking['reference']);
    }

    /** POST /account/bookings/{reference}/cancel */
    public function cancel(Request $request): Response
    {
        $userId = $this->requireUserId();
        $reference = (string) $request->route('reference');

        try {
            $this->bookingService->cancelByCustomer(
                $userId,
                $reference,
                $this->nullableString($request, 'cancellation_reason', 500)
            );

            Flash::success('Booking ' . $reference . ' has been cancelled.');
        } catch (BookingException $e) {
            Flash::error($e->getMessage());
        }

        return $this->redirect('/account/bookings/' . rawurlencode($reference));
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    /** @return array<string,mixed> */
    private function requireCar(Request $request): array
    {
        $car = $this->cars->findPublicBySlug((string) $request->route('slug'));

        if ($car === null) {
            throw HttpException::notFound('That car is no longer available for booking.');
        }

        return $car;
    }

    /**
     * Builds the booking selection from the request, falling back to whatever
     * was stored before the visitor was sent to Google.
     *
     * @param array<string,mixed> $car
     * @return array{car_slug:string,organization_slug:string,pickup_at:?string,return_at:?string,pickup_location_id:?int,return_location_id:?int}
     */
    private function resolveSelection(Request $request, array $car): array
    {
        $stored = $this->auth->peekIntendedBooking();

        // Only reuse a stored selection for the same car.
        if ($stored !== null && ($stored['car_slug'] ?? null) !== $car['slug']) {
            $stored = null;
        }

        $pickup = $request->queryParam('pickup_at');
        $return = $request->queryParam('return_at');
        $pickupLocation = $request->queryParam('pickup_location_id');
        $returnLocation = $request->queryParam('return_location_id');

        return [
            'car_slug'           => (string) $car['slug'],
            'organization_slug'  => (string) $car['organization_slug'],
            'pickup_at'          => $this->firstNonEmpty($pickup, $stored['pickup_at'] ?? null),
            'return_at'          => $this->firstNonEmpty($return, $stored['return_at'] ?? null),
            'pickup_location_id' => $this->intOrNull($pickupLocation ?? ($stored['pickup_location_id'] ?? null)),
            'return_location_id' => $this->intOrNull($returnLocation ?? ($stored['return_location_id'] ?? null)),
        ];
    }

    private function firstNonEmpty(mixed ...$values): ?string
    {
        foreach ($values as $value) {
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    private function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }

        return (int) $value > 0 ? (int) $value : null;
    }

    /** Location ids from a form are only honoured within the owning agency. */
    private function validLocationId(mixed $value, int $organizationId): ?int
    {
        $id = $this->intOrNull($value);

        if ($id === null) {
            return null;
        }

        return $this->locations->belongsToOrganization($id, $organizationId) ? $id : null;
    }

    /**
     * @param array<string,mixed> $car
     * @param array<string,mixed> $selection
     */
    private function checkoutUrl(array $car, array $selection): string
    {
        $query = array_filter([
            'pickup_at'          => $selection['pickup_at'],
            'return_at'          => $selection['return_at'],
            'pickup_location_id' => $selection['pickup_location_id'],
            'return_location_id' => $selection['return_location_id'],
        ], static fn ($value) => $value !== null && $value !== '');

        return '/cars/' . rawurlencode((string) $car['slug']) . '/book'
            . ($query === [] ? '' : '?' . http_build_query($query));
    }

    /** @param array<string,mixed> $car */
    private function checkoutUrlFromRequest(array $car, Request $request): string
    {
        return $this->checkoutUrl($car, [
            'pickup_at'          => $request->input('pickup_at'),
            'return_at'          => $request->input('return_at'),
            'pickup_location_id' => $request->input('pickup_location_id'),
            'return_location_id' => $request->input('return_location_id'),
        ]);
    }
}
