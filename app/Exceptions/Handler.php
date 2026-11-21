<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // Pesan pembungkus "The given data was invalid." berasal dari terjemahan yang tidak
        // pernah didefinisikan, jadi kepala pesan diganti dengan galat bidang pertama.
        $this->renderable(function (ValidationException $e, $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            $pertama = collect($e->errors())->flatten()->first();

            return response()->json([
                'success' => false,
                'message' => $pertama ?: 'Data yang dikirim belum sesuai.',
                'errors' => $e->errors(),
            ], $e->getCode() ?: 422);
        });

        $this->renderable(function (ModelNotFoundException $e, $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan atau sudah dihapus.',
            ], 404);
        });

        // Route model binding yang gagal melempar ModelNotFoundException di dalam
        // NotFoundHttpException, jadi keduanya ditangani pada satu tempat agar nama
        // kelas model dan jejak berkas tidak ikut terbaca pengguna.
        $this->renderable(function (NotFoundHttpException $e, $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => $e->getPrevious() instanceof ModelNotFoundException
                    ? 'Data tidak ditemukan atau sudah dihapus.'
                    : 'Alamat tujuan tidak ditemukan.',
            ], 404);
        });

        // Token hilang atau kedaluwarsa: terjemahan bawaan "Unauthenticated." ikut dialihbahasakan.
        $this->renderable(function (AuthenticationException $e, $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'Sesi berakhir atau kredensial tidak valid. Silakan masuk kembali.',
            ], 401);
        });
    }
}
