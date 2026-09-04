<?php

declare(strict_types=1);

use Rentivo\Http\Controllers\AccountController;
use Rentivo\Http\Controllers\AgencyController;
use Rentivo\Http\Controllers\AuthController;
use Rentivo\Http\Controllers\BookingController;
use Rentivo\Http\Controllers\CarController;
use Rentivo\Http\Controllers\DevController;
use Rentivo\Http\Controllers\DocumentController;
use Rentivo\Http\Controllers\FavoriteController;
use Rentivo\Http\Controllers\HomeController;
use Rentivo\Http\Controllers\InvitationController;
use Rentivo\Http\Controllers\Manage\BookingManagementController;
use Rentivo\Http\Controllers\Manage\CarManagementController;
use Rentivo\Http\Controllers\Manage\CategoryManagementController;
use Rentivo\Http\Controllers\Manage\CustomerManagementController;
use Rentivo\Http\Controllers\Manage\DashboardController;
use Rentivo\Http\Controllers\Manage\DocumentManagementController;
use Rentivo\Http\Controllers\Manage\EmployeeManagementController;
use Rentivo\Http\Controllers\Manage\InspectionImageController;
use Rentivo\Http\Controllers\Manage\LocationManagementController;
use Rentivo\Http\Controllers\Manage\ReportController;
use Rentivo\Http\Controllers\Manage\SettingsController;
use Rentivo\Http\Controllers\NotificationController;
use Rentivo\Http\Controllers\OrganizationController;
use Rentivo\Http\Router;

/**
 * Every HTTP entry point in Rentivo.
 *
 * Authorization is not expressed here: it lives in the controllers and
 * services, so a route can never be the only thing standing between a user
 * and another organization's data.
 */
return static function (Router $router): void {
    // -----------------------------------------------------------------
    // Public marketplace — no authentication required
    // -----------------------------------------------------------------
    $router->get('/', [HomeController::class, 'index'], 'home');

    $router->get('/cars', [CarController::class, 'index'], 'cars.index');
    $router->get('/cars/{slug}', [CarController::class, 'show'], 'cars.show');

    $router->get('/agencies', [AgencyController::class, 'index'], 'agencies.index');
    $router->get('/agency/{slug}', [AgencyController::class, 'show'], 'agencies.show');
    $router->get('/agency/{slug}/cars', [AgencyController::class, 'cars'], 'agencies.cars');
    $router->get('/agency/{slug}/cars/{carSlug}', [CarController::class, 'showForAgency'], 'agencies.car');

    // -----------------------------------------------------------------
    // Authentication (Google only)
    // -----------------------------------------------------------------
    $router->get('/login', [AuthController::class, 'login'], 'login');
    $router->get('/auth/google', [AuthController::class, 'redirectToGoogle'], 'auth.google');
    $router->get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback'], 'auth.google.callback');
    $router->post('/logout', [AuthController::class, 'logout'], 'logout');

    // -----------------------------------------------------------------
    // Booking
    // -----------------------------------------------------------------
    $router->get('/cars/{slug}/book', [BookingController::class, 'checkout'], 'booking.checkout');
    $router->post('/cars/{slug}/book', [BookingController::class, 'store'], 'booking.store');

    // -----------------------------------------------------------------
    // Customer account
    // -----------------------------------------------------------------
    $router->group('/account', static function (Router $router): void {
        $router->get('/', [AccountController::class, 'dashboard'], 'account');

        $router->get('/profile', [AccountController::class, 'profile'], 'account.profile');
        $router->post('/profile', [AccountController::class, 'updateProfile'], 'account.profile.update');

        $router->get('/bookings', [AccountController::class, 'bookings'], 'account.bookings');
        $router->get('/bookings/{reference}', [AccountController::class, 'bookingDetail'], 'account.bookings.show');
        $router->post('/bookings/{reference}/cancel', [BookingController::class, 'cancel'], 'account.bookings.cancel');

        $router->get('/favorites', [AccountController::class, 'favorites'], 'account.favorites');
        $router->post('/favorites/{slug}/remove', [FavoriteController::class, 'remove'], 'account.favorites.remove');

        $router->get('/documents', [DocumentController::class, 'index'], 'account.documents');
        $router->post('/documents', [DocumentController::class, 'store'], 'account.documents.store');
        $router->post('/documents/{id:int}/delete', [DocumentController::class, 'destroy'], 'account.documents.delete');

        $router->get('/notifications', [NotificationController::class, 'index'], 'account.notifications');
        $router->post('/notifications/read-all', [NotificationController::class, 'markAllRead'], 'account.notifications.readAll');
        $router->post('/notifications/{id:int}/read', [NotificationController::class, 'markRead'], 'account.notifications.read');
    });

    $router->post('/favorites/{slug}/toggle', [FavoriteController::class, 'toggle'], 'favorites.toggle');

    // Authorized private document delivery.
    $router->get('/documents/{id:int}/file', [DocumentController::class, 'stream'], 'documents.file');

    // -----------------------------------------------------------------
    // Organizations
    // -----------------------------------------------------------------
    $router->get('/organizations/create', [OrganizationController::class, 'create'], 'organizations.create');
    $router->post('/organizations', [OrganizationController::class, 'store'], 'organizations.store');

    $router->get('/invitations/{token}', [InvitationController::class, 'show'], 'invitations.show');
    $router->post('/invitations/{token}/accept', [InvitationController::class, 'accept'], 'invitations.accept');

    // -----------------------------------------------------------------
    // Organization management
    // -----------------------------------------------------------------
    $router->group('/manage/{org}', static function (Router $router): void {
        $router->get('/', [DashboardController::class, 'index'], 'manage.dashboard');
        $router->get('/dashboard', [DashboardController::class, 'index'], 'manage.dashboard.alt');

        // Fleet
        $router->get('/cars', [CarManagementController::class, 'index'], 'manage.cars');
        $router->get('/cars/create', [CarManagementController::class, 'create'], 'manage.cars.create');
        $router->post('/cars', [CarManagementController::class, 'store'], 'manage.cars.store');
        $router->get('/cars/{id:int}/edit', [CarManagementController::class, 'edit'], 'manage.cars.edit');
        $router->post('/cars/{id:int}', [CarManagementController::class, 'update'], 'manage.cars.update');
        $router->post('/cars/{id:int}/status', [CarManagementController::class, 'updateStatus'], 'manage.cars.status');
        $router->post('/cars/{id:int}/archive', [CarManagementController::class, 'archive'], 'manage.cars.archive');
        $router->post('/cars/{id:int}/restore', [CarManagementController::class, 'restore'], 'manage.cars.restore');
        $router->post('/cars/{id:int}/images', [CarManagementController::class, 'uploadImages'], 'manage.cars.images');
        $router->post('/cars/{id:int}/images/reorder', [CarManagementController::class, 'reorderImages'], 'manage.cars.images.reorder');
        $router->post('/cars/{id:int}/images/{imageId:int}/primary', [CarManagementController::class, 'makeImagePrimary'], 'manage.cars.images.primary');
        $router->post('/cars/{id:int}/images/{imageId:int}/delete', [CarManagementController::class, 'deleteImage'], 'manage.cars.images.delete');

        // Categories
        $router->get('/categories', [CategoryManagementController::class, 'index'], 'manage.categories');
        $router->post('/categories', [CategoryManagementController::class, 'store'], 'manage.categories.store');
        $router->post('/categories/{id:int}', [CategoryManagementController::class, 'update'], 'manage.categories.update');
        $router->post('/categories/{id:int}/delete', [CategoryManagementController::class, 'destroy'], 'manage.categories.delete');

        // Locations
        $router->get('/locations', [LocationManagementController::class, 'index'], 'manage.locations');
        $router->post('/locations', [LocationManagementController::class, 'store'], 'manage.locations.store');
        $router->post('/locations/{id:int}', [LocationManagementController::class, 'update'], 'manage.locations.update');

        // Bookings
        $router->get('/bookings', [BookingManagementController::class, 'index'], 'manage.bookings');
        $router->get('/bookings/{reference}', [BookingManagementController::class, 'show'], 'manage.bookings.show');
        $router->post('/bookings/{reference}/confirm', [BookingManagementController::class, 'confirm'], 'manage.bookings.confirm');
        $router->post('/bookings/{reference}/reject', [BookingManagementController::class, 'reject'], 'manage.bookings.reject');
        $router->post('/bookings/{reference}/cancel', [BookingManagementController::class, 'cancel'], 'manage.bookings.cancel');
        $router->post('/bookings/{reference}/ready', [BookingManagementController::class, 'markReady'], 'manage.bookings.ready');
        $router->post('/bookings/{reference}/no-show', [BookingManagementController::class, 'markNoShow'], 'manage.bookings.noShow');
        $router->post('/bookings/{reference}/payment', [BookingManagementController::class, 'updatePayment'], 'manage.bookings.payment');
        $router->post('/bookings/{reference}/notes', [BookingManagementController::class, 'updateNotes'], 'manage.bookings.notes');
        $router->get('/bookings/{reference}/checkout', [BookingManagementController::class, 'checkoutForm'], 'manage.bookings.checkout');
        $router->post('/bookings/{reference}/checkout', [BookingManagementController::class, 'checkout'], 'manage.bookings.checkout.store');
        $router->get('/bookings/{reference}/return', [BookingManagementController::class, 'returnForm'], 'manage.bookings.return');
        $router->post('/bookings/{reference}/return', [BookingManagementController::class, 'completeReturn'], 'manage.bookings.return.store');

        // Customers
        $router->get('/customers', [CustomerManagementController::class, 'index'], 'manage.customers');
        $router->get('/customers/{id:int}', [CustomerManagementController::class, 'show'], 'manage.customers.show');
        $router->post('/customers/{id:int}/notes', [CustomerManagementController::class, 'updateNotes'], 'manage.customers.notes');

        // Documents
        $router->get('/documents', [DocumentManagementController::class, 'index'], 'manage.documents');
        $router->post('/documents/{id:int}/review', [DocumentManagementController::class, 'review'], 'manage.documents.review');

        // Private inspection photographs
        $router->get('/inspections/{id:int}/file', [InspectionImageController::class, 'stream'], 'manage.inspections.file');

        // Employees (admin only)
        $router->get('/employees', [EmployeeManagementController::class, 'index'], 'manage.employees');
        $router->get('/employees/invite', [EmployeeManagementController::class, 'inviteForm'], 'manage.employees.invite');
        $router->post('/employees/invite', [EmployeeManagementController::class, 'invite'], 'manage.employees.invite.store');
        $router->post('/employees/invitations/{id:int}/revoke', [EmployeeManagementController::class, 'revokeInvitation'], 'manage.employees.invite.revoke');
        $router->get('/employees/{id:int}/permissions', [EmployeeManagementController::class, 'permissionsForm'], 'manage.employees.permissions');
        $router->post('/employees/{id:int}/permissions', [EmployeeManagementController::class, 'updatePermissions'], 'manage.employees.permissions.update');
        $router->post('/employees/{id:int}/remove', [EmployeeManagementController::class, 'remove'], 'manage.employees.remove');

        // Reporting and audit
        $router->get('/reports', [ReportController::class, 'index'], 'manage.reports');
        $router->get('/activity', [ReportController::class, 'activity'], 'manage.activity');

        // Settings (admin only)
        $router->get('/settings', [SettingsController::class, 'edit'], 'manage.settings');
        $router->post('/settings', [SettingsController::class, 'update'], 'manage.settings.update');
    });

    // -----------------------------------------------------------------
    // Development only
    // -----------------------------------------------------------------
    $router->get('/dev/components', [DevController::class, 'components'], 'dev.components');
};
