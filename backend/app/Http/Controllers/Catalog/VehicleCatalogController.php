<?php

namespace App\Http\Controllers\Catalog;

use App\Enums\VehicleStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\VehicleDetailResource;
use App\Http\Resources\VehicleResource;
use App\Models\Vehicle;
use App\Support\CatalogCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class VehicleCatalogController extends Controller
{
    /**
     * Public, unauthenticated catalog — safe to cache briefly per unique
     * filter combination. Caches the already-transformed JSON payload
     * (not the Eloquent paginator/models) since caching Eloquent objects
     * through the database cache store's serialize()/unserialize() is
     * fragile. See App\Support\CatalogCache for how writes (approve/
     * reject/sold/etc) invalidate this without waiting out the TTL.
     */
    public function index(Request $request): JsonResponse
    {
        $key = 'catalog.index.v'.CatalogCache::version().'.'.md5($request->getQueryString() ?? '');

        $payload = Cache::remember($key, CatalogCache::TTL_SECONDS, function () use ($request) {
            $vehicles = Vehicle::query()
                ->approved()
                ->with(['coverPhoto', 'seller:id,name'])
                ->when($request->query('brand'), fn ($query, $brand) => $query->where('brand', 'like', "%{$brand}%"))
                ->when($request->query('year_min'), fn ($query, $year) => $query->where('year', '>=', $year))
                ->when($request->query('year_max'), fn ($query, $year) => $query->where('year', '<=', $year))
                ->when($request->query('price_min'), fn ($query, $price) => $query->where('price', '>=', $price))
                ->when($request->query('price_max'), fn ($query, $price) => $query->where('price', '<=', $price))
                ->when($request->query('location'), fn ($query, $location) => $query->where('location', 'like', "%{$location}%"))
                ->latest()
                ->paginate(12);

            return VehicleResource::collection($vehicles)->response()->getData(true);
        });

        return response()->json($payload);
    }

    public function show(Vehicle $vehicle): VehicleDetailResource
    {
        abort_unless($vehicle->status === VehicleStatus::Approved, 404);

        return new VehicleDetailResource($vehicle->load(['photos', 'seller']));
    }
}
