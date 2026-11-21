# Engine Empire

A South African shop for tested engines, gearboxes and car parts, with a built-in **quote and invoice system**: a customer sends an enquiry, you turn it into a branded quote, they accept it online, you convert it to an invoice, send it by email or WhatsApp, and record the EFT payments.

**Stack:** PHP 8.1+ · MySQL/MariaDB (PDO) · PHPMailer · vanilla JS · no build step · SWIFTDNA design system (light + dark).

## Area → Where

| Area | Where (file) |
|---|---|
| Home page | `index.php` |
| Shop and filters | `shop.php` |
| One product (with its own quote form) | `part.php?s=<slug>` |
| Contact / get a quote | `contact.php` → saves to Enquiries (`ajax/enquiry.php`) |
| About, Privacy, Terms | `about.php`, `privacy.php`, `terms.php` |
| Customer's quote / invoice page (print → PDF, Accept quote) | `doc.php?t=<secret token>` |
| Sign-in and registration (admin, staff and customers) | `login.php`, `register.php`, `verify.php`, `forgot-password.php`, `reset-password.php` |
| Customer's account page | `account.php` |
| Admin: dashboard, enquiries, **quotes & invoices**, customers, products, settings, audit log | `admin/` |
| Quote and invoice logic (numbers, VAT, payments, WhatsApp/email) | `includes/documents.php` |
| Database tables (created for you on first load) | `database/migrate.php` |
| First 12 products | `database/seed-products.json` |
| Daily housekeeping | `cron.php` |
| Colours, fonts, components | `assets/css/style.css` (`--brand` is the only colour to change) |
| Quote / invoice sheet design | `assets/css/doc.css` |

## Accounts and the owner's guide

- **Customers** register at `/register.php`, confirm their email, and see only their own quotes and invoices (never drafts) in **My account**, each with a **View / PDF** button.
- **Admin → Users & logins** adds staff and admins, changes roles, confirms emails, resets passwords. **Settings → Admin email** changes your sign-in address.
- **Quote and invoice status** is set by hand on each document (**Set status**), like Durban's Pallets: quotes go Draft → Sent → Accepted → Invoiced; invoices Draft → Sent (unpaid) → Part paid → Paid.
- **PDF guide for the owner, with screenshots:** [docs/Engine-Empire-Owner-Guide.pdf](docs/Engine-Empire-Owner-Guide.pdf)

## How a sale flows

1. Customer fills the quote form → **Admin → Enquiries** (and you get an email).
2. Press **Create quote** → items and prices come from the enquiry/product → **Save**.
3. **Email to customer** or **Send on WhatsApp** → they open the secret link and press **Accept quote**.
4. **Convert to invoice** → send it → **Record payment** each time they pay (part payments are fine).
5. The dashboard shows open quotes, outstanding, overdue and paid-this-month.

Prices are entered **excluding VAT**. VAT is added at the rate on the document. If **Settings → Business → VAT number** is blank, new documents get 0% VAT and say "Invoice" instead of "Tax invoice".

## Run it locally

1. Start **Apache** and **MySQL** in XAMPP.
2. Copy `config/secrets.example.php` to `config/secrets.php`. Locally you only need `admin_seed_password`.
3. Open `http://localhost/engine-empire/` (or run `php -S localhost:8766` in this folder).
4. The database `engine-empire` is created for you on the first page load, with 12 sample products.
5. Sign in at `/login.php` with `admin@engine-empire.co.za` and your `admin_seed_password`.

**Test:** open **Admin → Quotes & invoices → New quote**, add a line, press **Save quote**, then **Open PDF view**.

## Deploy

Full click-by-click steps are in **[docs/SETUP-GUIDE.md](docs/SETUP-GUIDE.md)**. After every change: push → cPanel **Update from Remote** → **Deploy HEAD Commit**.

## After deploying: switch things on

Open **Admin → Settings** and fill in, in this order: **Business details**, **Banking details**, **Email**, **Contact (WhatsApp)**. Quotes and invoices print without an address or bank details until you do.

## Troubleshooting

| Problem | Fix |
|---|---|
| "Setup could not finish" page | The database user cannot create tables, or the database is not empty from an old site. Use a **new empty database** (guide section 1). |
| Quote/invoice email "not sent" | Fill in the mailbox under **Settings → Email**, then **Send test email**. Use **Send on WhatsApp** meanwhile. |
| Locked out of admin | Put `'admin_reset_password' => 'NewPassword123'` in `config/secrets.php`, load any page once, then remove the line. |
| "MySQL refused the username or password (error 1045)" | In `config/secrets.php` on the server, `db_name` and `db_user` must be the FULL names with the cPanel prefix (e.g. `yourname_engines`), the user must be added to the database with **All Privileges**, and `db_pass` must be that user's password (re-set it in **MySQL® Databases → Change Password** and paste it again). |
| Shop shows old placeholder products | Delete them under **Admin → Products** and add your own. |

## Not built yet (next phases)

Groq-written SEO articles and the cron auto-poster, Facebook/Instagram auto-posting, WhatsApp chat bot, and a Meta Pixel / Conversions API test button. The Groq helper (`includes/ai.php`) is already wired to **✨ Write with AI** on products.
