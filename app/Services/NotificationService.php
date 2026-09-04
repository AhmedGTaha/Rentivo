<?php

declare(strict_types=1);

namespace Rentivo\Services;

use Rentivo\Repositories\NotificationRepository;
use Rentivo\Repositories\UserRepository;
use Rentivo\Support\Config;

/**
 * Creates in-app notifications and, where appropriate, mirrors them by email.
 *
 * Every notification type declared by the SRS is produced from one of the
 * named methods here, so wording and dedupe rules live in a single place.
 */
final class NotificationService
{
    public const BOOKING_SUBMITTED = 'booking_submitted';
    public const BOOKING_CONFIRMED = 'booking_confirmed';
    public const BOOKING_REJECTED = 'booking_rejected';
    public const BOOKING_CANCELLED = 'booking_cancelled';
    public const PICKUP_REMINDER = 'pickup_reminder';
    public const RETURN_REMINDER = 'return_reminder';
    public const RENTAL_OVERDUE = 'rental_overdue';
    public const DOCUMENT_VERIFIED = 'document_verified';
    public const DOCUMENT_REJECTED = 'document_rejected';
    public const EMPLOYEE_INVITATION = 'employee_invitation';

    public function __construct(
        private NotificationRepository $notifications,
        private MailService $mail,
        private UserRepository $users
    ) {
    }

    /**
     * Core notification creation.
     *
     * @param array{
     *     organization_id?:?int,
     *     related_entity_type?:?string,
     *     related_entity_id?:?int,
     *     action_url?:?string,
     *     dedupe_key?:?string,
     *     email?:bool
     * } $options
     *
     * @return int Row id, or 0 when suppressed by the dedupe key.
     */
    public function notify(
        int $userId,
        string $type,
        string $title,
        string $message,
        array $options = []
    ): int {
        $id = $this->notifications->create([
            'user_id'             => $userId,
            'organization_id'     => $options['organization_id'] ?? null,
            'type'                => $type,
            'title'               => $title,
            'message'             => $message,
            'related_entity_type' => $options['related_entity_type'] ?? null,
            'related_entity_id'   => $options['related_entity_id'] ?? null,
            'action_url'          => $options['action_url'] ?? null,
            'dedupe_key'          => $options['dedupe_key'] ?? null,
        ]);

        // A duplicate dedupe key returns 0; do not email a suppressed reminder.
        if ($id > 0 && ($options['email'] ?? false)) {
            $this->email($userId, $title, $message, $options['action_url'] ?? null);
        }

        return $id;
    }

    /** @param list<int> $userIds */
    public function notifyMany(array $userIds, string $type, string $title, string $message, array $options = []): void
    {
        foreach (array_unique($userIds) as $userId) {
            $perUser = $options;

            // Dedupe keys must stay unique per recipient.
            if (isset($perUser['dedupe_key'])) {
                $perUser['dedupe_key'] .= ':u' . $userId;
            }

            $this->notify($userId, $type, $title, $message, $perUser);
        }
    }

    private function email(int $userId, string $title, string $message, ?string $actionUrl): void
    {
        $user = $this->users->find($userId);

        if ($user === null) {
            return;
        }

        $action = $actionUrl === null
            ? null
            : ['label' => 'Open in Rentivo', 'url' => rtrim((string) Config::get('url', ''), '/') . $actionUrl];

        $this->mail->send(
            (string) $user['email'],
            (string) $user['name'],
            $title,
            $this->mail->layout($title, '<p>' . $this->mail->text($message) . '</p>', $action)
        );
    }

    // -----------------------------------------------------------------
    // Booking lifecycle
    // -----------------------------------------------------------------

    /** @param array<string,mixed> $booking */
    public function bookingSubmitted(array $booking, string $organizationName): void
    {
        $this->notify(
            (int) $booking['user_id'],
            self::BOOKING_SUBMITTED,
            'Booking request submitted',
            sprintf(
                'Your booking %s with %s has been submitted and is awaiting confirmation.',
                $booking['reference'],
                $organizationName
            ),
            [
                'organization_id'     => (int) $booking['organization_id'],
                'related_entity_type' => 'booking',
                'related_entity_id'   => (int) $booking['id'],
                'action_url'          => '/account/bookings/' . $booking['reference'],
                'email'               => true,
            ]
        );
    }

    /**
     * Alerts organization staff that a new request needs a decision.
     *
     * @param list<int> $staffUserIds
     * @param array<string,mixed> $booking
     */
    public function bookingAwaitingReview(array $staffUserIds, array $booking, string $customerName): void
    {
        $this->notifyMany(
            $staffUserIds,
            self::BOOKING_SUBMITTED,
            'New booking request',
            sprintf('%s requested %s. Review and confirm or reject it.', $customerName, $booking['reference']),
            [
                'organization_id'     => (int) $booking['organization_id'],
                'related_entity_type' => 'booking',
                'related_entity_id'   => (int) $booking['id'],
                'dedupe_key'          => 'booking_review:' . $booking['id'],
            ]
        );
    }

    /** @param array<string,mixed> $booking */
    public function bookingConfirmed(array $booking, string $organizationName): void
    {
        $this->notify(
            (int) $booking['user_id'],
            self::BOOKING_CONFIRMED,
            'Booking confirmed',
            sprintf('%s has confirmed your booking %s.', $organizationName, $booking['reference']),
            [
                'organization_id'     => (int) $booking['organization_id'],
                'related_entity_type' => 'booking',
                'related_entity_id'   => (int) $booking['id'],
                'action_url'          => '/account/bookings/' . $booking['reference'],
                'email'               => true,
            ]
        );
    }

    /** @param array<string,mixed> $booking */
    public function bookingRejected(array $booking, string $organizationName, ?string $reason): void
    {
        $message = sprintf('%s could not accept booking %s.', $organizationName, $booking['reference']);

        if ($reason !== null && trim($reason) !== '') {
            $message .= ' Reason: ' . trim($reason);
        }

        $this->notify(
            (int) $booking['user_id'],
            self::BOOKING_REJECTED,
            'Booking not accepted',
            $message,
            [
                'organization_id'     => (int) $booking['organization_id'],
                'related_entity_type' => 'booking',
                'related_entity_id'   => (int) $booking['id'],
                'action_url'          => '/account/bookings/' . $booking['reference'],
                'email'               => true,
            ]
        );
    }

    /** @param array<string,mixed> $booking */
    public function bookingCancelled(array $booking, string $organizationName, bool $byCustomer, ?string $reason): void
    {
        $message = $byCustomer
            ? sprintf('Your booking %s with %s has been cancelled.', $booking['reference'], $organizationName)
            : sprintf('%s cancelled booking %s.', $organizationName, $booking['reference']);

        if ($reason !== null && trim($reason) !== '') {
            $message .= ' Reason: ' . trim($reason);
        }

        $this->notify(
            (int) $booking['user_id'],
            self::BOOKING_CANCELLED,
            'Booking cancelled',
            $message,
            [
                'organization_id'     => (int) $booking['organization_id'],
                'related_entity_type' => 'booking',
                'related_entity_id'   => (int) $booking['id'],
                'action_url'          => '/account/bookings/' . $booking['reference'],
                'email'               => true,
            ]
        );
    }

    // -----------------------------------------------------------------
    // Scheduler reminders (idempotent through dedupe keys)
    // -----------------------------------------------------------------

    /** @param array<string,mixed> $booking */
    public function pickupReminder(array $booking): int
    {
        return $this->notify(
            (int) $booking['user_id'],
            self::PICKUP_REMINDER,
            'Pickup coming up',
            sprintf(
                'Your %s %s from %s is ready for pickup soon (booking %s).',
                $booking['brand'],
                $booking['model'],
                $booking['organization_name'],
                $booking['reference']
            ),
            [
                'organization_id'     => (int) $booking['organization_id'],
                'related_entity_type' => 'booking',
                'related_entity_id'   => (int) $booking['id'],
                'action_url'          => '/account/bookings/' . $booking['reference'],
                'dedupe_key'          => 'pickup_reminder:' . $booking['id'],
                'email'               => true,
            ]
        );
    }

    /** @param array<string,mixed> $booking */
    public function returnReminder(array $booking): int
    {
        return $this->notify(
            (int) $booking['user_id'],
            self::RETURN_REMINDER,
            'Return coming up',
            sprintf(
                'Booking %s is due back at %s soon.',
                $booking['reference'],
                $booking['organization_name']
            ),
            [
                'organization_id'     => (int) $booking['organization_id'],
                'related_entity_type' => 'booking',
                'related_entity_id'   => (int) $booking['id'],
                'action_url'          => '/account/bookings/' . $booking['reference'],
                'dedupe_key'          => 'return_reminder:' . $booking['id'],
                'email'               => true,
            ]
        );
    }

    /**
     * Overdue alert for the customer and, separately, for organization staff.
     *
     * @param array<string,mixed> $booking
     * @param list<int> $staffUserIds
     */
    public function rentalOverdue(array $booking, array $staffUserIds = []): int
    {
        $created = $this->notify(
            (int) $booking['user_id'],
            self::RENTAL_OVERDUE,
            'Rental overdue',
            sprintf(
                'Booking %s was due back on %s. Please contact %s.',
                $booking['reference'],
                date_display((string) $booking['return_at']),
                $booking['organization_name']
            ),
            [
                'organization_id'     => (int) $booking['organization_id'],
                'related_entity_type' => 'booking',
                'related_entity_id'   => (int) $booking['id'],
                'action_url'          => '/account/bookings/' . $booking['reference'],
                'dedupe_key'          => 'overdue:' . $booking['id'],
                'email'               => true,
            ]
        );

        if ($staffUserIds !== []) {
            $this->notifyMany(
                $staffUserIds,
                self::RENTAL_OVERDUE,
                'Rental overdue',
                sprintf('Booking %s is overdue.', $booking['reference']),
                [
                    'organization_id'     => (int) $booking['organization_id'],
                    'related_entity_type' => 'booking',
                    'related_entity_id'   => (int) $booking['id'],
                    'dedupe_key'          => 'overdue_staff:' . $booking['id'],
                ]
            );
        }

        return $created;
    }

    // -----------------------------------------------------------------
    // Documents and invitations
    // -----------------------------------------------------------------

    public function documentVerified(int $userId, int $organizationId, string $organizationName, string $type): void
    {
        $this->notify(
            $userId,
            self::DOCUMENT_VERIFIED,
            'Document verified',
            sprintf('%s verified your %s.', $organizationName, str_replace('_', ' ', $type)),
            [
                'organization_id' => $organizationId,
                'action_url'      => '/account/documents',
                'email'           => true,
            ]
        );
    }

    public function documentRejected(
        int $userId,
        int $organizationId,
        string $organizationName,
        string $type,
        ?string $reason
    ): void {
        $message = sprintf('%s could not verify your %s.', $organizationName, str_replace('_', ' ', $type));

        if ($reason !== null && trim($reason) !== '') {
            $message .= ' Reason: ' . trim($reason);
        }

        $this->notify(
            $userId,
            self::DOCUMENT_REJECTED,
            'Document needs attention',
            $message,
            [
                'organization_id' => $organizationId,
                'action_url'      => '/account/documents',
                'email'           => true,
            ]
        );
    }

    public function employeeInvitationAccepted(int $userId, int $organizationId, string $organizationName, string $slug): void
    {
        $this->notify(
            $userId,
            self::EMPLOYEE_INVITATION,
            'You joined ' . $organizationName,
            sprintf('You now have staff access to %s.', $organizationName),
            [
                'organization_id' => $organizationId,
                'action_url'      => '/manage/' . $slug,
            ]
        );
    }
}
