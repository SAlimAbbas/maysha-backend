<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Order Confirmation - {{ $order->reference }}</title>
  <style type="text/css">
    body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
    table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
    img { -ms-interpolation-mode: bicubic; border: 0; outline: none; text-decoration: none; }
    body { margin: 0; padding: 0; width: 100% !important; min-width: 100%; background-color: #F7F5F0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1A1A1A; }
    @media only screen and (max-width: 640px) {
      .email-container { width: 100% !important; max-width: 100% !important; }
      .responsive-col { display: block !important; width: 100% !important; max-width: 100% !important; box-sizing: border-box !important; }
      .mobile-padding { padding-left: 20px !important; padding-right: 20px !important; }
      .receipt-col { margin-top: 24px !important; }
      .meta-cell { display: block !important; width: 100% !important; margin-bottom: 10px !important; }
    }
  </style>
</head>
<body style="margin: 0; padding: 0; background-color: #F7F5F0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; -webkit-font-smoothing: antialiased;">
  <!-- Preheader preview text -->
  <div style="display: none; font-size: 1px; color: #F7F5F0; line-height: 1px; max-height: 0px; max-width: 0px; opacity: 0; overflow: hidden;">
    Thank you for your order {{ $order->reference }}. We are preparing your clean botanical skincare formulations.
  </div>

  <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #F7F5F0; padding: 30px 10px;">
    <tr>
      <td align="center">
        <!-- Main Card Wrapper -->
        <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" class="email-container" style="max-width: 720px; background-color: #FFFFFF; border-radius: 20px; border: 1px solid #EAE6DF; box-shadow: 0 10px 30px rgba(0,0,0,0.04); overflow: hidden;">
          
          <!-- Brand Header -->
          <tr>
            <td align="center" style="padding: 32px 30px 24px 30px; border-bottom: 1px solid #F0ECE4;">
              <a href="https://mayshaindia.com" target="_blank" style="text-decoration: none; display: inline-block;">
                <span style="font-family: 'Playfair Display', Georgia, serif; font-size: 24px; font-weight: 700; letter-spacing: 4px; color: #2C3325; text-transform: uppercase;">MAYSHA</span>
              </a>
              <div style="font-size: 10px; letter-spacing: 2.5px; text-transform: uppercase; color: #736E67; margin-top: 4px; font-weight: 500;">
                Clean Botanical Science
              </div>
            </td>
          </tr>

          <!-- Main Content Area: 2-Column Split (Thank You / Details + Paper Receipt) -->
          <tr>
            <td style="padding: 36px 32px 36px 32px;" class="mobile-padding">
              <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                  
                  <!-- Left Column: Thank You, Address & Action Button -->
                  <td width="310" valign="top" class="responsive-col" style="padding-right: 20px;">
                    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                      <tr>
                        <td>
                          <h1 style="font-family: 'Playfair Display', Georgia, serif; font-size: 26px; line-height: 1.25; color: #1A1A1A; margin: 0 0 14px 0; font-weight: 700;">
                            Thank you for your purchase!
                          </h1>
                          <p style="font-size: 14px; line-height: 1.6; color: #736E67; margin: 0 0 28px 0;">
                            Your order has been received and will be processed within 24 hours during working days. We will notify you by email once your order has shipped.
                          </p>
                        </td>
                      </tr>

                      <!-- Shipping Address Box -->
                      <tr>
                        <td style="padding-top: 8px; padding-bottom: 24px; border-top: 1px solid #F0ECE4;">
                          <h3 style="font-size: 14px; font-weight: 700; color: #1A1A1A; text-transform: capitalize; margin: 0 0 10px 0;">
                            Shipping address
                          </h3>
                          <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="font-size: 13px; line-height: 1.6; color: #5A5650;">
                            <tr>
                              <td style="font-weight: 600; color: #1A1A1A; padding-bottom: 4px;">
                                {{ $order->shipping_address['name'] ?? 'Customer' }}
                              </td>
                            </tr>
                            <tr>
                              <td style="color: #5A5650; padding-bottom: 4px;">
                                {{ $order->shipping_address['line1'] ?? '' }}
                                @if(!empty($order->shipping_address['line2']))
                                  , {{ $order->shipping_address['line2'] }}
                                @endif
                                <br>
                                {{ $order->shipping_address['city'] ?? '' }}, {{ $order->shipping_address['state'] ?? '' }} - {{ $order->shipping_address['pincode'] ?? '' }}
                              </td>
                            </tr>
                            <tr>
                              <td style="color: #736E67; padding-top: 4px;">
                                <strong>Phone:</strong> {{ $order->shipping_address['phone'] ?? $order->guest_phone ?? 'N/A' }}
                              </td>
                            </tr>
                            <tr>
                              <td style="color: #736E67;">
                                <strong>Email:</strong> {{ $order->guest_email ?? ($order->user->email ?? 'N/A') }}
                              </td>
                            </tr>
                          </table>
                        </td>
                      </tr>

                      <!-- Track Order CTA Button -->
                      <tr>
                        <td style="padding-top: 8px;">
                          <a href="https://mayshaindia.com/orders/{{ $order->reference }}" target="_blank" style="display: inline-block; background-color: #4E5942; color: #FFFFFF; font-size: 14px; font-weight: 600; text-decoration: none; padding: 13px 30px; border-radius: 25px; letter-spacing: 0.3px; text-align: center; box-shadow: 0 2px 8px rgba(78,89,66,0.25);">
                            Track Your Order
                          </a>
                        </td>
                      </tr>
                    </table>
                  </td>

                  <!-- Right Column: Paper Receipt Summary -->
                  <td width="330" valign="top" class="responsive-col receipt-col">
                    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #FBF9F5; border: 1px solid #E8E3DA; border-radius: 12px; border-top: 3px dashed #D6D0C4; border-bottom: 3px dashed #D6D0C4; padding: 22px 20px;">
                      
                      <!-- Receipt Title -->
                      <tr>
                        <td style="padding-bottom: 14px;">
                          <h2 style="font-family: 'Playfair Display', Georgia, serif; font-size: 18px; font-weight: 700; color: #1A1A1A; margin: 0;">
                            Order Summary
                          </h2>
                        </td>
                      </tr>

                      <!-- Meta row: Date | Order Number | Payment Method -->
                      <tr>
                        <td style="padding-bottom: 16px; border-bottom: 1px solid #E8E3DA;">
                          <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                            <tr>
                              <td valign="top" style="font-size: 11px; color: #736E67; line-height: 1.3;">
                                <span style="display: block; font-weight: 600; text-transform: uppercase; font-size: 10px; color: #9E988F;">Date</span>
                                <span style="font-weight: 600; color: #1A1A1A;">
                                  {{ $order->placed_at ? $order->placed_at->format('d M Y') : date('d M Y') }}
                                </span>
                              </td>
                              <td valign="top" style="font-size: 11px; color: #736E67; line-height: 1.3; padding: 0 8px;">
                                <span style="display: block; font-weight: 600; text-transform: uppercase; font-size: 10px; color: #9E988F;">Order No.</span>
                                <span style="font-family: monospace; font-weight: 700; color: #1A1A1A;">
                                  {{ $order->reference }}
                                </span>
                              </td>
                              <td valign="top" style="font-size: 11px; color: #736E67; line-height: 1.3;">
                                <span style="display: block; font-weight: 600; text-transform: uppercase; font-size: 10px; color: #9E988F;">Payment</span>
                                <span style="font-weight: 600; color: #1A1A1A;">
                                  {{ $order->payment_method === 'cod' ? 'Cash on Delivery' : 'Prepaid (Online)' }}
                                </span>
                              </td>
                            </tr>
                          </table>
                        </td>
                      </tr>

                      <!-- Itemized Products List -->
                      <tr>
                        <td style="padding-top: 14px; padding-bottom: 14px; border-bottom: 1px solid #E8E3DA;">
                          <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                            @foreach($order->items as $item)
                              <tr>
                                <td style="padding: 7px 0;" valign="middle">
                                  <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                                    <tr>
                                      <!-- Thumbnail -->
                                      <td width="48" valign="top" style="padding-right: 12px;">
                                        <img src="{{ $item->product_image }}" alt="{{ $item->product_name }}" width="48" height="48" style="width: 48px; height: 48px; object-fit: cover; border-radius: 8px; border: 1px solid #E8E3DA; display: block; background-color: #EFECE4;">
                                      </td>
                                      <!-- Title & Variant Details -->
                                      <td valign="top">
                                        <div style="font-size: 13px; font-weight: 600; color: #1A1A1A; line-height: 1.3;">
                                          {{ $item->product_name }}
                                        </div>
                                        <div style="font-size: 11px; color: #736E67; margin-top: 3px;">
                                          @if($item->size_label) Size: {{ $item->size_label }} • @endif Qty: {{ $item->quantity }}
                                        </div>
                                      </td>
                                      <!-- Line Price -->
                                      <td align="right" valign="top" style="font-size: 13px; font-weight: 600; color: #1A1A1A; white-space: nowrap; padding-left: 8px;">
                                        ₹{{ number_format($item->total_rupees) }}
                                      </td>
                                    </tr>
                                  </table>
                                </td>
                              </tr>
                            @endforeach
                          </table>
                        </td>
                      </tr>

                      <!-- Financial Breakdown -->
                      <tr>
                        <td style="padding-top: 14px; padding-bottom: 14px;">
                          <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="font-size: 13px; color: #5A5650; line-height: 1.8;">
                            <tr>
                              <td style="color: #736E67;">Sub Total</td>
                              <td align="right" style="font-weight: 600; color: #1A1A1A;">
                                ₹{{ number_format($order->subtotal_rupees) }}
                              </td>
                            </tr>
                            
                            @if($order->discount_rupees > 0)
                              <tr>
                                <td style="color: #4E5942; font-weight: 600;">
                                  Coupon Discount @if($order->coupon_code)({{ $order->coupon_code }})@endif
                                </td>
                                <td align="right" style="font-weight: 600; color: #4E5942;">
                                  -₹{{ number_format($order->discount_rupees) }}
                                </td>
                              </tr>
                            @endif

                            <tr>
                              <td style="color: #736E67;">Shipping</td>
                              <td align="right" style="font-weight: 600; color: #1A1A1A;">
                                @if($order->shipping_rupees == 0)
                                  <span style="color: #4E5942;">FREE</span>
                                @else
                                  ₹{{ number_format($order->shipping_rupees) }}
                                @endif
                              </td>
                            </tr>

                            @if($order->cod_fee_rupees > 0 || $order->payment_method === 'cod')
                              <tr>
                                <td style="color: #8C6D1F; font-weight: 600;">
                                  Cash on Delivery Handling
                                </td>
                                <td align="right" style="font-weight: 600; color: #8C6D1F;">
                                  +₹{{ number_format($order->cod_fee_rupees) }}
                                </td>
                              </tr>
                            @endif

                            @if($order->tax_rupees > 0)
                              <tr>
                                <td style="color: #9E988F; font-size: 12px;">Tax Included (GST)</td>
                                <td align="right" style="font-size: 12px; color: #9E988F;">
                                  ₹{{ number_format($order->tax_rupees) }}
                                </td>
                              </tr>
                            @endif
                          </table>
                        </td>
                      </tr>

                      <!-- Grand Total Row -->
                      <tr>
                        <td style="padding-top: 12px; border-top: 1px solid #E8E3DA;">
                          <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                            <tr>
                              <td style="font-family: 'Playfair Display', Georgia, serif; font-size: 16px; font-weight: 700; color: #1A1A1A;">
                                Order Total
                              </td>
                              <td align="right" style="font-size: 18px; font-weight: 700; color: #1A1A1A;">
                                ₹{{ number_format($order->total_rupees) }}
                              </td>
                            </tr>
                          </table>
                        </td>
                      </tr>

                    </table>
                  </td>

                </tr>
              </table>
            </td>
          </tr>

          <!-- Botanical Ethos & Support Footer -->
          <tr>
            <td style="background-color: #FAF8F5; padding: 28px 30px; border-top: 1px solid #F0ECE4; text-align: center;">
              <p style="font-size: 13px; line-height: 1.6; color: #736E67; margin: 0 0 10px 0;">
                Have questions or need assistance? Reply directly to this email or reach us at <a href="mailto:info@mayshaindia.com" style="color: #4E5942; text-decoration: underline; font-weight: 600;">info@mayshaindia.com</a>.
              </p>
              <p style="font-size: 11px; letter-spacing: 1.5px; text-transform: uppercase; color: #9E988F; margin: 0 0 6px 0; font-weight: 600;">
                Pure Botanicals • 100% Biocompatible • Cruelty-Free
              </p>
              <p style="font-size: 11px; color: #B5AFA4; margin: 0;">
                © {{ date('Y') }} Maysha Skincare Technologies Pvt. Ltd. All rights reserved.
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>

