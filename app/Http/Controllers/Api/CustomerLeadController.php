<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerLead;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerLeadController extends Controller
{
    // 1. Tampilkan semua data (lengkap dengan filter & pencarian)
    public function index(Request $request)
    {
        $query = CustomerLead::with(['salesProfile.user', 'unit']);

        // Sales hanya melihat calon konsumen yang menjadi tanggung jawabnya.
        if ($request->user()->role === User::ROLE_SALES && $request->user()->salesProfile) {
            $query->where('sales_id', $request->user()->salesProfile->id);
        } elseif ($request->filled('sales_id')) {
            $query->where('sales_id', $request->sales_id);
        }

        // Search berdasarkan nama, phone, email, atau interested_unit
        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('interested_unit', 'like', "%{$search}%");
            });
        }

        // Filter berdasarkan status ('cold','warm','hot','booking')
        if ($request->has('status') && $request->status != '') {
            $query->where('status', $request->status);
        }

        return response()->json([
            'success' => true,
            'data'    => $query->latest()->get()
        ], 200);
    }

    // 2. Tambah data Customer Lead baru
    public function store(Request $request)
    {
        $validated = $request->validate([
            'sales_id'        => 'nullable|exists:sales_profiles,id',
            'unit_id'         => 'nullable|exists:units,id',
            'name'            => 'required|string|max:100',
            'phone'           => 'required|string|max:15',
            'email'           => 'nullable|email|max:100',
            'address'         => 'nullable|string',
            'status'          => ['required', Rule::in(CustomerLead::STATUSES)],
            'interested_unit' => 'nullable|string|max:50',
            'budget'          => 'nullable|numeric',
            'note'            => 'nullable|string',
            'last_follow_up'  => 'nullable|date',
            'source'          => 'nullable|string|max:50',
        ]);

        // Sales selalu menambah lead atas namanya sendiri.
        if ($request->user()->role === User::ROLE_SALES && empty($validated['sales_id'])) {
            $validated['sales_id'] = $request->user()->salesProfile?->id;
        }

        $lead = CustomerLead::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Calon konsumen berhasil ditambahkan',
            'data'    => $lead->load(['salesProfile.user', 'unit'])
        ], 201);
    }

    // 3. Detail satu Customer Lead
    public function show(Request $request, $id)
    {
        $lead = CustomerLead::with(['salesProfile.user', 'unit', 'followUpHistories.salesProfile.user', 'bookings'])
            ->find($id);

        if (!$lead) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        if ($this->deniedForSales($request, $lead)) {
            return response()->json(['success' => false, 'message' => 'Anda hanya dapat mengakses data calon konsumen Anda sendiri.'], 403);
        }

        return response()->json(['success' => true, 'data' => $lead], 200);
    }

    // 4. Update data & Status Follow Up
    public function update(Request $request, $id)
    {
        $lead = CustomerLead::find($id);

        if (!$lead) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        if ($this->deniedForSales($request, $lead)) {
            return response()->json(['success' => false, 'message' => 'Anda hanya dapat mengubah data calon konsumen Anda sendiri.'], 403);
        }

        $validated = $request->validate([
            'sales_id'        => 'sometimes|nullable|exists:sales_profiles,id',
            'unit_id'         => 'sometimes|nullable|exists:units,id',
            'name'            => 'sometimes|string|max:100',
            'phone'           => 'sometimes|string|max:15',
            'email'           => 'nullable|email|max:100',
            'address'         => 'nullable|string',
            'status'          => ['sometimes', Rule::in(CustomerLead::STATUSES)],
            'interested_unit' => 'nullable|string|max:50',
            'budget'          => 'nullable|numeric',
            'note'            => 'nullable|string',
            'last_follow_up'  => 'nullable|date',
            'source'          => 'nullable|string|max:50',
        ]);

        $lead->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Data calon konsumen berhasil diperbarui',
            'data'    => $lead->fresh(['salesProfile.user', 'unit'])
        ], 200);
    }

    // 5. Hapus data
    public function destroy(Request $request, $id)
    {
        $lead = CustomerLead::find($id);

        if (!$lead) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        if ($this->deniedForSales($request, $lead)) {
            return response()->json(['success' => false, 'message' => 'Anda hanya dapat menghapus data calon konsumen Anda sendiri.'], 403);
        }

        if ($lead->bookings()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Calon konsumen tidak dapat dihapus karena sudah memiliki pemesanan.',
            ], 422);
        }

        $lead->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data calon konsumen berhasil dihapus'
        ], 200);
    }

    /**
     * Seorang Sales hanya boleh mengelola lead yang melekat pada profilnya.
     */
    private function deniedForSales(Request $request, CustomerLead $lead): bool
    {
        $user = $request->user();

        if ($user->role !== User::ROLE_SALES) {
            return false;
        }

        return (int) $lead->sales_id !== (int) $user->salesProfile?->id;
    }
}