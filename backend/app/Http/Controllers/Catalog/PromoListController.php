<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Resources\PromoResource;
use App\Models\Promo;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Promo yang sedang berjalan untuk banner katalog & checkout pembeli.
 */
class PromoListController extends Controller
{
    public function __invoke(): AnonymousResourceCollection
    {
        return PromoResource::collection(
            Promo::query()->running()->orderBy('ends_at')->limit(12)->get()
        );
    }
}
