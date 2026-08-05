<?php

namespace App\Services\Payment;

use App\Services\Payment\Contracts\PaymentProviderInterface;
use App\Models\User;
use App\Models\PaymentConfirmation;
use App\Models\Subscription;
use Carbon\Carbon;
use Exception;

class ManualPaymentProvider implements PaymentProviderInterface
{
    public function createInvoice(User $user, float $amount, string $plan): array
    {
        // Create a payment confirmation record in pending state
        $confirmation = PaymentConfirmation::create([
            'user_id' => $user->id,
            'amount' => $amount,
            'status' => 'pending',
        ]);

        $cardNumber = env('MANUAL_PAYMENT_CARD', '8600 0000 0000 0000');
        $cardOwner = env('MANUAL_PAYMENT_CARD_OWNER', 'AI Bot Admin');

        $message = "💳 **Qo'lda to'lov qilish bo'yicha ko'rsatma:**\n\n" .
                   "Plan: *" . ucfirst($plan) . "*\n" .
                   "To'lov summasi: *" . number_format($amount, 0, ',', ' ') . " UZS*\n" .
                   "Karta raqami: `{$cardNumber}`\n" .
                   "Karta egasi: *{$cardOwner}*\n\n" .
                   "⚠️ **Muhim:** To'lovni amalga oshirgach, to'lov cheki (screenshot) yoki kvitansiyasini rasm ko'rinishida ushbu botga yuboring. To'lov tasdiqlangach tarifingiz avtomatik faollashadi.";

        return [
            'success' => true,
            'type' => 'manual',
            'payment_id' => (string) $confirmation->id,
            'message' => $message,
            'data' => [
                'confirmation_id' => $confirmation->id,
                'card_number' => $cardNumber,
                'card_owner' => $cardOwner,
            ]
        ];
    }

    public function verifyPayment(string $paymentId, array $data = []): bool
    {
        $confirmation = PaymentConfirmation::find($paymentId);
        if (!$confirmation) {
            throw new Exception("Payment confirmation record not found.");
        }

        $status = $data['status'] ?? 'approved';
        $reason = $data['rejection_reason'] ?? null;

        $confirmation->update([
            'status' => $status,
            'rejection_reason' => $reason,
        ]);

        if ($status === 'approved') {
            $user = $confirmation->user;
            
            // Determine plan based on amount or custom inputs
            // Let's check plan limits and update user subscription
            $plan = $data['plan'] ?? 'premium';
            $durationDays = $plan === 'business' ? 30 : 30;

            // Deactivate previous active subscriptions for the user
            Subscription::where('user_id', $user->id)
                ->where('status', 'active')
                ->update(['status' => 'expired']);

            // Create new active subscription
            Subscription::create([
                'user_id' => $user->id,
                'plan' => $plan,
                'price' => $confirmation->amount,
                'status' => 'active',
                'payment_method' => 'manual',
                'payment_id' => (string) $confirmation->id,
                'starts_at' => Carbon::now(),
                'expires_at' => Carbon::now()->addDays($durationDays),
            ]);

            // Update user plan
            $user->update([
                'plan' => $plan,
                'daily_limit' => $plan === 'business' ? 999999 : 999999, // unlimited for premium/business
            ]);

            return true;
        }

        return false;
    }
}
