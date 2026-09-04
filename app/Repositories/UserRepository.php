<?php

declare(strict_types=1);

namespace Rentivo\Repositories;

/**
 * Platform users and their customer profiles.
 *
 * Users are only ever created by a verified Google identity; there is no
 * password column to read or write.
 */
final class UserRepository extends Repository
{
    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->selectOne('SELECT * FROM `users` WHERE `id` = ?', [$id]);
    }

    /** @return array<string,mixed>|null */
    public function findByGoogleId(string $googleId): ?array
    {
        return $this->db->selectOne('SELECT * FROM `users` WHERE `google_id` = ?', [$googleId]);
    }

    /** @return array<string,mixed>|null */
    public function findByEmail(string $email): ?array
    {
        return $this->db->selectOne('SELECT * FROM `users` WHERE `email` = ?', [strtolower($email)]);
    }

    /**
     * @param list<int> $ids
     * @return array<int,array<string,mixed>> Keyed by user id.
     */
    public function findMany(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $rows = $this->db->select(
            'SELECT * FROM `users` WHERE `id` IN (' . $this->placeholders($ids) . ')',
            array_values($ids)
        );

        $keyed = [];
        foreach ($rows as $row) {
            $keyed[(int) $row['id']] = $row;
        }

        return $keyed;
    }

    /**
     * Creates a user from a verified Google identity.
     *
     * @param array{google_id:string,email:string,name:string,avatar:?string,email_verified:bool} $identity
     */
    public function createFromGoogle(array $identity): int
    {
        $now = $this->now();

        return $this->db->insert('users', [
            'google_id'         => $identity['google_id'],
            'email'             => strtolower($identity['email']),
            'name'              => $identity['name'],
            'google_avatar_url' => $identity['avatar'],
            'email_verified_at' => $identity['email_verified'] ? $now : null,
            'last_login_at'     => $now,
            'created_at'        => $now,
            'updated_at'        => $now,
        ]);
    }

    /**
     * Refreshes the mutable parts of the Google identity on every login.
     *
     * @param array{google_id:string,email:string,name:string,avatar:?string,email_verified:bool} $identity
     */
    public function updateFromGoogle(int $userId, array $identity): void
    {
        $now = $this->now();

        $this->db->update('users', [
            'google_id'         => $identity['google_id'],
            'email'             => strtolower($identity['email']),
            'name'              => $identity['name'],
            'google_avatar_url' => $identity['avatar'],
            'email_verified_at' => $identity['email_verified'] ? $now : null,
            'last_login_at'     => $now,
            'updated_at'        => $now,
        ], ['id' => $userId]);
    }

    public function touchLogin(int $userId): void
    {
        $this->db->update('users', ['last_login_at' => $this->now()], ['id' => $userId]);
    }

    public function updateName(int $userId, string $name): void
    {
        $this->db->update('users', [
            'name'       => $name,
            'updated_at' => $this->now(),
        ], ['id' => $userId]);
    }

    // -----------------------------------------------------------------
    // Profiles
    // -----------------------------------------------------------------

    /** @return array<string,mixed>|null */
    public function findProfile(int $userId): ?array
    {
        return $this->db->selectOne('SELECT * FROM `user_profiles` WHERE `user_id` = ?', [$userId]);
    }

    /**
     * Returns the profile, creating an empty one on first access so callers
     * never have to null-check every field.
     *
     * @return array<string,mixed>
     */
    public function profileOrCreate(int $userId): array
    {
        $profile = $this->findProfile($userId);

        if ($profile !== null) {
            return $profile;
        }

        $now = $this->now();
        $this->db->insert('user_profiles', [
            'user_id'    => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findProfile($userId) ?? [];
    }

    /** @param array<string,mixed> $data */
    public function updateProfile(int $userId, array $data): void
    {
        $this->profileOrCreate($userId);

        $data['updated_at'] = $this->now();

        $this->db->update('user_profiles', $data, ['user_id' => $userId]);
    }

    /** A phone number is mandatory before a booking may be submitted. */
    public function hasPhone(int $userId): bool
    {
        $phone = $this->db->scalar('SELECT `phone` FROM `user_profiles` WHERE `user_id` = ?', [$userId]);

        return is_string($phone) && trim($phone) !== '';
    }

    /**
     * User row joined with profile fields, used by account and management
     * customer screens.
     *
     * @return array<string,mixed>|null
     */
    public function findWithProfile(int $userId): ?array
    {
        return $this->db->selectOne(
            'SELECT u.*,
                    p.phone, p.date_of_birth, p.nationality, p.address, p.profile_image_path
             FROM `users` u
             LEFT JOIN `user_profiles` p ON p.`user_id` = u.`id`
             WHERE u.`id` = ?',
            [$userId]
        );
    }
}
