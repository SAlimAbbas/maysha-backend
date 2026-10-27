# Maysha Skincare: Shiprocket Logistics & Security Integration Guide

## 1. Executive Summary & Constraints

This document details the complete end-to-end integration of **Shiprocket Logistics** and the **Security & Architectural Re-Audit** for Maysha Skincare (`maysha-backend` and `maysha-frontend`).

> [!CAUTION]
> **Production Deployment Constraint**:
> **DO NOT** push to the Git remote or run `deploy.ps1` until official Shiprocket production API credentials have been created, entered into production `.env`, and verified using the Go-Live Verification Checklist (Section 8).
> All work in this release has been developed, tested, and validated locally on the `main` branch across both repositories.

---

## 2. Environment Configuration

### A. Backend (`maysha-backend/.env`)

Add the following keys to your production `.env` file once Shiprocket credentials are ready:

```dotenv
# -----------------------------------------------------------------------------
# SHIPROCKET API CONFIGURATION
# -----------------------------------------------------------------------------
SHIPROCKET_BASE_URL=https://apiv2.shiprocket.in/v1/external
SHIPROCKET_EMAIL="your-registered-shiprocket-email@mayshaskincare.com"
SHIPROCKET_PASSWORD="your-strong-shiprocket-password"
SHIPROCKET_WEBHOOK_TOKEN="generate-a-random-64-char-hex-token-here"
SHIPROCKET_DEFAULT_PICKUP_LOCATION="Primary"
SHIPROCKET_LOW_WALLET_THRESHOLD=500
SHIPROCKET_AUTO_CREATE=true
SHIPROCKET_COURIER_STRATEGY=cheapest
```

#### Key Explanations:
- `SHIPROCKET_EMAIL` & `SHIPROCKET_PASSWORD`: Your official Shiprocket API login credentials. The backend caches the authentication Bearer token for 23 hours in Redis/cache with atomic locking and automatically re-authenticates upon a 401 response.
- `SHIPROCKET_WEBHOOK_TOKEN`: A private shared secret. When Shiprocket sends webhook notifications, it must provide this in the `x-shiprocket-token` header. Constant-time string comparison (`hash_equals`) is enforced on every incoming webhook.
- `SHIPROCKET_DEFAULT_PICKUP_LOCATION`: The nickname/code of your primary pickup address configured in the Shiprocket dashboard (e.g., `Primary` or `Warehouse 1`).
- `SHIPROCKET_LOW_WALLET_THRESHOLD`: Minimum balance in INR before the admin dashboard flags a warning and the scheduled command alerts the admin team.
- `SHIPROCKET_AUTO_CREATE`: When `true`, paid orders automatically spawn a queued Shiprocket consignment (`CreateShiprocketShipmentJob`).

---

## 3. Shiprocket Dashboard Configuration

### Step 1: Add Pickup Location
1. Log in to the [Shiprocket Admin Panel](https://app.shiprocket.in/).
2. Navigate to **Settings > Pickup Addresses**.
3. Add your primary dispatch address (company name, street address, city, state, pincode, and contact number).
4. Assign a **Pickup Location Nickname** (e.g. `Primary`).
5. Ensure the nickname exactly matches `SHIPROCKET_DEFAULT_PICKUP_LOCATION` in `.env` and `shiprocket_pickup_location` in Maysha Site Settings.

### Step 2: Configure Webhook
1. In the Shiprocket dashboard, navigate to **Settings > API > Webhooks**.
2. Click **Add Webhook**.
3. **Webhook URL**:
   ```
   https://mayshaskincare.com/api/v1/logistics/hook
   ```
4. **Custom Headers**:
   - Header Key: `x-shiprocket-token`
   - Header Value: *(Paste the exact value from `SHIPROCKET_WEBHOOK_TOKEN` in your `.env`)*
5. **Subscribed Events**: Select all tracking lifecycle events:
   - Order Manifested
   - Order Picked Up
   - In Transit
   - Out for Delivery
   - Delivered
   - Delivery Failed / NDR
   - RTO Initiated / RTO Delivered
   - Order Cancelled
6. Save and test the webhook connection.

---

## 4. Console Commands & Automation

The integration introduces three dedicated Artisan commands:

### 1. `php artisan shiprocket:doctor`
Runs comprehensive pre-flight diagnostics:
- Validates the presence of required `.env` keys.
- Authenticates with Shiprocket API and tests token acquisition.
- Verifies pickup location serviceability.
- Queries wallet balance.
- Outputs diagnostic status table.
*(Note: Also accessible to super-admins via `GET /api/v1/admin/shiprocket/doctor` and the Admin Settings UI).*

### 2. `php artisan shipments:reconcile`
- Scans all non-terminal active shipments (`processing`, `manifested`, `in_transit`, `out_for_delivery`, `ndr`) that have not been updated in the last 4 hours.
- Queries Shiprocket tracking API for fresh milestones and synchronizes database state.
- **Recommended cron frequency**: Hourly.
  ```cron
  0 * * * * cd /path/to/maysha-backend && php artisan shipments:reconcile >> /dev/null 2>&1
  ```

### 3. `php artisan shiprocket:check-wallet`
- Checks the current Shiprocket prepaid wallet balance.
- If balance falls below `SHIPROCKET_LOW_WALLET_THRESHOLD` (or the dynamic site setting), it logs a warning and dispatches an admin alert notification.
- **Recommended cron frequency**: Daily at 09:00 AM.
  ```cron
  0 9 * * * cd /path/to/maysha-backend && php artisan shiprocket:check-wallet >> /dev/null 2>&1
  ```

---

## 5. Admin Portal Logistics Capabilities

Under the **Shipping** tab (`/admin?tab=shipping`) and **Orders** tab:

1. **Logistics Dashboard**:
   - **Wallet Monitor**: Displays live prepaid wallet balance with a color-coded warning alert if below threshold.
   - **Performance Metrics**: Six real-time cards tracking *Total Shipments*, *Manifested*, *In Transit*, *Out for Delivery*, *Delivered*, and *NDR Exceptions*.
   - **NDR Attention Queue**: Highlights packages where courier delivery failed, detailing the customer reason and providing a 1-click **Instruct Re-attempt** action modal.
   - **Registered Shipments Registry**: Filterable table of all consignments with direct links to live tracking, shipping labels, and carrier sync.

2. **Order Fulfillment Modal ("Ship with Shiprocket")**:
   - Accessible from any order row in the Admin Orders table.
   - Configurable package dimensions (`Weight in grams`, `Length`, `Breadth`, `Height` in cm), pre-populated from variant attributes.
   - **Consignment Generation**: 1-click registration of consignments with Shiprocket.
   - **Live Courier Comparison**: Live fetching of available couriers with ratings, delivery ETAs, freight charges, and "Best Rate" recommendation badges.
   - **Logistics Action Toolbar**:
     - *Schedule Pickup* (with date picker)
     - *Print Shipping Label* (direct thermal/A4 PDF download)
     - *Print Manifest* (for carrier handover sign-off)
     - *Print Tax Invoice*
     - *Sync Live Tracking* (pulls immediate carrier scan updates)
     - *Cancel Shipment*

3. **Product Dimensions per Variant**:
   - Variant rows in the Product Add/Edit modal now include dedicated parcel configuration fields (`weight_grams`, `length_cm`, `breadth_cm`, `height_cm`, and `sku`).
   - Defaults safely fall back to site-wide settings (250g, 15x10x8 cm).

4. **Settings Diagnostics**:
   - Integrated "Test Shiprocket Connection" button under Site Settings that executes live diagnostics and reports connection status, wallet balance, and configuration checks.

---

## 6. Customer Experience & Tracking

1. **Enhanced Order Summary (`/orders/[reference]`)**:
   - Automatic polling every 60 seconds for active shipments.
   - **Live Dispatch Card**: Displays carrier name, AWB code (with 1-click clipboard copy), and direct external tracking URL.
   - **Milestone Timeline**: Checkpoint-by-checkpoint scan logs detailing activity, location, and timestamp.
   - **NDR Notice**: If an order status becomes `ndr`, an informative banner alerts the customer with delivery tips and contact concierge links.
   - **Return Request Modal**: For delivered orders within the 7-day botanical guarantee window, customers can initiate a return or replacement directly.

2. **Public Track Order Page (`/orders/track`)**:
   - Supports searching by order reference or AWB.
   - 5-step visual tracking progress bar reflecting `in_transit` and `out_for_delivery` states.
   - Full checkpoint history and carrier information.

---

## 7. Security & Reliability Fixes (Step 0)

All items from the security re-audit have been resolved and verified with tests:

1. **Guest Coupon Crash**: Fixed first-order-only validation bug by scoping past-order checks to billing email and phone number when `user_id` is null.
2. **Next.js Upgrade**: Upgraded `maysha-frontend` to **Next.js 16.4.3** with Turbopack, resolving all known Next.js upstream advisories.
3. **Admin TOTP 2FA**: Implemented RFC 6238 TOTP two-factor authentication for administrators with encrypted secrets and single-use emergency recovery codes.
4. **BFF Header & Secret**: Added strict verification for `x-bff-secret` on private endpoints, preventing direct IP spoofing or untrusted client bypass.
5. **OAuth Code Exchange**: One-time exchange authorization code flow replacing token passing in query parameters, with 60-second expiry and replay protection.
6. **Razorpay Verification**: Strict server-side HMAC-SHA256 signature verification matching Razorpay order ID, payment ID, and signature.
7. **Order Status Transitions**: Enforced state-machine transition validation preventing invalid jumps (e.g., cancelled to delivered).
8. **HTML Sanitization & CSP**: Installed DOMPurify on product descriptions and journal articles, and added Content Security Policy Report-Only headers.

---

## 8. Go-Live Verification Checklist

Before running `deploy.ps1` or pushing to production:

- [ ] **Step 1**: Log in to [Shiprocket](https://app.shiprocket.in/) and confirm your account is active and KYC is complete.
- [ ] **Step 2**: Add money to your Shiprocket prepaid wallet (minimum ₹1,000 recommended).
- [ ] **Step 3**: Verify primary pickup address nickname in Shiprocket (e.g., `Primary`).
- [ ] **Step 4**: Generate a 64-character random webhook secret for `SHIPROCKET_WEBHOOK_TOKEN`.
- [ ] **Step 5**: Update production `.env` on VPS:
  ```dotenv
  SHIPROCKET_EMAIL="your-shiprocket-email@domain.com"
  SHIPROCKET_PASSWORD="your-shiprocket-password"
  SHIPROCKET_WEBHOOK_TOKEN="your-generated-token"
  SHIPROCKET_DEFAULT_PICKUP_LOCATION="Primary"
  ```
- [ ] **Step 6**: In the Shiprocket dashboard, register the webhook:
  - URL: `https://mayshaskincare.com/api/v1/logistics/hook`
  - Header: `x-shiprocket-token: <your-generated-token>`
- [ ] **Step 7**: Run the diagnostic command on VPS:
  ```bash
  php artisan shiprocket:doctor
  ```
  Ensure all checkmarks are GREEN (`[OK]`).
- [ ] **Step 8**: Place a test order (or use existing test order) in Admin Portal:
  - Open order, click "Ship with Shiprocket".
  - Verify rates are retrieved from couriers (Delhivery, Bluedart, etc.).
  - Assign courier and verify AWB is generated.
  - Test printing shipping label and invoice.
- [ ] **Step 9**: Verify tracking page shows carrier details and timeline.
- [ ] **Step 10**: Verify cron schedule is active on VPS (`crontab -l`) running `php artisan schedule:run`.
