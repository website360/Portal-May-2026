<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Support\Brand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A marca da agência — nome, subtítulo e logo que aparecem no menu.
 */
class BrandController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('configuracoes/marca', [
            'brand' => Brand::shared(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'subtitle' => ['nullable', 'string', 'max:80'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'remove_logo' => ['boolean'],
        ], [], [
            'name' => 'nome',
            'subtitle' => 'subtítulo',
            'logo' => 'logo',
        ]);

        $logoPath = Brand::all()['logo_path'];

        // Campo em branco significa "não mexi", não "apague": só o botão remove.
        if ($request->boolean('remove_logo') && $logoPath) {
            Storage::disk('public')->delete($logoPath);
            $logoPath = null;
        }

        if ($request->hasFile('logo')) {
            if ($logoPath) {
                Storage::disk('public')->delete($logoPath);
            }

            $logoPath = $request->file('logo')->store('brand', 'public');
        }

        Brand::save([
            'name' => $data['name'],
            'subtitle' => $data['subtitle'] ?? null,
            'logo_path' => $logoPath,
        ]);

        return back()->with('success', 'Marca atualizada.');
    }
}
