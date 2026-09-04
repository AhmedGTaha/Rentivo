<?php

declare(strict_types=1);

namespace Rentivo\Http;

use Rentivo\Application;
use Rentivo\Auth\SessionAuth;
use Rentivo\Repositories\NotificationRepository;
use Rentivo\Security\Authorization;
use Rentivo\Security\Csrf;
use Rentivo\Services\AuditService;
use Rentivo\Support\Config;
use Rentivo\Support\Flash;
use Rentivo\Support\Logger;
use Rentivo\Support\Session;
use Throwable;

/**
 * Turns a Request into a Response.
 *
 * The kernel owns the concerns that must never be re-implemented per page:
 * session startup, CSRF validation for state-changing requests, view state
 * shared with every template, and error rendering that keeps technical detail
 * out of production responses.
 */
final class Kernel
{
    public function __construct(private Application $app)
    {
    }

    public function handle(Request $request): Response
    {
        Session::start($request->isSecure() || Config::isProduction());

        try {
            $this->shareViewState($request);

            // One centralized CSRF gate covers every state-changing route.
            if ($request->isStateChanging() && !$this->csrfPasses($request)) {
                throw HttpException::forbidden('Your session expired or the form was invalid. Please try again.');
            }

            $route = $this->app->router()->match($request);
            $request->setRouteParameters($route['parameters']);

            /** @var AuditService $audit */
            $audit = $this->app->get(AuditService::class);
            $audit->setIpAddress($request->ip());

            return $this->callHandler($route['handler'], $request);
        } catch (HttpException $e) {
            return $this->renderHttpException($request, $e);
        } catch (Throwable $e) {
            Logger::exception($e, [
                'path'   => $request->path(),
                'method' => $request->method(),
            ]);

            return $this->renderServerError($request, $e);
        }
    }

    private function csrfPasses(Request $request): bool
    {
        $token = $request->input(Csrf::FIELD);

        if (!is_string($token) || $token === '') {
            $token = $request->header(Csrf::HEADER);
        }

        return Csrf::isValid(is_string($token) ? $token : null);
    }

    /**
     * @param mixed $handler Closure or [class-string, method]
     */
    private function callHandler(mixed $handler, Request $request): Response
    {
        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;

            $controller = $this->app->get($class);
            $result = $controller->{$method}($request);
        } elseif (is_callable($handler)) {
            $result = $handler($request, $this->app);
        } else {
            throw new \RuntimeException('Route handler is not callable.');
        }

        if ($result instanceof Response) {
            return $result;
        }

        if (is_string($result)) {
            return Response::html($result);
        }

        return Response::noContent();
    }

    /**
     * Data every template can rely on: the current user, flash messages,
     * validation errors, repopulated input and navigation state.
     */
    private function shareViewState(Request $request): void
    {
        /** @var SessionAuth $auth */
        $auth = $this->app->get(SessionAuth::class);
        $user = $auth->user();

        /** @var Authorization $authorization */
        $authorization = $this->app->get(Authorization::class);

        $unread = 0;

        if ($user !== null) {
            /** @var NotificationRepository $notifications */
            $notifications = $this->app->get(NotificationRepository::class);
            $unread = $notifications->unreadCount((int) $user['id']);
        }

        $view = $this->app->view();
        $view->shareMany([
            'currentUser'      => $user,
            'isAuthenticated'  => $user !== null,
            'memberships'      => $user === null ? [] : $authorization->memberships(),
            'unreadCount'      => $unread,
            'flashMessages'    => Flash::pull(),
            'errors'           => Flash::pullErrors(),
            'old'              => Flash::pullOld(),
            'currentPath'      => $request->path(),
            'currentFullPath'  => $request->fullPath(),
            'csrfToken'        => Csrf::token(),
            'isLocal'          => Config::isLocal(),
        ]);
    }

    private function renderHttpException(Request $request, HttpException $e): Response
    {
        $status = $e->status();

        // Send guests to sign in, preserving where they were heading.
        if ($status === 401 && !$request->expectsJson()) {
            /** @var SessionAuth $auth */
            $auth = $this->app->get(SessionAuth::class);
            $auth->setIntendedUrl($request->method() === 'GET' ? $request->fullPath() : '/account');

            Flash::info('Please sign in to continue.');

            return Response::redirect('/login');
        }

        if ($request->expectsJson()) {
            return Response::json(['error' => $e->getMessage()], $status);
        }

        return Response::html($this->renderErrorPage($status, $e->getMessage()), $status);
    }

    private function renderServerError(Request $request, Throwable $e): Response
    {
        // Technical detail is only ever shown when APP_DEBUG is on; production
        // sees a clean message and the detail goes to storage/logs.
        $message = Config::isDebug()
            ? $e::class . ': ' . $e->getMessage()
            : 'Something went wrong on our side. The issue has been logged.';

        if ($request->expectsJson()) {
            return Response::json(['error' => $message], 500);
        }

        return Response::html($this->renderErrorPage(500, $message, Config::isDebug() ? $e : null), 500);
    }

    private function renderErrorPage(int $status, string $message, ?Throwable $exception = null): string
    {
        try {
            return $this->app->view()->render('errors/error', [
                'status'    => $status,
                'message'   => $message,
                'exception' => $exception,
                'title'     => $status . ' — ' . HttpException::defaultMessage($status),
            ], 'public');
        } catch (Throwable $renderFailure) {
            Logger::exception($renderFailure, ['context' => 'error_page_render']);

            // Last-resort plain response if even the error view fails.
            return '<!doctype html><meta charset="utf-8"><title>' . $status . '</title>'
                . '<body style="font-family:system-ui;padding:48px;max-width:640px;margin:0 auto;">'
                . '<h1>' . $status . '</h1><p>'
                . htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
                . '</p><p><a href="/">Return home</a></p></body>';
        }
    }

    /** Exposed so tests can drive the kernel without a web server. */
    public function application(): Application
    {
        return $this->app;
    }
}
