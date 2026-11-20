# Engine Empire — setup guide

Follow the sections in order. Each one ends with a test.

🟦 **ACCOUNT** = another website (GitHub, Groq, Google, Meta) · 🟩 **ADMIN** = your admin at `/admin` · 🟧 **CPANEL** = your hosting

## Every URL you'll paste

| Where | Field | Paste exactly |
|---|---|---|
| cPanel → Git™ Version Control | Clone URL | `https://github.com/bothamaria169-code/engine-empire.git` |
| cPanel → Git™ Version Control | Repository Path | `repositories/engine-empire` |
| cPanel → Git™ Version Control | Repository Name | `engine-empire` |
| Google Search Console | Sitemap | `sitemap.xml` |
| Your browser | Admin sign-in | `https://<your-domain>/login.php` |

> The default domain in the code is `engine-empire.co.za`. If the site goes live on `southafricanengines.co.za` or another domain, set `'app_url'` in `config/secrets.php` and change the two domain lines in `.htaccess` and the sitemap line in `robots.txt`.

---

## 1. Database (5 min) 🟧 CPANEL

> Make a **new, empty** database. Do not reuse the old WordPress or Shopify site's database. Old tables with the same names stop the set-up.

1. cPanel → **MySQL® Databases**.
2. **Create New Database** → type `engines` → **Create Database**. Note the full name (it gets your account prefix, like `yourname_engines`).
3. **MySQL Users → Add New User** → Username `engines` → **Password Generator** → copy the password → **Create User**.
4. **Add User To Database** → pick the user and the database → **Add** → tick **ALL PRIVILEGES** → **Make Changes**.

**Test:** both appear under **Current Databases** with the user listed next to the database.

## 2. Secrets file (5 min) 🟧 CPANEL

1. On your computer, copy `config/secrets.example.php` and name the copy `secrets.php`.
2. Fill in `db_name`, `db_user`, `db_pass` from section 1.
3. Set `admin_seed_password` to a strong password. This is your first admin password.
4. Change `app_secret` and `cron_key` to long random text.
5. After section 3 has created the folders: cPanel → **File Manager** → `public_html/config` → **Upload** → choose your `secrets.php`.

> `secrets.php` is never in GitHub. Deploys do not touch it.

**Test:** in File Manager, `public_html/config/secrets.php` exists.

## 3. Put the site live (10 min) 🟦 ACCOUNT + 🟧 CPANEL

1. 🟦 The code is already on GitHub. The repository must not be empty when cPanel clones it.
2. 🟧 cPanel → **Git™ Version Control** → **Create**.
3. Leave **Clone a Repository** switched on.
4. **Clone URL**: `https://github.com/bothamaria169-code/engine-empire.git`
5. **Repository Path**: `repositories/engine-empire`
6. **Repository Name**: `engine-empire`
7. Click **Create**.
8. In the list, click **Manage** → **Pull or Deploy** → **Update from Remote** → **Deploy HEAD Commit**.
9. Upload `secrets.php` (section 2, step 5).
10. cPanel → **Select PHP Version** → choose **8.2**.

> **Error `"" is not a valid "branch"` and Deploy greyed out?** cPanel cloned while the repository was empty. cPanel → **Terminal** → `cd ~/repositories/engine-empire && git fetch origin && git checkout -B main origin/main` → refresh **Pull or Deploy**.

> The deploy copies the site into `public_html`. Old site files stay there too. Download a backup of `public_html` first if you want to keep the old site.

**Test:** open `https://<your-domain>/` in a private window. You see the Engine Empire home page.

## 4. Sign in and fill in your business (10 min) 🟩 ADMIN

1. Open `https://<your-domain>/login.php`.
2. Email `admin@engine-empire.co.za`, password = your `admin_seed_password`.
3. **Settings → Change my password** and set your own.
4. **Settings → Business details**: name, phone, email, address, VAT number (blank if not registered).
5. **Settings → Banking details**: bank, account name, number, type, branch code.
6. **Settings → Quote & invoice defaults**: check the terms and the validity and payment days.
7. **Settings → Contact**: WhatsApp number (shows the green buttons).
8. Press **Save settings**.

**Test:** **Quotes & invoices → New quote** → add a line → **Save quote** → **Open PDF view**. Your address and bank details appear on the sheet.

## 5. Email (10 min) 🟧 CPANEL + 🟩 ADMIN

1. 🟧 cPanel → **Email Accounts** → **Create** `info@<your-domain>`.
2. Click **Connect Devices** and note the **Outgoing Server** (e.g. `mail.<your-domain>`) and port **465**.
3. 🟩 **Admin → Settings → Email**: paste the server, port, mailbox and its password → **Save settings**.
4. Press **Send test email**.

**Test:** the test email arrives (check spam). Then open a quote and press **Email to customer** with your own email as the customer.

## 6. Products (15 min) 🟩 ADMIN

1. **Products** → delete the sample products you do not stock.
2. **Add a product**: title, make, model, engine code, capacity, fuel, type, condition, warranty, price (blank shows **Get a quote**).
3. **Add photos / videos**: photos are shrunk to WebP automatically.
4. Optional: **✨ Write with AI** needs a Groq key (**Settings → AI**, from console.groq.com → API Keys → **Create API Key**).

**Test:** open the product on the shop page. The photos, price and **Get a quote** button show.

## 7. Google (20 min) 🟦 ACCOUNT

1. **Google Search Console** → add `https://<your-domain>` → **HTML tag** → copy only the `content` code → **Admin → Settings → Google & Meta tracking → Search Console verification code** → **Save settings** → back in Search Console press **Verify**.
2. **Search Console → Sitemaps** → type `sitemap.xml` → **Submit**.
3. **Google Analytics 4** → create a property → copy the **Measurement ID** (`G-…`) → **Settings → Google Analytics 4 ID**.

**Test:** Search Console shows the sitemap as **Success**.

## 8. Daily housekeeping (5 min) 🟧 CPANEL

1. cPanel → **Cron Jobs** → **Common Settings: Once Per Day**.
2. Command: `/usr/local/bin/php /home/<cpanel-user>/public_html/cron.php >> /home/<cpanel-user>/cron.log 2>&1`
3. **Add New Cron Job**.

**Test:** the next day `cron.log` is not full of errors.

## After every change

Push to GitHub → 🟧 cPanel → **Git™ Version Control** → **Manage** → **Pull or Deploy** → **Update from Remote** → **Deploy HEAD Commit**. ("Deploy" alone re-copies the old commit.)
