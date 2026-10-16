# Petal Moments Flowers & Events

A full-stack flowers & events web application with a **customer side**, an **admin side**, and a **MySQL database**, built with PHP (PDO) running on XAMPP.

## Tech Stack

- **Frontend**: HTML, CSS, JavaScript
- **Backend**: PHP 8.2 (PDO with prepared statements)
- **Database**: MySQL / MariaDB
- **Server**: Apache (XAMPP)

## Project Structure

```
petal-moments/
├── index.php                 # Customer homepage (dynamic)
├── shop.php                  # Browse products, filter by category, search
├── product.php               # Product detail + add to cart
├── cart.php                  # Shopping cart (session-based)
├── checkout.php              # Checkout (creates order, requires login)
├── thanks.php                # Order confirmation
├── account.php               # Customer profile + order history
├── register.php / login.php / logout.php
├── events.php                # Event styling inquiry form
├── newsletter.php            # Newsletter signup handler
├── config/
│   ├── database.php          # PDO connection (Data Access Layer)
│   └── functions.php         # Shared helpers: auth, sessions, security
├── includes/
│   ├── header.php / footer.php   # Reusable customer layout
├── admin/
│   ├── index.php             # Admin dashboard (stats + recent orders)
│   ├── products.php          # Product CRUD
│   ├── categories.php        # Category CRUD
│   ├── orders.php            # Manage order status (pending/processing/delivered/cancelled)
│   ├── users.php             # Manage users (enable/disable, roles)
│   ├── inquiries.php         # Manage event inquiries
│   ├── subscribers.php       # Newsletter subscribers
│   └── includes/             # Admin layout
├── database.sql              # Full schema + seed data
└── styles.css / script.js
```

## Setup Instructions

1. **Install XAMPP** — this project already expects XAMPP installed at `C:\xampp` with Apache + MySQL enabled.
2. **Copy the project** folder into `C:\xampp\htdocs\` (so it lives at `C:\xampp\htdocs\petal-moments\`).
3. **Start Apache and MySQL** from the XAMPP Control Panel.
4. **Create the database**:
   - Open `http://localhost/phpmyadmin`, or
   - Run: `mysql -u root < database.sql` (from `C:\xampp\mysql\bin`)
5. **Open the site**: `http://localhost/petal-moments/`
6. **Open the admin panel**: `http://localhost/petal-moments/admin/` (must be logged in as admin)

## Employee access

Run `C:\xampp\php\php.exe scripts/install-employee-role.php` on existing databases. In **Admin → Users**, select Customer, Employee, or Admin and click **Save Role**.

Employees land on their own dashboard and can view orders, advance pending → processing → delivered, update event inquiry statuses, and read/mark customer messages. Online payments require admin verification before employees advance fulfillment. Employees cannot access the admin analytics dashboard, catalog management, users/roles, store settings, subscribers, reviews, CSV exports, payment verification, receipt images, message deletion, or order cancellation/reopening. Privileges are refreshed from the database each request, so demotions and disabled staff accounts lose staff access on their next request.

## Password recovery

Run `C:\xampp\php\php.exe scripts/install-password-reset.php` once after importing the database (also when deploying an existing installation). This adds the password reset table without changing accounts.

The login page links to `forgot-password.php`. Reset links are single-use, expire after 30 minutes, and only hashed tokens are stored. Request limits apply per email and IP address.

Configure a working PHP `mail()` transport in `php.ini` (XAMPP sendmail with an SMTP provider, or a local SMTP relay). Then create the git-ignored `config/mail.local.php`:

```php
<?php
return [
    'base_url' => 'https://your-real-website.example',
    'from' => 'your-verified-sender@example.com',
];
```

For local development, use `http://localhost/petal-moments` as `base_url`; links will only work on the machine running XAMPP. Environment variables `PETAL_BASE_URL` and `PETAL_MAIL_FROM` are also supported. Restart Apache after changing PHP/environment settings. The request button stays disabled until a sender is configured; mail delivery failures are recorded in the PHP error log without exposing reset tokens.

Run the CLI regression checks with `C:\xampp\php\php.exe tests/password-reset.php`.

## Default Accounts (from database.sql)

| Role     | Email                     | Password     |
|----------|---------------------------|--------------|
| Admin    | admin@petalmoments.com    | admin123     |
| Customer | customer@petalmoments.com | customer123  |

## Database Schema (ERD summary)

- **users** (1) ──→ (many) **orders**, **event_inquiries**, **wishlist**
- **categories** (1) ──→ (many) **products**
- **products** (1) ──→ (many) **order_items**
- **orders** (1) ──→ (many) **order_items**, **order_status_history**

Key tables: `users`, `categories`, `products`, `orders`, `order_items`, `order_status_history`, `wishlist`, `event_inquiries`, `newsletter_subscribers`.

## Security Notes

- Passwords stored using `password_hash()` / `password_verify()` (bcrypt).
- All database access uses **PDO prepared statements** to prevent SQL injection.
- Admin pages are protected by a session-based `require_admin()` check.
- Output is escaped with `htmlspecialchars()` to prevent XSS.
