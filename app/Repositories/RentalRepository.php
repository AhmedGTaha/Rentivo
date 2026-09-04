<?php

declare(strict_types=1);

namespace Rentivo\Repositories;

/**
 * Rental records and their private inspection photographs.
 */
final class RentalRepository extends Repository
{
    /** @return array<string,mixed>|null */
    public function findByBooking(int $bookingId, int $organizationId): ?array
    {
        return $this->db->selectOne(
            'SELECT r.*,
                    co.`name` AS `checkout_employee_name`,
                    ro.`name` AS `return_employee_name`
             FROM `rentals` r
             LEFT JOIN `users` co ON co.`id` = r.`checkout_employee_user_id`
             LEFT JOIN `users` ro ON ro.`id` = r.`return_employee_user_id`
             WHERE r.`booking_id` = ? AND r.`organization_id` = ?',
            [$bookingId, $organizationId]
        );
    }

    /** @return array<string,mixed>|null */
    public function findInOrganization(int $rentalId, int $organizationId): ?array
    {
        return $this->db->selectOne(
            'SELECT * FROM `rentals` WHERE `id` = ? AND `organization_id` = ?',
            [$rentalId, $organizationId]
        );
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): int
    {
        $now = $this->now();

        return $this->db->insert('rentals', $data + [
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @param array<string,mixed> $data */
    public function update(int $rentalId, int $organizationId, array $data): int
    {
        $data['updated_at'] = $this->now();

        return $this->db->update('rentals', $data, [
            'id'              => $rentalId,
            'organization_id' => $organizationId,
        ]);
    }

    public function addInspectionImage(int $rentalId, string $phase, string $filePath): int
    {
        return $this->db->insert('rental_inspection_images', [
            'rental_id'  => $rentalId,
            'phase'      => $phase,
            'file_path'  => $filePath,
            'created_at' => $this->now(),
        ]);
    }

    /** @return list<array<string,mixed>> */
    public function inspectionImages(int $rentalId): array
    {
        return $this->db->select(
            'SELECT * FROM `rental_inspection_images` WHERE `rental_id` = ? ORDER BY `phase`, `id`',
            [$rentalId]
        );
    }

    /**
     * Tenant-scoped inspection image lookup used by the private file route.
     *
     * @return array<string,mixed>|null
     */
    public function findInspectionImage(int $imageId, int $organizationId): ?array
    {
        return $this->db->selectOne(
            'SELECT rii.*, r.`organization_id`
             FROM `rental_inspection_images` rii
             INNER JOIN `rentals` r ON r.`id` = rii.`rental_id`
             WHERE rii.`id` = ? AND r.`organization_id` = ?',
            [$imageId, $organizationId]
        );
    }

    public function countActive(int $organizationId): int
    {
        return (int) $this->db->scalar(
            'SELECT COUNT(*) FROM `rentals` WHERE `organization_id` = ? AND `actual_return_at` IS NULL',
            [$organizationId]
        );
    }
}
