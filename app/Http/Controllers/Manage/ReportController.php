<?php

declare(strict_types=1);

namespace Rentivo\Http\Controllers\Manage;

use Rentivo\Auth\SessionAuth;
use Rentivo\Components\View;
use Rentivo\Http\Request;
use Rentivo\Http\Response;
use Rentivo\Repositories\ActivityLogRepository;
use Rentivo\Security\Authorization;
use Rentivo\Security\Permissions;
use Rentivo\Services\AuditService;
use Rentivo\Services\ReportService;
use Rentivo\Support\DateTimeHelper;

/**
 * Organization reports and the audit activity feed.
 */
final class ReportController extends ManageController
{
    public function __construct(
        View $view,
        SessionAuth $auth,
        Authorization $authorization,
        private ReportService $reports,
        private ActivityLogRepository $activity
    ) {
        parent::__construct($view, $auth, $authorization);
    }

    /** GET /manage/{org}/reports */
    public function index(Request $request): Response
    {
        $context = $this->organization($request);
        $context->authorize(Permissions::REPORTS_VIEW);

        $from = DateTimeHelper::fromInput((string) $request->queryParam('from', ''));
        $to = DateTimeHelper::fromInput((string) $request->queryParam('to', ''));

        $report = $this->reports->organizationReport(
            $context,
            $from === null ? null : DateTimeHelper::toDb($from),
            $to === null ? null : DateTimeHelper::toDb($to)
        );

        return $this->renderManage($context, 'manage/reports', [
            'title'         => 'Reports',
            'manageSection' => 'reports',
            'report'        => $report,
            'from'          => DateTimeHelper::toInput($from),
            'to'            => DateTimeHelper::toInput($to),
        ]);
    }

    /** GET /manage/{org}/activity */
    public function activity(Request $request): Response
    {
        $context = $this->organization($request);

        // The audit trail is management history; admins and reporting staff
        // are the audience for it.
        $context->authorizeAny([Permissions::REPORTS_VIEW]);

        $filters = [
            'search' => $this->search($request),
            'action' => (string) $request->queryParam('action', ''),
        ];

        $total = $this->activity->countForOrganization($context->organizationId(), $filters);
        $pagination = $this->paginate($request, $total, 25);

        return $this->renderManage($context, 'manage/activity', [
            'title'         => 'Activity',
            'manageSection' => 'activity',
            'entries'       => $this->activity->listForOrganization($context->organizationId(), $filters, $pagination),
            'total'         => $total,
            'pagination'    => $pagination,
            'filters'       => $filters,
            'actions'       => $this->activity->distinctActions($context->organizationId()),
            'labeller'      => static fn (string $key): string => AuditService::label($key),
        ]);
    }
}
