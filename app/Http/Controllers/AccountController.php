<?php

declare(strict_types=1);

namespace Rentivo\Http\Controllers;

use Rentivo\Auth\SessionAuth;
use Rentivo\Components\View;
use Rentivo\Http\HttpException;
use Rentivo\Http\Request;
use Rentivo\Http\Response;
use Rentivo\Repositories\BookingRepository;
use Rentivo\Repositories\FavoriteRepository;
use Rentivo\Repositories\NotificationRepository;
use Rentivo\Repositories\UserRepository;
use Rentivo\Security\Authorization;
use Rentivo\Services\FileStorageService;
use Rentivo\Services\ImageService;
use Rentivo\Services\UploadException;
use Rentivo\Support\Flash;

/**
 * The customer account area: dashboard, profile, bookings and favorites.
 *
 * Every read here is scoped to the authenticated user's own id.
 */
final class AccountController extends Controller
{
    public function __construct(
        View $view,
        SessionAuth $auth,
        Authorization $authorization,
        private UserRepository $users,
        private BookingRepository $bookings,
        private FavoriteRepository $favorites,
        private NotificationRepository $notifications,
        private ImageService $images,
        private FileStorageService $storage
    ) {
        parent::__construct($view, $auth, $authorization);
    }

    /** GET /account */
    public function dashboard(Request $request): Response
    {
        $user = $this->requireUser();
        $userId = (int) $user['id'];

        return $this->render('account/dashboard', [
            'title'          => 'Your account',
            'profile'        => $this->users->profileOrCreate($userId),
            'nextBooking'    => $this->bookings->nextUpcomingForUser($userId),
            'activeBooking'  => $this->bookings->activeForUser($userId),
            'recentBookings' => $this->bookings->recentForUser($userId, 4),
            'counts'         => $this->bookings->groupCountsForUser($userId),
            'favoriteCount'  => $this->favorites->countForUser($userId),
            'notifications'  => $this->notifications->recentForUser($userId, 4),
            'accountSection' => 'dashboard',
        ], 'account');
    }

    /** GET /account/profile */
    public function profile(Request $request): Response
    {
        $user = $this->requireUser();
        $userId = (int) $user['id'];

        $redirect = $request->queryParam('redirect');

        return $this->render('account/profile', [
            'title'          => 'Profile',
            'profile'        => $this->users->profileOrCreate($userId),
            'redirect'       => is_string($redirect) ? $redirect : null,
            'accountSection' => 'profile',
        ], 'account');
    }

    /** POST /account/profile */
    public function updateProfile(Request $request): Response
    {
        $user = $this->requireUser();
        $userId = (int) $user['id'];

        $validator = $this->validate($request, [
            'name'          => 'required|max:191',
            'phone'         => 'nullable|phone|max:32',
            'date_of_birth' => 'nullable|date',
            'nationality'   => 'nullable|max:96',
            'address'       => 'nullable|max:255',
        ], [
            'name'          => 'Full name',
            'phone'         => 'Phone number',
            'date_of_birth' => 'Date of birth',
        ]);

        if ($validator->fails()) {
            return $this->redirectWithErrors('/account/profile', $validator->errors(), $request->body());
        }

        $dateOfBirth = $validator->value('date_of_birth');

        $this->users->updateName($userId, (string) $validator->value('name'));

        $profileData = [
            'phone'         => $validator->value('phone'),
            'date_of_birth' => $dateOfBirth instanceof \DateTimeInterface ? $dateOfBirth->format('Y-m-d') : null,
            'nationality'   => $validator->value('nationality'),
            'address'       => $validator->value('address'),
        ];

        // Optional local avatar; the Google avatar remains the fallback.
        $upload = $request->file('profile_image');

        if (is_array($upload) && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $previous = $this->users->findProfile($userId)['profile_image_path'] ?? null;

                $profileData['profile_image_path'] = $this->images->storeUploadedImage(
                    $upload,
                    FileStorageService::DISK_PUBLIC,
                    'profiles/' . $userId,
                    512
                );

                if (is_string($previous) && $previous !== '') {
                    $this->storage->delete(FileStorageService::DISK_PUBLIC, $previous);
                }
            } catch (UploadException $e) {
                return $this->redirectWithErrors(
                    '/account/profile',
                    ['profile_image' => $e->getMessage()],
                    $request->body()
                );
            }
        }

        $this->users->updateProfile($userId, $profileData);
        $this->auth->refresh();

        Flash::success('Your profile has been updated.');

        $redirect = $request->input('redirect');

        return $this->redirect(is_string($redirect) && $redirect !== '' ? $redirect : '/account/profile');
    }

    /** GET /account/bookings */
    public function bookings(Request $request): Response
    {
        $userId = $this->requireUserId();

        $group = (string) $request->queryParam('group', 'upcoming');

        if (!in_array($group, ['upcoming', 'active', 'completed', 'cancelled', 'all'], true)) {
            $group = 'upcoming';
        }

        $total = $this->bookings->countForUser($userId, $group);
        $pagination = $this->paginate($request, $total, 10);

        return $this->render('account/bookings', [
            'title'          => 'Your bookings',
            'bookings'       => $this->bookings->listForUser($userId, $group, $pagination),
            'counts'         => $this->bookings->groupCountsForUser($userId),
            'group'          => $group,
            'total'          => $total,
            'pagination'     => $pagination,
            'accountSection' => 'bookings',
        ], 'account');
    }

    /** GET /account/bookings/{reference} */
    public function bookingDetail(Request $request): Response
    {
        $userId = $this->requireUserId();

        $booking = $this->bookings->findForUser((string) $request->route('reference'), $userId);

        if ($booking === null) {
            throw HttpException::notFound('That booking could not be found.');
        }

        return $this->render('account/booking-detail', [
            'title'          => 'Booking ' . $booking['reference'],
            'booking'        => $booking,
            'accountSection' => 'bookings',
        ], 'account');
    }

    /** GET /account/favorites */
    public function favorites(Request $request): Response
    {
        $userId = $this->requireUserId();

        $total = $this->favorites->countForUser($userId);
        $pagination = $this->paginate($request, $total, 12);

        return $this->render('account/favorites', [
            'title'          => 'Saved cars',
            'cars'           => $this->favorites->listForUser($userId, $pagination),
            'total'          => $total,
            'pagination'     => $pagination,
            'favoriteIds'    => $this->favorites->carIdsFor($userId),
            'accountSection' => 'favorites',
        ], 'account');
    }
}
