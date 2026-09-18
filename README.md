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
