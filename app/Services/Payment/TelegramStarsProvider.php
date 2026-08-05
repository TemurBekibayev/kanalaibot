<?php

namespace App\Services\Payment;

use App\Services\Payment\Contracts\PaymentProviderInterface;
use App\Models\User;
use App\Models\Subscription;
use App\Services\Telegram\TelegramBotService;
use Carbon\Carbon;
use Exception;

class TelegramStarsProvider implements PaymentProviderInterface
{
    protected TelegramBotService $telegram;

    public function __construct()
    {
        // Resolve Telegram service
        $this->telegram = app(TelegramBotService::class);
    }

    public function createInvoice(User $user, float $amount, string $plan): array
    {
        // amount represents Stars count, e.g. 250 Stars
        $starsAmount = (int) $amount;

        try {
            // Generate invoice payload
            $payload = json_encode([
                'user_id' => $user->id,
                'plan' => $plan,
                'amount' => $starsAmount,
            ]);

            // Call telegram bot service to generate invoice link
            $invoiceLink = $this->telegram->createInvoiceLink(
                title: "AI Kanal Manager " . ucfirst($plan),
                description: "Obuna tarifi: " . ucfirst($plan) . " (30 kun)",
                payload: $payload,
                currency: "XTR", // Telegram Stars currency
                prices: [
                    ['label' => ucfirst($plan) . " obuna", 'amount' => $starsAmount]
                ]
            );

            $message = "⭐️ **Telegram Stars orqali to'lov:**\n\n" .
                       "Tarif: *" . ucfirst($plan) . "*\n" .
                       "Summa: *{$starsAmount} Stars*\n\n" .
                       "To'lov qilish uchun quyidagi tugmani bosing:";

            return [
                'success' => true,
                'type' => 'stars',
                'payment_id' => md5($payload . time()),
                'message' => $message,
                'data' => [
                    'invoice_link' => $invoiceLink,
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'type' => 'stars',
                'payment_id' => '',
                'message' => "Telegram Stars to'lov kvitansiyasini yaratishda xato: " . $e->getMessage(),
                'data' => []
            ];
        }
    }

    public function verifyPayment(string $paymentId, array $data = []): bool
    {
        // This is called when a successful_payment webhook triggers
        $userId = $data['user_id'] ?? null;
        $plan = $data['plan'] ?? 'premium';
        $starsAmount = $data['amount'] ?? 0;
        $telegramPaymentChargeId = $data['telegram_payment_charge_id'] ?? $paymentId;

        $user = User::find($userId);
        if (!$user) {
            throw new Exception("Telegram Stars verification failed: user not found.");
        }

        // Deactivate previous active subscriptions for the user
        Subscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->update(['status' => 'expired']);

        // Create new active subscription
        Subscription::create([
            'user_id' => $user->id,
            'plan' => $plan,
            'price' => $starsAmount, // Store Stars quantity in place of price
            'status' => 'active',
            'payment_method' => 'stars',
            'payment_id' => $telegramPaymentChargeId,
            'starts_at' => Carbon::now(),
            'expires_at' => Carbon::now()->addDays(30),
        ]);

        // Update user plan
        $user->update([
            'plan' => $plan,
            'daily_limit' => $plan === 'business' ? 999999 : 999999,
        ]);

        return true;
    }
}
