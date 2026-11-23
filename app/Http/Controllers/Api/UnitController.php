<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Cluster;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class UnitController extends Controller
{
    /**
     * Menampilkan semua data unit.
     */
    public function index(Request $request)
    {
        $query = Unit::with('cluster.project');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('cluster_id')) {
            $query->where('cluster_id', $request->cluster_id);
        }

        if ($request->filled('unit_type')) {
            $query->where('unit_type', $request->unit_type);
        }

        if ($request->filled('price_max')) {
            $query->where('price', '<=', (float) $request->price_max);
        }

        return response()->json($query->orderBy('unit_code')->get(), 200);
    }

    /**
     * Menyimpan data unit baru.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'cluster_id'    => 'required|exists:clusters,id',
            'unit_code'     => ['required', 'string', 'max:30', Rule::unique('units', 'unit_code')->whereNull('deleted_at')],
            'unit_type'     => 'required|string|max:100',
            'land_size'     => 'required|integer|min:1',
            'building_size' => 'required|integer|min:1',
            'price'         => 'required|numeric|min:0',
            'status'        => ['nullable', Rule::in(Unit::STATUSES)],
            'description'   => 'nullable|string',
            'image'         => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $validated['status'] = $validated['status'] ?? Unit::STATUS_AVAILABLE;

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('units', 'public');
        } else {
            unset($validated['image']);
        }

        $unit = Unit::create($validated);

        return response()->json([
            'message' => 'Data unit berhasil disimpan.',
            'data'    => $unit->load('cluster.project'),
        ], 201);
    }

    /**
     * Menampilkan detail satu unit.
     */
    public function show(string $id)
    {
        $unit = Unit::with('cluster.project')->findOrFail($id);

        return response()->json([
            'message' => 'Detail unit berhasil ditemukan.',
            'data'    => $unit,
        ], 200);
    }

    /**
     * Mengubah data unit.
     */
    public function update(Request $request, string $id)
    {
        $unit = Unit::findOrFail($id);

        $validated = $request->validate([
            'cluster_id'    => ['sometimes', 'exists:clusters,id'],
            'unit_code'     => ['sometimes', 'string', 'max:30', Rule::unique('units', 'unit_code')->ignore($unit->id)->whereNull('deleted_at')],
            'unit_type'     => 'sometimes|string|max:100',
            'land_size'     => 'sometimes|integer|min:1',
            'building_size' => 'sometimes|integer|min:1',
            'price'         => 'sometimes|numeric|min:0',
            'status'        => ['nullable', Rule::in(Unit::STATUSES)],
            'description'   => 'nullable|string',
            'image'         => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        if ($request->hasFile('image')) {
            if ($unit->image && Storage::disk('public')->exists($unit->image)) {
                Storage::disk('public')->delete($unit->image);
            }
            $validated['image'] = $request->file('image')->store('units', 'public');
        } else {
            unset($validated['image']);
        }

        $unit->update($validated);

        return response()->json([
            'message' => 'Data unit berhasil diperbarui.',
            'data'    => $unit->fresh('cluster.project'),
        ], 200);
    }

    /**
     * Menghapus data unit.
     */
    public function destroy(string $id)
    {
        $unit = Unit::findOrFail($id);

        if ($unit->bookings()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Unit tidak dapat dihapus karena sudah memiliki riwayat pemesanan.',
            ], 422);
        }

        if ($unit->image && Storage::disk('public')->exists($unit->image)) {
            Storage::disk('public')->delete($unit->image);
        }

        $unit->delete();

        return response()->json([
            'message' => 'Data unit berhasil dihapus.',
        ], 200);
    }

    /**
     * Menampilkan unit yang tersedia.
     */
    public function availableUnits()
    {
        $units = Unit::with('cluster.project')
            ->where('status', Unit::STATUS_AVAILABLE)
            // Revisi 42: unit yang sudah memegang pemesanan aktif dikeluarkan dari
            // pilihan booking, walaupun statusnya masih tersedia.
            ->whereDoesntHave('bookings', fn ($booking) => $booking->where('status', '!=', Booking::STATUS_DIBATALKAN))
            ->orderBy('unit_code')
            ->get();

        return response()->json($units, 200);
    }

    /**
     * Menampilkan daftar cluster beserta proyeknya.
     */
    public function clusters()
    {
        return response()->json([
            'success' => true,
            'data'    => Cluster::with('project')->get(),
        ], 200);
    }
}
