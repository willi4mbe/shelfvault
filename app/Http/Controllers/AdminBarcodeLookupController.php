<?php

namespace App\Http\Controllers;

use App\Services\Installer\InstallationState;
use App\Services\Metadata\BarcodeLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminBarcodeLookupController extends Controller
{
    public function __invoke(
        InstallationState $installationState,
        Request $request,
        BarcodeLookupService $barcodeLookupService,
    ): JsonResponse {
        if (! $installationState->installed()) {
            return response()->json([
                'status' => 'not_installed',
                'message' => __('admin.collection.lookup.no_source_configured'),
                'data' => [],
                'source' => null,
            ], 404);
        }

        if (! Auth::check()) {
            return response()->json([
                'status' => 'unauthenticated',
                'message' => __('admin.auth.failed'),
                'data' => [],
                'source' => null,
            ], 401);
        }

        $validated = $request->validate([
            'barcode' => ['required', 'string', 'max:128'],
            'type' => ['nullable', 'string', 'max:32'],
        ]);

        $result = $barcodeLookupService->lookup(
            $validated['barcode'],
            $validated['type'] ?? null,
        );

        return response()->json($result->toArray(), $result->statusCode);
    }
}
