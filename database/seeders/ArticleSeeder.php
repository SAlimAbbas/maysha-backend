<?php

namespace Database\Seeders;

use App\Models\Article;
use Illuminate\Database\Seeder;

class ArticleSeeder extends Seeder
{
    public function run(): void
    {
        $articles = [
            [
                'slug' => 'the-art-of-botanical-distillation',
                'title' => 'The Art of Botanical Distillation: How Pure Damask Rose Water Calms Cellular Inflammation',
                'subtitle' => 'Exploring the therapeutic essence of hand-harvested Rosa Damascena and its clinically proven impact on skin redness and hydration retention.',
                'excerpt' => 'Steam-distilled at the break of dawn, pure Damask Rose water retains delicate water-soluble polyphenols and micro-essential oils that provide immediate anti-inflammatory relief to compromised skin barriers.',
                'content' => "Few botanicals in dermatological history carry the restorative heritage of Rosa Damascena. Harvested exclusively in early morning hours before sunlight evaporates volatile terpenes, the delicate petals undergo hydro-distillation in copper alembics to preserve pure cellular water.\n\nUnlike conventional toners that rely on synthetic water bases and alcohol penetrants, genuine distilled rose water possesses an innate biocompatibility with human skin. Formulated at a balanced pH of 5.5, it mirrors the epidermal acid mantle, instantly quelling surface irritation and delivering bio-available antioxidants.\n\nWhen incorporated into a daily skincare regimen, rose water acts as a molecular humectant bridge: it optimizes the absorption of subsequent serums while providing continuous cellular comfort against environmental oxidative stress.",
                'hero_image' => 'https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?auto=format&fit=crop&w=1200&q=85',
                'category' => 'Ingredient Deep Dives',
                'reading_time' => '4 min read',
                'author' => 'Dr. Aanya Sharma',
                'author_role' => 'Head of Botanical Research',
                'is_featured' => true,
                'recommended_product_slugs' => ['rose-water', 'hydrating-toner'],
                'published_at' => now()->subDays(2),
            ],
            [
                'slug' => 'understanding-your-skin-barrier',
                'title' => 'Understanding Your Skin Barrier: Why Lipids and Ceramides Are the Foundation of Healthy Skin',
                'subtitle' => 'The stratum corneum is your shield against the external world. Here is why reinforcing your physiological lipid matrix transforms skin resilience.',
                'excerpt' => 'When your skin feels tight, inflamed, or excessively oily, the culprit is often a compromised lipid barrier. Discover the essential physiological 3:1:1 lipid ratio that unlocks permanent barrier equilibrium.',
                'content' => "Modern skincare frequently falls prey to over-cleansing and excessive exfoliation. When aggressive surfactants strip away intercellular lipids, the skin barrier develops microscopic fissures through which moisture evaporates—a phenomenon dermatologists term Transepidermal Water Loss (TEWL).\n\nTo effectively restore a depleted barrier, topicals must deliver skin-identical lipids: Ceramides 1, 3, and 6-II, alongside cholesterol and free fatty acids in a precise physiological ratio. This golden ratio mimics the architecture of healthy stratum corneum cells, slotting into lipid gaps like mortar in a brick wall.\n\nWithin days of consistent barrier-first therapy, skin ceases its inflammatory defense response, redness diminishes, and natural water retention increases exponentially.",
                'hero_image' => 'https://images.unsplash.com/photo-1598440947619-2c35fc9aa908?auto=format&fit=crop&w=1200&q=85',
                'category' => 'Skincare Science',
                'reading_time' => '6 min read',
                'author' => 'Maysha Formulation Team',
                'author_role' => 'Clinical Product Development',
                'is_featured' => false,
                'recommended_product_slugs' => ['ceramide-barrier-cream', 'vitamin-c-niacinamide-face-wash'],
                'published_at' => now()->subDays(5),
            ],
            [
                'slug' => 'niacinamide-and-vitamin-c-myth-vs-reality',
                'title' => 'Niacinamide and Vitamin C: Debunking the Skincare Layering Myth',
                'subtitle' => 'For decades, beauty lore cautioned against using Vitamin C and Niacinamide simultaneously. Modern science reveals why this pairing is actually a brightening powerhouse.',
                'excerpt' => 'The ancient myth originated from 1960s laboratory tests using unstabilized raw chemicals at extreme temperatures. Modern ethylated ascorbic acid and pure niacinamide work synergistically to illuminate skin tone.',
                'content' => "Few formulation myths have persisted as stubbornly as the supposed antagonism between Vitamin C (Ascorbic Acid) and Vitamin C's frequent companion, Niacinamide (Vitamin B3). Old claims suggested they would neutralize each other or produce skin-flushing nicotinic acid.\n\nContemporary formulation science dispels this completely. Modern stabilized Vitamin C derivatives (such as 3-O-Ethyl Ascorbic Acid) operate at physiologic skin pH and remain stable in ambient conditions. When layered or formulated together, Vitamin C neutralizes oxidative free radicals while Niacinamide halts the transfer of melanosomes to surface keratinocytes.\n\nThe clinical outcome is a dual-mechanism brightening pathway: discoloration fades faster, pores appear tighter, and collagen synthesis receives optimal stimulation without triggering irritation.",
                'hero_image' => 'https://images.unsplash.com/photo-1620916566398-39f1143ab7be?auto=format&fit=crop&w=1200&q=85',
                'category' => 'Skincare Science',
                'reading_time' => '5 min read',
                'author' => 'Dr. Aanya Sharma',
                'author_role' => 'Head of Botanical Research',
                'is_featured' => false,
                'recommended_product_slugs' => ['vitamin-c-serum', 'niacinamide-clarifying-serum'],
                'published_at' => now()->subDays(10),
            ],
            [
                'slug' => 'the-evening-restorative-ritual',
                'title' => 'The Evening Restorative Ritual: Transitioning Your Skin from Daily Stress to Deep Nightly Renewal',
                'subtitle' => 'Circadian biology governs cutaneous repair. Cultivate an intentional evening skincare ritual that honors your skin\'s natural recovery cycle.',
                'excerpt' => 'During sleep, cellular proliferation peaks and blood flow to the skin surges. Aligning your nighttime routine with circadian cycles maximizes transdermal nutrient uptake and restores deep luminosity.',
                'content' => "At dusk, our biology undergoes a profound hormonal shift. As cortisol recedes and melatonin surges, the skin shifts from daily defense mode to nocturnal regeneration. Cellular DNA repair mechanisms accelerate, and skin permeability naturally increases.\n\nAn intentional evening ritual should therefore prioritize restorative nourishment rather than aggressive correction. Begin by melting away pollutants with a gentle herbal cleanser, followed by a misting of pure floral water to saturate the stratum corneum with hydration.\n\nFinish with a restorative serum and botanical facial oil pressed gently into pressure points across the temples and jawline. This ritual not only optimizes nightly repair but grounds the nervous system in restorative stillness.",
                'hero_image' => 'https://images.unsplash.com/photo-1556228720-195a672e8a03?auto=format&fit=crop&w=1200&q=85',
                'category' => 'Daily Rituals',
                'reading_time' => '5 min read',
                'author' => 'Elena Roy',
                'author_role' => 'Holistic Wellness Director',
                'is_featured' => false,
                'recommended_product_slugs' => ['rosemary-hair-oil', 'face-serum'],
                'published_at' => now()->subDays(14),
            ],
        ];

        foreach ($articles as $data) {
            Article::updateOrCreate(
                ['slug' => $data['slug']],
                $data
            );
        }
    }
}
