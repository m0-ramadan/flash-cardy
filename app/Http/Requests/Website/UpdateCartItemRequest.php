<?php
namespace App\Http\Requests\Website;
use Illuminate\Foundation\Http\FormRequest;
class UpdateCartItemRequest extends FormRequest {
    public function authorize(): bool { return (bool)$this->user(); }
    public function rules(): array { return [
        'quantity'=>'sometimes|integer|min:1|max:10000','is_sample'=>'sometimes|boolean','note'=>'nullable|string|max:2000','quantity_id'=>'nullable|integer',
        'size_id'=>'nullable|integer|exists:sizes,id','color_id'=>'nullable|integer|exists:colors,id','material_id'=>'nullable|integer|exists:materials,id',
        'printing_method_id'=>'nullable|integer|exists:printing_methods,id','design_service_id'=>'nullable|integer|exists:design_services,id',
        'print_locations'=>'nullable|array|max:20','print_locations.*'=>'integer|distinct|exists:print_locations,id',
        'embroider_locations'=>'nullable|array|max:20','embroider_locations.*'=>'integer|distinct|exists:embroider_locations,id',
        'selected_options'=>'nullable|array|max:50','selected_options.*'=>'integer|distinct|exists:product_options,id',
    ]; }
}
