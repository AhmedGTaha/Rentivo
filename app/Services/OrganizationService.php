<?php

declare(strict_types=1);

namespace Rentivo\Services;

use Rentivo\Database\Connection;
use Rentivo\Repositories\CategoryRepository;
use Rentivo\Repositories\OrganizationRepository;
use Rentivo\Repositories\OrganizationUserRepository;
use Rentivo\Security\OrganizationContext;
use Rentivo\Support\Slug;

/**
 * Organization creation and settings.
 */
final class OrganizationService
{
    /** Starter categories so a new agency can add its first car immediately. */
    private const STARTER_CATEGORIES = ['Economy', 'Sedan', 'SUV', 'Luxury', 'Sports', 'Van'];

    public function __construct(
        private Connection $db,
        private OrganizationRepository $organizations,
        private OrganizationUserRepository $members,
        private CategoryRepository $categories,
        private AuditService $audit,
        private ImageService $images
    ) {
    }

    /**
     * Creates an organization and makes the creator its admin.
     *
     * @param array{
     *     name:string,
     *     contact_email:string,
     *     phone:string,
     *     description?:?string,
     *     address?:?string,
     *     primary_color?:?string,
     *     rental_terms?:?string
     * } $data
     *
     * @return array<string,mixed> The created organization.
     */
    public function create(array $data, int $creatorUserId): array
    {
        $organizationId = $this->db->transaction(function () use ($data, $creatorUserId): int {
            $slug = Slug::unique(
                $data['name'],
                fn (string $candidate): bool => $this->organizations->slugExists($candidate)
            );

            $organizationId = $this->organizations->create([
                'name'          => $data['name'],
                'slug'          => $slug,
                'contact_email' => $data['contact_email'],
                'phone'         => $data['phone'],
                'description'   => $data['description'] ?? null,
                'address'       => $data['address'] ?? null,
                'primary_color' => $data['primary_color'] ?? '#111111',
                'rental_terms'  => $data['rental_terms'] ?? null,
                'is_active'     => 1,
            ]);

            $this->members->create($organizationId, $creatorUserId, OrganizationContext::ROLE_ADMIN);

            foreach (self::STARTER_CATEGORIES as $name) {
                $this->categories->create($organizationId, $name, Slug::make($name));
            }

            return $organizationId;
        });

        $this->audit->record(
            $organizationId,
            $creatorUserId,
            AuditService::ORGANIZATION_CREATED,
            'organization',
            $organizationId,
            ['name' => $data['name']]
        );

        return $this->organizations->find($organizationId) ?? [];
    }

    /**
     * Updates organization settings. Admin-only; the caller has already
     * asserted that through OrganizationContext::authorizeAdmin().
     *
     * @param array<string,mixed> $data
     */
    public function update(OrganizationContext $context, array $data): array
    {
        $organizationId = $context->organizationId();
        $before = $context->organization();

        $this->organizations->update($organizationId, $data);

        $changed = [];
        foreach ($data as $key => $value) {
            if (($before[$key] ?? null) != $value) {
                $changed[] = $key;
            }
        }

        if ($changed !== []) {
            $this->audit->record(
                $organizationId,
                $context->userId(),
                AuditService::ORGANIZATION_UPDATED,
                'organization',
                $organizationId,
                ['changed' => $changed]
            );
        }

        return $this->organizations->find($organizationId) ?? $before;
    }

    /**
     * Replaces the organization logo, removing the previous file.
     *
     * @param array{name:string,type:string,tmp_name:string,error:int,size:int} $file
     *
     * @throws UploadException
     */
    public function updateLogo(OrganizationContext $context, array $file): string
    {
        $organizationId = $context->organizationId();

        $path = $this->images->storeUploadedImage(
            $file,
            FileStorageService::DISK_PUBLIC,
            'organizations/' . $organizationId,
            600
        );

        $previous = $context->organization()['logo_path'] ?? null;

        $this->organizations->update($organizationId, ['logo_path' => $path]);

        if (is_string($previous) && $previous !== '' && $previous !== $path) {
            $this->images->storageService()->delete(FileStorageService::DISK_PUBLIC, $previous);
        }

        $this->audit->record(
            $organizationId,
            $context->userId(),
            AuditService::ORGANIZATION_UPDATED,
            'organization',
            $organizationId,
            ['changed' => ['logo_path']]
        );

        return $path;
    }

    public function organizations(): OrganizationRepository
    {
        return $this->organizations;
    }
}
