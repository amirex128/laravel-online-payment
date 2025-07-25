<?php

namespace PhpMonsters\Larapay;

use PhpMonsters\Log\Facades\XLog;
use PhpMonsters\Larapay\Contracts\LarapayTransaction as LarapayTransactionContract;
use PhpMonsters\Larapay\Exceptions\EmptyAmountException;
use PhpMonsters\Larapay\Facades\Larapay;
use Exception;

trait Payable
{
    /**
     * Get all transactions for this model.
     */
    public function transactions()
    {
        return $this->morphMany(app(LarapayTransactionContract::class), 'model');
    }

    /**
     * Get accomplished transactions for this model.
     */
    public function accomplishedTransactions()
    {
        return $this->morphMany(app(LarapayTransactionContract::class), 'model')->where('accomplished', true);
    }

    /**
     * Check if this model has any accomplished transactions.
     */
    public function isPaid(): bool
    {
        return $this->accomplishedTransactions()->exists();
    }

    /**
     * Get the total paid amount for this model.
     */
    public function paidAmount(): int
    {
        return $this->accomplishedTransactions()->sum('amount');
    }

    /**
     * Create a new transaction for this model.
     *
     * @param string $paymentGateway The payment gateway name
     * @param int|null $amount The transaction amount
     * @param string|null $description The transaction description
     * @param array $additionalData Additional data for the transaction
     * @param array $sharing Sharing data for the transaction
     * @return mixed
     * @throws EmptyAmountException
     */
    public function createTransaction(
        string $paymentGateway,
        ?int $amount = null,
        ?string $description = null,
        array $additionalData = [],
        array $sharing = []
    ) {
        $transactionData = [];

        $transactionData['amount'] = $amount ?? $this->getAmount();

        if (empty($transactionData['amount']) || $transactionData['amount'] <= 0) {
            throw new EmptyAmountException();
        }

        $paymentGateway = ucfirst(strtolower($paymentGateway));

        $transactionData['description'] = $description;
        $transactionData['gate_name'] = $paymentGateway;
        $transactionData['submitted'] = true;
        $transactionData['bank_order_id'] = $this->generateBankOrderId($paymentGateway);
        $transactionData['payment_method'] = 'ONLINE';
        $transactionData['additional_data'] = empty($additionalData) ? '{}' : json_encode($additionalData, JSON_UNESCAPED_UNICODE);
        $transactionData['sharing'] = empty($sharing) ? '{}' : json_encode($sharing, JSON_UNESCAPED_UNICODE);

        return $this->transactions()->create($transactionData);
    }

    /**
     * Get the amount for this model (converted to Rial).
     */
    public function getAmount(): int
    {
        return intval($this->amount) * 10;
    }

    /**
     * Generate a unique bank order ID.
     *
     * @param string|null $bank The bank name
     * @return int
     */
    public function generateBankOrderId(?string $bank = null): int
    {
        // handle each gateway exception
        switch ($bank) {
            default:
                return (int) (time() . mt_rand(10, 99));
        }
    }
}
