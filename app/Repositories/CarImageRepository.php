<?php

declare(strict_types=1);

namespace Rentivo\Repositories;

/**
 * Marketing images for cars.
 *
 * Car images are always addressed through their parent car id, which
 * management code has already resolved with a tenant-scoped query.
 */
final class CarImageRepository extends Repository
{
    /** @return list<array<string,mixed>> */
    public function listForCar(int $carId): array
    {
        return $this->db->select(
            'SELECT * FROM `car_images`
             WHERE `car_id` = ?
             ORDER BY `is_primary` DESC, `display_order` ASC, `id` ASC',
            [$carId]
        );
    }

    /** @return array<string,mixed>|null */
    public function findForCar(int $imageId, int $carId): ?array
    {
        return $this->db->selectOne(
            'SELECT * FROM `car_images` WHERE `id` = ? AND `car_id` = ?',
            [$imageId, $carId]
        );
    }

    public function countForCar(int $carId): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM `car_images` WHERE `car_id` = ?', [$carId]);
    }

    public function create(int $carId, string $filePath, bool $isPrimary = false): int
    {
        $order = (int) $this->db->scalar(
            'SELECT COALESCE(MAX(`display_order`), 0) + 1 FROM `car_images` WHERE `car_id` = ?',
            [$carId]
        );

        return $this->db->insert('car_images', [
            'car_id'        => $carId,
            'file_path'     => $filePath,
            'display_order' => $order,
            'is_primary'    => $isPrimary ? 1 : 0,
            'created_at'    => $this->now(),
        ]);
    }

    /**
     * Makes one image primary and clears the flag on every sibling, so a car
     * always has exactly zero or one primary image.
     */
    public function makePrimary(int $imageId, int $carId): void
    {
        $this->db->affectingStatement(
            'UPDATE `car_images` SET `is_primary` = 0 WHERE `car_id` = ?',
            [$carId]
        );

        $this->db->affectingStatement(
            'UPDATE `car_images` SET `is_primary` = 1 WHERE `id` = ? AND `car_id` = ?',
            [$imageId, $carId]
        );
    }

    public function delete(int $imageId, int $carId): int
    {
        return $this->db->affectingStatement(
            'DELETE FROM `car_images` WHERE `id` = ? AND `car_id` = ?',
            [$imageId, $carId]
        );
    }

    /**
     * Picks a replacement primary after the current one is deleted. Leaves the
     * car with no primary image when no images remain.
     */
    public function ensurePrimary(int $carId): void
    {
        $hasPrimary = (int) $this->db->scalar(
            'SELECT COUNT(*) FROM `car_images` WHERE `car_id` = ? AND `is_primary` = 1',
            [$carId]
        );

        if ($hasPrimary > 0) {
            return;
        }

        $next = $this->db->selectOne(
            'SELECT `id` FROM `car_images` WHERE `car_id` = ? ORDER BY `display_order` ASC, `id` ASC LIMIT 1',
            [$carId]
        );

        if ($next !== null) {
            $this->makePrimary((int) $next['id'], $carId);
        }
    }

    /**
     * Applies an explicit display order supplied by the management UI.
     *
     * @param list<int> $orderedImageIds
     */
    public function reorder(int $carId, array $orderedImageIds): void
    {
        $position = 1;

        foreach ($orderedImageIds as $imageId) {
            $this->db->affectingStatement(
                'UPDATE `car_images` SET `display_order` = ? WHERE `id` = ? AND `car_id` = ?',
                [$position, (int) $imageId, $carId]
            );
            $position++;
        }
    }

    /** @return list<string> File paths, used when purging a car's storage. */
    public function pathsForCar(int $carId): array
    {
        $rows = $this->db->select('SELECT `file_path` FROM `car_images` WHERE `car_id` = ?', [$carId]);

        return array_map(static fn (array $row): string => (string) $row['file_path'], $rows);
    }
}
