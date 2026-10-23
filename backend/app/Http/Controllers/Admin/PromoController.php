<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PromoRequest;
use App\Http\Resources\PromoResource;
use App\Models\Promo;
use App\Models\Transaction;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Kelola promo pembeli: harga coret otomatis, voucher, gratis perluasan
 * asuransi, dan bunga kredit spesial.
 */
class PromoController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('manage', Transaction::class);

        return PromoResource::collection(
            Promo::query()->withCount('redemptions')->withSum('redemptions', 'benefit_amount')->latest()->get()
        );
    }

    public function store(PromoRequest $request): PromoResource
    {
        return new PromoResource(Promo::create($request->promoData())->loadCount('redemptions')->loadSum('redemptions', 'benefit_amount'));
    }

    public function update(PromoRequest $request, Promo $promo): PromoResource
    {
        $promo->update($request->promoData());

        return new PromoResource($promo->loadCount('redemptions')->loadSum('redemptions', 'benefit_amount'));
    }

    /**
     * Promo yang sudah pernah dipakai tidak dihapus supaya laporan subsidi
     * tetap utuh — nonaktifkan saja.
     */
    public function destroy(Promo $promo): Response
    {
        $this->authorize('manage', Transaction::class);
        abort_if($promo->redemptions()->exists(), 409, 'Promo ini sudah pernah dipakai pembeli. Nonaktifkan saja supaya jejak laporan tetap tersimpan.');

        $promo->delete();

        return response()->noContent();
    }
}
