<?php

declare(strict_types=1);

namespace Rentivo\Http\Controllers;

use Rentivo\Auth\SessionAuth;
use Rentivo\Components\View;
use Rentivo\Http\Request;
use Rentivo\Http\Response;
use Rentivo\Repositories\NotificationRepository;
use Rentivo\Security\Authorization;
use Rentivo\Support\Flash;

/**
 * In-app notification centre. Every action is scoped to the signed-in user's
 * own notifications.
 */
final class NotificationController extends Controller
{
    public function __construct(
        View $view,
        SessionAuth $auth,
        Authorization $authorization,
        private NotificationRepository $notifications
    ) {
        parent::__construct($view, $auth, $authorization);
    }

    /** GET /account/notifications */
    public function index(Request $request): Response
    {
        $userId = $this->requireUserId();

        $unreadOnly = $request->queryParam('filter') === 'unread';

        $total = $this->notifications->countForUser($userId, $unreadOnly);
        $pagination = $this->paginate($request, $total, 20);

        return $this->render('account/notifications', [
            'title'          => 'Notifications',
            'notifications'  => $this->notifications->listForUser($userId, $pagination, $unreadOnly),
            'total'          => $total,
            'unreadOnly'     => $unreadOnly,
            'unread'         => $this->notifications->unreadCount($userId),
            'pagination'     => $pagination,
            'accountSection' => 'notifications',
        ], 'account');
    }

    /** POST /account/notifications/{id}/read */
    public function markRead(Request $request): Response
    {
        $userId = $this->requireUserId();

        $this->notifications->markRead($request->routeInt('id'), $userId);

        if ($request->expectsJson()) {
            return Response::json(['unread' => $this->notifications->unreadCount($userId)]);
        }

        return $this->back($request, '/account/notifications');
    }

    /** POST /account/notifications/read-all */
    public function markAllRead(Request $request): Response
    {
        $userId = $this->requireUserId();

        $count = $this->notifications->markAllRead($userId);

        if ($request->expectsJson()) {
            return Response::json(['unread' => 0, 'marked' => $count]);
        }

        Flash::success($count === 0 ? 'You have no unread notifications.' : 'All notifications marked as read.');

        return $this->redirect('/account/notifications');
    }
}
