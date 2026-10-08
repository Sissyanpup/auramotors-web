<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\BankAccountRequest;
use App\Http\Resources\SellerProfileResource;

class SellerBankAccountController extends Controller
{
    public function update(BankAccountRequest $request): SellerProfileResource
    {
        $profile = $request->user()->sellerProfile;

        abort_if($profile === null, 404, 'Lengkapi KYC terlebih dahulu sebelum menambahkan rekening bank.');

        $this->authorize('manageBankAccount', $profile);

        $profile->update($request->validated());

        return new SellerProfileResource($profile);
    }
}
