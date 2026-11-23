<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentScheme;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentSchemeController extends Controller
{
    public function index()
    {
        return response()->json(['success' => true, 'data' => PaymentScheme::all()], 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'scheme_name'          => ['required', 'string', 'max:50', Rule::unique('payment_schemes', 'scheme_name')->whereNull('deleted_at')],
            'scheme_type'          => ['required', Rule::in(PaymentScheme::TYPES)],
            'description'          => 'nullable|string',
            'down_payment_percent' => 'required|numeric|between:0,100',
            'installment_months'   => 'required|integer|min:0',
            'interest_rate'        => 'required|numeric|between:0,100',
            'is_active'            => 'nullable|boolean',
        ]);

        $scheme = PaymentScheme::create($validated);
        return response()->json(['success' => true, 'data' => $scheme], 201);
    }

    public function show($id)
    {
        $scheme = PaymentScheme::find($id);
        if (!$scheme) return response()->json(['message' => 'Skema pembayaran tidak ditemukan'], 404);
        return response()->json(['success' => true, 'data' => $scheme], 200);
    }

    public function update(Request $request, $id)
    {
        $scheme = PaymentScheme::find($id);
        if (!$scheme) return response()->json(['message' => 'Skema pembayaran tidak ditemukan'], 404);

        $validated = $request->validate([
            'scheme_name'          => ['sometimes', 'string', 'max:50', Rule::unique('payment_schemes', 'scheme_name')->ignore($id)->whereNull('deleted_at')],
            'scheme_type'          => ['sometimes', Rule::in(PaymentScheme::TYPES)],
            'description'          => 'nullable|string',
            'down_payment_percent' => 'sometimes|numeric|between:0,100',
            'installment_months'   => 'sometimes|integer|min:0',
            'interest_rate'        => 'sometimes|numeric|between:0,100',
            'is_active'            => 'sometimes|boolean',
        ]);

        $scheme->update($validated);
        return response()->json(['success' => true, 'data' => $scheme], 200);
    }

    /**
     * Simulasi uang muka, angsuran, dan bunga untuk satu harga unit (kebutuhan c.3).
     */
    public function simulate(Request $request, $id)
    {
        $scheme = PaymentScheme::find($id);

        if (!$scheme) {
            return response()->json(['message' => 'Skema pembayaran tidak ditemukan'], 404);
        }

        $validated = $request->validate([
            'total_price' => 'required_without:unit_id|numeric|min:0',
            'unit_id'     => 'nullable|exists:units,id',
        ]);

        $totalPrice = (float) ($validated['total_price'] ?? 0);

        if ($totalPrice <= 0 && !empty($validated['unit_id'])) {
            $totalPrice = (float) Unit::findOrFail($validated['unit_id'])->price;
        }

        $breakdown = $scheme->calculateBreakdown($totalPrice);

        return response()->json([
            'success' => true,
            'data'    => $breakdown + [
                'total_price'   => round($totalPrice, 2),
                'scheme_name'   => $scheme->scheme_name,
                'scheme_type'   => $scheme->scheme_type,
            ],
        ], 200);
    }

    public function destroy($id)
    {
        $scheme = PaymentScheme::find($id);
        if (!$scheme) return response()->json(['message' => 'Skema pembayaran tidak ditemukan'], 404);
        $scheme->delete();
        return response()->json(['success' => true, 'message' => 'Deleted'], 200);
    }
}