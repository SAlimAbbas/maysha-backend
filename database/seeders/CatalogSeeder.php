<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Concern;
use App\Models\Media;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Categories
        $categoriesData = [
            [
                'slug' => 'cleansers',
                'name' => 'Cleansers',
                'subtitle' => 'Gentle, non-stripping cleansers that balance skin pH',
                'image' => '/images/products/face-wash/face-wash-1.png',
                'sort_order' => 1,
            ],
            [
                'slug' => 'toners',
                'name' => 'Toners',
                'subtitle' => 'Hydrating essences to rebalance and prep your skin barrier',
                'image' => 'https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?auto=format&fit=crop&w=600&q=80',
                'sort_order' => 2,
            ],
            [
                'slug' => 'serums',
                'name' => 'Serums',
                'subtitle' => 'High-concentration botanical & clinical treatment solutions',
                'image' => 'https://images.unsplash.com/photo-1620916566398-39f1143ab7be?auto=format&fit=crop&w=600&q=80',
                'sort_order' => 3,
            ],
            [
                'slug' => 'moisturizers',
                'name' => 'Moisturizers',
                'subtitle' => 'Skin-identical barrier repair creams and gel-creams',
                'image' => 'https://images.unsplash.com/photo-1598440947619-2c35fc9aa908?auto=format&fit=crop&w=600&q=80',
                'sort_order' => 4,
            ],
            [
                'slug' => 'sunscreen',
                'name' => 'Sunscreen',
                'subtitle' => 'Broad-spectrum mineral SPF with zero white cast finish',
                'image' => 'https://images.unsplash.com/photo-1556228720-195a672e8a03?auto=format&fit=crop&w=600&q=80',
                'sort_order' => 5,
            ],
            [
                'slug' => 'treatments',
                'name' => 'Treatments & Oils',
                'subtitle' => 'Targeted botanical oils, scalp therapy, and fragrant ambiance',
                'image' => '/images/products/rosemary-hair-oil/hair-oil-1.png',
                'sort_order' => 6,
            ],
        ];

        $categories = [];
        foreach ($categoriesData as $cat) {
            $categories[$cat['slug']] = Category::updateOrCreate(['slug' => $cat['slug']], $cat);
        }

        // 2. Concerns
        $concernsData = [
            [
                'slug' => 'uneven-tone',
                'name' => 'Uneven Tone',
                'image' => 'https://images.unsplash.com/photo-1535585209827-a15fcdbc4c2d?auto=format&fit=crop&w=600&q=80',
                'description' => 'Fade stubborn dark spots and sun pigmentation for radiant clarity',
                'sort_order' => 1,
            ],
            [
                'slug' => 'acne-control',
                'name' => 'Acne Control',
                'image' => 'https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?auto=format&fit=crop&w=600&q=80',
                'description' => 'Target active breakouts, purify congested pores, and balance excess sebum',
                'sort_order' => 2,
            ],
            [
                'slug' => 'barrier-repair',
                'name' => 'Barrier Repair',
                'image' => 'https://images.unsplash.com/photo-1598440947619-2c35fc9aa908?auto=format&fit=crop&w=600&q=80',
                'description' => 'Rebuild compromised skin defenses with ceramides and calming peptides',
                'sort_order' => 3,
            ],
            [
                'slug' => 'dehydration-dryness',
                'name' => 'Dehydration & Dryness',
                'image' => 'https://images.unsplash.com/photo-1556228720-195a672e8a03?auto=format&fit=crop&w=600&q=80',
                'description' => 'Multi-depth cellular hydration for parched, tight, or flaking skin',
                'sort_order' => 4,
            ],
            [
                'slug' => 'fine-lines-aging',
                'name' => 'Fine Lines & Aging',
                'image' => 'https://images.unsplash.com/photo-1508759073847-9ca702cec7d2?auto=format&fit=crop&w=600&q=80',
                'description' => 'Support collagen renewal and firmness with bakuchiol and tripeptides',
                'sort_order' => 5,
            ],
        ];

        $concerns = [];
        foreach ($concernsData as $con) {
            $concerns[$con['slug']] = Concern::updateOrCreate(['slug' => $con['slug']], $con);
        }

        // 3. Products
        $productsData = [
            [
                'slug' => 'vitamin-c-niacinamide-face-wash',
                'name' => 'Vitamin C & Niacinamide Face Wash',
                'subtitle' => 'Brightens & Soothes | Mild everyday herbal cleanser designed for regular use with Aloe Vera & Glycerin.',
                'category_slug' => 'cleansers',
                'concern_slug' => 'uneven-tone',
                'badge' => 'Bestseller',
                'is_featured' => true,
                'rating_avg' => 4.9,
                'review_count' => 186,
                'key_benefits' => [
                    'Mild everyday cleanser designed for gentle, regular daily use',
                    'Sulfate-free & paraben-free: Cleanses without stripping essential moisture',
                    'Vitamin C & Niacinamide help improve dullness, uneven tone, and dark spots',
                    'Aloe Vera extract and Glycerin support barrier repair and deep hydration',
                    'Regulates excess sebum and refines pores for a soft, balanced complexion',
                ],
                'skin_types' => ['All Skin Types', 'Sensitive Skin', 'Dull & Uneven Skin', 'Normal to Oily'],
                'ingredients' => 'Aqua, Sodium Cocoyl Isethionate, Glycerin, Niacinamide (Vitamin B3), Ethyl Ascorbic Acid (Vitamin C), Aloe Barbadensis (Aloe Vera) Leaf Extract, Cocamidopropyl Betaine, Allantoin, Panthenol (Pro-Vitamin B5), Citric Acid, Phenoxyethanol, Ethylhexylglycerin.',
                'usage_instructions' => 'Take a coin-sized amount of face wash on your wet hands. Lather gently: Massage onto your face in circular motions for 30–60 seconds. Rinse thoroughly with water and pat dry with a clean towel. Use twice daily, morning and night.',
                'description' => 'Maysha Vitamin C & Niacinamide Face Wash is a gentle everyday herbal cleanser designed to brighten, soothe, and protect your skin. Infused with antioxidant-rich Vitamin C, barrier-strengthening Niacinamide, and soothing Aloe Vera, it cleanses away daily impurities, pollution, and excess sebum without disturbing your skin\'s natural moisture barrier. Skin feels refreshed, soft, and visibly radiant.',
                'variants' => [
                    ['sku' => 'VCFW-100', 'size_label' => '100 ml', 'price_paise' => 39900, 'mrp_paise' => 49900, 'stock' => 200],
                    ['sku' => 'VCFW-BOGO', 'size_label' => 'Pack of 2 (Buy 1 Get 1 Free)', 'price_paise' => 49900, 'mrp_paise' => 99800, 'stock' => 150],
                ],
                'images' => [
                    '/images/products/face-wash/face-wash-1.png',
                    '/images/products/face-wash/face-wash-2.png',
                    '/images/products/face-wash/face-wash-3.png',
                    '/images/products/face-wash/face-wash-4.png',
                ],
            ],
            [
                'slug' => 'rosemary-hair-oil',
                'name' => 'Rosemary Hair Oil with Blend of 8 Oils',
                'subtitle' => 'Nourishes Roots & Natural Shine | Promotes Healthier, Stronger Hair & Controls Frizz.',
                'category_slug' => 'treatments',
                'concern_slug' => 'barrier-repair',
                'badge' => 'Trending',
                'is_featured' => true,
                'rating_avg' => 4.9,
                'review_count' => 142,
                'key_benefits' => [
                    'Strengthens hair roots and minimizes hair fall due to breakage',
                    'Potent blend of 8 pure botanical oils for natural shine and softness',
                    'Controls frizz and significantly improves hair manageability',
                    'Rosemary and castor oil help promote fuller, healthier-looking hair growth',
                    'Nourishes dry scalp and helps repair environmental hair damage',
                ],
                'skin_types' => ['All Hair Types', 'Dry Scalp', 'Damaged Hair', 'Frizzy Hair'],
                'ingredients' => 'Rosemary (Rosmarinus Officinalis) Leaf Oil, Pure Moroccan Argan Oil, Sweet Almond Oil, Golden Jojoba Oil, Extra Virgin Olive Oil, Pure Castor Seed Oil, Virgin Coconut Oil, Watermelon Seed Oil, Tocopherol (Vitamin E).',
                'usage_instructions' => 'Take a small amount of oil on your palm. Massage gently into scalp and hair, focusing on roots and dry strands. Leave on for a few hours or overnight for deep nourishment. Wash off with a mild shampoo for best results. Use 2–3 times a week.',
                'description' => 'Maysha Rosemary Hair Oil is a revitalizing Ayurvedic botanical elixir powered by pure rosemary extract and a synergistic blend of 8 nutrient-dense natural oils. Designed to penetrate deep into hair follicles, it invigorates scalp circulation, fortifies hair roots, tames unruly frizz, and restores silky radiance without greasy heaviness.',
                'variants' => [
                    ['sku' => 'RHO-100', 'size_label' => '100 ml', 'price_paise' => 49900, 'mrp_paise' => 69900, 'stock' => 180],
                    ['sku' => 'RHO-200', 'size_label' => '200 ml (Value Pack)', 'price_paise' => 89900, 'mrp_paise' => 139800, 'stock' => 100],
                ],
                'images' => [
                    '/images/products/rosemary-hair-oil/hair-oil-1.png',
                    '/images/products/rosemary-hair-oil/hair-oil-2.png',
                    '/images/products/rosemary-hair-oil/hair-oil-4.png',
                    '/images/products/rosemary-hair-oil/hair-oil-3.png',
                ],
            ],
            [
                'slug' => 'rose-air-freshener',
                'name' => 'Maysha Rose Air Freshener',
                'subtitle' => 'The Next Level Of Fragrance | Fine Mist Spray for Bedroom, Living Room & Everyday Spaces.',
                'category_slug' => 'treatments',
                'concern_slug' => 'dehydration-dryness',
                'badge' => 'New Launch',
                'is_featured' => true,
                'rating_avg' => 4.8,
                'review_count' => 96,
                'key_benefits' => [
                    'Fresh, authentic Damask rose fragrance that uplifts any room',
                    'Instant odor-neutralizing action to eliminate stale odors quickly',
                    'Ergonomic fine-mist trigger spray for wide, even coverage',
                    'Perfect for bedroom, living room, office, kitchen, and car',
                    'Long-lasting botanical aroma without harsh chemical aerosols',
                ],
                'skin_types' => ['Home Ambiance', 'Office Friendly', 'Automobile', 'Living Spaces'],
                'ingredients' => 'Purified Water, Rosa Damascena (Rose) Flower Distillate, Natural Botanical Fragrance Essential Oils, Odor Neutralizing Bio-Enzymes, PEG-40 Hydrogenated Castor Oil, Preservative Complex.',
                'usage_instructions' => 'Turn the nozzle to the spray position. Hold bottle upright and aim toward the center of the room. Spray 4–5 bursts evenly in the desired area. Reapply whenever you want a fresh, revitalizing rose ambiance.',
                'description' => 'Transform your daily surroundings into a serene floral sanctuary with Maysha Rose Air Freshener. Formulated with authentic rose flower distillate and odor-capturing natural bio-enzymes, this fine-mist spray eliminates unwanted odors immediately while diffusing a luxurious, fresh rose fragrance that lingers gently for hours.',
                'variants' => [
                    ['sku' => 'RAF-300', 'size_label' => '300 ml', 'price_paise' => 34900, 'mrp_paise' => 49900, 'stock' => 150],
                    ['sku' => 'RAF-TWIN', 'size_label' => 'Twin Pack (300 ml x 2)', 'price_paise' => 59900, 'mrp_paise' => 99800, 'stock' => 80],
                ],
                'images' => [
                    '/images/products/rose-air-freshener/air-freshener-1.png',
                    '/images/products/rose-air-freshener/air-freshener-4.png',
                    '/images/products/rose-air-freshener/air-freshener-2.png',
                    '/images/products/rose-air-freshener/air-freshener-3.png',
                ],
            ],
            [
                'slug' => 'gentle-face-cleanser',
                'name' => 'Gentle Face Cleanser',
                'subtitle' => 'A mild, non-drying cleanser that removes impurities while keeping your skin balanced and hydrated.',
                'category_slug' => 'cleansers',
                'concern_slug' => 'barrier-repair',
                'badge' => 'Bestseller',
                'is_featured' => true,
                'rating_avg' => 4.9,
                'review_count' => 124,
                'key_benefits' => [
                    'Deeply cleanses without stripping natural moisture',
                    'Formulated with soothing oat extract and provitamin B5',
                    'Sulfate-free, gentle foam with skin-neutral pH 5.5',
                    'Leaves skin feeling calm, soft and supple',
                ],
                'skin_types' => ['All Skin Types', 'Sensitive', 'Dry to Normal'],
                'ingredients' => 'Aqua, Sodium Cocoyl Glycinate, Glycerin, Avena Sativa (Oat) Kernel Extract, Panthenol (Provitamin B5), Allantoin, Chamomilla Recutita Flower Extract, Ethylhexylglycerin, Phenoxyethanol.',
                'usage_instructions' => 'Dispense a small coin-sized amount onto wet palms. Gently massage onto damp face in circular motions for 60 seconds. Rinse thoroughly with lukewarm water. Use morning and night.',
                'description' => 'This gentle face cleanser is enriched with natural botanical ingredients that help remove dirt, oil, sunscreen, and daily pollutants without stripping your skin. Formulated at a biocompatible pH of 5.5, it preserves your vital skin barrier while leaving skin feeling refreshingly clean and silky soft.',
                'variants' => [
                    ['sku' => 'GFC-50', 'size_label' => '50 ml', 'price_paise' => 24900, 'mrp_paise' => 45000, 'stock' => 150],
                    ['sku' => 'GFC-100', 'size_label' => '100 ml', 'price_paise' => 39900, 'mrp_paise' => 75000, 'stock' => 250],
                    ['sku' => 'GFC-200', 'size_label' => '200 ml', 'price_paise' => 69900, 'mrp_paise' => 129900, 'stock' => 80],
                ],
            ],
            [
                'slug' => 'hydrating-toner',
                'name' => 'Hydrating Toner',
                'subtitle' => 'Replenishing multi-depth moisture essence that preps and rebalances the skin barrier.',
                'category_slug' => 'toners',
                'concern_slug' => 'dehydration-dryness',
                'badge' => 'Bestseller',
                'is_featured' => true,
                'rating_avg' => 4.8,
                'review_count' => 98,
                'key_benefits' => [
                    'Multi-weight Hyaluronic Acid locks in moisture',
                    'Restores healthy skin barrier post-cleansing',
                    'Alcohol-free, fragrance-free formula',
                    'Instant soothing relief for dehydrated skin',
                ],
                'skin_types' => ['Dry', 'Normal', 'Combination'],
                'ingredients' => 'Aqua, Betaine, Butylene Glycol, Sodium Hyaluronate, Panthenol, Centella Asiatica Extract, Polyglutamic Acid, Disodium EDTA, Phenoxyethanol.',
                'usage_instructions' => 'Pour 4-5 drops onto cleansed palms and gently press into skin until fully absorbed. Layer 2-3 times for intensive hydration.',
                'description' => 'Our Hydrating Toner delivers essential electrolytes and moisture binding humectants deep into skin layers to prep your face for subsequent serums and creams.',
                'variants' => [
                    ['sku' => 'HT-100', 'size_label' => '100 ml', 'price_paise' => 44900, 'mrp_paise' => 89900, 'stock' => 180],
                    ['sku' => 'HT-200', 'size_label' => '200 ml', 'price_paise' => 79900, 'mrp_paise' => 149900, 'stock' => 100],
                ],
            ],
            [
                'slug' => 'vitamin-c-serum',
                'name' => '10% Vitamin C + Ferulic Glow Serum',
                'subtitle' => 'Clinically proven antioxidant elixir to brighten dark spots and promote luminous clarity.',
                'category_slug' => 'serums',
                'concern_slug' => 'uneven-tone',
                'badge' => 'Award Winner',
                'is_featured' => true,
                'rating_avg' => 4.9,
                'review_count' => 210,
                'key_benefits' => [
                    '10% stabilized Ethyl Ascorbic Acid targets pigmentation',
                    'Ferulic acid and Vitamin E boost antioxidant efficacy',
                    'Fades stubborn acne marks and sun spots',
                    'Protects against oxidative environmental stressors',
                ],
                'skin_types' => ['All Skin Types', 'Dull Skin', 'Hyperpigmentation Prone'],
                'ingredients' => 'Aqua, 3-O-Ethyl Ascorbic Acid, Propanediol, Ferulic Acid, Tocopherol (Vitamin E), Sodium Hyaluronate, Camellia Sinensis Leaf Extract, Ethylhexylglycerin.',
                'usage_instructions' => 'Apply 3-4 drops to cleansed and toned face in the morning. Pat lightly until absorbed. Follow with moisturizer and broad-spectrum sunscreen.',
                'description' => 'A gold-standard brightening treatment powered by pure, stabilized Vitamin C and Ferulic Acid to awaken radiant clarity and protect skin from pollution.',
                'variants' => [
                    ['sku' => 'VCS-30', 'size_label' => '30 ml', 'price_paise' => 59900, 'mrp_paise' => 109900, 'stock' => 300],
                ],
            ],
            [
                'slug' => 'ceramide-barrier-cream',
                'name' => 'Ceramide Barrier Moisture Cream',
                'subtitle' => 'Rich, velvety barrier restoration cream with bio-identical ceramides 1, 3 & 6-II.',
                'category_slug' => 'moisturizers',
                'concern_slug' => 'barrier-repair',
                'badge' => 'Trending',
                'is_featured' => true,
                'rating_avg' => 4.9,
                'review_count' => 165,
                'key_benefits' => [
                    '3:1:1 physiological ratio of Ceramides, Cholesterol & Fatty Acids',
                    'Repairs cracked or inflamed skin barrier within 7 days',
                    'Provides 48-hour continuous moisture retention',
                    'Non-comedogenic and fragrance-free',
                ],
                'skin_types' => ['Dry', 'Very Dry', 'Compromised Barrier', 'Sensitive'],
                'ingredients' => 'Aqua, Caprylic/Capric Triglyceride, Glycerin, Ceramide NP, Ceramide AP, Ceramide EOP, Phytosphingosine, Cholesterol, Carbomer, Xanthan Gum, Phenoxyethanol.',
                'usage_instructions' => 'Apply an even layer over face and neck as the final step in your routine. Use morning and evening.',
                'description' => 'A deeply comforting restorative moisturizer designed to seal in active serums, soothe flaking, and fortify your natural lipid barrier.',
                'variants' => [
                    ['sku' => 'CBC-50', 'size_label' => '50 ml', 'price_paise' => 54900, 'mrp_paise' => 99900, 'stock' => 220],
                ],
            ],
            [
                'slug' => 'mineral-matte-sunscreen-spf50',
                'name' => 'Mineral Matte Sunscreen SPF 50+ PA++++',
                'subtitle' => 'Ultra-lightweight invisible physical shield with micronized zinc oxide and green tea.',
                'category_slug' => 'sunscreen',
                'concern_slug' => 'uneven-tone',
                'badge' => 'New Launch',
                'is_featured' => true,
                'rating_avg' => 4.7,
                'review_count' => 84,
                'key_benefits' => [
                    '100% Mineral Zinc Oxide filter',
                    'Broad-spectrum UVA/UVB + Blue Light protection',
                    'Zero white cast on all skin tones with silky matte finish',
                    'Sweat and water-resistant for up to 80 minutes',
                ],
                'skin_types' => ['All Skin Types', 'Oily to Combination', 'Acne-Prone'],
                'ingredients' => 'Zinc Oxide (Micronized), Aqua, Isododecane, Dimethicone, Camellia Sinensis (Green Tea) Leaf Extract, Niacinamide, Silica, Tocopheryl Acetate.',
                'usage_instructions' => 'Apply generously 15 minutes before sun exposure. Reapply every 2 hours or after 80 minutes of swimming/sweating.',
                'description' => 'A next-generation mineral sunscreen with zero greasy residue or chalky white cast, delivering broad spectrum PA++++ defense.',
                'variants' => [
                    ['sku' => 'MMS-50', 'size_label' => '50 ml', 'price_paise' => 49900, 'mrp_paise' => 89900, 'stock' => 190],
                ],
            ],
            [
                'slug' => 'niacinamide-clarifying-serum',
                'name' => '10% Niacinamide + 1% Zinc PCA Serum',
                'subtitle' => 'Pore-refining, sebum-balancing formulation that soothes blemishes and clears congestion.',
                'category_slug' => 'serums',
                'concern_slug' => 'acne-control',
                'badge' => 'Bestseller',
                'is_featured' => false,
                'rating_avg' => 4.8,
                'review_count' => 142,
                'key_benefits' => [
                    '10% pure Niacinamide tightens enlarged pores',
                    '1% Zinc PCA regulates daily sebum secretion',
                    'Reduces redness and prevents post-acne scarring',
                    'Lightweight water-gel absorbs in seconds',
                ],
                'skin_types' => ['Oily', 'Combination', 'Blemish-Prone'],
                'ingredients' => 'Aqua, Niacinamide, Zinc PCA, Dimethyl Isosorbide, Hydroxyethylcellulose, Allantoin, Phenoxyethanol, Ethylhexylglycerin.',
                'usage_instructions' => 'Dispense 2-3 drops onto clean face before heavier creams. Suitable for daily AM and PM use.',
                'description' => 'A clinical-strength blemish defense serum designed to diminish enlarged pores, regulate oil production, and enhance clear skin texture.',
                'variants' => [
                    ['sku' => 'NCS-30', 'size_label' => '30 ml', 'price_paise' => 44900, 'mrp_paise' => 79900, 'stock' => 160],
                ],
            ],
            [
                'slug' => 'multi-peptide-repair-serum',
                'name' => 'Multi-Peptide & Copper Restorative Serum',
                'subtitle' => 'Age-defying peptide complex that targets elasticity, fine lines, and cellular firmness.',
                'category_slug' => 'serums',
                'concern_slug' => 'fine-lines-aging',
                'badge' => 'New Launch',
                'is_featured' => false,
                'rating_avg' => 4.9,
                'review_count' => 76,
                'key_benefits' => [
                    'Copper Tripeptide-1 promotes collagen synthesis',
                    'Matrixyl 3000 smooths expression lines',
                    'Enhances skin resilience and youthful bounce',
                    'Dewy, non-tacky finish',
                ],
                'skin_types' => ['Mature', 'Normal', 'Dry'],
                'ingredients' => 'Aqua, Glycerin, Copper Tripeptide-1, Palmitoyl Tripeptide-1, Palmitoyl Tetrapeptide-7, Sodium Hyaluronate, Pullulan, Phenoxyethanol.',
                'usage_instructions' => 'Smooth 3-4 drops across face and neck after cleansing. Use morning and night.',
                'description' => 'Advanced multi-peptide formulation that promotes structural elasticity and firm skin renewal.',
                'variants' => [
                    ['sku' => 'MPS-30', 'size_label' => '30 ml', 'price_paise' => 69900, 'mrp_paise' => 129900, 'stock' => 120],
                ],
            ],
            [
                'slug' => 'aha-bha-exfoliating-peel',
                'name' => '2% BHA + 5% Lactic Acid Exfoliating Solution',
                'subtitle' => 'Gentle resurfacing liquid peel that unclogs pores, clears blackheads, and unveils smooth skin.',
                'category_slug' => 'treatments',
                'concern_slug' => 'acne-control',
                'badge' => 'Trending',
                'is_featured' => false,
                'rating_avg' => 4.7,
                'review_count' => 63,
                'key_benefits' => [
                    'Salicylic acid penetrates oil-filled pores to dissolve blackheads',
                    'Lactic acid gently loosens dull dead surface cells',
                    'Tasmanian pepperberry calms redness during exfoliation',
                    'Reveals silky, even-textured radiance',
                ],
                'skin_types' => ['Combination', 'Oily', 'Congested Skin'],
                'ingredients' => 'Aqua, Lactic Acid, Salicylic Acid, Aloe Barbadensis Leaf Juice, Propanediol, Tasmannia Lanceolata Fruit/Leaf Extract, Sodium Hydroxide, Phenoxyethanol.',
                'usage_instructions' => 'Apply 3-4 drops on clean dry skin at night. Do not rinse off. Use 2-3 times weekly. Always follow with SPF the next morning.',
                'description' => 'A potent dual-action chemical exfoliant combining BHA and AHA to clear blackheads and renew skin surface texture.',
                'variants' => [
                    ['sku' => 'ABE-30', 'size_label' => '30 ml', 'price_paise' => 49900, 'mrp_paise' => 89900, 'stock' => 140],
                ],
            ],
        ];

        foreach ($productsData as $pData) {
            $cat = $categories[$pData['category_slug']] ?? null;
            $con = $concerns[$pData['concern_slug']] ?? null;

            if (! $cat) {
                continue;
            }

            $product = Product::updateOrCreate(
                ['slug' => $pData['slug']],
                [
                    'name' => $pData['name'],
                    'subtitle' => $pData['subtitle'],
                    'description' => $pData['description'],
                    'category_id' => $cat->id,
                    'concern_id' => $con?->id,
                    'badge' => $pData['badge'],
                    'key_benefits' => $pData['key_benefits'],
                    'skin_types' => $pData['skin_types'],
                    'ingredients' => $pData['ingredients'],
                    'usage_instructions' => $pData['usage_instructions'],
                    'is_featured' => $pData['is_featured'],
                    'is_active' => true,
                    'rating_avg' => $pData['rating_avg'],
                    'review_count' => $pData['review_count'],
                ]
            );

            // Variants
            foreach ($pData['variants'] as $vData) {
                ProductVariant::updateOrCreate(
                    ['sku' => $vData['sku']],
                    [
                        'product_id' => $product->id,
                        'size_label' => $vData['size_label'],
                        'price_paise' => $vData['price_paise'],
                        'mrp_paise' => $vData['mrp_paise'],
                        'stock' => $vData['stock'],
                        'is_active' => true,
                    ]
                );
            }

            // Product Media
            if (! empty($pData['images'])) {
                foreach ($pData['images'] as $idx => $imgUrl) {
                    $media = Media::firstOrCreate(
                        ['provider_id' => $imgUrl],
                        [
                            'provider' => 'local',
                            'type' => 'image',
                            'status' => 'ready',
                            'alt' => $product->name,
                        ]
                    );

                    $role = $idx === 0 ? 'primary' : ($idx === 1 ? 'hover' : 'gallery');
                    $product->media()->syncWithoutDetaching([
                        $media->id => [
                            'role' => $role,
                            'sort_order' => $idx + 1,
                        ],
                    ]);
                }
            }

            // Reviews
            Review::updateOrCreate(
                [
                    'product_id' => $product->id,
                    'author_name' => 'Meera K.',
                ],
                [
                    'rating' => 5,
                    'title' => 'Absolute Holy Grail!',
                    'comment' => 'Transformed my skin within two weeks. Zero irritation, lightweight, and deeply hydrating.',
                    'is_verified' => true,
                    'is_approved' => true,
                    'helpful_count' => 14,
                ]
            );

            Review::updateOrCreate(
                [
                    'product_id' => $product->id,
                    'author_name' => 'Rohan V.',
                ],
                [
                    'rating' => 5,
                    'title' => 'Dermatologist recommended quality',
                    'comment' => 'The ingredients are top tier and free of artificial fragrances. Love how gentle it feels on the face.',
                    'is_verified' => true,
                    'is_approved' => true,
                    'helpful_count' => 9,
                ]
            );
        }
    }
}
