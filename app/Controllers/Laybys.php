<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Customer;
use App\Models\Laybyinstallment;
use App\Models\Laybyplan;

/**
 * Layby / Installment Payment Plan module.
 *
 * Create layby plans, track installments, record payments,
 * manage overdue accounts.
 */
class Laybys extends Secure_Controller
{
    protected Laybyplan $laybyPlan;
    protected Laybyinstallment $installment;
    protected Customer $customer;

    public function __construct()
    {
        parent::__construct('laybys');
        $this->laybyPlan   = model(Laybyplan::class);
        $this->installment = model(Laybyinstallment::class);
        $this->customer    = model(Customer::class);
    }

    /**
     * GET /laybys — Dashboard with summary + list
     */
    public function index()
    {
        $data['title']         = lang('Laybys.title');
        $data['status_counts'] = $this->laybyPlan->getStatusCounts();
        $data['summary']       = $this->laybyPlan->getSummary();
        $data['laybys']        = $this->laybyPlan->orderBy('created_at', 'DESC')->findAll();
        $data['overdue']       = $this->installment->getOverdue();
        $data['upcoming']      = $this->installment->getUpcoming(14);

        echo view('laybys/index', $data);
    }

    /**
     * GET /laybys/view/(:num) — Layby detail + installment schedule
     */
    public function view(int $id = 0)
    {
        $data['layby'] = $this->laybyPlan->find($id);
        if (! $data['layby']) {
            return redirect()->to('/laybys');
        }

        $data['title']           = lang('Laybys.detail') . ' ' . $data['layby']->layby_number;
        $data['installments']    = $this->installment->getByLayby($id);
        $data['customer']        = $this->customer->get_info($data['layby']->customer_id);
        $data['payment_methods'] = ['cash', 'debit_card', 'credit_card', 'bank_transfer', 'ecocash', 'pesepay'];

        echo view('laybys/view', $data);
    }

    /**
     * GET /laybys/new — Create new layby
     */
    public function new()
    {
        $data['title']     = lang('Laybys.new_layby');
        $data['layby']     = null;
        $data['customers'] = $this->customer->get_all()->getResult();
        $data['employees'] = $this->employee->get_all()->getResult();

        echo view('laybys/form', $data);
    }

    /**
     * GET /laybys/edit/(:num)
     */
    public function edit(int $id = 0)
    {
        $data['layby'] = $this->laybyPlan->find($id);
        if (! $data['layby']) {
            return redirect()->to('/laybys');
        }

        $data['title']     = lang('Laybys.edit_layby') . ' #' . $data['layby']->layby_number;
        $data['customers'] = $this->customer->get_all()->getResult();
        $data['employees'] = $this->employee->get_all()->getResult();

        echo view('laybys/form', $data);
    }

    /**
     * POST /laybys/save — Save layby plan + generate installments
     */
    public function save()
    {
        $id = (int) $this->request->getPost('id');

        $total   = (float) ($this->request->getPost('total_amount') ?: 0);
        $deposit = (float) ($this->request->getPost('deposit_amount') ?: 0);
        $rate    = (float) ($this->request->getPost('interest_rate') ?: 0);
        $count   = (int) ($this->request->getPost('installment_count') ?: 1);
        $freq    = $this->request->getPost('frequency') ?: 'monthly';

        // Calculate interest
        $interestAmt       = round($total * ($rate / 100), 2);
        $totalWithInterest = $total + $interestAmt;
        $balance           = $totalWithInterest - $deposit;

        // Calculate installment amount
        $installmentAmount = $count > 0 ? round($balance / $count, 2) : $balance;

        // Calculate end date
        $startDate = $this->request->getPost('start_date') ?: date('Y-m-d');
        $endDate   = match ($freq) {
            'weekly'   => date('Y-m-d', strtotime("{$startDate} + " . ($count * 7) . ' days')),
            'biweekly' => date('Y-m-d', strtotime("{$startDate} + " . ($count * 14) . ' days')),
            'monthly'  => date('Y-m-d', strtotime("{$startDate} + {$count} months")),
            default    => date('Y-m-d', strtotime("{$startDate} + {$count} months")),
        };

        $input = [
            'customer_id'         => (int) ($this->request->getPost('customer_id') ?: 0),
            'employee_id'         => (int) ($this->request->getPost('employee_id') ?: 0) ?: null,
            'total_amount'        => $total,
            'deposit_amount'      => $deposit,
            'balance'             => $balance,
            'interest_rate'       => $rate,
            'interest_amount'     => $interestAmt,
            'total_with_interest' => $totalWithInterest,
            'installment_amount'  => $installmentAmount,
            'installment_count'   => $count,
            'frequency'           => $freq,
            'start_date'          => $startDate,
            'end_date'            => $endDate,
            'status'              => 'active',
            'document_signed'     => (int) $this->request->getPost('document_signed'),
            'notes'               => $this->request->getPost('notes'),
        ];

        if ($id > 0) {
            $this->laybyPlan->update($id, $input);
            $laybyId = $id;
        } else {
            $input['layby_number'] = $this->laybyPlan->generateLaybyNumber();
            $laybyId               = $this->laybyPlan->insert($input, true);

            // Generate installment schedule
            $this->generateInstallments($laybyId, $count, $installmentAmount, $startDate, $freq);
        }

        return redirect()->to('/laybys/view/' . $laybyId)->with('message', lang('Laybys.saved'));
    }

    /**
     * POST /laybys/pay/(:num) — Record a payment on an installment
     */
    public function pay(int $installmentId = 0)
    {
        $amount = (float) ($this->request->getPost('amount') ?: 0);
        $method = $this->request->getPost('payment_method') ?: 'cash';
        $ref    = $this->request->getPost('reference_number');

        $this->installment->recordPayment($installmentId, $amount, $method, $ref);

        // Update layby balance
        $installment = $this->installment->find($installmentId);
        if ($installment) {
            $this->refreshLaybyBalance($installment->layby_id);
        }

        return redirect()->back()->with('message', lang('Laybys.payment_recorded'));
    }

    /**
     * POST /laybys/cancel/(:num)
     */
    public function cancel(int $id = 0)
    {
        $this->laybyPlan->update($id, ['status' => 'cancelled']);

        return redirect()->to('/laybys')->with('message', lang('Laybys.cancelled'));
    }

    /**
     * POST /laybys/mark_signed/(:num) — Mark document as signed
     */
    public function markSigned(int $id = 0)
    {
        $this->laybyPlan->update($id, ['document_signed' => 1]);

        return redirect()->to('/laybys/view/' . $id)->with('message', lang('Laybys.document_signed'));
    }

    // ── Helpers ──

    private function generateInstallments(int $laybyId, int $count, float $amount, string $startDate, string $frequency): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $dueDate = match ($frequency) {
                'weekly'   => date('Y-m-d', strtotime("{$startDate} + " . (($i - 1) * 7) . ' days')),
                'biweekly' => date('Y-m-d', strtotime("{$startDate} + " . (($i - 1) * 14) . ' days')),
                'monthly'  => date('Y-m-d', strtotime("{$startDate} + " . ($i - 1) . ' months')),
                default    => date('Y-m-d', strtotime("{$startDate} + " . ($i - 1) . ' months')),
            };

            $this->installment->insert([
                'layby_id'           => $laybyId,
                'installment_number' => $i,
                'amount_due'         => $amount,
                'amount_paid'        => 0,
                'due_date'           => $dueDate,
                'status'             => 'pending',
            ]);
        }
    }

    private function refreshLaybyBalance(int $laybyId): void
    {
        $installments = $this->installment->getByLayby($laybyId);
        $totalPaid    = array_sum(array_column($installments, 'amount_paid'));

        $layby = $this->laybyPlan->find($laybyId);
        if (! $layby) {
            return;
        }

        $newBalance = $layby->total_with_interest - $layby->deposit_amount - $totalPaid;
        $newBalance = max(0, $newBalance);

        $newStatus = 'active';
        if ($newBalance <= 0) {
            $newStatus = 'paid';
        } elseif ($layby->end_date && $layby->end_date < date('Y-m-d')) {
            $newStatus = 'overdue';
        }

        $this->laybyPlan->update($laybyId, [
            'balance' => $newBalance,
            'status'  => $newStatus,
        ]);
    }
}
