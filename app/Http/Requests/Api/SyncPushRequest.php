<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SyncPushRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'business_id' => ['required', 'integer'],
            'device_identifier' => ['required', 'string'],
            'request_id' => ['required', 'uuid'],
            'changes' => ['present', 'array'],

            // Categories
            'changes.categories' => ['sometimes', 'array', 'max:100'],
            'changes.categories.*' => ['array'],
            'changes.categories.*.sync_id' => ['required', 'uuid'],
            'changes.categories.*.base_sync_version' => ['nullable', 'integer', 'min:1'],
            'changes.categories.*.name' => ['required', 'string', 'max:255'],
            'changes.categories.*.status' => ['sometimes', 'string'],

            // Products
            'changes.products' => ['sometimes', 'array', 'max:100'],
            'changes.products.*' => ['array'],
            'changes.products.*.sync_id' => ['required', 'uuid'],
            'changes.products.*.base_sync_version' => ['nullable', 'integer', 'min:1'],
            'changes.products.*.category_sync_id' => ['nullable', 'uuid'],
            'changes.products.*.name' => ['required', 'string', 'max:255'],
            'changes.products.*.sku' => ['required', 'string', 'max:255'],
            'changes.products.*.barcode' => ['nullable', 'string', 'max:255'],
            'changes.products.*.price' => ['required', 'integer', 'min:0'],
            'changes.products.*.kind' => ['sometimes', 'string', 'in:product,service'],
            'changes.products.*.cost' => ['sometimes', 'numeric', 'min:0'],
            'changes.products.*.stock' => ['sometimes', 'numeric', 'min:0'],
            'changes.products.*.unit' => ['sometimes', 'string', 'max:50'],
            'changes.products.*.min_stock' => ['sometimes', 'numeric', 'min:0'],
            'changes.products.*.pricing_unit' => ['sometimes', 'string', 'max:50'],
            'changes.products.*.min_quantity' => ['sometimes', 'numeric', 'min:0'],
            'changes.products.*.estimated_duration' => ['nullable', 'string', 'max:255'],
            'changes.products.*.status' => ['sometimes', 'string'],

            // Customers
            'changes.customers' => ['sometimes', 'array', 'max:100'],
            'changes.customers.*' => ['array'],
            'changes.customers.*.sync_id' => ['required', 'uuid'],
            'changes.customers.*.base_sync_version' => ['nullable', 'integer', 'min:1'],
            'changes.customers.*.name' => ['required', 'string', 'max:255'],
            'changes.customers.*.phone' => ['nullable', 'string', 'max:255'],
            'changes.customers.*.email' => ['nullable', 'email', 'max:255'],
            'changes.customers.*.address' => ['nullable', 'string'],
            'changes.customers.*.notes' => ['nullable', 'string'],
            'changes.customers.*.status' => ['sometimes', 'string'],

            // Shifts
            'changes.shifts' => ['sometimes', 'array', 'max:100'],
            'changes.shifts.*' => ['array'],
            'changes.shifts.*.sync_id' => ['required', 'uuid'],
            'changes.shifts.*.base_sync_version' => ['nullable', 'integer', 'min:1'],
            'changes.shifts.*.shift_number' => ['required', 'string', 'max:255'],
            'changes.shifts.*.status' => ['sometimes', 'string'],
            'changes.shifts.*.opening_cash' => ['sometimes', 'integer', 'min:0'],
            'changes.shifts.*.closing_cash' => ['nullable', 'integer', 'min:0'],
            'changes.shifts.*.opened_at' => ['required', 'date'],
            'changes.shifts.*.closed_at' => ['nullable', 'date'],
            'changes.shifts.*.notes' => ['nullable', 'string'],

            // Sales
            'changes.sales' => ['sometimes', 'array', 'max:100'],
            'changes.sales.*' => ['array'],
            'changes.sales.*.sync_id' => ['required', 'uuid'],
            'changes.sales.*.base_sync_version' => ['nullable', 'integer', 'min:1'],
            'changes.sales.*.customer_sync_id' => ['nullable', 'uuid'],
            'changes.sales.*.shift_sync_id' => ['nullable', 'uuid'],
            'changes.sales.*.transaction_number' => ['required', 'string', 'max:255'],
            'changes.sales.*.status' => ['sometimes', 'string'],
            'changes.sales.*.subtotal' => ['required', 'integer', 'min:0'],
            'changes.sales.*.discount_amount' => ['sometimes', 'integer', 'min:0'],
            'changes.sales.*.tax_amount' => ['sometimes', 'integer', 'min:0'],
            'changes.sales.*.total_amount' => ['required', 'integer', 'min:0'],
            'changes.sales.*.payment_method' => ['nullable', 'string', 'max:50'],
            'changes.sales.*.payment_status' => ['sometimes', 'string', 'in:paid,unpaid'],
            'changes.sales.*.paid_at' => ['nullable', 'date'],
            'changes.sales.*.cash_received' => ['nullable', 'integer', 'min:0'],
            'changes.sales.*.change_amount' => ['nullable', 'integer', 'min:0'],
            'changes.sales.*.sold_at' => ['required', 'date'],

            // Sale Items
            'changes.sale_items' => ['sometimes', 'array', 'max:100'],
            'changes.sale_items.*' => ['array'],
            'changes.sale_items.*.sync_id' => ['required', 'uuid'],
            'changes.sale_items.*.base_sync_version' => ['nullable', 'integer', 'min:1'],
            'changes.sale_items.*.sale_sync_id' => ['required', 'uuid'],
            'changes.sale_items.*.product_sync_id' => ['required', 'uuid'],
            'changes.sale_items.*.product_name' => ['required', 'string', 'max:255'],
            'changes.sale_items.*.product_sku' => ['required', 'string', 'max:255'],
            'changes.sale_items.*.unit_price' => ['required', 'integer', 'min:0'],
            'changes.sale_items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'changes.sale_items.*.line_total' => ['required', 'integer', 'min:0'],

            // Expenses
            'changes.expenses' => ['sometimes', 'array', 'max:100'],
            'changes.expenses.*' => ['array'],
            'changes.expenses.*.sync_id' => ['required', 'uuid'],
            'changes.expenses.*.base_sync_version' => ['nullable', 'integer', 'min:1'],
            'changes.expenses.*.shift_sync_id' => ['nullable', 'uuid'],
            'changes.expenses.*.description' => ['required', 'string', 'max:255'],
            'changes.expenses.*.amount' => ['required', 'integer', 'min:0'],
            'changes.expenses.*.status' => ['sometimes', 'string'],
            'changes.expenses.*.occurred_at' => ['required', 'date'],
            'changes.expenses.*.notes' => ['nullable', 'string'],

            // Cash ledger
            'changes.cash_ledger' => ['sometimes', 'array', 'max:100'],
            'changes.cash_ledger.*' => ['array'],
            'changes.cash_ledger.*.sync_id' => ['required', 'uuid'],
            'changes.cash_ledger.*.base_sync_version' => ['nullable', 'integer', 'min:1'],
            'changes.cash_ledger.*.shift_sync_id' => ['nullable', 'uuid'],
            'changes.cash_ledger.*.type' => ['required', 'string', 'in:in,out'],
            'changes.cash_ledger.*.amount' => ['required', 'integer', 'min:1'],
            'changes.cash_ledger.*.category' => ['nullable', 'string', 'max:255'],
            'changes.cash_ledger.*.note' => ['nullable', 'string'],
            'changes.cash_ledger.*.reference_id' => ['nullable', 'string', 'max:255'],
            'changes.cash_ledger.*.sale_sync_id' => ['nullable', 'uuid'],
            'changes.cash_ledger.*.occurred_at' => ['required', 'date'],

            // Stock movements
            'changes.stock_movements' => ['sometimes', 'array', 'max:100'],
            'changes.stock_movements.*' => ['array'],
            'changes.stock_movements.*.sync_id' => ['required', 'uuid'],
            'changes.stock_movements.*.base_sync_version' => ['nullable', 'integer', 'min:1'],
            'changes.stock_movements.*.product_sync_id' => ['required', 'uuid'],
            'changes.stock_movements.*.movement_type' => ['required', 'string', 'max:50'],
            'changes.stock_movements.*.quantity_change' => ['required', 'numeric'],
            'changes.stock_movements.*.stock_before' => ['sometimes', 'numeric', 'min:0'],
            'changes.stock_movements.*.stock_after' => ['sometimes', 'numeric', 'min:0'],
            'changes.stock_movements.*.reference_id' => ['nullable', 'string', 'max:255'],
            'changes.stock_movements.*.category' => ['nullable', 'string', 'max:255'],
            'changes.stock_movements.*.note' => ['nullable', 'string'],
            'changes.stock_movements.*.sale_sync_id' => ['nullable', 'uuid'],
            'changes.stock_movements.*.occurred_at' => ['required', 'date'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $changes = $this->input('changes');
            if (is_array($changes)) {
                $allowedKeys = ['categories', 'products', 'customers', 'shifts', 'sales', 'sale_items', 'expenses', 'cash_ledger', 'stock_movements'];
                $unknownKeys = array_diff(array_keys($changes), $allowedKeys);
                if (! empty($unknownKeys)) {
                    $v->errors()->add('changes', 'The changes payload contains unsupported entity types.');
                }
            }
        });
    }
}
