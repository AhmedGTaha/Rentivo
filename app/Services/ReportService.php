<?php

declare(strict_types=1);

namespace Rentivo\Services;

use Rentivo\Repositories\ReportRepository;
use Rentivo\Security\OrganizationContext;
use Rentivo\Security\Permissions;
use Rentivo\Support\DateTimeHelper;

/**
 * Organization-scoped reporting and dashboard aggregates.
 *
 * Every method takes an OrganizationContext and passes only that
 * organization's id to the repository, so no report can span tenants.
 */
final class ReportService
{
    public function __construct(private ReportRepository $reports)
    {
    }

    /**
     * Dashboard summary.
     *
     * @return array<string,mixed>
     */
    public function dashboard(OrganizationContext $context): array
    {
        $organizationId = $context->organizationId();

        $fleet = $this->reports->fleetStatusDistribution($organizationId);
        $bookings = $this->reports->bookingStatusCounts($organizationId);

        [$monthStart, $monthEnd] = $this->currentMonthBounds();

        return [
            'fleet'             => $fleet,
            'total_cars'        => array_sum($fleet),
            'bookings'          => $bookings,
            'pending_bookings'  => $bookings['pending'],
            'active_rentals'    => $this->reports->countActiveRentals($organizationId),
            'overdue_rentals'   => $this->reports->countOverdueRentals($organizationId),
            'customers'         => $this->reports->countCustomers($organizationId),
            'month_revenue_fils' => $this->reports->revenueForPeriodFils($organizationId, $monthStart, $monthEnd),
        ];
    }

    /**
     * The full reports page.
     *
     * @return array<string,mixed>
     */
    public function organizationReport(OrganizationContext $context, ?string $from = null, ?string $to = null): array
    {
        $context->authorize(Permissions::REPORTS_VIEW);

        $organizationId = $context->organizationId();

        return [
            'bookings'        => $this->reports->bookingStatusCounts($organizationId, $from, $to),
            'revenue_fils'    => $this->reports->revenueFils($organizationId, $from, $to),
            'active_rentals'  => $this->reports->countActiveRentals($organizationId),
            'overdue_rentals' => $this->reports->countOverdueRentals($organizationId),
            'most_rented'     => $this->reports->mostRentedCars($organizationId),
            'by_month'        => $this->reports->bookingsByMonth($organizationId),
            'fleet'           => $this->reports->fleetStatusDistribution($organizationId),
            'total_cars'      => $this->reports->countCars($organizationId),
            'customers'       => $this->reports->countCustomers($organizationId),
        ];
    }

    /** @return array{0:string,1:string} UTC bounds of the current business month. */
    private function currentMonthBounds(): array
    {
        $now = DateTimeHelper::now()->setTimezone(DateTimeHelper::business());

        $start = $now->modify('first day of this month')->setTime(0, 0, 0);
        $end = $start->modify('+1 month');

        return [
            $start->setTimezone(DateTimeHelper::utc())->format(DateTimeHelper::DB_FORMAT),
            $end->setTimezone(DateTimeHelper::utc())->format(DateTimeHelper::DB_FORMAT),
        ];
    }
}
