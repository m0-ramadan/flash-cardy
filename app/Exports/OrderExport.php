<?php

namespace App\Exports;

use App\Models\Order;
use App\Services\LikeCardService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class OrderExport implements FromArray, WithHeadings, ShouldAutoSize
{
    protected $order;
    protected $likeCardService;

    public function __construct(Order $order)
    {
        $this->order = $order->load(['user', 'items.product']);
        $this->likeCardService = app(LikeCardService::class);
    }

    public function headings(): array
    {
        return [
            'رقم الطلب',
            'الحالة',
            'المزود',
            'اسم العميل',
            'البريد الإلكتروني',
            'الهاتف',
            'اسم المنتج',
            'الكمية',
            'سعر الوحدة',
            'الإجمالي',
            'الرقم التسلسلي',
            'Serial Code',
            'قسائم الشراء',
            'الرقم الخارجي للمنتج',
            'ملاحظة المنتج',
            'ملاحظات الطلب',
            'طريقة الدفع',
            'رقم المعاملة',
            'رقم الطلب الخارجي',
            'تاريخ الإنشاء',
        ];
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->order->items as $item) {
            $serialNumbers = [];
            $serialCodes = [];
            $voucherCodes = [];

            if (!empty($item->serial_codes) && is_array($item->serial_codes)) {
                foreach ($item->serial_codes as $code) {
                    if (is_array($code)) {
                        $serialNumber = $code['serial_number'] ?? null;
                        $serialCode   = $code['serial_code'] ?? null;
                        $voucherCode  = $code['voucher_code'] ?? null;

                        if (!empty($serialNumber)) {
                            $serialNumbers[] = $serialNumber;
                        }

                        if (!empty($serialCode)) {
                            $serialCodes[] = $serialCode;
                        }

                        if (!empty($voucherCode)) {
                            $voucherCodes[] = $voucherCode;
                        } elseif (!empty($serialCode)) {
                            try {
                                $decrypted = $this->likeCardService->decryptSerial($serialCode);
                                if (!empty($decrypted)) {
                                    $voucherCodes[] = $decrypted;
                                }
                            } catch (\Throwable $e) {
                            }
                        }
                    } elseif (is_string($code)) {
                        $serialNumbers[] = $code;
                    }
                }
            }

            $rows[] = [
                $this->order->order_number,
                $this->order->status_label ?? $this->order->status,
                $this->order->provider_label ?? ($this->order->provider == 'like4app' ? 'لايك كارد' : 'داخلي'),
                $this->order->customer_name,
                $this->order->customer_email,
                $this->order->customer_phone,
                $item->product->name ?? 'منتج محذوف',
                $item->quantity,
                $item->price_per_unit,
                $item->total_price,
                implode(' | ', $serialNumbers),
                implode(' | ', $serialCodes),
                implode(' | ', $voucherCodes),
                $item->product->external_id ?? '',
                $item->note ?? '',
                $this->order->notes ?? '',
                $this->getPaymentMethodLabel($this->order->payment_method),
                $this->order->transaction_id ?? '',
                $this->order->provider_order_id ?? '',
                optional($this->order->created_at)->format('Y-m-d H:i:s'),
            ];
        }

        if (empty($rows)) {
            $rows[] = [
                $this->order->order_number,
                $this->order->status_label ?? $this->order->status,
                $this->order->provider_label ?? ($this->order->provider == 'like4app' ? 'لايك كارد' : 'داخلي'),
                $this->order->customer_name,
                $this->order->customer_email,
                $this->order->customer_phone,
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                $this->order->notes ?? '',
                $this->getPaymentMethodLabel($this->order->payment_method),
                $this->order->transaction_id ?? '',
                $this->order->provider_order_id ?? '',
                optional($this->order->created_at)->format('Y-m-d H:i:s'),
            ];
        }

        return $rows;
    }

    private function getPaymentMethodLabel($method): string
    {
        return match ($method) {
            'cash' => 'نقداً',
            'credit_card' => 'بطاقة ائتمان',
            'bank_transfer' => 'تحويل بنكي',
            'wallet' => 'محفظة إلكترونية',
            default => $method ?? '--',
        };
    }
}