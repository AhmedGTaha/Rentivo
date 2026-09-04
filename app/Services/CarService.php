<?php

declare(strict_types=1);

namespace Rentivo\Services;

use Rentivo\Database\Connection;
use Rentivo\Repositories\CarImageRepository;
use Rentivo\Repositories\CarRepository;
use Rentivo\Security\OrganizationContext;
use Rentivo\Security\Permissions;
use Rentivo\Support\Config;
use Rentivo\Support\Slug;

/**
 * Fleet management: cars and their marketing images.
 *
 * Cars are never hard-deleted through the management UI; archival keeps the
 * historical booking references intact.
 */
final class CarService
{
    public function __construct(
        private Connection $db,
        private CarRepository $cars,
        private CarImageRepository $images,
        private ImageService $imageService,
        private AuditService $audit
    ) {
    }

    /** @return list<string> */
    public static function statuses(): array
    {
        return ['available', 'reserved', 'rented', 'maintenance', 'inactive'];
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'available'   => 'Available',
            'reserved'    => 'Reserved',
            'rented'      => 'Rented',
            'maintenance' => 'Maintenance',
            'inactive'    => 'Inactive',
            default       => ucfirst($status),
        };
    }

    public static function statusTone(string $status): string
    {
        return match ($status) {
            'available'   => 'success',
            'reserved'    => 'info',
            'rented'      => 'warning',
            'maintenance' => 'danger',
            default       => 'neutral',
        };
    }

    /** @return array<string,string> */
    public static function transmissions(): array
    {
        return ['automatic' => 'Automatic', 'manual' => 'Manual'];
    }

    /** @return array<string,string> */
    public static function fuelTypes(): array
    {
        return [
            'petrol'   => 'Petrol',
            'diesel'   => 'Diesel',
            'hybrid'   => 'Hybrid',
            'electric' => 'Electric',
        ];
    }

    /**
     * @param array<string,mixed> $data Already validated by the controller.
     *
     * @return array<string,mixed> The created car.
     */
    public function create(OrganizationContext $context, array $data): array
    {
        $context->authorize(Permissions::CARS_CREATE);

        $organizationId = $context->organizationId();

        $data['slug'] = Slug::unique(
            trim($data['brand'] . ' ' . $data['model'] . ' ' . $data['year']),
            fn (string $candidate): bool => $this->cars->slugExists($organizationId, $candidate)
        );

        $carId = $this->cars->create($organizationId, $data);

        $this->audit->record(
            $organizationId,
            $context->userId(),
            AuditService::CAR_CREATED,
            'car',
            $carId,
            ['brand' => $data['brand'], 'model' => $data['model'], 'year' => $data['year']]
        );

        return $this->cars->findInOrganization($carId, $organizationId) ?? [];
    }

    /**
     * @param array<string,mixed> $data
     *
     * @throws CarException
     */
    public function update(OrganizationContext $context, int $carId, array $data): array
    {
        $context->authorize(Permissions::CARS_EDIT);

        $organizationId = $context->organizationId();
        $car = $this->requireCar($context, $carId);

        // Keep the slug aligned with the identity fields when they change.
        if ($car['brand'] !== $data['brand'] || $car['model'] !== $data['model'] || (int) $car['year'] !== (int) $data['year']) {
            $data['slug'] = Slug::unique(
                trim($data['brand'] . ' ' . $data['model'] . ' ' . $data['year']),
                fn (string $candidate): bool => $this->cars->slugExists($organizationId, $candidate, $carId)
            );
        }

        $this->cars->update($carId, $organizationId, $data);

        $changed = [];
        foreach ($data as $key => $value) {
            if (array_key_exists($key, $car) && $car[$key] != $value) {
                $changed[] = $key;
            }
        }

        $this->audit->record(
            $organizationId,
            $context->userId(),
            AuditService::CAR_UPDATED,
            'car',
            $carId,
            ['changed' => $changed]
        );

        return $this->cars->findInOrganization($carId, $organizationId) ?? $car;
    }

    /** @throws CarException */
    public function changeStatus(OrganizationContext $context, int $carId, string $status): array
    {
        $context->authorize(Permissions::CARS_EDIT);

        if (!in_array($status, self::statuses(), true)) {
            throw new CarException('That car status is not valid.');
        }

        $car = $this->requireCar($context, $carId);

        $this->cars->setStatus($carId, $context->organizationId(), $status);

        $this->audit->record(
            $context->organizationId(),
            $context->userId(),
            AuditService::CAR_STATUS_CHANGED,
            'car',
            $carId,
            ['from' => $car['status'], 'to' => $status]
        );

        return $this->cars->findInOrganization($carId, $context->organizationId()) ?? $car;
    }

    /** @throws CarException */
    public function archive(OrganizationContext $context, int $carId): void
    {
        $context->authorize(Permissions::CARS_ARCHIVE);

        $car = $this->requireCar($context, $carId);

        $this->cars->archive($carId, $context->organizationId());

        $this->audit->record(
            $context->organizationId(),
            $context->userId(),
            AuditService::CAR_ARCHIVED,
            'car',
            $carId,
            ['brand' => $car['brand'], 'model' => $car['model']]
        );
    }

    /** @throws CarException */
    public function restore(OrganizationContext $context, int $carId): void
    {
        $context->authorize(Permissions::CARS_ARCHIVE);

        $car = $this->requireCar($context, $carId);

        $this->cars->restore($carId, $context->organizationId());

        $this->audit->record(
            $context->organizationId(),
            $context->userId(),
            AuditService::CAR_RESTORED,
            'car',
            $carId,
            ['brand' => $car['brand'], 'model' => $car['model']]
        );
    }

    // -----------------------------------------------------------------
    // Images
    // -----------------------------------------------------------------

    /**
     * Stores uploaded marketing images for a car.
     *
     * @param list<array{name:string,type:string,tmp_name:string,error:int,size:int}> $files
     *
     * @return array{stored:int,errors:list<string>}
     *
     * @throws CarException
     */
    public function addImages(OrganizationContext $context, int $carId, array $files): array
    {
        $context->authorize(Permissions::CARS_MANAGE_IMAGES);

        $this->requireCar($context, $carId);

        $maximum = (int) Config::get('uploads.max_car_images', 10);
        $existing = $this->images->countForCar($carId);
        $remaining = max(0, $maximum - $existing);

        if ($remaining === 0) {
            throw new CarException('This car already has the maximum of ' . $maximum . ' images.');
        }

        $directory = sprintf('cars/%d/%d', $context->organizationId(), $carId);

        $stored = 0;
        $errors = [];

        foreach (array_slice($files, 0, $remaining) as $file) {
            try {
                $path = $this->imageService->storeUploadedImage(
                    $file,
                    FileStorageService::DISK_PUBLIC,
                    $directory
                );

                // The first image a car ever receives becomes its primary.
                $this->images->create($carId, $path, $existing === 0 && $stored === 0);
                $stored++;
            } catch (UploadException $e) {
                $errors[] = $e->getMessage();
            }
        }

        if (count($files) > $remaining) {
            $errors[] = sprintf('Only %d more image(s) could be added (maximum %d per car).', $remaining, $maximum);
        }

        if ($stored > 0) {
            $this->images->ensurePrimary($carId);

            $this->audit->record(
                $context->organizationId(),
                $context->userId(),
                AuditService::CAR_IMAGES_CHANGED,
                'car',
                $carId,
                ['added' => $stored]
            );
        }

        return ['stored' => $stored, 'errors' => $errors];
    }

    /** @throws CarException */
    public function deleteImage(OrganizationContext $context, int $carId, int $imageId): void
    {
        $context->authorize(Permissions::CARS_MANAGE_IMAGES);

        $this->requireCar($context, $carId);

        $image = $this->images->findForCar($imageId, $carId);

        if ($image === null) {
            throw new CarException('Image not found.');
        }

        $this->images->delete($imageId, $carId);
        // Deleting the primary promotes the next image, or leaves none.
        $this->images->ensurePrimary($carId);

        $this->imageService->storageService()->delete(
            FileStorageService::DISK_PUBLIC,
            (string) $image['file_path']
        );

        $this->audit->record(
            $context->organizationId(),
            $context->userId(),
            AuditService::CAR_IMAGES_CHANGED,
            'car',
            $carId,
            ['deleted' => $imageId]
        );
    }

    /** @throws CarException */
    public function makeImagePrimary(OrganizationContext $context, int $carId, int $imageId): void
    {
        $context->authorize(Permissions::CARS_MANAGE_IMAGES);

        $this->requireCar($context, $carId);

        if ($this->images->findForCar($imageId, $carId) === null) {
            throw new CarException('Image not found.');
        }

        $this->images->makePrimary($imageId, $carId);

        $this->audit->record(
            $context->organizationId(),
            $context->userId(),
            AuditService::CAR_IMAGES_CHANGED,
            'car',
            $carId,
            ['primary' => $imageId]
        );
    }

    /**
     * @param list<int> $imageIds
     * @throws CarException
     */
    public function reorderImages(OrganizationContext $context, int $carId, array $imageIds): void
    {
        $context->authorize(Permissions::CARS_MANAGE_IMAGES);

        $this->requireCar($context, $carId);

        // Only ids that actually belong to this car are honoured.
        $owned = array_map(
            static fn (array $image): int => (int) $image['id'],
            $this->images->listForCar($carId)
        );

        $ordered = array_values(array_filter(
            array_map('intval', $imageIds),
            static fn (int $id): bool => in_array($id, $owned, true)
        ));

        $this->images->reorder($carId, $ordered);
    }

    /**
     * @return array<string,mixed>
     * @throws CarException
     */
    private function requireCar(OrganizationContext $context, int $carId): array
    {
        $car = $this->cars->findInOrganization($carId, $context->organizationId());

        if ($car === null) {
            throw new CarException('Car not found.');
        }

        return $car;
    }

    public function cars(): CarRepository
    {
        return $this->cars;
    }

    public function images(): CarImageRepository
    {
        return $this->images;
    }
}
