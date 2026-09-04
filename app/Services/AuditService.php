<?php

declare(strict_types=1);

namespace Rentivo\Services;

use Rentivo\Repositories\ActivityLogRepository;

/**
 * Records material organization actions to the immutable audit trail.
 *
 * Action keys are declared as constants so the same string is never spelled
 * two different ways across the codebase.
 */
final class AuditService
{
    public const ORGANIZATION_CREATED = 'organization.created';
    public const ORGANIZATION_UPDATED = 'organization.updated';

    public const EMPLOYEE_INVITED = 'employee.invited';
    public const EMPLOYEE_INVITE_REVOKED = 'employee.invite_revoked';
    public const EMPLOYEE_JOINED = 'employee.joined';
    public const EMPLOYEE_REMOVED = 'employee.removed';
    public const EMPLOYEE_PERMISSIONS_CHANGED = 'employee.permissions_changed';

    public const LOCATION_CREATED = 'location.created';
    public const LOCATION_UPDATED = 'location.updated';

    public const CATEGORY_CREATED = 'category.created';
    public const CATEGORY_UPDATED = 'category.updated';
    public const CATEGORY_DELETED = 'category.deleted';

    public const CAR_CREATED = 'car.created';
    public const CAR_UPDATED = 'car.updated';
    public const CAR_ARCHIVED = 'car.archived';
    public const CAR_RESTORED = 'car.restored';
    public const CAR_STATUS_CHANGED = 'car.status_changed';
    public const CAR_IMAGES_CHANGED = 'car.images_changed';

    public const BOOKING_CREATED = 'booking.created';
    public const BOOKING_CONFIRMED = 'booking.confirmed';
    public const BOOKING_REJECTED = 'booking.rejected';
    public const BOOKING_CANCELLED = 'booking.cancelled';
    public const BOOKING_READY_FOR_PICKUP = 'booking.ready_for_pickup';
    public const BOOKING_NO_SHOW = 'booking.no_show';
    public const BOOKING_NOTES_UPDATED = 'booking.notes_updated';
    public const PAYMENT_STATUS_CHANGED = 'booking.payment_status_changed';

    public const CHECKOUT_COMPLETED = 'rental.checkout_completed';
    public const RETURN_COMPLETED = 'rental.return_completed';

    public const DOCUMENT_VERIFIED = 'document.verified';
    public const DOCUMENT_REJECTED = 'document.rejected';

    public const CUSTOMER_NOTES_UPDATED = 'customer.notes_updated';

    private ?string $ipAddress = null;

    public function __construct(private ActivityLogRepository $logs)
    {
    }

    /** Set once per request by the kernel so every entry carries the client IP. */
    public function setIpAddress(?string $ipAddress): void
    {
        $this->ipAddress = $ipAddress;
    }

    /**
     * @param array<string,mixed>|null $metadata
     */
    public function record(
        ?int $organizationId,
        ?int $actorUserId,
        string $actionKey,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $metadata = null
    ): int {
        return $this->logs->record(
            $organizationId,
            $actorUserId,
            $actionKey,
            $entityType,
            $entityId,
            $metadata,
            $this->ipAddress
        );
    }

    /**
     * Human label for an action key, used by the activity feed.
     */
    public static function label(string $actionKey): string
    {
        return match ($actionKey) {
            self::ORGANIZATION_CREATED          => 'Organization created',
            self::ORGANIZATION_UPDATED          => 'Organization settings updated',
            self::EMPLOYEE_INVITED              => 'Employee invited',
            self::EMPLOYEE_INVITE_REVOKED       => 'Invitation revoked',
            self::EMPLOYEE_JOINED               => 'Employee joined',
            self::EMPLOYEE_REMOVED              => 'Employee removed',
            self::EMPLOYEE_PERMISSIONS_CHANGED  => 'Employee permissions changed',
            self::LOCATION_CREATED              => 'Location added',
            self::LOCATION_UPDATED              => 'Location updated',
            self::CATEGORY_CREATED              => 'Category added',
            self::CATEGORY_UPDATED              => 'Category updated',
            self::CATEGORY_DELETED              => 'Category removed',
            self::CAR_CREATED                   => 'Car added',
            self::CAR_UPDATED                   => 'Car updated',
            self::CAR_ARCHIVED                  => 'Car archived',
            self::CAR_RESTORED                  => 'Car restored',
            self::CAR_STATUS_CHANGED            => 'Car status changed',
            self::CAR_IMAGES_CHANGED            => 'Car images updated',
            self::BOOKING_CREATED               => 'Booking submitted',
            self::BOOKING_CONFIRMED             => 'Booking confirmed',
            self::BOOKING_REJECTED              => 'Booking rejected',
            self::BOOKING_CANCELLED             => 'Booking cancelled',
            self::BOOKING_READY_FOR_PICKUP      => 'Booking marked ready for pickup',
            self::BOOKING_NO_SHOW               => 'Booking marked no-show',
            self::BOOKING_NOTES_UPDATED         => 'Booking notes updated',
            self::PAYMENT_STATUS_CHANGED        => 'Payment status changed',
            self::CHECKOUT_COMPLETED            => 'Pickup completed',
            self::RETURN_COMPLETED              => 'Return completed',
            self::DOCUMENT_VERIFIED             => 'Document verified',
            self::DOCUMENT_REJECTED             => 'Document rejected',
            self::CUSTOMER_NOTES_UPDATED        => 'Customer notes updated',
            default                             => ucfirst(str_replace(['.', '_'], ' ', $actionKey)),
        };
    }
}
