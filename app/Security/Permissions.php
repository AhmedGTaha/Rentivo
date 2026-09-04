<?php

declare(strict_types=1);

namespace Rentivo\Security;

/**
 * The complete, closed set of employee permission keys defined by the SRS.
 *
 * Organization admins are never assigned permission rows; their authority is
 * implicit and evaluated in OrganizationContext.
 */
final class Permissions
{
    public const CARS_VIEW = 'cars.view';
    public const CARS_CREATE = 'cars.create';
    public const CARS_EDIT = 'cars.edit';
    public const CARS_ARCHIVE = 'cars.archive';
    public const CARS_MANAGE_IMAGES = 'cars.manage_images';

    public const BOOKINGS_VIEW = 'bookings.view';
    public const BOOKINGS_CONFIRM = 'bookings.confirm';
    public const BOOKINGS_REJECT = 'bookings.reject';
    public const BOOKINGS_CANCEL = 'bookings.cancel';
    public const BOOKINGS_CHECKOUT = 'bookings.checkout';
    public const BOOKINGS_COMPLETE_RETURN = 'bookings.complete_return';
    public const BOOKINGS_MANAGE_PAYMENT = 'bookings.manage_payment';

    public const CUSTOMERS_VIEW = 'customers.view';
    public const CUSTOMERS_EDIT_NOTES = 'customers.edit_notes';

    public const DOCUMENTS_VIEW = 'documents.view';
    public const DOCUMENTS_VERIFY = 'documents.verify';

    public const LOCATIONS_VIEW = 'locations.view';
    public const LOCATIONS_MANAGE = 'locations.manage';

    public const REPORTS_VIEW = 'reports.view';

    /**
     * Permission definitions grouped for the seeder and the permission matrix
     * UI. Order here is the display order.
     *
     * @return array<string, array{label:string, permissions: array<string,string>}>
     */
    public static function groups(): array
    {
        return [
            'cars' => [
                'label' => 'Fleet',
                'permissions' => [
                    self::CARS_VIEW          => 'View cars',
                    self::CARS_CREATE        => 'Add cars',
                    self::CARS_EDIT          => 'Edit cars',
                    self::CARS_ARCHIVE       => 'Archive cars',
                    self::CARS_MANAGE_IMAGES => 'Manage car images',
                ],
            ],
            'bookings' => [
                'label' => 'Bookings',
                'permissions' => [
                    self::BOOKINGS_VIEW            => 'View bookings',
                    self::BOOKINGS_CONFIRM         => 'Confirm bookings',
                    self::BOOKINGS_REJECT          => 'Reject bookings',
                    self::BOOKINGS_CANCEL          => 'Cancel bookings',
                    self::BOOKINGS_CHECKOUT        => 'Process pickups',
                    self::BOOKINGS_COMPLETE_RETURN => 'Process returns',
                    self::BOOKINGS_MANAGE_PAYMENT  => 'Manage payment status',
                ],
            ],
            'customers' => [
                'label' => 'Customers',
                'permissions' => [
                    self::CUSTOMERS_VIEW       => 'View customers',
                    self::CUSTOMERS_EDIT_NOTES => 'Edit customer notes',
                ],
            ],
            'documents' => [
                'label' => 'Documents',
                'permissions' => [
                    self::DOCUMENTS_VIEW   => 'View customer documents',
                    self::DOCUMENTS_VERIFY => 'Verify or reject documents',
                ],
            ],
            'locations' => [
                'label' => 'Locations',
                'permissions' => [
                    self::LOCATIONS_VIEW   => 'View locations',
                    self::LOCATIONS_MANAGE => 'Manage locations',
                ],
            ],
            'reports' => [
                'label' => 'Reports',
                'permissions' => [
                    self::REPORTS_VIEW => 'View reports',
                ],
            ],
        ];
    }

    /** @return list<string> Every valid permission key. */
    public static function all(): array
    {
        $keys = [];

        foreach (self::groups() as $group) {
            foreach (array_keys($group['permissions']) as $key) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /** @return array<string,string> key => human label */
    public static function labels(): array
    {
        $labels = [];

        foreach (self::groups() as $group) {
            foreach ($group['permissions'] as $key => $label) {
                $labels[$key] = $label;
            }
        }

        return $labels;
    }

    public static function label(string $key): string
    {
        return self::labels()[$key] ?? $key;
    }

    public static function exists(string $key): bool
    {
        return in_array($key, self::all(), true);
    }

    /**
     * Filters arbitrary (request-supplied) keys down to the known set.
     *
     * @param list<string> $keys
     * @return list<string>
     */
    public static function filterValid(array $keys): array
    {
        return array_values(array_unique(array_filter(
            $keys,
            static fn (string $key): bool => self::exists($key)
        )));
    }
}
