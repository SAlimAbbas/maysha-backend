<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    /**
     * In-memory or database-backed Maysha catalog
     */
    protected function getCatalog(): array
    {
        return [
            [
                'id' => 'prod-1',
                'slug' => 'gentle-face-cleanser',
                'name' => 'Gentle Face Cleanser',
                'subtitle' => 'A mild, non-drying cleanser that removes impurities while keeping your skin balanced and hydrated.',
                'category' => 'Cleansers',
                'category_slug' => 'cleansers',
                'concern' => 'Barrier Repair',
                'concern_slug' => 'barrier-repair',
                'price' => 399,
                'mrp' => 750,
                'discount_percentage' => 47,
                'size' => '100 ml',
                'rating' => 4.9,
                'review_count' => 124,
                'badge' => 'Bestseller',
                'in_stock' => true,
                'images' => [
                    'https://images.unsplash.com/photo-1556228720-195a672e8a03?auto=format&fit=crop&w=900&q=85',
                    'https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?auto=format&fit=crop&w=900&q=85',
                ],
                'variants' => [
                    ['size' => '50 ml', 'price' => 249, 'mrp' => 450],
                    ['size' => '100 ml', 'price' => 399, 'mrp' => 750],
                    ['size' => '200 ml', 'price' => 699, 'mrp' => 1299],
                ],
                'ingredients' => 'Aqua, Sodium Cocoyl Glycinate, Glycerin, Avena Sativa (Oat) Kernel Extract, Panthenol, Allantoin.',
                'usage' => 'Dispense coin-sized amount onto damp face, massage for 60s, rinse with lukewarm water.'
            ],
            [
                'id' => 'prod-2',
                'slug' => 'hydrating-toner',
                'name' => 'Hydrating Toner',
                'subtitle' => 'Replenishing multi-depth moisture essence that preps and rebalances the skin barrier.',
                'category' => 'Toners',
                'category_slug' => 'toners',
                'concern' => 'Dehydration & Dryness',
                'concern_slug' => 'dehydration-dryness',
                'price' => 449,
                'mrp' => 899,
                'discount_percentage' => 50,
                'size' => '100 ml',
                'rating' => 4.8,
                'review_count' => 98,
                'badge' => 'Bestseller',
                'in_stock' => true,
                'images' => [
                    'https://images.unsplash.com/photo-1608248597359-0a69a9e32a68?auto=format&fit=crop&w=900&q=85',
                ],
                'variants' => [
                    ['size' => '100 ml', 'price' => 449, 'mrp' => 899],
                    ['size' => '200 ml', 'price' => 749, 'mrp' => 1499],
                ],
                'ingredients' => 'Aqua, Camellia Sinensis (Green Tea) Leaf Water, Butylene Glycol, Sodium Hyaluronate, Centella Asiatica.',
                'usage' => 'Pour 3-4 drops into palms, pat onto clean skin until absorbed.'
            ],
            [
                'id' => 'prod-3',
                'slug' => 'vitamin-c-serum',
                'name' => 'Vitamin C Serum',
                'subtitle' => 'High-potency 10% ethyl ascorbic acid with ferulic acid for radiant, illuminated skin tone.',
                'category' => 'Serums',
                'category_slug' => 'serums',
                'concern' => 'Uneven Tone',
                'concern_slug' => 'uneven-tone',
                'price' => 699,
                'mrp' => 1299,
                'discount_percentage' => 46,
                'size' => '30 ml',
                'rating' => 4.9,
                'review_count' => 215,
                'badge' => 'Bestseller',
                'in_stock' => true,
                'images' => [
                    'https://images.unsplash.com/photo-1620916566398-39f1143ab7be?auto=format&fit=crop&w=900&q=85',
                ],
                'variants' => [
                    ['size' => '30 ml', 'price' => 699, 'mrp' => 1299],
                    ['size' => '50 ml', 'price' => 1049, 'mrp' => 1899],
                ],
                'ingredients' => 'Aqua, 3-O-Ethyl Ascorbic Acid (10%), Propanediol, Ferulic Acid (0.5%), Citrus Aurantium Dulcis Extract.',
                'usage' => 'Apply 3-4 drops to face each morning before sunscreen.'
            ],
            [
                'id' => 'prod-4',
                'slug' => 'lightweight-moisturizer',
                'name' => 'Lightweight Moisturizer',
                'subtitle' => 'Weightless ceramide gel-cream that locks in deep hydration without clogging pores.',
                'category' => 'Moisturizers',
                'category_slug' => 'moisturizers',
                'concern' => 'Barrier Repair',
                'concern_slug' => 'barrier-repair',
                'price' => 549,
                'mrp' => 1099,
                'discount_percentage' => 50,
                'size' => '50 ml',
                'rating' => 4.8,
                'review_count' => 167,
                'badge' => 'Bestseller',
                'in_stock' => true,
                'images' => [
                    'https://images.unsplash.com/photo-1598440947619-2c35fc9aa908?auto=format&fit=crop&w=900&q=85',
                ],
                'variants' => [
                    ['size' => '50 ml', 'price' => 549, 'mrp' => 1099],
                    ['size' => '100 ml', 'price' => 899, 'mrp' => 1799],
                ],
                'ingredients' => 'Aqua, Glycerin, Ceramide NP, Ceramide AP, Ceramide EOP, Phytosphingosine, Squalane.',
                'usage' => 'Apply a blueberry-sized amount over face and neck morning and night.'
            ],
            [
                'id' => 'prod-5',
                'slug' => 'dewy-mineral-sunscreen-spf-50',
                'name' => 'Dewy Mineral Sunscreen SPF 50',
                'subtitle' => 'Broad-spectrum PA++++ sheer zinc protection with zero white cast and a natural dewy finish.',
                'category' => 'Sunscreen',
                'category_slug' => 'sunscreen',
                'concern' => 'Uneven Tone',
                'concern_slug' => 'uneven-tone',
                'price' => 499,
                'mrp' => 899,
                'discount_percentage' => 44,
                'size' => '50 g',
                'rating' => 4.9,
                'review_count' => 182,
                'badge' => 'New Launch',
                'in_stock' => true,
                'images' => [
                    'https://images.unsplash.com/photo-1567928815104-b7980ee5032e?auto=format&fit=crop&w=900&q=85',
                ],
                'variants' => [
                    ['size' => '50 g', 'price' => 499, 'mrp' => 899],
                ],
                'ingredients' => 'Aqua, Zinc Oxide (Non-nano 18%), Niacinamide (3%), Polyglyceryl-3 Polyricinoleate, Silica.',
                'usage' => 'Apply generously 15 minutes before sun exposure using the two-finger rule.'
            ]
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $products = collect($this->getCatalog());

        if ($request->has('category') && $request->category !== 'all') {
            $products = $products->where('category_slug', $request->category);
        }

        if ($request->has('concern') && $request->concern !== 'all') {
            $products = $products->where('concern_slug', $request->concern);
        }

        if ($request->has('search') && !empty($request->search)) {
            $q = strtolower($request->search);
            $products = $products->filter(function ($item) use ($q) {
                return str_contains(strtolower($item['name']), $q) ||
                       str_contains(strtolower($item['category']), $q) ||
                       str_contains(strtolower($item['subtitle']), $q);
            });
        }

        if ($request->get('sort') === 'price_asc') {
            $products = $products->sortBy('price');
        } elseif ($request->get('sort') === 'price_desc') {
            $products = $products->sortByDesc('price');
        } elseif ($request->get('sort') === 'rating') {
            $products = $products->sortByDesc('rating');
        }

        return response()->json([
            'success' => true,
            'count' => $products->count(),
            'data' => $products->values()
        ]);
    }

    public function bestsellers(): JsonResponse
    {
        $products = collect($this->getCatalog())
            ->where('badge', 'Bestseller')
            ->values();

        return response()->json([
            'success' => true,
            'count' => $products->count(),
            'data' => $products
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $product = collect($this->getCatalog())->firstWhere('slug', $slug);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $product
        ]);
    }
}
