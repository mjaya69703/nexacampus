<?php

namespace App\Support\Campus;

use App\Models\Campus\AssetCategory;
use App\Models\Campus\CampusAsset;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Agregasi produk aset: 1 baris = 1 kombinasi nama+merk+model+kategori.
 * Kategori dirujuk by id (master asset_categories); NULL satu grup.
 * NULL diperlakukan sebagai satu grup (GROUP BY SQL) dan dicocokkan
 * dengan whereNull saat drill-down.
 */
class AssetProductService
{
    /**
     * @param array{name?: string|null, brand?: string|null, model?: string|null, category?: int|string|null} $key
     */
    public static function applyKey(Builder $query, array $key): Builder
    {
        foreach (['name' => $key['name'] ?? null, 'brand' => $key['brand'] ?? null, 'model_number' => $key['model'] ?? null] as $column => $value) {
            if ($value === null || $value === '') {
                $query->whereNull($column);
            } else {
                $query->where($column, $value);
            }
        }

        $category = $key['category'] ?? null;

        if ($category === null || $category === '') {
            $query->whereNull('category_id');
        } else {
            $query->where('category_id', (int) $category);
        }

        return $query;
    }

    /**
     * @param array{q?: string|null, category?: int|string|null, condition?: string|null, status?: string|null, funding?: string|null} $filters
     */
    public static function paginatedGroups(array $filters, string $sort, string $direction, int $perPage): LengthAwarePaginator
    {
        $query = CampusAsset::query()->with('category:id,name');

        if (filled($filters['q'] ?? null)) {
            $q = $filters['q'];
            $query->where(fn ($sub) => $sub
                ->where('campus_assets.name', 'like', "%{$q}%")
                ->orWhere('campus_assets.brand', 'like', "%{$q}%")
                ->orWhere('campus_assets.model_number', 'like', "%{$q}%")
                ->orWhere('campus_assets.asset_code', 'like', "%{$q}%"));
        }

        if (filled($filters['category'] ?? null)) {
            $query->where('campus_assets.category_id', (int) $filters['category']);
        }

        if (filled($filters['funding'] ?? null)) {
            $query->where('campus_assets.funding_source', $filters['funding']);
        }

        if (filled($filters['condition'] ?? null)) {
            $condition = $filters['condition'];
            $query->whereExists(function ($sub) use ($condition) {
                $sub->selectRaw('1')->from('campus_assets as inner_assets')
                    ->where('inner_assets.condition', $condition);
                self::constrainSameGroup($sub, 'inner_assets');
            });
        }

        if (filled($filters['status'] ?? null)) {
            $status = $filters['status'];
            $query->whereExists(function ($sub) use ($status) {
                $sub->selectRaw('1')->from('campus_assets as inner_assets')
                    ->where('inner_assets.status', $status);
                self::constrainSameGroup($sub, 'inner_assets');
            });
        }

        $query
            ->select(['campus_assets.name', 'campus_assets.brand', 'campus_assets.model_number', 'campus_assets.category_id'])
            ->selectRaw('COUNT(*) as qty')
            ->selectRaw("SUM(CASE WHEN `condition` = 'good' THEN 1 ELSE 0 END) as good_count")
            ->selectRaw("SUM(CASE WHEN `condition` IN ('minor_damage', 'major_damage') THEN 1 ELSE 0 END) as damaged_count")
            ->selectRaw('COALESCE(SUM(purchase_cost), 0) as total_value')
            ->selectRaw('MAX(campus_assets.created_at) as latest_created')
            ->groupBy(['campus_assets.name', 'campus_assets.brand', 'campus_assets.model_number', 'campus_assets.category_id']);

        $sortColumn = match ($sort) {
            'qty' => 'qty',
            'latest' => 'latest_created',
            default => 'campus_assets.name',
        };

        $query->orderBy($sortColumn, $direction);

        if ($sortColumn === 'campus_assets.name') {
            $query->orderBy('campus_assets.brand')->orderBy('campus_assets.model_number');
        }

        $paginator = $query->paginate($perPage);

        $categories = AssetCategory::query()->pluck('name', 'id')->all();

        $paginator->getCollection()->transform(fn ($row) => self::presentGroup($row, $categories));

        return $paginator;
    }

    /**
     * @param array{name?: string|null, brand?: string|null, model?: string|null, category?: int|string|null} $key
     */
    public static function unitsFor(array $key): Collection
    {
        return self::applyKey(CampusAsset::query()->with(['room:id,name', 'room.building:id,name', 'category:id,name']), $key)
            ->orderBy('asset_code')
            ->get();
    }

    /**
     * @param array{name?: string|null, brand?: string|null, model?: string|null, category?: int|string|null} $key
     */
    public static function summarize(array $key): array
    {
        $units = self::unitsFor($key);
        $categoryId = isset($key['category']) && $key['category'] !== '' ? (int) $key['category'] : null;

        return [
            'key' => self::normalizeKey($key),
            'name' => $key['name'] ?? null,
            'brand' => $key['brand'] ?? null,
            'model' => $key['model'] ?? null,
            'categoryId' => $categoryId,
            'categoryLabel' => $units->first()?->category?->name,
            'qty' => $units->count(),
            'good' => $units->where('condition', 'good')->count(),
            'damaged' => $units->whereIn('condition', ['minor_damage', 'major_damage'])->count(),
            'totalValue' => (float) $units->sum('purchase_cost'),
            'rooms' => $units->map(fn ($u) => $u->room?->name)->filter()->unique()->values()->all(),
        ];
    }

    /**
     * @param array{name?: string|null, brand?: string|null, model?: string|null, category?: int|string|null} $key
     */
    public static function normalizeKey(array $key): array
    {
        $category = $key['category'] ?? null;

        return [
            'name' => $key['name'] ?? null,
            'brand' => $key['brand'] ?? null,
            'model' => $key['model'] ?? null,
            'category' => $category === null || $category === '' ? null : (int) $category,
        ];
    }

    /**
     * Samakan grup (null-safe, portabel MySQL/SQLite): tiap kolom kunci
     * sama nilainya ATAU sama-sama null.
     */
    private static function constrainSameGroup(mixed $sub, string $alias): void
    {
        foreach (['name', 'brand', 'model_number', 'category_id'] as $column) {
            $sub->where(function ($w) use ($alias, $column) {
                $w->whereRaw("{$alias}.{$column} = campus_assets.{$column}")
                    ->orWhere(function ($nulls) use ($alias, $column) {
                        $nulls->whereNull("{$alias}.{$column}")->whereNull("campus_assets.{$column}");
                    });
            });
        }
    }

    private static function presentGroup(object $row, array $categories): array
    {
        $key = self::normalizeKey([
            'name' => $row->name,
            'brand' => $row->brand,
            'model' => $row->model_number,
            'category' => $row->category_id,
        ]);

        return [
            'key' => $key,
            'name' => $row->name,
            'brand' => $row->brand,
            'model' => $row->model_number,
            'categoryId' => $key['category'],
            'categoryLabel' => $key['category'] !== null ? ($categories[$key['category']] ?? null) : null,
            'qty' => (int) $row->qty,
            'good' => (int) $row->good_count,
            'damaged' => (int) $row->damaged_count,
            'totalValue' => (float) $row->total_value,
            'rooms' => self::roomsFor($key),
        ];
    }

    /**
     * @param array{name?: string|null, brand?: string|null, model?: string|null, category?: int|string|null} $key
     */
    private static function roomsFor(array $key): array
    {
        return self::applyKey(CampusAsset::query()->with('room:id,name'), $key)
            ->get(['room_id'])
            ->map(fn ($u) => $u->room?->name)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
