<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cluster;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Pengelolaan proyek (nama lama: cluster) — daftar, detail, tambah, ubah,
 * arsipkan, dan hapus. Seluruh aksi tulis hanya milik Super Admin (lihat
 * routes/api.php), sedangkan peran monitoring memakai aksi baca untuk keperluan
 * penyaringan data. Tabel basis data dan rute API tetap memakai nama clusters.
 */
class ClusterController extends Controller
{
    /**
     * Daftar proyek beserta perumahan induknya dan jumlah unit yang memakainya.
     * Param status dipakai formulir unit agar hanya proyek aktif yang ditawarkan.
     */
    public function index(Request $request)
    {
        $query = Cluster::with('project')->withCount('units')->orderBy('name');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->integer('project_id'));
        }

        if ($request->filled('q')) {
            $kata = '%' . str_replace(['%', '_'], ['\%', '\_'], $request->string('q')->toString()) . '%';
            $query->where(fn ($inner) => $inner->where('name', 'like', $kata)
                ->orWhere('description', 'like', $kata));
        }

        return response()->json([
            'success' => true,
            'data' => $query->get(),
        ], 200);
    }

    /** Detail satu proyek: jumlah unit per status sebagai dasar keputusan hapus/arsip. */
    public function show($id)
    {
        $cluster = Cluster::with('project')->withCount('units')->find($id);

        if (! $cluster) {
            return response()->json(['success' => false, 'message' => 'Data proyek tidak ditemukan.'], 404);
        }

        $perStatus = $cluster->units()
            ->selectRaw('status, count(*) as jumlah')
            ->groupBy('status')
            ->pluck('jumlah', 'status');

        return response()->json([
            'success' => true,
            'data' => $cluster->toArray() + [
                'unit_per_status' => $perStatus,
                'masih_dipakai' => $cluster->units_count > 0,
            ],
        ], 200);
    }

    /**
     * Daftar perumahan sebagai pilihan induk proyek pada formulir.
     */
    public function projects()
    {
        return response()->json([
            'success' => true,
            'data'    => Project::orderBy('name')->get(),
        ], 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->aturan($request));

        $validated['status'] = $request->input('status', Cluster::STATUS_AKTIF);

        $cluster = Cluster::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Proyek baru berhasil ditambahkan.',
            'data'    => $cluster->load('project'),
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $cluster = Cluster::find($id);

        if (! $cluster) {
            return response()->json(['success' => false, 'message' => 'Data proyek tidak ditemukan.'], 404);
        }

        $validated = $request->validate($this->aturan($request, $cluster->id));

        if ($request->filled('status')) {
            $validated['status'] = $request->status;
        }

        $cluster->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Proyek berhasil diperbarui.',
            'data'    => $cluster->fresh('project'),
        ], 200);
    }

    /** Arsip menahan proyek dari formulir tanpa menghapus datanya. */
    public function archive(Request $request, $id)
    {
        $cluster = Cluster::find($id);

        if (! $cluster) {
            return response()->json(['success' => false, 'message' => 'Data proyek tidak ditemukan.'], 404);
        }

        $valid = $request->validate(['status' => ['required', Rule::in(Cluster::STATUSES)]]);

        $cluster->update(['status' => $valid['status']]);

        return response()->json([
            'success' => true,
            'message' => $cluster->status === Cluster::STATUS_DIARSIPKAN
                ? 'Proyek berhasil diarsipkan dan tidak muncul lagi pada formulir unit.'
                : 'Proyek berhasil diaktifkan kembali.',
            'data' => $cluster->fresh('project'),
        ], 200);
    }

    /**
     * Penghapusan proyek ditolak selama masih ada unit yang memakainya, supaya
     * riwayat penjualan tidak kehilangan pengelompokannya.
     */
    public function destroy($id)
    {
        $cluster = Cluster::withCount('units')->find($id);

        if (! $cluster) {
            return response()->json(['success' => false, 'message' => 'Data proyek tidak ditemukan.'], 404);
        }

        if ($cluster->units_count > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Proyek masih dipakai ' . $cluster->units_count . ' unit. Pindahkan atau hapus unitnya lebih dulu, '
                    . 'atau gunakan aksi Arsipkan agar proyek tidak ditawarkan pada formulir baru.',
            ], 409);
        }

        $cluster->delete();

        return response()->json([
            'success' => true,
            'message' => 'Proyek berhasil dihapus. Baris datanya tetap tersimpan di basis data.',
        ], 200);
    }

    /**
     * Nama proyek hanya unik di dalam satu perumahan karena indeks clusters(project_id, name)
     * berlaku; baris yang sudah dihapus lunak tidak ikut menghitung nama.
     */
    private function aturan(Request $request, ?int $idLewati = null): array
    {
        return [
            'project_id'  => 'required|exists:projects,id',
            'name'        => [
                'required', 'string', 'max:100',
                Rule::unique('clusters', 'name')
                    ->where(fn ($query) => $query->where('project_id', $request->input('project_id'))->whereNull('deleted_at'))
                    ->ignore($idLewati),
            ],
            'description' => 'nullable|string|max:1000',
            'address'     => 'nullable|string|max:255',
            'latitude'    => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'   => ['nullable', 'numeric', 'between:-180,180'],
            'status'      => ['nullable', Rule::in(Cluster::STATUSES)],
        ];
    }
}
