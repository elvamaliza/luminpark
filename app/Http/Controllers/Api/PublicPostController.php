<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PublicPost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * Kabar terbaru pada halaman publik. Hanya Super Admin yang berhak mengelola isinya.
 */
class PublicPostController extends Controller
{
    private const RULES = [
        'title'        => 'required|string|max:150',
        'content'      => 'required|string|max:2000',
        'posted_on'    => 'required|date',
        'is_published' => 'nullable|boolean',
        'image'        => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
    ];

    public function index()
    {
        $posts = PublicPost::orderByDesc('posted_on')->orderByDesc('id')->get();

        return response()->json([
            'success' => true,
            'data'    => $posts->map(fn (PublicPost $post) => $this->present($post))->values(),
        ], 200);
    }

    public function store(Request $request)
    {
        $validated = $this->validateRequest($request, false);

        $post = PublicPost::create($this->attributes($request, $validated));

        return response()->json([
            'success' => true,
            'message' => 'Kabar terbaru berhasil ditambahkan dan tampil di halaman publik.',
            'data'    => $this->present($post),
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $post = PublicPost::find($id);

        if (!$post) {
            return response()->json(['success' => false, 'message' => 'Kabar terbaru tidak ditemukan'], 404);
        }

        $validated = $this->validateRequest($request, true);
        $lama = $post->image;

        $post->update($this->attributes($request, $validated));

        if ($lama && ($request->hasFile('image') || $request->input('remove_image'))) {
            Storage::disk('public')->delete($lama);
        }

        return response()->json([
            'success' => true,
            'message' => 'Kabar terbaru berhasil diperbarui.',
            'data'    => $this->present($post->fresh()),
        ], 200);
    }

    public function destroy($id)
    {
        $post = PublicPost::find($id);

        if (!$post) {
            return response()->json(['success' => false, 'message' => 'Kabar terbaru tidak ditemukan'], 404);
        }

        if ($post->image) {
            Storage::disk('public')->delete($post->image);
        }

        $post->delete();

        return response()->json(['success' => true, 'message' => 'Kabar terbaru berhasil dihapus.'], 200);
    }

    private function validateRequest(Request $request, bool $sebagian): array
    {
        $rules = $sebagian
            ? array_map(fn (string $rule) => 'sometimes|' . $rule, self::RULES)
            : self::RULES;

        return Validator::make($request->all(), $rules)->validate();
    }

    /** Nilai tersimpan, termasuk berkas gambar dan penanda penghapusan gambar. */
    private function attributes(Request $request, array $validated): array
    {
        if (isset($validated['is_published'])) {
            $validated['is_published'] = filter_var($validated['is_published'], FILTER_VALIDATE_BOOLEAN);
        }

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('publik', 'public');
        } elseif ($request->input('remove_image')) {
            $validated['image'] = null;
        } else {
            unset($validated['image']);
        }

        return $validated;
    }

    private function present(PublicPost $post): array
    {
        return [
            'id'           => $post->id,
            'title'        => $post->title,
            'content'      => $post->content,
            'posted_on'    => $post->posted_on?->toDateString(),
            'image'        => $post->image,
            'image_url'    => $post->image_url,
            'is_published' => (bool) $post->is_published,
            'created_at'   => $post->created_at,
        ];
    }
}
