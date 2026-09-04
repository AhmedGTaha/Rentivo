<?php

declare(strict_types=1);

namespace Rentivo\Http\Controllers\Manage;

use Rentivo\Auth\SessionAuth;
use Rentivo\Components\View;
use Rentivo\Http\Request;
use Rentivo\Http\Response;
use Rentivo\Repositories\ActivityLogRepository;
use Rentivo\Repositories\BookingRepository;
use Rentivo\Security\Authorization;
use Rentivo\Services\ReportService;
use Rentivo\Support\DateTimeHelper;

/**
 * The organization dashboard. Every figure is scoped to the current
 * organization through the resolved context.
 */
final class DashboardController extends ManageController
{
    public function __construct(
        View $view,
        SessionAuth $auth,
        Authorization $authorization,
        private ReportService $reports,
        private BookingRepository $bookings,
        private ActivityLogRepository $activity
    ) {
        parent::__construct($view, $auth, $authorization);
    }

    /** GET /manage/{org} and /manage/{org}/dashboard */
    public function index(Request $request): Response
    {
        $context = $this->organization($request);
        $organizationId = $context->organizationId();

        [$dayStart, $dayEnd] = DateTimeHelper::businessDayBounds();

        return $this->renderManage($context, 'manage/dashboard', [
            'title'          => 'Dashboard',
            'manageSection'  => 'dashboard',
            'metrics'        => $this->reports->dashboard($context),
            'todayPickups'   => $this->bookings->pickupsBetween($organizationId, $dayStart, $dayEnd),
            'todayReturns'   => $this->bookings->returnsBetween($organizationId, $dayStart, $dayEnd),
            'overdue'        => $this->bookings->overdueForOrganization($organizationId, 5),
            'recentBookings' => $this->bookings->recentForOrganization($organizationId, 6),
            'recentActivity' => $this->activity->recentForOrganization($organizationId, 8),
        ]);
    }
}
