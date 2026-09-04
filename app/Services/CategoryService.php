<?php

declare(strict_types=1);

namespace Rentivo\Services;

use Rentivo\Repositories\CategoryRepository;
use Rentivo\Security\OrganizationContext;
use Rentivo\Security\Permissions;
use Rentivo\Support\Slug;

/**
 * Organization category management. Categories are data, never a hard-coded
 * enumeration.
 */
final class CategoryService
{
    public function __construct(
        private CategoryRepository $categories,
        private AuditService $audit
    ) {
    }

    /** @throws CategoryException */
    public function create(OrganizationContext $context, string $name): int
    {
        $context->authorize(Permissions::CARS_EDIT);

        $organizationId = $context->organizationId();
        $name = trim($name);

        if ($this->categories->nameExists($organizationId, $name)) {
            throw new CategoryException('A category with that name already exists.');
        }

        $slug = Slug::unique(
            $name,
            fn (string $candidate): bool => $this->categories->slugExists($organizationId, $candidate),
            140
        );

        $id = $this->categories->create($organizationId, $name, $slug);

        $this->audit->record(
            $organizationId,
            $context->userId(),
            AuditService::CATEGORY_CREATED,
            'category',
            $id,
            ['name' => $name]
        );

        return $id;
    }

    /** @throws CategoryException */
    public function update(OrganizationContext $context, int $categoryId, string $name): void
    {
        $context->authorize(Permissions::CARS_EDIT);

        $organizationId = $context->organizationId();
        $name = trim($name);

        $category = $this->categories->findInOrganization($categoryId, $organizationId);

        if ($category === null) {
            throw new CategoryException('Category not found.');
        }

        if ($this->categories->nameExists($organizationId, $name, $categoryId)) {
            throw new CategoryException('A category with that name already exists.');
        }

        $slug = Slug::unique(
            $name,
            fn (string $candidate): bool => $this->categories->slugExists($organizationId, $candidate, $categoryId),
            140
        );

        $this->categories->update($categoryId, $organizationId, $name, $slug);

        $this->audit->record(
            $organizationId,
            $context->userId(),
            AuditService::CATEGORY_UPDATED,
            'category',
            $categoryId,
            ['from' => $category['name'], 'to' => $name]
        );
    }

    /** @throws CategoryException */
    public function delete(OrganizationContext $context, int $categoryId): void
    {
        $context->authorize(Permissions::CARS_EDIT);

        $organizationId = $context->organizationId();
        $category = $this->categories->findInOrganization($categoryId, $organizationId);

        if ($category === null) {
            throw new CategoryException('Category not found.');
        }

        if ($this->categories->delete($categoryId, $organizationId) === 0) {
            throw new CategoryException('This category is still assigned to cars and cannot be removed.');
        }

        $this->audit->record(
            $organizationId,
            $context->userId(),
            AuditService::CATEGORY_DELETED,
            'category',
            $categoryId,
            ['name' => $category['name']]
        );
    }

    public function categories(): CategoryRepository
    {
        return $this->categories;
    }
}
