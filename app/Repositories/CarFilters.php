<?php

declare(strict_types=1);

namespace Rentivo\Repositories;

use DateTimeImmutable;
use Rentivo\Support\Currency;
use Rentivo\Support\DateTimeHelper;

/**
 * Normalised public browse/filter state.
 *
 * Built once from the query string and then reused by the repository (to build
 * SQL) and by the views (to re-render the filter panel and build shareable
 * URLs), so filter handling is never duplicated.
 */
final class CarFilters
{
    public const SORT_PRICE_ASC = 'price_asc';
    public const SORT_PRICE_DESC = 'price_desc';
    public const SORT_NEWEST = 'newest';
    public const SORT_POPULAR = 'popular';

    public string $search = '';
    public ?int $organizationId = null;
    public string $agency = '';
    public string $category = '';
    public string $brand = '';
    public string $model = '';
    public ?int $year = null;
    public ?int $minPriceFils = null;
    public ?int $maxPriceFils = null;
    public string $transmission = '';
    public string $fuelType = '';
    public ?int $minSeats = null;
    public string $location = '';
    public ?DateTimeImmutable $pickupAt = null;
    public ?DateTimeImmutable $returnAt = null;
    public string $sort = self::SORT_NEWEST;
    public int $page = 1;

    /** Raw (already-validated) values, used to rebuild shareable URLs. */
    private array $raw = [];

    /**
     * @param array<string,mixed> $query
     */
    public static function fromQuery(array $query): self
    {
        $filters = new self();

        $string = static function (array $query, string $key, int $max = 100): string {
            $value = $query[$key] ?? '';

            return is_string($value) ? mb_substr(trim($value), 0, $max) : '';
        };

        $filters->search = $string($query, 'q', 120);
        $filters->agency = $string($query, 'agency', 191);
        $filters->category = $string($query, 'category', 120);
        $filters->brand = $string($query, 'brand', 80);
        $filters->model = $string($query, 'model', 80);
        $filters->location = $string($query, 'location', 150);

        $year = (int) ($query['year'] ?? 0);
        $filters->year = ($year >= 1950 && $year <= (int) date('Y') + 2) ? $year : null;

        $filters->minPriceFils = Currency::tryToFils(
            is_string($query['min_price'] ?? null) ? $query['min_price'] : null
        );
        $filters->maxPriceFils = Currency::tryToFils(
            is_string($query['max_price'] ?? null) ? $query['max_price'] : null
        );

        // A reversed range would silently return nothing; swap it instead.
        if ($filters->minPriceFils !== null
            && $filters->maxPriceFils !== null
            && $filters->minPriceFils > $filters->maxPriceFils
        ) {
            [$filters->minPriceFils, $filters->maxPriceFils] = [$filters->maxPriceFils, $filters->minPriceFils];
        }

        $transmission = $string($query, 'transmission', 20);
        $filters->transmission = in_array($transmission, ['automatic', 'manual'], true) ? $transmission : '';

        $fuel = $string($query, 'fuel_type', 20);
        $filters->fuelType = in_array($fuel, ['petrol', 'diesel', 'hybrid', 'electric'], true) ? $fuel : '';

        $seats = (int) ($query['min_seats'] ?? 0);
        $filters->minSeats = ($seats >= 2 && $seats <= 15) ? $seats : null;

        $pickup = DateTimeHelper::fromInput(is_string($query['pickup_at'] ?? null) ? $query['pickup_at'] : null);
        $return = DateTimeHelper::fromInput(is_string($query['return_at'] ?? null) ? $query['return_at'] : null);

        // Only a complete, ordered window constrains availability.
        if ($pickup !== null && $return !== null && $return > $pickup) {
            $filters->pickupAt = $pickup;
            $filters->returnAt = $return;
        }

        $sort = $string($query, 'sort', 20);
        $filters->sort = in_array($sort, self::sortOptions(), true) ? $sort : self::SORT_NEWEST;

        $filters->page = max(1, (int) ($query['page'] ?? 1));

        $filters->raw = [
            'q'            => $filters->search,
            'agency'       => $filters->agency,
            'category'     => $filters->category,
            'brand'        => $filters->brand,
            'model'        => $filters->model,
            'year'         => $filters->year,
            'min_price'    => $filters->minPriceFils === null ? '' : Currency::toInput($filters->minPriceFils),
            'max_price'    => $filters->maxPriceFils === null ? '' : Currency::toInput($filters->maxPriceFils),
            'transmission' => $filters->transmission,
            'fuel_type'    => $filters->fuelType,
            'min_seats'    => $filters->minSeats,
            'location'     => $filters->location,
            'pickup_at'    => DateTimeHelper::toInput($filters->pickupAt),
            'return_at'    => DateTimeHelper::toInput($filters->returnAt),
            'sort'         => $filters->sort === self::SORT_NEWEST ? '' : $filters->sort,
        ];

        return $filters;
    }

    /** Restricts the result set to a single agency (storefront browsing). */
    public function forOrganization(int $organizationId): self
    {
        $clone = clone $this;
        $clone->organizationId = $organizationId;
        $clone->agency = '';
        unset($clone->raw['agency']);

        return $clone;
    }

    /** @return list<string> */
    public static function sortOptions(): array
    {
        return [self::SORT_PRICE_ASC, self::SORT_PRICE_DESC, self::SORT_NEWEST, self::SORT_POPULAR];
    }

    /** @return array<string,string> */
    public static function sortLabels(): array
    {
        return [
            self::SORT_NEWEST     => 'Newest',
            self::SORT_PRICE_ASC  => 'Price: low to high',
            self::SORT_PRICE_DESC => 'Price: high to low',
            self::SORT_POPULAR    => 'Most popular',
        ];
    }

    public function hasDateWindow(): bool
    {
        return $this->pickupAt !== null && $this->returnAt !== null;
    }

    /** Number of filters (excluding sort/page) the visitor has applied. */
    public function activeCount(): int
    {
        $count = 0;

        foreach (['agency', 'category', 'brand', 'model', 'transmission', 'fuelType', 'location'] as $property) {
            if ($this->{$property} !== '') {
                $count++;
            }
        }

        foreach (['year', 'minPriceFils', 'maxPriceFils', 'minSeats'] as $property) {
            if ($this->{$property} !== null) {
                $count++;
            }
        }

        if ($this->hasDateWindow()) {
            $count++;
        }

        return $count;
    }

    /**
     * Query parameters representing the current state, for building links.
     *
     * @return array<string,mixed>
     */
    public function toQuery(array $overrides = []): array
    {
        $query = array_merge($this->raw, $overrides);

        return array_filter(
            $query,
            static fn ($value) => $value !== null && $value !== '' && $value !== []
        );
    }
}
