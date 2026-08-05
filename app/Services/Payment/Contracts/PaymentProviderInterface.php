<?php

namespace App\Services\Payment\Contracts;

use App\Models\User;

interface PaymentProviderInterface
{
    /**
     * Create an invoice or invoice instructions for a user.
     *
     * @param User $user
     * @param float $amount
     * @param string $plan
     * @return array{
     *     success: bool,
     *     type: string, // manual or stars
     *     payment_id: string,
     *     message: string,
     *     data: array
     * }
     */
    public function createInvoice(User $user, float $amount, string $plan): array;

    /**
     * Verify payment status.
     *
     * @param string $paymentId
     * @param array $data
     * @return bool
     */
    public function verifyPayment(string $paymentId, array $data = []): bool;
}
