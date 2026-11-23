<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ScopesSalesData;
use App\Models\FollowUpHistory;
use App\Models\CustomerLead;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FollowUpHistoryController extends Controller
{
    use ScopesSalesData;

    // 1. Tampilkan semua riwayat follow-up (bisa filter per lead_id)
    public function index(Request $request)
    {
        $query = FollowUpHistory::with(['lead', 'salesProfile']);

        // Butir d.4: catatan tindak lanjut sales lain tidak ikut terbaca.
        $milik = $this->salesIdTerbatas($request);

        if ($milik !== null) {
            $query->where('sales_id', $milik);
        } elseif ($request->filled('sales_id')) {
            $query->where('sales_id', $request->sales_id);
        }

        if ($request->has('lead_id') && $request->lead_id != '') {
            $query->where('lead_id', $request->lead_id);
        }

        return response()->json([
            'success' => true,
            'data'    => $query->latest('follow_up_date')->get()
        ], 200);
    }

    // 2. Tambah catatan follow-up baru (otomatis perbarui status & last_follow_up di CustomerLead)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'lead_id'        => 'required|exists:customer_leads,id',
            'sales_id'       => 'nullable|exists:sales_profiles,id',
            'follow_up_date' => 'required|date',
            'status_before'  => ['required', Rule::in(CustomerLead::STATUSES)],
            'status_after'   => ['required', Rule::in(CustomerLead::STATUSES)],
            'method'         => 'required|in:whatsapp,phone,visit,email',
            'note'           => 'nullable|string',
        ]);

        $validated['sales_id'] = $validated['sales_id'] ?? $request->user()->salesProfile?->id;

        // Catatan tindak lanjut selalu atas nama sales yang sedang masuk.
        $milik = $this->salesIdTerbatas($request);

        if ($milik !== null) {
            $validated['sales_id'] = $milik;
        }

        if (!$validated['sales_id']) {
            return response()->json(['success' => false, 'message' => 'Bidang sales wajib dipilih.'], 422);
        }

        $history = FollowUpHistory::create($validated);

        // Update status & tgl follow up terakhir di tabel customer_leads
        $lead = CustomerLead::find($request->lead_id);
        if ($lead) {
            $lead->update([
                'status'         => $request->status_after,
                'last_follow_up' => $request->follow_up_date,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Riwayat follow-up berhasil dicatat',
            'data'    => $history->load(['lead', 'salesProfile.user'])
        ], 201);
    }

    // 3. Detail satu riwayat follow-up
    public function show(Request $request, $id)
    {
        $history = FollowUpHistory::with(['lead', 'salesProfile'])->find($id);

        if (!$history) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        if ($tolak = $this->tolakDataSalesLain($request, (int) $history->sales_id)) {
            return $tolak;
        }

        return response()->json(['success' => true, 'data' => $history], 200);
    }

    // 4. Hapus catatan follow-up
    public function destroy(Request $request, $id)
    {
        $history = FollowUpHistory::find($id);

        if (!$history) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        if ($tolak = $this->tolakDataSalesLain($request, (int) $history->sales_id)) {
            return $tolak;
        }

        $history->delete();

        return response()->json([
            'success' => true,
            'message' => 'Riwayat follow-up berhasil dihapus'
        ], 200);
    }
}