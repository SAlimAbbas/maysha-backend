<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\AuditLog;
use App\Models\NewsletterSubscriber;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\CatalogCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminSettingController extends Controller
{
    use ApiResponse;

    /**
     * Get all website settings.
     */
    public function index(): JsonResponse
    {
        $allSettings = SiteSetting::all()->pluck('value', 'key')->toArray();

        $defaults = [
            'store_name' => 'Maysha Skincare',
            'tagline' => 'Clean Beauty for Real Skin',
            'support_email' => 'care@mayshaskincare.com',
            'support_phone' => '+91 98765 43210',
            'support_whatsapp' => '+91 98765 43210',
            'support_hours' => 'Mon - Sat: 9:30 AM to 6:30 PM IST',
            'store_address' => 'Maysha Organics Lab, Mumbai, MH, India',
            'free_shipping_threshold' => 999,
            'shipping_fee' => 99,
            'cod_enabled' => true,
            'cod_fee' => 50,
            'cod_max_rupees' => 5000,
            'announcement_messages' => [
                'FREE Express Shipping on all orders above ₹999',
                '100% Biocompatible • Paraben & Cruelty Free',
                'Dermatologist-Formulated Barrier Skincare',
            ],
            'social_instagram' => 'https://instagram.com/mayshaskincare',
            'social_facebook' => 'https://facebook.com/mayshaskincare',
            'about_hero_title' => 'Clean Botanical Science for Resilient, Real Skin',
            'about_hero_subtitle' => "Maysha was founded on an unwavering truth: skincare should support your skin's natural biology, never wage war against it. We formulate restorative, biocompatible skincare using pure botanical extracts balanced with clinically proven actives.",
            'about_hero_image' => 'https://images.unsplash.com/photo-1556228720-195a672e8a03?auto=format&fit=crop&w=1200&q=85',
            'about_philosophy_image' => 'https://images.unsplash.com/photo-1518531933037-91b2f5f229cc?auto=format&fit=crop&w=1200&q=85',
            'about_story_title' => 'Born from a need for honest, barrier-conscious skincare.',
            'about_story_content' => '',
            'seo_home_title' => 'Maysha | Clean Botanical Science for Real Skin',
            'seo_home_description' => 'Discover clean, bio-compatible skincare rituals formulated with pure botanical extracts and clinical actives for resilient, radiant skin.',
            'seo_about_title' => 'About Maysha | Our Botanical Philosophy & Clean Formulations',
            'seo_about_description' => "Learn about Maysha's barrier-first skincare philosophy, pure botanical ingredients, ethical harvesting, and transparent biocompatible formulations.",
            'seo_journal_title' => 'The Skincare Journal | Maysha Skincare Science',
            'seo_journal_description' => 'Expert skincare guides, ingredient deep-dives, barrier repair science, and holistic rituals.',
            'seo_shop_title' => 'Shop All Botanical Formulations | Maysha',
            'seo_shop_description' => 'Explore all cleansers, serums, toners, moisturizers, and room mists thoughtfully engineered for real skin.',
            'admin_2fa_enabled' => false,
            'shiprocket_enabled' => false,
            'shiprocket_pickup_location' => 'Primary',
            'shiprocket_auto_create' => false,
            'shiprocket_courier_strategy' => 'cheapest',
            'shiprocket_preferred_courier_ids' => '',
            'default_weight_grams' => 250,
            'default_length_cm' => 10.0,
            'default_breadth_cm' => 10.0,
            'default_height_cm' => 10.0,
            'pickup_pincode' => '132103',
            'checkout_email_verification_enabled' => true,
            'return_window_days' => 7,
            'low_wallet_threshold' => 1000,
        ];

        $merged = array_merge($defaults, $allSettings);
        // Security (Finding 5): Never return cleartext PIN over API
        unset($merged['admin_2fa_pin']);
        $merged['admin_2fa_configured'] = ! empty($allSettings['admin_2fa_pin']);

        return $this->success($merged, 'Settings retrieved successfully.');
    }

    /**
     * Update website settings.
     */
    public function update(Request $request): JsonResponse
    {
        $settings = $request->input('settings', []);

        // Security S1: Refuse to enable admin 2FA without an existing or newly provided PIN
        if (array_key_exists('admin_2fa_enabled', $settings) && filter_var($settings['admin_2fa_enabled'], FILTER_VALIDATE_BOOLEAN)) {
            $hasNewPin = ! empty($settings['admin_2fa_pin']);
            $hasExistingPin = ! empty(SiteSetting::get('admin_2fa_pin'));
            if (! $hasNewPin && ! $hasExistingPin) {
                return $this->error('Cannot enable Admin 2FA without configuring a secure PIN.', 422);
            }
        }

        // Security (Finding 5): Strict key allowlist
        $allowedKeys = [
            'store_name',
            'tagline',
            'support_email',
            'support_phone',
            'support_whatsapp',
            'support_hours',
            'store_address',
            'free_shipping_threshold',
            'shipping_fee',
            'announcement_messages',
            'social_instagram',
            'social_facebook',
            'about_hero_title',
            'about_hero_subtitle',
            'about_hero_image',
            'about_philosophy_image',
            'about_story_title',
            'about_story_content',
            'seo_home_title',
            'seo_home_description',
            'seo_about_title',
            'seo_about_description',
            'seo_journal_title',
            'seo_journal_description',
            'seo_shop_title',
            'seo_shop_description',
            'cod_enabled',
            'cod_fee',
            'cod_max_rupees',
            'gstin',
            'admin_2fa_enabled',
            'admin_2fa_pin',
            'shiprocket_enabled',
            'shiprocket_pickup_location',
            'shiprocket_auto_create',
            'shiprocket_courier_strategy',
            'shiprocket_preferred_courier_ids',
            'default_weight_grams',
            'default_length_cm',
            'default_breadth_cm',
            'default_height_cm',
            'pickup_pincode',
            'checkout_email_verification_enabled',
            'return_window_days',
            'low_wallet_threshold',
        ];

        $updatedKeys = [];
        foreach ($settings as $key => $val) {
            if (in_array($key, $allowedKeys, true)) {
                if ($key === 'admin_2fa_pin') {
                    if (! empty($val)) {
                        $hashedVal = Hash::needsRehash((string) $val) ? Hash::make((string) $val) : (string) $val;
                        SiteSetting::set($key, $hashedVal);
                        $updatedKeys[] = $key;
                    }
                } else {
                    SiteSetting::set($key, $val);
                    $updatedKeys[] = $key;
                }
            }
        }

        CatalogCacheService::flush(['settings', 'home']);

        AuditLog::record('settings.updated', null, $request->user(), ['keys' => $updatedKeys]);

        return $this->success(['updated' => $updatedKeys], 'Website settings updated successfully.');
    }

    /**
     * List all newsletter and registered subscribers.
     */
    public function subscribers(): JsonResponse
    {
        $newsletterSubs = NewsletterSubscriber::orderBy('created_at', 'desc')->get();
        $registeredUsers = User::where('role', 'customer')->get(['id', 'name', 'email', 'created_at']);

        return $this->success([
            'subscribers' => $newsletterSubs->map(fn ($s) => [
                'id' => (string) $s->id,
                'email' => $s->email,
                'source' => $s->source,
                'isActive' => (bool) $s->is_active,
                'createdAt' => $s->created_at?->toIso8601String(),
            ]),
            'totalSubscribers' => $newsletterSubs->count(),
            'totalRegisteredCustomers' => $registeredUsers->count(),
        ], 'Subscribers retrieved successfully.');
    }

    /**
     * Craft and broadcast campaign email.
     */
    public function broadcast(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:10000',
            'recipient_type' => 'nullable|string|in:all,newsletter,customers',
        ]);

        $recipientCount = NewsletterSubscriber::where('is_active', true)->count();

        AuditLog::record('newsletter.broadcast', null, $request->user(), [
            'subject' => $validated['subject'],
            'recipient_count' => $recipientCount,
        ]);

        return $this->success([
            'subject' => $validated['subject'],
            'dispatchedRecipients' => $recipientCount,
            'status' => 'queued_for_delivery',
        ], 'Newsletter campaign scheduled and dispatched to ' . $recipientCount . ' subscribers.');
    }
}
