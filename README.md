# Maysha — REST API Backend

A production-ready Laravel REST API supporting the **Maysha Skincare** ecommerce platform.

## Architecture
- **Framework:** Laravel 12 (PHP 8.2+)
- **API Version:** v1 (`/api/v1/...`)
- **Database:** MySQL / SQLite
- **Endpoints:**
  - `GET /api/v1/store-info` — Store configuration & shipping rules
  - `GET /api/v1/products` — Filterable product catalog (category, concern, search, sort)
  - `GET /api/v1/products/bestsellers` — Curated top-performing formulations
  - `GET /api/v1/products/{slug}` — Full product detail view with variants & usage
  - `GET /api/v1/categories` — Product categories
  - `GET /api/v1/concerns` — Targeted skin concerns
  - `GET /api/v1/cart` — Cart session inspection
  - `POST /api/v1/cart/items` — Add item to cart
  - `POST /api/v1/cart/coupon` — Validate & calculate promo discounts (e.g. `MAYSHA10`)
  - `POST /api/v1/orders` — Checkout order placement
  - `GET /api/v1/reviews` — Customer reviews and ratings

---

## Getting Started

### 1. Install Dependencies
```bash
composer install
```

### 2. Environment Configuration
```bash
cp .env.example .env
php artisan key:generate
```

### 3. Run Development Server
```bash
php artisan serve --port=8000
```
API endpoints will be active on [http://127.0.0.1:8000/api/v1/store-info](http://127.0.0.1:8000/api/v1/store-info).

---

## Connecting to your GitHub Repository

This repository is already initialized with Git and an initial commit. To push it to your own GitHub account:

```bash
git remote add origin https://github.com/<YOUR_USERNAME>/<YOUR_BACKEND_REPO_NAME>.git
git branch -M main
git push -u origin main
```
