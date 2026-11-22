<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\User;
use App\Services\Wallet\UserWalletService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class WalletController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private UserWalletService $walletService)
    {
        $this->middleware(['auth:sanctum', 'user.active']);
    }

    public function getBalance(Request $request)
    {
        try {
            return $this->successResponse($this->walletService->getBalance($request->user()), 'تم الحصول على رصيد المحفظة بنجاح');
        } catch (\Throwable $e) {
            report($e);
            return $this->errorResponse('فشل في الحصول على رصيد المحفظة', 500);
        }
    }

    public function initiateDeposit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:10|max:50000',
            'payment_method' => 'sometimes|in:paymob',
        ]);
        if ($validator->fails()) return $this->validationError($validator->errors(), 'خطأ في البيانات المرسلة');

        try {
            $result = $this->walletService->initiateDeposit($request->user(), (float) $request->amount, [
                'payment_method' => $request->payment_method ?? 'paymob',
            ]);
            return $this->successResponse($result, 'تم إنشاء طلب الدفع بنجاح');
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function withdraw(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:50',
            'bank_account_id' => 'required|exists:bank_accounts,id',
            'description' => 'nullable|string|max:255',
            'payment_identifier' => 'required|string|max:255',
        ]);
        if ($validator->fails()) return $this->validationError($validator->errors(), 'خطأ في البيانات المرسلة');

        try {
            $entry = $this->walletService->withdraw($request->user(), (float) $request->amount, [
                'bank_account_id' => $request->bank_account_id,
                'description' => $request->description,
                'payment_identifier' => $request->payment_identifier,
                'idempotency_key' => $request->header('Idempotency-Key') ?: 'withdraw_' . Str::uuid(),
            ]);
            return $this->successResponse([
                'entry' => ['id' => $entry->id, 'reference' => $entry->reference, 'amount' => $entry->amount, 'status' => $entry->status],
            ], 'تم تقديم طلب السحب بنجاح');
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function transfer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
            'to_user_id' => ['required', 'exists:users,id', \Illuminate\Validation\Rule::notIn([$request->user()->id])],
            'description' => 'nullable|string|max:255',
        ]);
        if ($validator->fails()) return $this->validationError($validator->errors(), 'خطأ في البيانات المرسلة');

        try {
            $toUser = User::findOrFail($request->to_user_id);
            $transfer = $this->walletService->transfer($request->user(), $toUser, (float) $request->amount, [
                'description' => $request->description,
                'idempotency_key' => $request->header('Idempotency-Key') ?: 'transfer_' . Str::uuid(),
            ]);
            return $this->successResponse([
                'transfer' => [
                    'id' => $transfer->id,
                    'reference' => $transfer->reference,
                    'debit_entry_id' => $transfer->debit_entry_id,
                    'credit_entry_id' => $transfer->credit_entry_id,
                    'amount' => $transfer->amount,
                    'currency' => $transfer->currency,
                ],
            ], 'تم التحويل بنجاح');
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function getTransactions(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'nullable|string|max:30',
            'status' => 'nullable|string|max:30',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);
        if ($validator->fails()) return $this->validationError($validator->errors(), 'خطأ في البيانات المرسلة');

        try {
            $entries = $this->walletService->getTransactionHistory($request->user(), $request->only(['type', 'status', 'start_date', 'end_date', 'limit']));
            return $this->paginated($entries, 'تم جلب سجل المعاملات بنجاح');
        } catch (\Throwable $e) {
            report($e);
            return $this->errorResponse('فشل في الحصول على سجل المعاملات', 500);
        }
    }

    public function getBanks(Request $request)
    {
        $wallet = $request->user()->userWallet ?? $request->user()->createUserWallet();
        $banks = BankAccount::query()
            ->where('is_active', true)
            ->where(function ($q) use ($request) {
                $q->whereNull('user_id')->orWhere('user_id', $request->user()->id);
            })
            ->where(function ($q) use ($wallet) {
                $q->whereNull('currency')->orWhere('currency', $wallet->currency);
            })
            ->select(['id', 'name', 'image', 'type', 'account_holder', 'account_identifier', 'currency'])
            ->get();
        return $this->successResponse($banks, 'تم جلب الحسابات البنكية');
    }
}
