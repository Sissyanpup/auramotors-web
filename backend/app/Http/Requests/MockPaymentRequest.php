<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Data dari portal bank simulasi (/bank-portal). Proyek offline: angka apa
 * pun diterima selama formatnya masuk akal (tanpa cek Luhn / tanggal kedaluwarsa),
 * OTP cukup 6 digit. Tanpa `method` sama sekali = simulasi langsung (alur lama).
 *
 * Nomor kartu & CVV hanya divalidasi lalu dibuang — yang disimpan cuma brand +
 * 4 digit terakhir (lihat maskedAccount()).
 */
class MockPaymentRequest extends FormRequest
{
    public const METHODS = ['card', 'paypal', 'virtual_account'];

    public const VA_BANKS = ['BCA', 'MANDIRI', 'BNI', 'BRI', 'PERMATA', 'AURA'];

    public function authorize(): bool
    {
        // Kepemilikan transaksi dicek di controller lewat TransactionPolicy.
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'method' => ['nullable', Rule::in(self::METHODS)],
            'otp' => ['required_with:method', 'nullable', 'digits:6'],

            'card_number' => ['required_if:method,card', 'nullable', 'regex:/^[0-9 ]{12,23}$/'],
            'card_holder' => ['required_if:method,card', 'nullable', 'string', 'max:100'],
            'card_expiry' => ['required_if:method,card', 'nullable', 'regex:/^(0[1-9]|1[0-2])\/[0-9]{2}$/'],
            'card_cvv' => ['required_if:method,card', 'nullable', 'digits_between:3,4'],

            'paypal_email' => ['required_if:method,paypal', 'nullable', 'email', 'max:150'],
            'paypal_password' => ['required_if:method,paypal', 'nullable', 'string', 'max:100'],

            'va_bank' => ['required_if:method,virtual_account', 'nullable', Rule::in(self::VA_BANKS)],
            'va_user_id' => ['required_if:method,virtual_account', 'nullable', 'string', 'max:100'],
            'va_pin' => ['required_if:method,virtual_account', 'nullable', 'digits:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'card_number.regex' => 'Nomor kartu harus 12–19 digit angka.',
            'card_expiry.regex' => 'Masa berlaku harus berformat MM/YY.',
            'card_cvv.digits_between' => 'CVV harus 3 atau 4 digit.',
            'otp.digits' => 'Kode OTP harus 6 digit angka.',
            'va_pin.digits' => 'PIN harus 6 digit angka.',
        ];
    }

    /**
     * @return array{payment_method: string|null, payment_channel: string|null, payment_account_masked: string|null, payment_payer_name: string|null}
     */
    public function paymentDetails(): array
    {
        $method = $this->validated('method');

        return match ($method) {
            'card' => [
                'payment_method' => 'card',
                'payment_channel' => $this->cardBrand(),
                'payment_account_masked' => '•••• •••• •••• '.substr($this->cardDigits(), -4),
                'payment_payer_name' => mb_strtoupper((string) $this->validated('card_holder')),
            ],
            'paypal' => [
                'payment_method' => 'paypal',
                'payment_channel' => 'PAYPAL',
                'payment_account_masked' => $this->maskEmail((string) $this->validated('paypal_email')),
                'payment_payer_name' => $this->user()->name,
            ],
            'virtual_account' => [
                'payment_method' => 'virtual_account',
                'payment_channel' => (string) $this->validated('va_bank'),
                'payment_account_masked' => 'User ID '.$this->maskMiddle((string) $this->validated('va_user_id')),
                'payment_payer_name' => $this->user()->name,
            ],
            default => [
                'payment_method' => 'simulation',
                'payment_channel' => 'SIMULASI',
                'payment_account_masked' => null,
                'payment_payer_name' => $this->user()->name,
            ],
        };
    }

    private function cardDigits(): string
    {
        return preg_replace('/\D/', '', (string) $this->validated('card_number'));
    }

    /**
     * Deteksi jaringan kartu dari prefix nomor (IIN) — hanya untuk label.
     */
    private function cardBrand(): string
    {
        $digits = $this->cardDigits();

        return match (true) {
            str_starts_with($digits, '4') => 'VISA',
            (bool) preg_match('/^(5[1-5]|2[2-7])/', $digits) => 'MASTERCARD',
            (bool) preg_match('/^3[47]/', $digits) => 'AMEX',
            str_starts_with($digits, '35') => 'JCB',
            default => 'KARTU DEBIT',
        };
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($local, 0, 2).str_repeat('*', max(1, mb_strlen($local) - 2)).'@'.$domain;
    }

    private function maskMiddle(string $value): string
    {
        $length = mb_strlen($value);

        if ($length <= 3) {
            return str_repeat('*', $length);
        }

        return mb_substr($value, 0, 2).str_repeat('*', $length - 3).mb_substr($value, -1);
    }
}
