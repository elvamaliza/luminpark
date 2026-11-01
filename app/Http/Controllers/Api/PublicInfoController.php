<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerLead;
use App\Models\PaymentScheme;
use App\Models\PublicPost;
use App\Models\SalesProfile;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\Request;

class PublicInfoController extends Controller
{
    // 1. API Unit Publik (Daftar unit yang tersedia untuk konsumen)
    public function publicUnits(Request $request)
    {
        $query = Unit::with('cluster.project')
            ->where('status', Unit::STATUS_AVAILABLE);

        if ($request->filled('type')) {
            $query->where('unit_type', $request->type);
        }

        if ($request->filled('cluster_id')) {
            $query->where('cluster_id', $request->cluster_id);
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', (float) $request->max_price);
        }

        return response()->json([
            'success' => true,
            'data'    => $query->orderBy('unit_code')->get()->map($this->presentUnit(...)),
        ], 200);
    }

    // 2. API Detail Unit Publik
    public function publicUnitDetail($id)
    {
        $unit = Unit::with('cluster.project')->find($id);

        if (!$unit) {
            return response()->json(['message' => 'Unit tidak ditemukan'], 404);
        }

        if ($unit->status !== Unit::STATUS_AVAILABLE) {
            return response()->json(['message' => 'Unit tidak ditemukan atau sudah dipesan'], 404);
        }

        return response()->json(['success' => true, 'data' => $this->presentUnit($unit)], 200);
    }

    // 3. Skema pembayaran yang tersedia bagi konsumen
    public function paymentSchemes()
    {
        $schemes = PaymentScheme::where('is_active', true)
            ->orderBy('scheme_name')
            ->get()
            ->map(fn (PaymentScheme $s) => [
                'id'                   => $s->id,
                'scheme_name'          => $s->scheme_name,
                'scheme_type'          => $s->scheme_type,
                'description'          => $s->description,
                'down_payment_percent' => (float) $s->down_payment_percent,
                'installment_months'   => (int) $s->installment_months,
                'interest_rate'        => (float) $s->interest_rate,
            ]);

        return response()->json(['success' => true, 'data' => $schemes], 200);
    }

    // 4. Narahubung Sales untuk tombol "Hubungi Sales"
    public function salesContacts()
    {
        $contacts = SalesProfile::with('user')
            ->where('status', 'aktif')
            ->whereNotNull('whatsapp_number')
            ->whereHas('user', fn ($q) => $q->where('role', User::ROLE_SALES)->where('status', User::STATUS_AKTIF))
            ->limit(3)
            ->get()
            ->map(fn (SalesProfile $p) => [
                'sales_code'      => $p->sales_code,
                'name'            => $p->user?->name,
                'whatsapp_number' => $p->whatsapp_number,
                'whatsapp_link'   => 'https://wa.me/' . preg_replace('/\D+/', '', (string) $p->whatsapp_number),
            ]);

        return response()->json(['success' => true, 'data' => $contacts], 200);
    }

    // 5. API Berita & Galeri Publik
    public function newsAndGallery()
    {
        $gallery = Unit::whereNotNull('image')
            ->where('status', '!=', Unit::STATUS_SOLD_OUT)
            ->get(['id', 'unit_code', 'unit_type', 'image'])
            ->map(fn (Unit $u) => [
                'id'        => $u->id,
                'title'     => $u->unit_type . ' — ' . $u->unit_code,
                'image_url' => $u->image_url,
            ]);

        $news = PublicPost::where('is_published', true)
            ->orderByDesc('posted_on')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn (PublicPost $p) => [
                'id'        => $p->id,
                'title'     => $p->title,
                'date'      => $p->posted_on?->toDateString(),
                'content'   => $p->content,
                'image_url' => $p->image_url,
            ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'news'    => $news,
                'gallery' => $gallery,
            ],
        ], 200);
    }

    /**
     * Seksi Unit Unggulan di beranda: unit pilihan Manajer lebih dulu, dilengkapi
     * urutan otomatis sampai sejumlah public_featured_count.
     */
    public function featuredUnits()
    {
        $limit = max(1, min(12, (int) Setting::value('public_featured_count', '3')));
        $urutan = array_flip(SettingController::featuredUnitIds());

        $units = Unit::with('cluster.project')
            ->where('status', Unit::STATUS_AVAILABLE)
            ->orderBy('unit_code')
            ->get()
            ->sortBy(fn (Unit $u) => $urutan[$u->id] ?? PHP_INT_MAX)
            ->take($limit)
            ->values();

        return response()->json([
            'success' => true,
            'data'    => $units->map($this->presentUnit(...))->values(),
        ], 200);
    }

    // 6. Form Kontak Publik (Otomatis masuk sebagai Customer Lead baru)
    public function submitContactForm(Request $request)
    {
        $validated = $request->validate([
            'name'    => 'required|string|max:100',
            'email'   => 'nullable|email|max:100',
            'phone'   => 'required|string|max:15',
            'address' => 'nullable|string',
            'note'    => 'nullable|string',
            'unit_id' => 'nullable|exists:units,id',
        ]);

        $unit = isset($validated['unit_id']) ? Unit::find($validated['unit_id']) : null;

        $lead = CustomerLead::create([
            'name'            => $validated['name'],
            'email'           => $validated['email'] ?? null,
            'phone'           => $validated['phone'],
            'address'         => $validated['address'] ?? null,
            'note'            => $validated['note'] ?? 'Form Kontak Landing Page',
            'unit_id'         => $unit?->id,
            'interested_unit' => $unit?->unit_code,
            'status'          => CustomerLead::STATUS_COLD,
            'source'          => 'website',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pesan Anda berhasil dikirim! Tim sales kami akan segera menghubungi Anda.',
            'data'    => ['id' => $lead->id, 'name' => $lead->name, 'status' => $lead->status],
        ], 201);
    }

    private function presentUnit(Unit $unit): array
    {
        return [
            'id'            => $unit->id,
            'unit_code'     => $unit->unit_code,
            'unit_type'     => $unit->unit_type,
            'land_size'     => $unit->land_size,
            'building_size' => $unit->building_size,
            'price'         => (float) $unit->price,
            'description'   => $unit->description,
            'image'         => $unit->image,
            'image_url'     => $unit->image_url,
            'cluster'       => $unit->cluster?->name,
            'project'       => $unit->cluster?->project?->name,
        ];
    }
}
