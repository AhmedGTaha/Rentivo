<?php

declare(strict_types=1);

namespace Rentivo\Services;

use Rentivo\Repositories\LocationRepository;
use Rentivo\Security\OrganizationContext;
use Rentivo\Security\Permissions;

/**
 * Organization location management.
 */
final class LocationService
{
    public function __construct(
        private LocationRepository $locations,
        private AuditService $audit
    ) {
    }

    /** @param array<string,mixed> $data */
    public function create(OrganizationContext $context, array $data): int
    {
        $context->authorize(Permissions::LOCATIONS_MANAGE);

        $id = $this->locations->create($context->organizationId(), $data);

        $this->audit->record(
            $context->organizationId(),
            $context->userId(),
            AuditService::LOCATION_CREATED,
            'location',
            $id,
            ['name' => $data['name']]
        );

        return $id;
    }

    /**
     * @param array<string,mixed> $data
     * @throws LocationException
     */
    public function update(OrganizationContext $context, int $locationId, array $data): void
    {
        $context->authorize(Permissions::LOCATIONS_MANAGE);

        $location = $this->locations->findInOrganization($locationId, $context->organizationId());

        if ($location === null) {
            throw new LocationException('Location not found.');
        }

        $this->locations->update($locationId, $context->organizationId(), $data);

        $this->audit->record(
            $context->organizationId(),
            $context->userId(),
            AuditService::LOCATION_UPDATED,
            'location',
            $locationId,
            ['name' => $data['name'] ?? $location['name']]
        );
    }

    public function locations(): LocationRepository
    {
        return $this->locations;
    }
}
