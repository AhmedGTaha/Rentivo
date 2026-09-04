<?php

declare(strict_types=1);

namespace Rentivo\Http\Controllers;

use Rentivo\Auth\SessionAuth;
use Rentivo\Components\View;
use Rentivo\Http\HttpException;
use Rentivo\Http\Request;
use Rentivo\Http\Response;
use Rentivo\Security\Authorization;
use Rentivo\Security\OrganizationContext;
use Rentivo\Support\Config;
use Rentivo\Support\Flash;
use Rentivo\Support\Pagination;
use Rentivo\Validation\Validator;

/**
 * Shared controller behaviour.
 *
 * Controllers stay thin: they validate input, resolve authorization, call a
 * service, and choose a response. No SQL and no business rules live here.
 */
abstract class Controller
{
    public function __construct(
        protected View $view,
        protected SessionAuth $auth,
        protected Authorization $authorization
    ) {
    }

    // -----------------------------------------------------------------
    // Responses
    // -----------------------------------------------------------------

    /** @param array<string,mixed> $data */
    protected function render(string $template, array $data = [], string $layout = 'public'): Response
    {
        return Response::html($this->view->render($template, $data, $layout));
    }

    protected function redirect(string $location): Response
    {
        return Response::redirect($location);
    }

    protected function back(Request $request, string $fallback = '/'): Response
    {
        $referer = $request->header('Referer');

        if (is_string($referer) && $referer !== '') {
            $appUrl = rtrim((string) Config::get('url', ''), '/');
            $path = parse_url($referer, PHP_URL_PATH);
            $query = parse_url($referer, PHP_URL_QUERY);

            // Only follow a referer that points back into this application.
            $sameHost = $appUrl === '' || str_starts_with($referer, $appUrl . '/') || str_starts_with($referer, '/');

            if ($sameHost && is_string($path) && $path !== '') {
                return Response::redirect($path . ($query === null ? '' : '?' . $query));
            }
        }

        return Response::redirect($fallback);
    }

    /**
     * Redirects back with validation errors and the submitted input, so the
     * form re-renders exactly as the user left it.
     *
     * @param array<string,string> $errors
     * @param array<string,mixed>  $input
     */
    protected function redirectWithErrors(string $location, array $errors, array $input, ?string $message = null): Response
    {
        Flash::withErrors($errors);
        Flash::withInput($input);

        if ($message !== null) {
            Flash::error($message);
        } elseif ($errors !== []) {
            Flash::error('Please correct the highlighted fields.');
        }

        return Response::redirect($location);
    }

    // -----------------------------------------------------------------
    // Authentication and authorization
    // -----------------------------------------------------------------

    /**
     * @return array<string,mixed>
     * @throws HttpException 401, which the kernel converts to a login redirect.
     */
    protected function requireUser(): array
    {
        return $this->auth->requireUser();
    }

    protected function requireUserId(): int
    {
        return (int) $this->requireUser()['id'];
    }

    /**
     * Resolves the organization for a /manage/{org}/... route.
     *
     * @throws HttpException
     */
    protected function organization(Request $request): OrganizationContext
    {
        $slug = $request->route('org');

        if ($slug === null || $slug === '') {
            throw HttpException::notFound();
        }

        return $this->authorization->organizationContext($slug);
    }

    // -----------------------------------------------------------------
    // Input
    // -----------------------------------------------------------------

    /**
     * @param array<string,string> $rules
     * @param array<string,string> $labels
     */
    protected function validate(Request $request, array $rules, array $labels = []): Validator
    {
        return Validator::make($request->all(), $rules, $labels);
    }

    protected function paginate(Request $request, int $total, ?int $perPage = null): Pagination
    {
        return new Pagination(
            max(1, (int) $request->queryParam('page', 1)),
            $perPage ?? (int) Config::get('pagination.default_per_page', 20),
            $total
        );
    }

    /** Nullable trimmed string from request input. */
    protected function nullableString(Request $request, string $key, int $maxLength = 2000): ?string
    {
        $value = $request->input($key);

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : mb_substr($value, 0, $maxLength);
    }
}
