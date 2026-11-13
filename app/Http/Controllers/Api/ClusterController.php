<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cluster;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClusterController extends Controller
{
    /**
     * Daftar cluster beserta proyek induknya, dipakai oleh halaman Data Unit dan formulir unit.
     */
    public function index()
    {
        return response()->json([
            'success' => true,
            'data'    => Cluster::with('project')->orderBy('name')->get(),
        ], 200);
    }

    /**
     * Daftar proyek sebagai pilihan induk cluster pada formulir.
     */
    public function projects()
    {
        return response()->json([
            'success' => true,
            'data'    => Project::orderBy('name')->get(),
        ], 200);
    }

    /**
     * Menyimpan cluster baru. Nama hanya unik di dalam satu proyek karena indeks unik
     * clusters(project_id, name) masih berlaku di basis data; tanpa cek lebih dulu, duplikasi
     * akan melempar QueryException dan menghasilkan respons 500.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_id'  => 'required|exists:projects,id',
            'name'        => [
                'required', 'string', 'max:100',
                Rule::unique('clusters', 'name')->where('project_id', $request->input('project_id')),
            ],
            'description' => 'nullable|string|max:1000',
        ]);

        $cluster = Cluster::create($validated);

        return response()->json([
            'message' => 'Cluster baru berhasil ditambahkan.',
            'data'    => $cluster->load('project'),
        ], 201);
    }
}
