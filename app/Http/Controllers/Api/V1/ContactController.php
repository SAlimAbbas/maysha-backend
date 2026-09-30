<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\ContactMessage;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    use ApiResponse;

    /**
     * Subscribe to Maysha clean beauty newsletter.
     */
    public function subscribe(Request $request): JsonResponse
    {
        // Bot honeypot check: if 'website' or 'bot_trap' is filled, silently discard or reject
        if ($request->filled('website') || $request->filled('bot_trap')) {
            return $this->success(null, 'Thank you for subscribing to Maysha!');
        }

        $request->validate([
            'email' => 'required|email|max:255',
            'source' => 'nullable|string|max:50',
        ]);

        $email = strtolower($request->input('email'));

        NewsletterSubscriber::updateOrCreate(
            ['email' => $email],
            [
                'source' => $request->input('source', 'storefront_footer'),
                'is_active' => true,
            ]
        );

        return $this->success(null, 'Thank you for subscribing to the Maysha Clean Beauty Journal!');
    }

    /**
     * Submit a customer support or skincare inquiry message.
     */
    public function contact(Request $request): JsonResponse
    {
        // Bot honeypot check
        if ($request->filled('website') || $request->filled('bot_trap')) {
            return $this->success(null, 'Thank you! Your message has been received.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'subject' => 'nullable|string|max:255',
            'message' => 'required|string|max:3000',
        ]);

        ContactMessage::create([
            'name' => strip_tags($validated['name']),
            'email' => strtolower($validated['email']),
            'phone' => $validated['phone'] ?? null,
            'subject' => isset($validated['subject']) ? strip_tags($validated['subject']) : null,
            'message' => strip_tags($validated['message']),
            'ip' => $request->ip(),
        ]);

        return $this->success(null, 'Thank you! Your inquiry has been received. Our skincare experts will respond shortly.');
    }
}
