<?php
declare(strict_types=1);

/**
 * Self-running migrations: creates missing tables on the first page load
 * after a deploy, seeds the admin and the first catalogue.
 * No MySQL 8 reserved words as names (e.g. `condition` → `grade`).
 */
const SCHEMA_VERSION = '2026-11-20.3';

function runMigrations(PDO $db): void
{
    try {
        $v = $db->query("SELECT v FROM app_settings WHERE k = 'schema_version'")->fetchColumn();
        if ($v === SCHEMA_VERSION) return;
    } catch (PDOException) {
        // First run: no settings table yet.
    }

    try {
        $tables = [
            "CREATE TABLE IF NOT EXISTS app_settings (
                k VARCHAR(80) PRIMARY KEY, v MEDIUMTEXT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS users (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) NOT NULL UNIQUE,
                full_name VARCHAR(120) NULL,
                password_hash VARCHAR(255) NULL,
                role ENUM('admin','staff') NOT NULL DEFAULT 'admin',
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                last_login_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS login_attempts (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) NOT NULL, ip VARCHAR(45) NOT NULL,
                success TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX (email), INDEX (ip), INDEX (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS products (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                slug VARCHAR(160) NOT NULL UNIQUE,
                title VARCHAR(200) NOT NULL,
                make VARCHAR(60) NOT NULL,
                model VARCHAR(80) NOT NULL,
                engine_code VARCHAR(40) NULL,
                capacity VARCHAR(20) NULL,
                fuel ENUM('petrol','diesel') NULL,
                year_from SMALLINT UNSIGNED NULL,
                year_to SMALLINT UNSIGNED NULL,
                category VARCHAR(40) NOT NULL DEFAULT 'engine',
                grade VARCHAR(40) NULL,
                warranty VARCHAR(80) NULL,
                description TEXT NULL,
                part_number VARCHAR(80) NULL,
                price DECIMAL(10,2) NULL,
                qty SMALLINT UNSIGNED NOT NULL DEFAULT 1,
                stock_status ENUM('available','reserved','sold','hidden') NOT NULL DEFAULT 'available',
                stock_checked_at DATETIME NULL,
                views INT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX (make), INDEX (model), INDEX (category), INDEX (fuel), INDEX (stock_status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS product_media (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                product_id INT UNSIGNED NOT NULL,
                media_kind ENUM('image','video') NOT NULL DEFAULT 'image',
                path VARCHAR(255) NOT NULL,
                poster VARCHAR(255) NULL,
                width SMALLINT UNSIGNED NULL, height SMALLINT UNSIGNED NULL,
                sort_order SMALLINT NOT NULL DEFAULT 0,
                INDEX (product_id, sort_order),
                FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS enquiries (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                product_id INT UNSIGNED NULL,
                customer_name VARCHAR(120) NOT NULL,
                phone VARCHAR(40) NOT NULL,
                email VARCHAR(255) NULL,
                area VARCHAR(120) NULL,
                vehicle VARCHAR(160) NULL,
                message TEXT NULL,
                source VARCHAR(30) NOT NULL DEFAULT 'chat',
                status ENUM('new','contacted','quoted','paid','dispatched','completed','cancelled') NOT NULL DEFAULT 'new',
                admin_notes TEXT NULL,
                ip VARCHAR(45) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX (status), INDEX (created_at), INDEX (product_id),
                FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS customers (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(160) NOT NULL,
                company VARCHAR(160) NULL,
                phone VARCHAR(40) NULL,
                email VARCHAR(255) NULL,
                address VARCHAR(400) NULL,
                vat_no VARCHAR(30) NULL,
                notes TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX (name), INDEX (phone)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS documents (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                doc_type ENUM('quote','invoice') NOT NULL DEFAULT 'quote',
                doc_number VARCHAR(30) NOT NULL UNIQUE,
                token CHAR(32) NOT NULL UNIQUE,
                customer_id INT UNSIGNED NULL,
                enquiry_id INT UNSIGNED NULL,
                converted_from INT UNSIGNED NULL,
                cust_name VARCHAR(160) NOT NULL,
                cust_company VARCHAR(160) NULL,
                cust_phone VARCHAR(40) NULL,
                cust_email VARCHAR(255) NULL,
                cust_address VARCHAR(400) NULL,
                cust_vat_no VARCHAR(30) NULL,
                status ENUM('draft','sent','accepted','declined','part_paid','paid','cancelled') NOT NULL DEFAULT 'draft',
                issue_date DATE NOT NULL,
                due_date DATE NULL,
                vat_rate DECIMAL(5,2) NOT NULL DEFAULT 15.00,
                discount DECIMAL(12,2) NOT NULL DEFAULT 0,
                subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
                vat_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
                total DECIMAL(12,2) NOT NULL DEFAULT 0,
                amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0,
                notes TEXT NULL,
                terms TEXT NULL,
                view_count INT UNSIGNED NOT NULL DEFAULT 0,
                viewed_at DATETIME NULL,
                emailed_at DATETIME NULL,
                whatsapped_at DATETIME NULL,
                created_by INT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX (doc_type, status), INDEX (issue_date), INDEX (customer_id), INDEX (enquiry_id),
                FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
                FOREIGN KEY (enquiry_id) REFERENCES enquiries(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS document_items (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                document_id INT UNSIGNED NOT NULL,
                product_id INT UNSIGNED NULL,
                description VARCHAR(500) NOT NULL,
                qty DECIMAL(10,2) NOT NULL DEFAULT 1,
                unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
                sort_order SMALLINT NOT NULL DEFAULT 0,
                INDEX (document_id, sort_order),
                FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS payments (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                document_id INT UNSIGNED NOT NULL,
                amount DECIMAL(12,2) NOT NULL,
                method VARCHAR(20) NOT NULL DEFAULT 'eft',
                reference VARCHAR(120) NULL,
                paid_on DATE NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX (document_id),
                FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS document_events (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                document_id INT UNSIGNED NOT NULL,
                kind VARCHAR(20) NOT NULL,
                detail VARCHAR(255) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX (document_id),
                FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS ai_requests (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                kind VARCHAR(40) NOT NULL, ok TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS audit_log (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NULL,
                action VARCHAR(80) NOT NULL, detail VARCHAR(500) NULL,
                ip VARCHAR(45) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];
        foreach ($tables as $sql) $db->exec($sql);

        seedAdmin($db);
        seedProducts($db);
        seedSettings($db);

        $db->prepare("REPLACE INTO app_settings (k, v) VALUES ('schema_version', ?)")->execute([SCHEMA_VERSION]);
    } catch (Throwable $e) {
        error_log('EngineEmpire migration failed: ' . $e->getMessage());
        http_response_code(500);
        exit('<!doctype html><meta charset="utf-8"><body style="font-family:system-ui;padding:2rem"><h1>Setup could not finish</h1>'
            . '<p>The database update failed. For the site owner: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</p>');
    }
}

function seedAdmin(PDO $db): void
{
    $pw = (string) secret('admin_seed_password', '');
    if ($pw === '' || $pw === 'CHANGE_ME') return;
    $s = $db->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
    $s->execute([ADMIN_EMAIL]);
    if ((int) $s->fetchColumn() > 0) return;
    $db->prepare("INSERT INTO users (email, full_name, password_hash, role) VALUES (?, 'Administrator', ?, 'admin')")
       ->execute([ADMIN_EMAIL, password_hash($pw, PASSWORD_DEFAULT)]);
}

/**
 * Catalogue from database/seed-products.json (imported from the old southafricanengines.co.za shop: titles, prices, photos).
 * Runs once: on an empty table, or when the table only holds the 12 original sample rows (never touches products you added).
 */
function seedProducts(PDO $db): void
{
    if ($db->query("SELECT v FROM app_settings WHERE k = 'catalogue_import'")->fetchColumn()) return;
    $file = __DIR__ . '/seed-products.json';
    if (!is_file($file)) return;
    $items = json_decode((string) file_get_contents($file), true) ?: [];
    if (!$items) return;

    $sampleSlugs = ['toyota-hilux-2-7-petrol-engine-2tr', 'toyota-hilux-3-0-diesel-engine-1kd', 'vw-polo-1-6-petrol-engine-bts', 'audi-a3-tt-1-8-turbo-engine-aum', 'audi-2-8-6-cyl-petrol-engine-amx',
        'bmw-320i-2-0-petrol-engine-n46', 'ford-ranger-3-2-diesel-engine-p5at', 'nissan-np200-1-6-petrol-engine-k7m', 'hyundai-atos-picanto-1-1-sub-assembly-g4hg',
        'audi-vw-1-8-turbo-manual-gearbox-bfb', 'alfa-romeo-2-0-5spd-gearbox', 'toyota-corolla-automatic-gearbox'];
    $rows = $db->query('SELECT slug FROM products')->fetchAll(PDO::FETCH_COLUMN);
    if ($rows && array_diff($rows, $sampleSlugs)) return; // the owner already has their own products: leave them alone
    $db->beginTransaction();
    $db->exec('DELETE FROM products'); // only the sample rows (checked above)
    $p = $db->prepare("INSERT INTO products (slug, title, make, model, engine_code, capacity, fuel, category, grade, description, part_number, price, year_from, stock_status, stock_checked_at)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'available', NOW())");
    $m = $db->prepare("INSERT INTO product_media (product_id, media_kind, path, sort_order) VALUES (?, 'image', ?, ?)");
    foreach ($items as $it) {
        $p->execute([$it['slug'], $it['title'], $it['make'], $it['model'], $it['code'] ?? null, $it['cap'] ?? null, $it['fuel'] ?? null, $it['category'],
                     $it['grade'] ?? null, $it['desc'] ?? null, $it['part'] ?? null, $it['price'] ?? null, $it['year'] ?? null]);
        $id = (int) $db->lastInsertId();
        foreach (($it['imgs'] ?? []) as $n => $path) $m->execute([$id, $path, $n]);
    }
    $db->prepare("REPLACE INTO app_settings (k, v) VALUES ('catalogue_import', ?)")->execute([date('c')]);
    $db->commit();
}

/** First-run defaults (never overwrites what the owner has saved in Admin → Settings). */
function seedSettings(PDO $db): void
{
    $ins = $db->prepare('INSERT IGNORE INTO app_settings (k, v) VALUES (?, ?)');
    foreach ([
        'whatsapp_number' => '+27673192682', 'business_phone' => '+27 67 319 2682',
        'contact_email' => 'admin@engine-empire.co.za', 'business_email' => 'admin@engine-empire.co.za',
    ] as $k => $v) $ins->execute([$k, $v]);
}
