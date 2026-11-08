<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(): View
    {
        return view('products.index', [
            'products' => Product::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:40', Rule::unique('products', 'code')],
            'name' => ['required', 'string', 'max:180'],
            'unit_code' => ['required', 'string', 'size:3'],
            'unit_price' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99'],
            'tax_rate' => ['required', 'numeric', 'in:18'],
        ]);

        if (blank($validated['code'] ?? null)) {
            do {
                $validated['code'] = 'PROD-' . Str::upper(Str::random(8));
            } while (Product::query()->where('code', $validated['code'])->exists());
        }

        Product::create($validated);

        return redirect()->route('products.index')->with('status', 'Producto guardado.');
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:40', Rule::unique('products', 'code')->ignore($product->id)],
            'name' => ['required', 'string', 'max:180'],
            'unit_code' => ['required', 'string', 'size:3'],
            'unit_price' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99'],
            'tax_rate' => ['required', 'numeric', 'in:18'],
        ]);

        $product->update([
            ...$validated,
            'active' => $request->boolean('active'),
        ]);

        return redirect()->route('products.index')->with('status', 'Producto actualizado.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return redirect()->route('products.index')->with('status', 'Producto eliminado. Los datos guardados en ventas anteriores se conservaron.');
    }
}
