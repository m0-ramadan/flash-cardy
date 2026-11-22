@php
    $editingProduct = $product ?? null;
    $selectedSource = old('fulfillment_source', $editingProduct?->fulfillment_source ?? ($editingProduct?->external_id ? 'like4app' : 'none'));
    $selectedMode = old('fulfillment_mode', $editingProduct?->fulfillment_mode ?? ($selectedSource === 'like4app' ? 'automatic' : 'manual'));
@endphp
<div class="card mb-4" id="digital-fulfillment-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-key me-2"></i>الاشتراك والتسليم الرقمي</h5>
        @if($editingProduct)
            <a href="{{ route('admin.products.serials.index', $editingProduct) }}" class="btn btn-sm btn-outline-primary">
                <i class="fas fa-list me-1"></i>إدارة الأكواد
                <span class="badge bg-primary ms-1">{{ $editingProduct->serials()->usable()->count() }}</span>
            </a>
        @endif
    </div>
    <div class="card-body">
        <div class="alert alert-info py-2">
            <strong>طريقة العمل:</strong> يمكنك بيع المنتج بدون أكواد جاهزة. عند اختيار "أكواد محلية" تحدد هل الإرسال تلقائي بعد تأكيد الدفع أو يدوي من الطلب. ويمكن إضافة كود جديد مباشرة من صفحة الطلب إذا نفد المخزون.
        </div>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label">نوع المنتج</label>
                <select class="form-select" name="type" id="product_type">
                    @foreach(['product'=>'منتج عادي','subscription'=>'اشتراك','gift_card'=>'بطاقة هدية','digital_code'=>'كود رقمي','service'=>'خدمة'] as $value=>$label)
                        <option value="{{ $value }}" @selected(old('type', $editingProduct?->type ?? 'product') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">طريقة التسليم</label>
                <select class="form-select" name="fulfillment_source" id="fulfillment_source">
                    <option value="none" @selected($selectedSource === 'none')>بدون كود / تنفيذ عادي</option>
                    <option value="local_serials" @selected($selectedSource === 'local_serials')>أكواد Serial مخزنة محلياً</option>
                    <option value="like4app" @selected($selectedSource === 'like4app')>Like4App</option>
                </select>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">وضع إرسال الكود</label>
                <select class="form-select" name="fulfillment_mode" id="fulfillment_mode">
                    <option value="automatic" @selected($selectedMode === 'automatic')>تلقائي بعد تأكيد الدفع</option>
                    <option value="manual" @selected($selectedMode === 'manual')>يدوي من شاشة الطلب</option>
                </select>
            </div>
        </div>

        <div class="row">
            <div class="col-md-3 mb-3"><label class="form-label">البراند</label><input class="form-control" name="brand" maxlength="150" value="{{ old('brand', $editingProduct?->brand) }}" placeholder="Netflix / Apple / PlayStation"></div>
            <div class="col-md-3 mb-3"><label class="form-label">المنطقة / الدولة</label><input class="form-control" name="region" maxlength="150" value="{{ old('region', $editingProduct?->region) }}" placeholder="Egypt / US / Global"></div>
            <div class="col-md-3 mb-3"><label class="form-label">الفئة / القيمة</label><input class="form-control" name="denomination" maxlength="100" value="{{ old('denomination', $editingProduct?->denomination) }}" placeholder="25 USD / 1 Month"></div>
            <div class="col-md-3 mb-3"><label class="form-label">مدة الصلاحية العامة</label><input class="form-control" name="validity_text" maxlength="255" value="{{ old('validity_text', $editingProduct?->validity_text) }}" placeholder="صالح 12 شهر"></div>
        </div>
        <div class="row mb-2">
            <div class="col-md-3 form-check form-switch ms-3"><input type="hidden" name="is_featured" value="0"><input class="form-check-input" type="checkbox" name="is_featured" value="1" id="is_featured" @checked(old('is_featured', $editingProduct?->is_featured ?? false))><label class="form-check-label" for="is_featured">منتج مميز</label></div>
            <div class="col-md-3 form-check form-switch ms-3"><input type="hidden" name="is_best_seller" value="0"><input class="form-check-input" type="checkbox" name="is_best_seller" value="1" id="is_best_seller" @checked(old('is_best_seller', $editingProduct?->is_best_seller ?? false))><label class="form-check-label" for="is_best_seller">الأكثر مبيعاً</label></div>
        </div>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label">عنوان فرعي</label>
                <input class="form-control" type="text" name="subtitle" maxlength="255" value="{{ old('subtitle', $editingProduct?->subtitle) }}">
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Provider Product ID / Like4App ID</label>
                <input class="form-control" type="text" name="external_id" maxlength="255" value="{{ old('external_id', $editingProduct?->external_id) }}">
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">أقصى كمية لكل طلب (0 = بدون حد)</label>
                <input class="form-control" type="number" min="0" name="max_quantity" value="{{ old('max_quantity', $editingProduct?->max_quantity ?? 0) }}">
            </div>
        </div>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label">نوع المخزون</label>
                <select class="form-select" name="inventory_type" id="inventory_type">
                    @foreach(['physical'=>'فعلي','digital'=>'رقمي','service'=>'خدمة'] as $value=>$label)
                        <option value="{{ $value }}" @selected(old('inventory_type', $editingProduct?->inventory_type ?? 'physical') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-8 mb-3 d-flex flex-wrap gap-4 align-items-end pb-2">
                <div class="form-check form-switch">
                    <input type="hidden" name="track_stock" value="0">
                    <input class="form-check-input" type="checkbox" name="track_stock" value="1" id="track_stock" @checked(old('track_stock', $editingProduct?->track_stock ?? true))>
                    <label class="form-check-label" for="track_stock">تتبع مخزون فعلي</label>
                </div>
                <div class="form-check form-switch">
                    <input type="hidden" name="allow_serial_backorder" value="0">
                    <input class="form-check-input" type="checkbox" name="allow_serial_backorder" value="1" id="allow_serial_backorder" @checked(old('allow_serial_backorder', $editingProduct?->allow_serial_backorder ?? true))>
                    <label class="form-check-label" for="allow_serial_backorder">السماح بإنشاء الطلب عند نفاد الأكواد</label>
                </div>
                <div class="form-check form-switch">
                    <input type="hidden" name="includes_shipping" value="0">
                    <input class="form-check-input" type="checkbox" name="includes_shipping" value="1" id="includes_shipping" @checked(old('includes_shipping', $editingProduct?->includes_shipping ?? false))>
                    <label class="form-check-label" for="includes_shipping">يشمل شحن</label>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">تعليمات الاستخدام</label>
                <textarea class="form-control" name="instructions" rows="3">{{ old('instructions', $editingProduct?->instructions) }}</textarea>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">الشروط</label>
                <textarea class="form-control" name="terms" rows="3">{{ old('terms', $editingProduct?->terms) }}</textarea>
            </div>
            <div class="col-md-12 mb-3">
                <label class="form-label">رابط مرجعي / صفحة المزود</label>
                <input class="form-control" type="url" name="url" maxlength="500" value="{{ old('url', $editingProduct?->url) }}">
            </div>
        </div>

        <div id="initial-serials-section" style="{{ $selectedSource === 'local_serials' ? '' : 'display:none' }}">
            <hr>
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div>
                    <h6 class="mb-1"><i class="fas fa-barcode me-1"></i>إضافة أكواد مع المنتج</h6>
                    <small class="text-muted">اختياري. كل كود يمكن أن يكون له تاريخ انتهاء وPIN وملاحظة. الأكواد لا تظهر كاملة في القوائم بعد الحفظ.</small>
                </div>
                <button type="button" class="btn btn-sm btn-outline-success" id="add-serial-row"><i class="fas fa-plus"></i> كود جديد</button>
            </div>
            <div id="serial-rows">
                @php $oldSerials = old('serials', []); @endphp
                @if(empty($oldSerials))
                    @php $oldSerials = [['code'=>'','pin'=>'','expires_at'=>'','notes'=>'']]; @endphp
                @endif
                @foreach($oldSerials as $i=>$serial)
                    <div class="row g-2 mb-2 serial-entry">
                        <div class="col-md-4"><input class="form-control" name="serials[{{ $i }}][code]" placeholder="Serial / Voucher Code" value="{{ $serial['code'] ?? '' }}"></div>
                        <div class="col-md-2"><input class="form-control" name="serials[{{ $i }}][pin]" placeholder="PIN اختياري" value="{{ $serial['pin'] ?? '' }}"></div>
                        <div class="col-md-2"><input class="form-control" type="datetime-local" name="serials[{{ $i }}][expires_at]" value="{{ $serial['expires_at'] ?? '' }}"></div>
                        <div class="col-md-3"><input class="form-control" name="serials[{{ $i }}][notes]" placeholder="ملاحظة" value="{{ $serial['notes'] ?? '' }}"></div>
                        <div class="col-md-1"><button type="button" class="btn btn-outline-danger remove-serial-row"><i class="fas fa-times"></i></button></div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const source = document.getElementById('fulfillment_source');
    const section = document.getElementById('initial-serials-section');
    const inventoryType = document.getElementById('inventory_type');
    const trackStock = document.getElementById('track_stock');
    const mode = document.getElementById('fulfillment_mode');
    const rows = document.getElementById('serial-rows');
    let serialIndex = rows ? rows.querySelectorAll('.serial-entry').length : 0;

    function syncFulfillmentUi() {
        const digital = ['local_serials', 'like4app'].includes(source?.value);
        if (section) section.style.display = source?.value === 'local_serials' ? '' : 'none';
        if (digital && inventoryType) inventoryType.value = 'digital';
        if (digital && trackStock) trackStock.checked = false;
        if (source?.value === 'like4app' && mode) mode.value = 'automatic';
    }
    source?.addEventListener('change', syncFulfillmentUi);
    syncFulfillmentUi();

    document.getElementById('add-serial-row')?.addEventListener('click', function () {
        const wrapper = document.createElement('div');
        wrapper.className = 'row g-2 mb-2 serial-entry';
        wrapper.innerHTML = `
            <div class="col-md-4"><input class="form-control" name="serials[${serialIndex}][code]" placeholder="Serial / Voucher Code"></div>
            <div class="col-md-2"><input class="form-control" name="serials[${serialIndex}][pin]" placeholder="PIN اختياري"></div>
            <div class="col-md-2"><input class="form-control" type="datetime-local" name="serials[${serialIndex}][expires_at]"></div>
            <div class="col-md-3"><input class="form-control" name="serials[${serialIndex}][notes]" placeholder="ملاحظة"></div>
            <div class="col-md-1"><button type="button" class="btn btn-outline-danger remove-serial-row"><i class="fas fa-times"></i></button></div>`;
        rows.appendChild(wrapper); serialIndex++;
    });
    rows?.addEventListener('click', function (e) {
        const button = e.target.closest('.remove-serial-row');
        if (button && rows.querySelectorAll('.serial-entry').length > 1) button.closest('.serial-entry').remove();
    });
});
</script>
