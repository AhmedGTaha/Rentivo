<?php

declare(strict_types=1);

namespace Rentivo\Http\Controllers\Manage;

use Rentivo\Http\Controllers\Controller;
use Rentivo\Http\Request;
use Rentivo\Http\Response;
use Rentivo\Security\OrganizationContext;

/**
 * Base for every /manage/{org}/... controller.
 *
 * Management views always receive the resolved OrganizationContext, which is
 * what lets navigation and action buttons reflect real server-side
 * permissions rather than guessing.
 */
abstract class ManageController extends Controller
{
    /**
     * Renders a management page inside the management shell.
     *
     * @param array<string,mixed> $data
     */
    protected function renderManage(
        OrganizationContext $context,
        string $template,
        array $data = []
    ): Response {
        return Response::html($this->view->render($template, $data + [
            'context'      => $context,
            'organization' => $context->organization(),
            'orgSlug'      => $context->slug(),
        ], 'manage'));
    }

    /** Convenience for building management URLs. */
    protected function manageUrl(OrganizationContext $context, string $path = ''): string
    {
        return '/manage/' . $context->slug() . ($path === '' ? '' : '/' . ltrim($path, '/'));
    }

    /** Trimmed search term from the query string. */
    protected function search(Request $request, string $key = 'q', int $maxLength = 120): ?string
    {
        $value = $request->queryParam($key);

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : mb_substr($value, 0, $maxLength);
    }
}
