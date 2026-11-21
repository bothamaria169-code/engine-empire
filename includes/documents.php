<?php
declare(strict_types=1);

require_once __DIR__ . '/catalog.php';

/**
 * Quotes and invoices. Unit prices are entered EXCLUDING VAT; VAT is added on top
 * of (subtotal - discount) at the document's own rate (0 when the business is not VAT registered).
 */
const DOC_EVENT_LABELS = [
    'created'  => ['Created', 'fa-file-circle-plus'],
    'email'    => ['Emailed to customer', 'fa-envelope'],
    'whatsapp' => ['Sent on WhatsApp', 'fa-brands fa-whatsapp'],
    'viewed'   => ['Opened by customer', 'fa-eye'],
    'accepted' => ['Quote accepted', 'fa-circle-check'],
    'declined' => ['Quote declined', 'fa-circle-xmark'],
    'payment'  => ['Payment recorded', 'fa-money-bill-wave'],
    'status'   => ['Status changed', 'fa-pen'],
    'converted' => ['Converted to invoice', 'fa-file-invoice'],
];

function docNumberFor(string $type, ?string $date = null): string
{
    $db = getDB();
    $prefix = $type === 'invoice' ? (get_setting('invoice_prefix', 'INV')) : (get_setting('quote_prefix', 'QT'));
    $year = date('Y', strtotime($date ?: 'now'));
    $s = $db->prepare('SELECT doc_number FROM documents WHERE doc_type = ? AND doc_number LIKE ? ORDER BY id DESC LIMIT 1');
    $s->execute([$type, "{$prefix}-{$year}-%"]);
    $last = (string) $s->fetchColumn();
    $n = $last !== '' ? (int) substr($last, strrpos($last, '-') + 1) + 1 : 1;
    return sprintf('%s-%s-%04d', $prefix, $year, $n);
}

function docById(int $id): ?array
{
    $s = getDB()->prepare('SELECT * FROM documents WHERE id = ?');
    $s->execute([$id]);
    return $s->fetch() ?: null;
}

function docByToken(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{32}$/', $token)) return null;
    $s = getDB()->prepare('SELECT * FROM documents WHERE token = ?');
    $s->execute([$token]);
    return $s->fetch() ?: null;
}

function docItems(int $docId): array
{
    $s = getDB()->prepare('SELECT * FROM document_items WHERE document_id = ? ORDER BY sort_order, id');
    $s->execute([$docId]);
    return $s->fetchAll();
}

function docPayments(int $docId): array
{
    $s = getDB()->prepare('SELECT * FROM payments WHERE document_id = ? ORDER BY paid_on, id');
    $s->execute([$docId]);
    return $s->fetchAll();
}

function docEvents(int $docId): array
{
    $s = getDB()->prepare('SELECT * FROM document_events WHERE document_id = ? ORDER BY id DESC LIMIT 30');
    $s->execute([$docId]);
    return $s->fetchAll();
}

function docUrl(array $doc): string
{
    return APP_URL . '/doc.php?t=' . $doc['token'];
}

function logDocEvent(int $docId, string $kind, string $detail = ''): void
{
    getDB()->prepare('INSERT INTO document_events (document_id, kind, detail) VALUES (?, ?, ?)')->execute([$docId, $kind, mb_substr($detail, 0, 255) ?: null]);
}

/** @return array{subtotal:float,discount:float,vat:float,total:float} */
function calcTotals(array $items, float $discount, float $vatRate): array
{
    $sub = 0.0;
    foreach ($items as $it) $sub += round((float) $it['qty'] * (float) $it['unit_price'], 2);
    $discount = max(0.0, min($discount, $sub));
    $net = $sub - $discount;
    $vat = round($net * $vatRate / 100, 2);
    return ['subtotal' => round($sub, 2), 'discount' => round($discount, 2), 'vat' => $vat, 'total' => round($net + $vat, 2)];
}

function parseMoney(mixed $v): float
{
    $s = str_replace([' ', ',', 'R', 'r'], ['', '.', '', ''], (string) $v);
    return is_numeric($s) ? round((float) $s, 2) : 0.0;
}

/**
 * Creates or updates a document from the admin form ($_POST). Returns the document id.
 * Items come as parallel arrays: item_desc[], item_qty[], item_price[], item_product[].
 */
function saveDocument(array $in, ?int $id, int $userId): int
{
    $db = getDB();
    $type = ($in['doc_type'] ?? 'quote') === 'invoice' ? 'invoice' : 'quote';
    $items = [];
    foreach ((array) ($in['item_desc'] ?? []) as $i => $desc) {
        $desc = mb_substr(trim((string) $desc), 0, 500);
        $qty = parseMoney($in['item_qty'][$i] ?? 1) ?: 1.0;
        $price = parseMoney($in['item_price'][$i] ?? 0);
        if ($desc === '') continue;
        $items[] = ['desc' => $desc, 'qty' => $qty, 'price' => $price, 'product' => (int) ($in['item_product'][$i] ?? 0) ?: null];
    }
    $vatRate = max(0.0, min(30.0, parseMoney($in['vat_rate'] ?? 0)));
    $t = calcTotals(array_map(fn($x) => ['qty' => $x['qty'], 'unit_price' => $x['price']], $items), parseMoney($in['discount'] ?? 0), $vatRate);
    $field = fn(string $k, int $len) => mb_substr(trim((string) ($in[$k] ?? '')), 0, $len) ?: null;
    $issue = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($in['issue_date'] ?? '')) ? (string) $in['issue_date'] : date('Y-m-d');
    $due = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($in['due_date'] ?? '')) ? (string) $in['due_date'] : null;

    // Keep the customer list tidy: link by phone/email/name, or create.
    $customerId = (int) ($in['customer_id'] ?? 0) ?: null;
    $name = (string) ($field('cust_name', 160) ?? 'Customer');
    if (!$customerId) $customerId = upsertCustomer($name, $field('cust_company', 160), $field('cust_phone', 40), $field('cust_email', 255), $field('cust_address', 400), $field('cust_vat_no', 30));

    $cols = [$customerId, $name, $field('cust_company', 160), $field('cust_phone', 40), $field('cust_email', 255), $field('cust_address', 400), $field('cust_vat_no', 30),
             $issue, $due, $vatRate, $t['discount'], $t['subtotal'], $t['vat'], $t['total'], $field('notes', 2000), $field('terms', 3000)];

    $db->beginTransaction();
    try {
        if ($id) {
            $db->prepare('UPDATE documents SET customer_id=?, cust_name=?, cust_company=?, cust_phone=?, cust_email=?, cust_address=?, cust_vat_no=?, issue_date=?, due_date=?, vat_rate=?, discount=?, subtotal=?, vat_amount=?, total=?, notes=?, terms=? WHERE id=?')
               ->execute([...$cols, $id]);
            $db->prepare('DELETE FROM document_items WHERE document_id = ?')->execute([$id]);
        } else {
            $db->prepare('INSERT INTO documents (doc_type, doc_number, token, enquiry_id, customer_id, cust_name, cust_company, cust_phone, cust_email, cust_address, cust_vat_no, issue_date, due_date, vat_rate, discount, subtotal, vat_amount, total, notes, terms, created_by)
                          VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
               ->execute([$type, docNumberFor($type, $issue), bin2hex(random_bytes(16)), (int) ($in['enquiry_id'] ?? 0) ?: null, ...$cols, $userId]);
            $id = (int) $db->lastInsertId();
            logDocEvent($id, 'created');
        }
        $ins = $db->prepare('INSERT INTO document_items (document_id, product_id, description, qty, unit_price, sort_order) VALUES (?,?,?,?,?,?)');
        foreach ($items as $n => $x) $ins->execute([$id, $x['product'], $x['desc'], $x['qty'], $x['price'], $n]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
    refreshPaymentStatus($id);
    linkDocumentToUser($id);
    return $id;
}

/** Attaches the document to the customer's account when a verified account with that email exists. */
function linkDocumentToUser(int $docId): void
{
    getDB()->prepare("UPDATE documents d JOIN users u ON u.email = d.cust_email AND u.email_verified_at IS NOT NULL AND u.role = 'customer' SET d.user_id = u.id WHERE d.id = ?")->execute([$docId]);
}

/**
 * Sets a status by hand (like Durban's Pallets). Marking an invoice paid records the outstanding balance as a payment,
 * so the books agree. Optionally emails the customer about the change.
 * @return string '' or a message for the admin
 */
function setDocumentStatus(int $id, string $status, bool $notify = false): string
{
    $db = getDB();
    $d = docById($id);
    if (!$d) return 'Document not found.';
    $allowed = $d['doc_type'] === 'quote' ? QUOTE_STATUSES : INVOICE_STATUSES;
    if (!in_array($status, $allowed, true)) return 'That status does not apply to a ' . $d['doc_type'] . '.';
    if ($status === 'paid' && $d['doc_type'] === 'invoice') {
        $bal = round((float) $d['total'] - (float) $d['amount_paid'], 2);
        if ($bal > 0) addPayment($id, $bal, 'eft', 'Marked as paid', date('Y-m-d'));
        $db->prepare("UPDATE documents SET status = 'paid' WHERE id = ?")->execute([$id]);
    } else {
        $db->prepare('UPDATE documents SET status = ? WHERE id = ?')->execute([$status, $id]);
        if ($d['doc_type'] === 'invoice') refreshPaymentStatus($id);
    }
    logDocEvent($id, 'status', DOC_STATUSES[$d['status']] . ' → ' . DOC_STATUSES[$status]);
    if ($notify && $d['cust_email']) {
        $fresh = docById($id);
        $word = $d['doc_type'] === 'quote' ? 'quote' : 'invoice';
        $err = sendMail($d['cust_email'], ucfirst($word) . ' ' . $d['doc_number'] . ' is now ' . strtolower(DOC_STATUSES[$fresh['status']]),
            "Hi " . (trim(explode(' ', trim((string) $d['cust_name']))[0] ?? '') ?: 'there') . ",\n\nYour {$word} {$d['doc_number']} is now: " . DOC_STATUSES[$fresh['status']] . ".\nView it here: " . docUrl($d) . "\n\n" . business()['name'], business()['email'] ?: null);
        if ($err !== '') return 'Status saved, but the email failed: ' . $err;
        logDocEvent($id, 'email', 'Status update to ' . $d['cust_email']);
    }
    return '';
}

function upsertCustomer(string $name, ?string $company, ?string $phone, ?string $email, ?string $address, ?string $vat): ?int
{
    if ($name === '') return null;
    $db = getDB();
    $digits = preg_replace('/\D/', '', (string) $phone);
    $s = $db->prepare('SELECT id FROM customers WHERE (? <> \'\' AND REPLACE(REPLACE(phone, \' \', \'\'), \'-\', \'\') LIKE ?) OR (? IS NOT NULL AND email = ?) OR (? = 1 AND name = ?) LIMIT 1');
    // The name is only used when we have neither phone nor email, so two people called "Thabo" are never merged.
    $s->execute([$digits, '%' . substr($digits, -9), $email, $email, ($digits === '' && $email === null) ? 1 : 0, $name]);
    if ($cid = $s->fetchColumn()) {
        $db->prepare('UPDATE customers SET company = COALESCE(?, company), phone = COALESCE(?, phone), email = COALESCE(?, email), address = COALESCE(?, address), vat_no = COALESCE(?, vat_no) WHERE id = ?')
           ->execute([$company, $phone, $email, $address, $vat, $cid]);
        return (int) $cid;
    }
    $db->prepare('INSERT INTO customers (name, company, phone, email, address, vat_no) VALUES (?,?,?,?,?,?)')->execute([$name, $company, $phone, $email, $address, $vat]);
    return (int) $db->lastInsertId();
}

/** Re-adds up the payments and sets paid / part paid on invoices. */
function refreshPaymentStatus(int $id): void
{
    $db = getDB();
    $doc = docById($id);
    if (!$doc) return;
    $paid = (float) $db->query('SELECT COALESCE(SUM(amount),0) FROM payments WHERE document_id = ' . $id)->fetchColumn();
    $status = $doc['status'];
    if ($doc['doc_type'] === 'invoice' && $status !== 'cancelled') {
        if ($paid >= (float) $doc['total'] && (float) $doc['total'] > 0) $status = 'paid';
        elseif ($paid > 0) $status = 'part_paid';
        elseif (in_array($status, ['paid', 'part_paid'], true)) $status = 'sent';
    }
    $db->prepare('UPDATE documents SET amount_paid = ?, status = ? WHERE id = ?')->execute([$paid, $status, $id]);
}

function addPayment(int $docId, float $amount, string $method, string $reference, string $date): void
{
    if ($amount <= 0) return;
    $method = isset(PAY_METHODS[$method]) ? $method : 'eft';
    $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : date('Y-m-d');
    getDB()->prepare('INSERT INTO payments (document_id, amount, method, reference, paid_on) VALUES (?,?,?,?,?)')->execute([$docId, $amount, $method, mb_substr($reference, 0, 120) ?: null, $date]);
    refreshPaymentStatus($docId);
    logDocEvent($docId, 'payment', rands($amount) . ' by ' . (PAY_METHODS[$method]));
}

/** Copies an accepted/sent quote into a new draft invoice. Returns the invoice id. */
function convertToInvoice(int $quoteId, int $userId): int
{
    $q = docById($quoteId);
    if (!$q || $q['doc_type'] !== 'quote') throw new RuntimeException('Not a quote.');
    $db = getDB();
    $existing = $db->prepare("SELECT id FROM documents WHERE converted_from = ? AND doc_type = 'invoice' AND status <> 'cancelled' LIMIT 1");
    $existing->execute([$quoteId]);
    if ($eid = $existing->fetchColumn()) return (int) $eid;

    $today = date('Y-m-d');
    $days = (int) get_setting('payment_terms_days', '7');
    $db->beginTransaction();
    try {
        $db->prepare('INSERT INTO documents (doc_type, doc_number, token, customer_id, enquiry_id, converted_from, cust_name, cust_company, cust_phone, cust_email, cust_address, cust_vat_no,
                      issue_date, due_date, vat_rate, discount, subtotal, vat_amount, total, notes, terms, created_by)
                      VALUES (\'invoice\', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
           ->execute([docNumberFor('invoice', $today), bin2hex(random_bytes(16)), $q['customer_id'], $q['enquiry_id'], $quoteId, $q['cust_name'], $q['cust_company'], $q['cust_phone'], $q['cust_email'],
                      $q['cust_address'], $q['cust_vat_no'], $today, date('Y-m-d', strtotime("+{$days} days")), $q['vat_rate'], $q['discount'], $q['subtotal'], $q['vat_amount'], $q['total'],
                      $q['notes'], get_setting('invoice_terms', DEFAULT_INVOICE_TERMS) ?: null, $userId]);
        $nid = (int) $db->lastInsertId();
        $db->prepare('INSERT INTO document_items (document_id, product_id, description, qty, unit_price, sort_order) SELECT ?, product_id, description, qty, unit_price, sort_order FROM document_items WHERE document_id = ?')
           ->execute([$nid, $quoteId]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
    logDocEvent($nid, 'created', 'From quote ' . $q['doc_number']);
    logDocEvent($quoteId, 'converted', 'Invoice created');
    $db->prepare("UPDATE documents SET status = 'invoiced' WHERE id = ?")->execute([$quoteId]);
    linkDocumentToUser($nid);
    if ($q['enquiry_id']) $db->prepare("UPDATE enquiries SET status = 'quoted' WHERE id = ? AND status IN ('new','contacted')")->execute([$q['enquiry_id']]);
    return $nid;
}

function waDigits(?string $phone): string
{
    $d = preg_replace('/\D/', '', (string) $phone);
    if ($d === '') return '';
    if (str_starts_with($d, '00')) $d = substr($d, 2);
    if (str_starts_with($d, '0') && strlen($d) === 10) $d = '27' . substr($d, 1);
    return $d;
}

function docIsOverdue(array $doc): bool
{
    return $doc['doc_type'] === 'invoice' && in_array($doc['status'], ['sent', 'part_paid'], true) && $doc['due_date'] && $doc['due_date'] < date('Y-m-d');
}

function docMessage(array $doc): string
{
    $b = business();
    $first = trim(explode(' ', trim((string) $doc['cust_name']))[0] ?? '') ?: 'there';
    $word = $doc['doc_type'] === 'quote' ? 'quote' : 'invoice';
    $line = $doc['doc_type'] === 'quote'
        ? 'It is valid until ' . date('j M Y', strtotime((string) ($doc['due_date'] ?: '+7 days'))) . '. You can accept it online.'
        : 'It is due on ' . date('j M Y', strtotime((string) ($doc['due_date'] ?: 'now'))) . '. Banking details are on the invoice.';
    return "Hi {$first}, here is your {$word} {$doc['doc_number']} for " . rands($doc['total']) . ". {$line}\n\nView / download: " . docUrl($doc) . "\n\nThank you,\n{$b['name']}";
}

function docWhatsappUrl(array $doc): string
{
    return 'https://wa.me/' . waDigits($doc['cust_phone']) . '?text=' . rawurlencode(docMessage($doc));
}

/** Emails the document link to the customer. @return string '' when sent, else the reason */
function emailDocument(array $doc): string
{
    if (!$doc['cust_email']) return 'This customer has no email address.';
    $word = $doc['doc_type'] === 'quote' ? 'Quote' : 'Invoice';
    $err = sendMail($doc['cust_email'], "{$word} {$doc['doc_number']} from " . business()['name'], docMessage($doc), business()['email'] ?: null);
    if ($err === '') {
        getDB()->prepare("UPDATE documents SET emailed_at = NOW(), status = IF(status = 'draft', 'sent', status) WHERE id = ?")->execute([$doc['id']]);
        logDocEvent((int) $doc['id'], 'email', $doc['cust_email']);
    }
    return $err;
}
