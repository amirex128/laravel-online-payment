<?php
declare(strict_types=1);

namespace PhpMonsters\Larapay\Models;

use Illuminate\Database\Eloquent\Model;
use PhpMonsters\Log\Facades\XLog;
use PhpMonsters\Larapay\Exceptions\FailedReverseTransactionException;
use PhpMonsters\Larapay\Facades\Larapay;
use PhpMonsters\Larapay\Models\Traits\OnlineTransactionTrait;
use PhpMonsters\Larapay\Transaction\TransactionInterface;
use Illuminate\Database\Eloquent\SoftDeletes;
use Exception;

class LarapayTransaction extends Model implements TransactionInterface
{
    use SoftDeletes;
    use OnlineTransactionTrait;

    protected $table = 'larapay_transactions';

    protected $fillable = [
        'accomplished',
        'gate_name',
        'amount',
        'bank_order_id',
        'gate_refid',
        'gate_status',
        'paid_at',
        'verified',
        'after_verified',
        'reversed',
        'submitted',
        'approved',
        'rejected',
        'description',
        'extra_params',
        'model',
        'additional_data',
        'sharing',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'paid_at' => 'datetime',
        'accomplished' => 'boolean',
        'verified' => 'boolean',
        'after_verified' => 'boolean',
        'reversed' => 'boolean',
        'submitted' => 'boolean',
        'approved' => 'boolean',
        'rejected' => 'boolean',
        'amount' => 'integer',
        'extra_params' => 'array',
        'additional_data' => 'array',
        'sharing' => 'array',
    ];

    /**
     * Get the parent model that this transaction belongs to.
     */
    public function model()
    {
        return $this->morphTo();
    }

    /**
     * Reverse this transaction.
     *
     * @return bool
     * @throws FailedReverseTransactionException
     */
    public function reverseTransaction(): bool
    {
        //make payment gateway handler
        $gatewayProperties     = json_decode($this->extra_params, true);
        $paymentGatewayHandler = Larapay::make($this->gate_name, $this, $gatewayProperties);
        //$paymentGatewayHandler->setParameters($gatewayProperties);
        //get reference id
        $referenceId = $paymentGatewayHandler->getGatewayReferenceId();
        //try 3 times to reverse transaction
        $reversed = false;
        for ($i = 1; $i <= 3; $i++) {
            try {
                $reverseResult = $paymentGatewayHandler->reverse();
                if ($reverseResult) {
                    $reversed = true;
                }

                break;
            } catch (Exception $e) {
                XLog::error('Exception: ' . $e->getMessage(), ['try' => $i, 'tag' => $referenceId]);
                usleep(500);
                continue;
            }
        }
        //throw exception when 3 times failed
        if ($reversed !== true) {
            XLog::error('invoice reverse failed', ['tag' => $referenceId]);
            throw new FailedReverseTransactionException(trans('larapay::larapay.reversed_failed'));
        }

        //set reversed flag
        $this->reversed = true;
        $this->save();
        //log true result
        XLog::info('invoice reversed successfully', ['tag' => $referenceId]);

        return true;
    }

    /**
     * Generate payment form HTML.
     *
     * @param bool $autoSubmit Whether to auto-submit the form
     * @param string|null $callback Custom callback route name
     * @param array $adapterConfig Adapter configuration
     * @return string
     */
    public function generateForm(bool $autoSubmit = false, ?string $callback = null, array $adapterConfig = []): string
    {
        $paymentGatewayHandler = $this->gatewayHandler($adapterConfig);

        $callbackRoute = route(config("larapay.payment_callback"), [
            'gateway'        => $this->gate_name,
            'transaction_id' => $this->id,
        ]);

        if ($callback != null) {
            $callbackRoute = route($callback, [
                'gateway'        => $this->gate_name,
                'transaction_id' => $this->id,
            ]);
        }

        $paymentParams = [
            'order_id'     => $this->getBankOrderId(),
            'redirect_url' => $callbackRoute,
            'amount'       => $this->amount,
            'sharing'      => json_decode($this->sharing, true),
            'submit_label' => trans('larapay::larapay.goto_gate'),
        ];

        try {
            if ($autoSubmit) {
                $paymentParams['auto_submit'] = true;
            }

            $form = $paymentGatewayHandler->form($paymentParams);

            return $form;
        } catch (Exception $e) {
            XLog::emergency($this->gate_name . ' #' . $e->getCode() . '-' . $e->getMessage());

            throw $e;
        }
    }

    /**
     * Get payment form parameters.
     *
     * @param string|null $callback Custom callback route name
     * @param array $adapterConfig Adapter configuration
     * @return array
     */
    public function formParams(?string $callback = null, array $adapterConfig = []): array
    {
        $paymentGatewayHandler = $this->gatewayHandler($adapterConfig);

        $callbackRoute = route(config("larapay.payment_callback"), [
            'gateway'        => $this->gate_name,
            'transaction_id' => $this->id,
        ]);

        if ($callback != null) {
            $callbackRoute = route($callback, [
                'gateway'        => $this->gate_name,
                'transaction_id' => $this->id,
            ]);
        }

        $paymentParams = [
            'order_id'     => $this->getBankOrderId(),
            'redirect_url' => $callbackRoute,
            'amount'       => $this->amount,
            'sharing'      => json_decode($this->sharing, true),
            'submit_label' => trans('larapay::larapay.goto_gate'),
        ];

        try {
            return $paymentGatewayHandler->formParams($paymentParams);
        } catch (Exception $e) {
            XLog::emergency($this->gate_name . ' #' . $e->getCode() . '-' . $e->getMessage());

            return [];
        }
    }

    /**
     * Get the gateway handler for this transaction.
     *
     * @param array $adapterConfig Adapter configuration
     * @return mixed
     */
    public function gatewayHandler(array $adapterConfig = [])
    {
        return Larapay::make($this->gate_name, $this, $adapterConfig);
    }
}
