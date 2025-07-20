<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FontAwesomeIconController extends Controller
{
    public function serveIcon(string $style, string $icon): BinaryFileResponse
    {
        $path = base_path('node_modules/@fortawesome/fontawesome-free/svgs/' . $style . '/' . $icon . '.svg');

        if (!File::exists($path)) {
            abort(404);
        }

        return response()->file($path, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=31536000',
        ]);
    }
}
