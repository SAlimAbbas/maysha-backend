<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\SiteSetting;
use App\Services\CatalogCacheService;
use Illuminate\Http\JsonResponse;

class StoreInfoController extends Controller
{
    use ApiResponse;

    /**
     * Get storefront configuration and operational parameters.
     */
    public function show(): JsonResponse
    {
        $info = CatalogCacheService::remember('store:info', ['settings', 'home'], 3600, function () {
            return [
                'brand' => 'Maysha Skincare',
                'tagline' => 'Clean Beauty for Real Skin',
                'currency' => 'INR',
                'symbol' => '₹',
                'freeShippingThreshold' => (int) SiteSetting::get('free_shipping_threshold', env('FREE_SHIPPING_THRESHOLD', 999)),
                'shippingFee' => (int) SiteSetting::get('shipping_fee', env('SHIPPING_FEE', 99)),
                'codEnabled' => (bool) SiteSetting::get('cod_enabled', true),
                'codFee' => (int) SiteSetting::get('cod_fee', 50),
                'codMaxRupees' => (int) SiteSetting::get('cod_max_rupees', 5000),
                'emailVerificationRequired' => (bool) SiteSetting::get('checkout_email_verification_enabled', true),
                'announcementMessages' => SiteSetting::get('announcement_messages', [
                    'Free Express Shipping on Orders Above ₹999',
                    'Get 10% Off on Your First Order with Code MAYSHA10',
                ]),
                'supportEmail' => (string) SiteSetting::get('support_email', 'care@mayshaskincare.com'),
                'supportPhone' => (string) SiteSetting::get('support_phone', '+91 98765 43210'),
                'supportWhatsapp' => (string) SiteSetting::get('support_whatsapp', '+91 98765 43210'),
                'supportHours' => (string) SiteSetting::get('support_hours', 'Mon - Sat: 9:30 AM to 6:30 PM IST'),
                'storeAddress' => (string) SiteSetting::get('store_address', 'Maysha Organics Lab, Mumbai, MH, India'),
                'socialLinks' => [
                    'instagram' => (string) SiteSetting::get('social_instagram', 'https://instagram.com/mayshaskincare'),
                    'facebook' => (string) SiteSetting::get('social_facebook', 'https://facebook.com/mayshaskincare'),
                ],
                'gstin' => (string) SiteSetting::get('gstin', '29ABCDE1234F1Z5'),
                'admin2faEnabled' => (bool) filter_var(SiteSetting::get('admin_2fa_enabled', false), FILTER_VALIDATE_BOOLEAN),
                'status' => 'operational',
                'about' => [
                    'heroTitle' => (string) SiteSetting::get('about_hero_title', 'Clean Botanical Science for Resilient, Real Skin'),
                    'heroSubtitle' => (string) SiteSetting::get('about_hero_subtitle', "Maysha was founded on an unwavering truth: skincare should support your skin's natural biology, never wage war against it. We formulate restorative, biocompatible skincare using pure botanical extracts balanced with clinically proven actives."),
                    'heroImage' => (string) SiteSetting::get('about_hero_image', 'https://images.unsplash.com/photo-1556228720-195a672e8a03?auto=format&fit=crop&w=1200&q=85'),
                    'philosophyImage' => (string) SiteSetting::get('about_philosophy_image', 'https://images.unsplash.com/photo-1518531933037-91b2f5f229cc?auto=format&fit=crop&w=1200&q=85'),
                    'storyTitle' => (string) SiteSetting::get('about_story_title', 'Born from a need for honest, barrier-conscious skincare.'),
                    'storyContent' => (string) SiteSetting::get('about_story_content', ''),
                ],
                'seo' => [
                    'homeTitle' => (string) SiteSetting::get('seo_home_title', 'Maysha | Clean Botanical Science for Real Skin'),
                    'homeDescription' => (string) SiteSetting::get('seo_home_description', 'Discover clean, bio-compatible skincare rituals formulated with pure botanical extracts and clinical actives for resilient, radiant skin.'),
                    'aboutTitle' => (string) SiteSetting::get('seo_about_title', 'About Maysha | Our Botanical Philosophy & Clean Formulations'),
                    'aboutDescription' => (string) SiteSetting::get('seo_about_description', "Learn about Maysha's barrier-first skincare philosophy, pure botanical ingredients, ethical harvesting, and transparent biocompatible formulations."),
                    'journalTitle' => (string) SiteSetting::get('seo_journal_title', 'The Skincare Journal | Maysha Skincare Science'),
                    'journalDescription' => (string) SiteSetting::get('seo_journal_description', 'Expert skincare guides, ingredient deep-dives, barrier repair science, and holistic rituals.'),
                    'shopTitle' => (string) SiteSetting::get('seo_shop_title', 'Shop All Botanical Formulations | Maysha'),
                    'shopDescription' => (string) SiteSetting::get('seo_shop_description', 'Explore all cleansers, serums, toners, moisturizers, and room mists thoughtfully engineered for real skin.'),
                ],
            ];
        });

        return $this->success($info, 'Store info retrieved successfully.');
    }
}
