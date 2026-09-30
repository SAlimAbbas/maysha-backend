# Maysha — REST API Backend (Laravel 12)

Production backend for the **Maysha** clean, science-backed skincare ecommerce platform. Built to serve the Next.js frontend with strict security, high performance, robust data integrity, and exact frontend type compatibility.

---

## 1. Tech Stack & Architecture

- **Framework:** Laravel 12 (PHP 8.2+)
- **Database:** MySQL in dev & prod (SQLite in-memory for lightning-fast test suite)
- **Cache & Queues:** Redis
- **Authentication:** Laravel Sanctum (HttpOnly cookie SPA mode / API tokens) + Laravel Socialite (Google OAuth)
- **Media Storage & Streaming:** Cloudflare Images & Cloudflare Stream (Direct Creator Upload flow)
- **Payment Processing:** Razorpay (Server-side order generation, HMAC SHA-256 signature verification, idempotent webhook handling)
- **Money Handling:** Stored strictly as **integer paise** in the database; API Resources expose rupees to match frontend TypeScript types.
- **Envelope Standard:**
  ```json
  { "success": true, "data": {}, "meta": {} }
  { "success": false, "message": "...", "errors": { "field": ["..."] } }
  ```

---

## 2. API Endpoints Directory (`/api/v1/...`)

### 2.1 Authentication & Profile (`/api/v1/auth`)
| Method | Endpoint | Description | Rate Limit |
|---|---|---|---|
| `POST` | `/auth/register` | Register customer with strong password validation | 5 / hr / IP |
| `POST` | `/auth/login` | Email/password login with lockout throttle | 5 / min / email+IP |
| `POST` | `/auth/logout` | Invalidate current session/token | Authenticated |
| `POST` | `/auth/forgot-password` | Send password reset link (anti-enumeration response) | 3 / hr / email |
| `POST` | `/auth/reset-password` | Reset password via signed token | 3 / hr / email |
| `GET` | `/auth/verify-email/{id}/{hash}` | Verify email address | Public |
| `POST` | `/auth/resend-verification` | Resend verification email | 3 / 10 min |
| `GET` | `/auth/google/redirect` | Redirect to Google OAuth consent screen | 20 / min |
| `GET` | `/auth/google/callback` | Google OAuth callback (with anti-takeover linking rule) | 20 / min |
| `GET` | `/auth/me` | Fetch authenticated user profile & addresses | Authenticated |
| `PATCH` | `/auth/me` | Update customer profile details | Authenticated |
| `POST` | `/auth/change-password` | Change password with old password verification | Authenticated |

### 2.2 Catalog & Storefront (`/api/v1`)
| Method | Endpoint | Description | Rate Limit |
|---|---|---|---|
| `GET` | `/store-info` | Store configuration (free shipping threshold ₹999, shipping fee ₹99) | 120 / min |
| `GET` | `/products` | Filterable catalog (`category`, `concern`, `min_price`, `max_price`, `sort`, `q`) | 120 / min |
| `GET` | `/products/bestsellers` | Curated top-performing formulations | 120 / min |
| `GET` | `/products/new` | Latest product launches | 120 / min |
| `GET` | `/products/{slug}` | Full product details with variants, benefits, usage, reviews | 120 / min |
| `GET` | `/search/suggest` | Real-time autocomplete suggestions | 30 / min |
| `GET` | `/categories` | Active categories with product count | 120 / min |
| `GET` | `/concerns` | Targeted skin concerns | 120 / min |
| `GET` | `/banners` | Active homepage carousel banners (max 7 active rule) | 120 / min |
| `GET` | `/reviews` | Public approved product reviews | 120 / min |
| `POST` | `/reviews` | Submit product review (requires verified email & purchase check) | 3 / hr / user |

### 2.3 Cart & Checkout (`/api/v1`)
| Method | Endpoint | Description | Rate Limit |
|---|---|---|---|
| `GET` | `/cart` | Fetch guest (via cookie token) or authenticated user cart | 60 / min |
| `POST` | `/cart/items` | Add variant to cart (server checks real-time inventory) | 60 / min |
| `PUT` | `/cart/items/{id}` | Update item quantity | 60 / min |
| `DELETE` | `/cart/items/{id}` | Remove line item | 60 / min |
| `POST` | `/cart/coupon` | Apply coupon code (e.g. `WELCOME10`, `MAYSHA10`) | 10 / min |
| `DELETE` | `/cart/coupon` | Remove applied coupon | 10 / min |
| `POST` | `/checkout/quote` | Validate address and calculate shipping + order totals | 10 / hr |
| `POST` | `/orders` | Transactional order placement with `Idempotency-Key` header & row-locking | 10 / hr |
| `GET` | `/orders/{reference}` | Get order details (IDOR-protected: owner or matching guest email) | 60 / min |

### 2.4 Payments (`/api/v1/payments/razorpay`)
| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/create-order` | Generate Razorpay order ID server-side |
| `POST` | `/verify` | Verify payment signature (HMAC SHA-256) and mark order paid |
| `POST` | `/webhook` | Razorpay webhook listener (`payment.captured`, `payment.failed`) with idempotency |

### 2.5 Newsletter & Customer Inquiries (`/api/v1`)
| Method | Endpoint | Description | Rate Limit |
|---|---|---|---|
| `POST` | `/newsletter` | Subscribe to journal with invisible honeypot trap | 3 / hr / IP |
| `POST` | `/contact` | Submit skincare consultation message (sanitized HTML, honeypot) | 3 / hr / IP |

### 2.6 Admin Portal (`/api/v1/admin/*` — Requires `role: admin` & Sanctum auth)
| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/admin/dashboard/stats` | Aggregate metrics (revenue, orders count, pending orders, low stock alerts, recent audits) |
| `GET` | `/admin/customers` | Customer directory with order counts |
| `GET` | `/admin/products` | Paginated product catalog management |
| `POST` | `/admin/products` | Create product with multiple variants & inventory (audited) |
| `PUT` | `/admin/products/{id}` | Update product information (audited) |
| `DELETE` | `/admin/products/{id}` | Soft-delete product (audited) |
| `GET` | `/admin/orders` | View orders list with status filter |
| `PUT/PATCH` | `/admin/orders/{id}/status` | Update fulfillment status; automatically restores inventory if cancelled/refunded |
| `POST` | `/admin/orders/{id}/refund` | Refund order and return items to inventory (audited) |
| `GET` | `/admin/reviews` | List reviews with approval filter |
| `PUT/PATCH` | `/admin/reviews/{id}/approve` | Approve review and automatically recalculate product rating average & review count |
| `DELETE` | `/admin/reviews/{id}` | Remove review and recalculate product ratings |
| `GET` | `/admin/media` | List Cloudflare uploaded media |
| `POST` | `/admin/media/upload-url` | Generate Cloudflare direct creator upload URL (MIME & size validated) |
| `POST` | `/admin/media/{id}/confirm` | Verify uploaded asset on Cloudflare and activate media record |
| `POST` | `/admin/banners` | Create homepage banner (strictly enforces max 7 active banners) |
| `PUT` | `/admin/banners/{id}` | Update banner |
| `DELETE` | `/admin/banners/{id}` | Delete banner |
| `POST` | `/admin/banners/reorder` | Update display order of banners |

---

## 3. Security Highlights

1. **Anti-Account Takeover OAuth Linking:** Google sign-in checks `google_id` first. It links to existing accounts by email **only if Google reports `email_verified=true`** AND the local account has also verified its email.
2. **Timing-Attack Resilient Authentication:** `login` and `forgot-password` execute uniform dummy hashes and identical messages to prevent user enumeration.
3. **IDOR Defense on Orders:** Guests cannot inspect arbitrary orders by guessing references; guest requests must supply the purchaser's email or signed token.
4. **Idempotent Order Creation:** Checkout orders require an `Idempotency-Key` header. Duplicate requests within 24 hours safely return the original order without double-charging or deducting extra stock.
5. **Real-time Inventory Row-Locking:** Variant stock checks and decrements use `lockForUpdate()` within atomic database transactions, preventing race conditions.
6. **Defense Against Bots:** Honeypot fields (`website`, `bot_trap`) on public newsletter and contact forms silently discard automated bots.
7. **Strict Rate Limiting:** Named Redis-backed limiters protect every sensitive endpoint group (`auth-login`, `auth-register`, `coupon`, `checkout`, `media-sign`, etc.).
8. **Security Headers Middleware:** Out-of-the-box `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy`.

---

## 4. Setup & Installation

### 4.1 Prerequisites
- PHP 8.2 or higher with `pdo_mysql`, `bcmath`, `curl`, `mbstring`, `fileinfo`
- MySQL 8.0+
- Composer 2.x
- Redis server (recommended for caching & queue workers)

### 4.2 Installation
```bash
# Clone the repository
git clone https://github.com/syedalimabbas/maysha-backend.git
cd maysha-backend

# Install PHP dependencies
composer install

# Environment configuration
cp .env.example .env
php artisan key:generate
```

### 4.3 Configure `.env`
Update `.env` with your database and service credentials:
```ini
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=maysha_db
DB_USERNAME=root
DB_PASSWORD=

FRONTEND_URL=http://localhost:3000
SANCTUM_STATEFUL_DOMAINS=localhost:3000,127.0.0.1:3000

RAZORPAY_KEY_ID=rzp_test_yourkey
RAZORPAY_KEY_SECRET=your_secret
RAZORPAY_WEBHOOK_SECRET=your_webhook_secret

CLOUDFLARE_ACCOUNT_ID=your_cf_account
CLOUDFLARE_API_TOKEN=your_cf_token
CLOUDFLARE_IMAGES_DELIVERY_URL=https://imagedelivery.net/your_hash
```

### 4.4 Run Migrations & Seeders
```bash
# Run database migrations
php artisan migrate

# Seed catalog, categories, concerns, sample products, variants, and reviews
php artisan db:seed

# Seed the initial administrator account
php artisan maysha:seed-admin --email="admin@maysha.com" --password="YourSecureAdminPassword123!"
```

### 4.5 Start Development Server
```bash
php artisan serve --port=8000
```
API root: `http://127.0.0.1:8000/api/v1`

---

## 5. Testing & Code Quality

The backend features an automated test suite covering authentication, IDOR prevention, cart calculations, idempotency, Razorpay verification, Cloudflare media flows, and administrative moderation:

```bash
# Run full PHPUnit test suite (41 tests, 464 assertions)
php artisan test

# Run code style formatting (Laravel Pint)
.\vendor\bin\pint

# Security vulnerability audit
composer audit
```

---

## 6. License
Proprietary software for Maysha Skincare. All rights reserved.
