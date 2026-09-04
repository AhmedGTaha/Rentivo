<?php

declare(strict_types=1);

namespace Rentivo;

use Rentivo\Auth\GoogleAuthService;
use Rentivo\Auth\SessionAuth;
use Rentivo\Components\View;
use Rentivo\Database\Connection;
use Rentivo\Database\Migrator;
use Rentivo\Http\Router;
use Rentivo\Repositories\ActivityLogRepository;
use Rentivo\Repositories\BookingRepository;
use Rentivo\Repositories\CarImageRepository;
use Rentivo\Repositories\CarRepository;
use Rentivo\Repositories\CategoryRepository;
use Rentivo\Repositories\DocumentRepository;
use Rentivo\Repositories\FavoriteRepository;
use Rentivo\Repositories\InvitationRepository;
use Rentivo\Repositories\LocationRepository;
use Rentivo\Repositories\NotificationRepository;
use Rentivo\Repositories\OrganizationCustomerRepository;
use Rentivo\Repositories\OrganizationRepository;
use Rentivo\Repositories\OrganizationUserRepository;
use Rentivo\Repositories\PermissionRepository;
use Rentivo\Repositories\RentalRepository;
use Rentivo\Repositories\ReportRepository;
use Rentivo\Repositories\UserRepository;
use Rentivo\Security\Authorization;
use Rentivo\Security\RateLimiter;
use Rentivo\Services\AuditService;
use Rentivo\Services\AvailabilityService;
use Rentivo\Services\BookingService;
use Rentivo\Services\CarService;
use Rentivo\Services\CategoryService;
use Rentivo\Services\DocumentService;
use Rentivo\Services\EmployeeService;
use Rentivo\Services\FileStorageService;
use Rentivo\Services\ImageService;
use Rentivo\Services\LocationService;
use Rentivo\Services\MailService;
use Rentivo\Services\NotificationService;
use Rentivo\Services\OrganizationService;
use Rentivo\Services\PricingService;
use Rentivo\Services\RentalService;
use Rentivo\Services\ReportService;
use Rentivo\Services\SchedulerService;
use Rentivo\Support\Config;

/**
 * Wires the application object graph.
 *
 * Every binding is explicit so the dependencies of each service are visible in
 * one place, and so tests can swap any single collaborator.
 */
final class Application
{
    private Container $container;

    public function __construct(
        private string $basePath,
        private View $view
    ) {
        $this->container = new Container();
        $this->registerBindings();
    }

    public function basePath(string $path = ''): string
    {
        return $this->basePath . ($path === '' ? '' : '/' . ltrim($path, '/'));
    }

    public function view(): View
    {
        return $this->view;
    }

    public function container(): Container
    {
        return $this->container;
    }

    /**
     * @template T of object
     * @param class-string<T> $id
     * @return T
     */
    public function get(string $id): mixed
    {
        return $this->container->get($id);
    }

    public function db(): Connection
    {
        return $this->container->get(Connection::class);
    }

    public function router(): Router
    {
        return $this->container->get(Router::class);
    }

    private function registerBindings(): void
    {
        $c = $this->container;
        $base = $this->basePath;

        $c->instance(self::class, $this);
        $c->instance(View::class, $this->view);

        $c->bind(Connection::class, static fn (): Connection => new Connection(
            /** @phpstan-ignore-next-line configuration shape is fixed by config/app.php */
            Config::get('database')
        ));

        $c->bind(Migrator::class, static fn (Container $c): Migrator => new Migrator(
            $c->get(Connection::class),
            $base . '/database/migrations'
        ));

        $c->bind(Router::class, static function () use ($base): Router {
            $router = new Router();
            (require $base . '/routes/web.php')($router);

            return $router;
        });

        // ---------------------------------------------------------------
        // Repositories
        // ---------------------------------------------------------------
        $repositories = [
            UserRepository::class,
            OrganizationRepository::class,
            OrganizationUserRepository::class,
            PermissionRepository::class,
            InvitationRepository::class,
            LocationRepository::class,
            CategoryRepository::class,
            CarRepository::class,
            CarImageRepository::class,
            FavoriteRepository::class,
            OrganizationCustomerRepository::class,
            BookingRepository::class,
            RentalRepository::class,
            DocumentRepository::class,
            NotificationRepository::class,
            ActivityLogRepository::class,
            ReportRepository::class,
        ];

        foreach ($repositories as $repository) {
            $c->bind($repository, static fn (Container $c): object => new $repository($c->get(Connection::class)));
        }

        // ---------------------------------------------------------------
        // Security and authentication
        // ---------------------------------------------------------------
        $c->bind(SessionAuth::class, static fn (Container $c): SessionAuth => new SessionAuth(
            $c->get(UserRepository::class)
        ));

        $c->bind(Authorization::class, static fn (Container $c): Authorization => new Authorization(
            $c->get(SessionAuth::class),
            $c->get(OrganizationRepository::class),
            $c->get(OrganizationUserRepository::class)
        ));

        $c->bind(RateLimiter::class, static fn (Container $c): RateLimiter => new RateLimiter(
            $c->get(Connection::class)
        ));

        $c->bind(GoogleAuthService::class, static fn (Container $c): GoogleAuthService => new GoogleAuthService(
            $c->get(UserRepository::class)
        ));

        // ---------------------------------------------------------------
        // Infrastructure services
        // ---------------------------------------------------------------
        $c->bind(FileStorageService::class, static fn (): FileStorageService => new FileStorageService($base));
        $c->bind(ImageService::class, static fn (Container $c): ImageService => new ImageService(
            $c->get(FileStorageService::class)
        ));
        $c->bind(MailService::class, static fn (): MailService => new MailService());

        $c->bind(AuditService::class, static fn (Container $c): AuditService => new AuditService(
            $c->get(ActivityLogRepository::class)
        ));

        $c->bind(NotificationService::class, static fn (Container $c): NotificationService => new NotificationService(
            $c->get(NotificationRepository::class),
            $c->get(MailService::class),
            $c->get(UserRepository::class)
        ));

        // ---------------------------------------------------------------
        // Domain services
        // ---------------------------------------------------------------
        $c->bind(OrganizationService::class, static fn (Container $c): OrganizationService => new OrganizationService(
            $c->get(Connection::class),
            $c->get(OrganizationRepository::class),
            $c->get(OrganizationUserRepository::class),
            $c->get(CategoryRepository::class),
            $c->get(AuditService::class),
            $c->get(ImageService::class)
        ));

        $c->bind(EmployeeService::class, static fn (Container $c): EmployeeService => new EmployeeService(
            $c->get(Connection::class),
            $c->get(OrganizationUserRepository::class),
            $c->get(InvitationRepository::class),
            $c->get(PermissionRepository::class),
            $c->get(UserRepository::class),
            $c->get(AuditService::class),
            $c->get(NotificationService::class),
            $c->get(MailService::class)
        ));

        $c->bind(LocationService::class, static fn (Container $c): LocationService => new LocationService(
            $c->get(LocationRepository::class),
            $c->get(AuditService::class)
        ));

        $c->bind(CategoryService::class, static fn (Container $c): CategoryService => new CategoryService(
            $c->get(CategoryRepository::class),
            $c->get(AuditService::class)
        ));

        $c->bind(CarService::class, static fn (Container $c): CarService => new CarService(
            $c->get(Connection::class),
            $c->get(CarRepository::class),
            $c->get(CarImageRepository::class),
            $c->get(ImageService::class),
            $c->get(AuditService::class)
        ));

        $c->bind(AvailabilityService::class, static fn (Container $c): AvailabilityService => new AvailabilityService(
            $c->get(BookingRepository::class),
            $c->get(CarRepository::class)
        ));

        $c->bind(PricingService::class, static fn (): PricingService => new PricingService());

        $c->bind(BookingService::class, static fn (Container $c): BookingService => new BookingService(
            $c->get(Connection::class),
            $c->get(BookingRepository::class),
            $c->get(CarRepository::class),
            $c->get(OrganizationCustomerRepository::class),
            $c->get(AvailabilityService::class),
            $c->get(PricingService::class),
            $c->get(NotificationService::class),
            $c->get(AuditService::class),
            $c->get(OrganizationUserRepository::class)
        ));

        $c->bind(RentalService::class, static fn (Container $c): RentalService => new RentalService(
            $c->get(Connection::class),
            $c->get(RentalRepository::class),
            $c->get(BookingRepository::class),
            $c->get(CarRepository::class),
            $c->get(BookingService::class),
            $c->get(FileStorageService::class),
            $c->get(ImageService::class),
            $c->get(AuditService::class),
            $c->get(NotificationService::class)
        ));

        $c->bind(DocumentService::class, static fn (Container $c): DocumentService => new DocumentService(
            $c->get(Connection::class),
            $c->get(DocumentRepository::class),
            $c->get(OrganizationCustomerRepository::class),
            $c->get(OrganizationUserRepository::class),
            $c->get(FileStorageService::class),
            $c->get(AuditService::class),
            $c->get(NotificationService::class)
        ));

        $c->bind(ReportService::class, static fn (Container $c): ReportService => new ReportService(
            $c->get(ReportRepository::class)
        ));

        $c->bind(SchedulerService::class, static fn (Container $c): SchedulerService => new SchedulerService(
            $c->get(BookingRepository::class),
            $c->get(NotificationService::class),
            $c->get(RateLimiter::class)
        ));
    }
}
