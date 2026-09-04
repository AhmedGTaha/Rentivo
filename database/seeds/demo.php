<?php

declare(strict_types=1);

use Rentivo\Application;
use Rentivo\Database\Connection;
use Rentivo\Repositories\BookingRepository;
use Rentivo\Repositories\CarRepository;
use Rentivo\Repositories\CategoryRepository;
use Rentivo\Repositories\LocationRepository;
use Rentivo\Repositories\OrganizationCustomerRepository;
use Rentivo\Repositories\OrganizationRepository;
use Rentivo\Repositories\OrganizationUserRepository;
use Rentivo\Repositories\PermissionRepository;
use Rentivo\Repositories\UserRepository;
use Rentivo\Security\OrganizationContext;
use Rentivo\Security\Permissions;
use Rentivo\Services\PricingService;
use Rentivo\Support\DateTimeHelper;
use Rentivo\Support\Slug;

/**
 * Development demo content.
 *
 * Creates two independent agencies, staff with contrasting permission sets,
 * customers, fleets and bookings across several statuses — enough to exercise
 * tenant isolation, availability and the management screens by hand.
 *
 * Never runs unless `php scripts/seed.php --demo` is invoked explicitly.
 */
return static function (Application $app, callable $write): void {
    /** @var Connection $db */
    $db = $app->get(Connection::class);

    /** @var UserRepository $users */
    $users = $app->get(UserRepository::class);
    /** @var OrganizationRepository $organizations */
    $organizations = $app->get(OrganizationRepository::class);
    /** @var OrganizationUserRepository $members */
    $members = $app->get(OrganizationUserRepository::class);
    /** @var PermissionRepository $permissions */
    $permissions = $app->get(PermissionRepository::class);
    /** @var CategoryRepository $categories */
    $categories = $app->get(CategoryRepository::class);
    /** @var LocationRepository $locations */
    $locations = $app->get(LocationRepository::class);
    /** @var CarRepository $cars */
    $cars = $app->get(CarRepository::class);
    /** @var BookingRepository $bookings */
    $bookings = $app->get(BookingRepository::class);
    /** @var OrganizationCustomerRepository $organizationCustomers */
    $organizationCustomers = $app->get(OrganizationCustomerRepository::class);
    /** @var PricingService $pricing */
    $pricing = $app->get(PricingService::class);

    // Demo users carry a clearly fake google_id so they can never collide with
    // a real Google subject id.
    $makeUser = static function (string $email, string $name) use ($users): int {
        $existing = $users->findByEmail($email);

        if ($existing !== null) {
            return (int) $existing['id'];
        }

        $userId = $users->createFromGoogle([
            'google_id'      => 'demo-' . substr(hash('sha256', $email), 0, 24),
            'email'          => $email,
            'name'           => $name,
            'avatar'         => null,
            'email_verified' => true,
        ]);

        $users->profileOrCreate($userId);
        $users->updateProfile($userId, ['phone' => '+973 3600 ' . random_int(1000, 9999)]);

        return $userId;
    };

    $makeOrganization = static function (array $attributes, int $adminUserId) use ($organizations, $members): array {
        $existing = $organizations->findBySlug($attributes['slug']);

        if ($existing !== null) {
            return $existing;
        }

        $id = $organizations->create($attributes + ['is_active' => 1]);
        $members->create($id, $adminUserId, OrganizationContext::ROLE_ADMIN);

        return $organizations->find($id) ?? [];
    };

    $write('Creating demo users...');

    $adminA = $makeUser('demo.admin.a@example.com', 'Layla Al Mansoor');
    $adminB = $makeUser('demo.admin.b@example.com', 'Omar Haddad');
    $employeeA = $makeUser('demo.employee.a@example.com', 'Sara Nasser');
    $customer1 = $makeUser('demo.customer1@example.com', 'Yusuf Rahman');
    $customer2 = $makeUser('demo.customer2@example.com', 'Noor Khalid');

    $write('Creating demo organizations...');

    $orgA = $makeOrganization([
        'name'          => 'Manama Motors',
        'slug'          => 'manama-motors',
        'contact_email' => 'hello@manamamotors.example',
        'phone'         => '+973 1700 1000',
        'address'       => 'Building 210, Road 1502, Manama',
        'description'   => 'A Manama-based agency specialising in comfortable, well-maintained '
            . 'city and family vehicles with airport delivery.',
        'primary_color' => '#1f4f3f',
        'rental_terms'  => "Minimum driver age 21. A valid driving licence and national ID are required at pickup.\n"
            . "Fuel is supplied full and must be returned full.\n"
            . 'Mileage is unlimited within the Kingdom of Bahrain.',
    ], $adminA);

    $orgB = $makeOrganization([
        'name'          => 'Gulf Prestige Rentals',
        'slug'          => 'gulf-prestige-rentals',
        'contact_email' => 'reservations@gulfprestige.example',
        'phone'         => '+973 1700 2000',
        'address'       => 'Seef District, Manama',
        'description'   => 'Premium and performance vehicles for business travel and special occasions.',
        'primary_color' => '#2b2b6b',
        'rental_terms'  => "Minimum driver age 25 for premium vehicles.\n"
            . "A security deposit is authorised at pickup and released on return.\n"
            . 'Cross-causeway travel requires prior written approval.',
    ], $adminB);

    if ($orgA === [] || $orgB === []) {
        $write('Demo organizations already exist; nothing further to seed.');

        return;
    }

    $orgAId = (int) $orgA['id'];
    $orgBId = (int) $orgB['id'];

    // An employee of agency A with a deliberately partial permission set, so
    // permission enforcement is easy to exercise by hand.
    if (!$members->exists($orgAId, $employeeA)) {
        $membershipId = $members->create($orgAId, $employeeA, OrganizationContext::ROLE_EMPLOYEE);

        $members->syncPermissions($membershipId, $permissions->idsForKeys([
            Permissions::CARS_VIEW,
            Permissions::BOOKINGS_VIEW,
            Permissions::BOOKINGS_CONFIRM,
            Permissions::CUSTOMERS_VIEW,
            Permissions::LOCATIONS_VIEW,
        ]));
    }

    $write('Creating locations and categories...');

    $ensureCategories = static function (int $organizationId) use ($categories): array {
        $map = [];

        foreach (['Economy', 'Sedan', 'SUV', 'Luxury', 'Sports', 'Van'] as $name) {
            if ($categories->nameExists($organizationId, $name)) {
                foreach ($categories->listForOrganization($organizationId) as $row) {
                    if ($row['name'] === $name) {
                        $map[$name] = (int) $row['id'];
                    }
                }
                continue;
            }

            $map[$name] = $categories->create($organizationId, $name, Slug::make($name));
        }

        return $map;
    };

    $categoriesA = $ensureCategories($orgAId);
    $categoriesB = $ensureCategories($orgBId);

    $locationA = $locations->create($orgAId, [
        'name'          => 'Bahrain International Airport',
        'address'       => 'Arrivals Hall, Muharraq',
        'phone'         => '+973 1700 1001',
        'opening_hours' => 'Open 24 hours',
        'latitude'      => '26.2708000',
        'longitude'     => '50.6336000',
        'is_active'     => 1,
    ]);

    $locationA2 = $locations->create($orgAId, [
        'name'          => 'Manama City Branch',
        'address'       => 'Road 1502, Manama',
        'phone'         => '+973 1700 1002',
        'opening_hours' => 'Sun–Thu 08:00–20:00, Fri–Sat 09:00–18:00',
        'is_active'     => 1,
    ]);

    $locationB = $locations->create($orgBId, [
        'name'          => 'Seef Showroom',
        'address'       => 'Seef District, Manama',
        'phone'         => '+973 1700 2001',
        'opening_hours' => 'Daily 09:00–21:00',
        'is_active'     => 1,
    ]);

    $write('Creating demo fleet...');

    $makeCar = static function (
        int $organizationId,
        int $locationId,
        array $categoryMap,
        array $attributes
    ) use ($cars): int {
        $slug = Slug::unique(
            $attributes['brand'] . ' ' . $attributes['model'] . ' ' . $attributes['year'],
            static fn (string $candidate): bool => $cars->slugExists($organizationId, $candidate)
        );

        return $cars->create($organizationId, [
            'category_id'     => $categoryMap[$attributes['category']] ?? null,
            'location_id'     => $locationId,
            'brand'           => $attributes['brand'],
            'model'           => $attributes['model'],
            'slug'            => $slug,
            'year'            => $attributes['year'],
            'plate_number'    => $attributes['plate'],
            'vin'             => strtoupper(bin2hex(random_bytes(8))),
            'transmission'    => $attributes['transmission'] ?? 'automatic',
            'fuel_type'       => $attributes['fuel'] ?? 'petrol',
            'seats'           => $attributes['seats'] ?? 5,
            'doors'           => $attributes['doors'] ?? 4,
            'mileage'         => random_int(8000, 65000),
            'color'           => $attributes['color'],
            'daily_rate_fils' => $attributes['rate_fils'],
            'description'     => $attributes['description'],
            'status'          => $attributes['status'] ?? 'available',
        ]);
    };

    $fleetA = [
        ['brand' => 'Toyota', 'model' => 'Corolla', 'year' => 2024, 'plate' => '414 726', 'category' => 'Economy',
         'color' => 'Pearl White', 'rate_fils' => 12000, 'seats' => 5,
         'description' => 'Efficient and effortless around the city, with Apple CarPlay and a reversing camera.'],
        ['brand' => 'Honda', 'model' => 'Accord', 'year' => 2023, 'plate' => '512 380', 'category' => 'Sedan',
         'color' => 'Graphite', 'rate_fils' => 18500,
         'description' => 'A refined executive saloon with a quiet cabin and generous boot space.'],
        ['brand' => 'Nissan', 'model' => 'X-Trail', 'year' => 2024, 'plate' => '628 114', 'category' => 'SUV',
         'color' => 'Midnight Blue', 'rate_fils' => 22000, 'seats' => 7,
         'description' => 'Seven seats, elevated ride height and ample luggage room for family trips.'],
        ['brand' => 'Hyundai', 'model' => 'Elantra', 'year' => 2023, 'plate' => '733 902', 'category' => 'Economy',
         'color' => 'Silver', 'rate_fils' => 13500,
         'description' => 'Comfortable, economical and well equipped for longer drives.'],
        ['brand' => 'Toyota', 'model' => 'Hiace', 'year' => 2022, 'plate' => '840 551', 'category' => 'Van',
         'color' => 'White', 'rate_fils' => 28000, 'seats' => 12, 'doors' => 5, 'fuel' => 'diesel',
         'description' => 'Twelve-seat van suited to group transfers and airport runs.',
         'status' => 'maintenance'],
    ];

    $fleetB = [
        ['brand' => 'Mercedes-Benz', 'model' => 'E-Class', 'year' => 2024, 'plate' => '901 233', 'category' => 'Luxury',
         'color' => 'Obsidian Black', 'rate_fils' => 45000,
         'description' => 'A flagship executive saloon with massaging seats and adaptive cruise control.'],
        ['brand' => 'BMW', 'model' => 'X5', 'year' => 2024, 'plate' => '118 447', 'category' => 'SUV',
         'color' => 'Alpine White', 'rate_fils' => 52000, 'seats' => 5,
         'description' => 'Commanding presence with all-wheel drive and a premium audio system.'],
        ['brand' => 'Porsche', 'model' => '911 Carrera', 'year' => 2023, 'plate' => '226 705', 'category' => 'Sports',
         'color' => 'Guards Red', 'rate_fils' => 98000, 'seats' => 2, 'doors' => 2,
         'description' => 'An icon. Rear-engined, precise, and unmistakable on the coast road.'],
        ['brand' => 'Range Rover', 'model' => 'Velar', 'year' => 2023, 'plate' => '335 618', 'category' => 'Luxury',
         'color' => 'Santorini Black', 'rate_fils' => 61000,
         'description' => 'Understated luxury with a beautifully finished, minimal interior.'],
        ['brand' => 'Tesla', 'model' => 'Model 3', 'year' => 2024, 'plate' => '447 029', 'category' => 'Sedan',
         'color' => 'Deep Blue', 'rate_fils' => 34000, 'fuel' => 'electric',
         'description' => 'Fully electric with rapid acceleration and a minimalist cabin.'],
    ];

    $carIdsA = [];
    foreach ($fleetA as $index => $attributes) {
        $carIdsA[] = $makeCar($orgAId, $index % 2 === 0 ? $locationA : $locationA2, $categoriesA, $attributes);
    }

    $carIdsB = [];
    foreach ($fleetB as $attributes) {
        $carIdsB[] = $makeCar($orgBId, $locationB, $categoriesB, $attributes);
    }

    $write('Creating demo bookings...');

    $makeBooking = static function (
        int $organizationId,
        int $carId,
        int $userId,
        ?int $locationId,
        string $pickupModifier,
        string $returnModifier,
        string $status,
        string $paymentStatus = 'unpaid'
    ) use ($bookings, $cars, $pricing, $organizationCustomers, $db): void {
        $car = $db->selectOne('SELECT * FROM `cars` WHERE `id` = ?', [$carId]);

        if ($car === null) {
            return;
        }

        $pickupAt = DateTimeHelper::now()->modify($pickupModifier);
        $returnAt = DateTimeHelper::now()->modify($returnModifier);

        $quote = $pricing->quote($pickupAt, $returnAt, (int) $car['daily_rate_fils']);

        $bookingId = $bookings->create([
            'reference'                => $bookings->nextReference(),
            'organization_id'          => $organizationId,
            'user_id'                  => $userId,
            'car_id'                   => $carId,
            'pickup_location_id'       => $locationId,
            'return_location_id'       => $locationId,
            'pickup_at'                => DateTimeHelper::toDb($pickupAt),
            'return_at'                => DateTimeHelper::toDb($returnAt),
            'rental_days'              => $quote['rental_days'],
            'daily_rate_snapshot_fils' => $quote['daily_rate_fils'],
            'subtotal_fils'            => $quote['subtotal_fils'],
            'additional_charges_fils'  => 0,
            'total_fils'               => $quote['total_fils'],
            'status'                   => $status,
            'payment_status'           => $paymentStatus,
            'confirmed_at'             => in_array($status, ['confirmed', 'active', 'completed'], true)
                ? DateTimeHelper::nowDb()
                : null,
            'completed_at'             => $status === 'completed' ? DateTimeHelper::nowDb() : null,
        ]);

        $organizationCustomers->ensure($organizationId, $userId);

        unset($bookingId);
    };

    // A spread of statuses so every management filter has something to show.
    $makeBooking($orgAId, $carIdsA[0], $customer1, $locationA, '+2 days', '+5 days', 'pending');
    $makeBooking($orgAId, $carIdsA[1], $customer2, $locationA2, '+1 day', '+4 days', 'confirmed');
    $makeBooking($orgAId, $carIdsA[2], $customer1, $locationA, '-2 days', '+1 day', 'active', 'paid');
    $makeBooking($orgAId, $carIdsA[3], $customer2, $locationA2, '-20 days', '-15 days', 'completed', 'paid');
    $makeBooking($orgAId, $carIdsA[0], $customer2, $locationA, '-8 days', '-6 days', 'cancelled');

    $makeBooking($orgBId, $carIdsB[0], $customer1, $locationB, '+3 days', '+6 days', 'pending');
    $makeBooking($orgBId, $carIdsB[2], $customer2, $locationB, '+7 days', '+9 days', 'confirmed');
    $makeBooking($orgBId, $carIdsB[4], $customer1, $locationB, '-30 days', '-27 days', 'completed', 'paid');

    // Reflect the active rental in the car's operational status.
    $db->update('cars', ['status' => 'rented'], ['id' => $carIdsA[2]]);

    $write('Demo content created.', "\033[32m");
    $write('');
    $write('Demo accounts (sign in with Google using these addresses to adopt them):');
    $write('  Admin of Manama Motors ............ demo.admin.a@example.com');
    $write('  Admin of Gulf Prestige Rentals .... demo.admin.b@example.com');
    $write('  Employee of Manama Motors ......... demo.employee.a@example.com');
    $write('  Customers ......................... demo.customer1@example.com, demo.customer2@example.com');
    $write('');
    $write('Demo cars have no photographs; upload images from the management fleet screen.');
};
