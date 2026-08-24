<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;

class InstallController extends Controller
{
    public function publicStorage(string $path)
    {
        abort_if(str_contains($path, '..'), 404);
        abort_unless(Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path);
    }
}
