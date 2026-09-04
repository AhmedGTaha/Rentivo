<?php

declare(strict_types=1);

namespace Rentivo\Http\Controllers\Manage;

use Rentivo\Auth\SessionAuth;
use Rentivo\Components\View;
use Rentivo\Http\Request;
use Rentivo\Http\Response;
use Rentivo\Repositories\CategoryRepository;
use Rentivo\Security\Authorization;
use Rentivo\Security\Permissions;
use Rentivo\Services\CategoryException;
use Rentivo\Services\CategoryService;
use Rentivo\Support\Flash;

/**
 * Organization category management. Categories are database rows, never a
 * hard-coded list.
 */
final class CategoryManagementController extends ManageController
{
    public function __construct(
        View $view,
        SessionAuth $auth,
        Authorization $authorization,
        private CategoryService $categories,
        private CategoryRepository $repository
    ) {
        parent::__construct($view, $auth, $authorization);
    }

    /** GET /manage/{org}/categories */
    public function index(Request $request): Response
    {
        $context = $this->organization($request);
        $context->authorize(Permissions::CARS_VIEW);

        $editId = (int) $request->queryParam('edit', 0);

        return $this->renderManage($context, 'manage/categories', [
            'title'         => 'Categories',
            'manageSection' => 'categories',
            'categories'    => $this->repository->listForOrganization($context->organizationId()),
            'editing'       => $editId > 0
                ? $this->repository->findInOrganization($editId, $context->organizationId())
                : null,
        ]);
    }

    /** POST /manage/{org}/categories */
    public function store(Request $request): Response
    {
        $context = $this->organization($request);

        $validator = $this->validate($request, ['name' => 'required|max:120|min:2']);

        if ($validator->fails()) {
            return $this->redirectWithErrors(
                $this->manageUrl($context, 'categories'),
                $validator->errors(),
                $request->body()
            );
        }

        try {
            $this->categories->create($context, (string) $validator->value('name'));

            Flash::success('Category added.');
        } catch (CategoryException $e) {
            Flash::error($e->getMessage());
        }

        return $this->redirect($this->manageUrl($context, 'categories'));
    }

    /** POST /manage/{org}/categories/{id} */
    public function update(Request $request): Response
    {
        $context = $this->organization($request);

        $validator = $this->validate($request, ['name' => 'required|max:120|min:2']);
        $categoryId = $request->routeInt('id');

        if ($validator->fails()) {
            return $this->redirectWithErrors(
                $this->manageUrl($context, 'categories?edit=' . $categoryId),
                $validator->errors(),
                $request->body()
            );
        }

        try {
            $this->categories->update($context, $categoryId, (string) $validator->value('name'));

            Flash::success('Category updated.');
        } catch (CategoryException $e) {
            Flash::error($e->getMessage());
        }

        return $this->redirect($this->manageUrl($context, 'categories'));
    }

    /** POST /manage/{org}/categories/{id}/delete */
    public function destroy(Request $request): Response
    {
        $context = $this->organization($request);

        try {
            $this->categories->delete($context, $request->routeInt('id'));

            Flash::success('Category removed.');
        } catch (CategoryException $e) {
            Flash::error($e->getMessage());
        }

        return $this->redirect($this->manageUrl($context, 'categories'));
    }
}
