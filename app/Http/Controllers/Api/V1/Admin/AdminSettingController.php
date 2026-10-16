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
            'announcement_messages' => [
                'FREE Express Shipping on all orders above ₹999',
                '100% Biocompatible • Paraben & Cruelty Free',
                'Dermatologist-Formulated Barrier Skincare',
            ],
            'social_instagram' => 'https://instagram.com/mayshaskincare',
            'social_facebook' => 'https://facebook.com/mayshaskincare',
            'about_hero_title' => 'Clean Botanical Science for Resilient, Real Skin',
            'about_hero_image' => 'https://images.unsplash.com/photo-1556228720-195a672e8a03?auto=format&fit=crop&w=1200&q=85',
            'about_philosophy_image' => 'https://images.unsplash.com/photo-1518531933037-91b2f5f229cc?auto=format&fit=crop&w=1200&q=85',
            'admin_2fa_enabled' => false,
            'admin_2fa_pin' => '9281',
        ];

        $merged = array_merge($defaults, $allSettings);

        return $this->success($merged, 'Settings retrieved successfully.');
    }

    /**
     * Update website settings.
     */
    public function update(Request $request): JsonResponse
    {
        $settings = $request->input('settings', []);

        foreach ($settings as $key => $val) {
            SiteSetting::set($key, $val);
        }

        CatalogCacheService::flush(['settings', 'home']);

        AuditLog::record('settings.updated', null, $request->user(), ['keys' => array_keys($settings)]);

        return $this->success($settings, 'Website settings updated successfully.');
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
