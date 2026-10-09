<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Manila');
if(PHP_SAPI!=='cli') {
ini_set('session.use_strict_mode', '1');
$sessionPath=dirname(__DIR__).'/storage/sessions';
if(!is_dir($sessionPath)) mkdir($sessionPath,0700,true);
session_save_path($sessionPath);
session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax','secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
session_start();
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');
}
function db(): PDO {
    static $db;
    if (!$db) {
        require_once __DIR__.'/config.php';
        $db=mysql_connection();
    }
    return $db;
}
function query(string $sql, array $params=[]): PDOStatement { $q=db()->prepare($sql); $q->execute($params); return $q; }
function rows(string $sql, array $params=[]): array { return query($sql,$params)->fetchAll(); }
function one(string $sql, array $params=[]): ?array { return query($sql,$params)->fetch() ?: null; }
function e($v): string { return htmlspecialchars((string)($v??''),ENT_QUOTES,'UTF-8'); }
function money($v): string { return '₱'.number_format((int)$v/100,2); }
function cents(string $name, bool $signed=false): int {
    $v=trim((string)($_POST[$name]??'0'));
    if (!preg_match($signed?'/^-?\d{1,8}(\.\d{1,2})?$/':'/^\d{1,8}(\.\d{1,2})?$/',$v)) throw new RuntimeException('Enter a valid amount for '.str_replace('_',' ',$name).'.');
    return (int)round((float)$v*100);
}
function required(string $name): string { $v=trim((string)($_POST[$name]??'')); if ($v==='' || strlen($v)>1000) throw new RuntimeException('Please provide '.str_replace('_',' ',$name).'.'); return $v; }
function date_input(string $name): string { $v=required($name); $d=DateTime::createFromFormat('!Y-m-d',$v); if (!$d || $d->format('Y-m-d')!==$v) throw new RuntimeException('Invalid '.$name.'.'); return $v; }
function user(): ?array { return isset($_SESSION['uid']) ? one('SELECT * FROM users WHERE id=? AND active=1',[$_SESSION['uid']]) : null; }
function require_role(array $roles): array { $u=user(); if (!$u || !in_array($u['role'],$roles,true)) { http_response_code(403); throw new RuntimeException('You do not have access to this action.'); } return $u; }
function audit(string $action, string $type='', ?int $id=null, string $details=''): void { query('INSERT INTO audit_logs(user_id,action,record_type,record_id,details) VALUES(?,?,?,?,?)',[user()['id']??null,$action,$type,$id,$details]); }
function token(): string { return $_SESSION['csrf']??=bin2hex(random_bytes(32)); }
function csrf(): string { return '<input type="hidden" name="csrf" value="'.e(token()).'">'; }
function redirect(string $page): never { header('Location: index.php?page='.urlencode($page)); exit; }
function flash(string $message,string $type='success'): void { $_SESSION['flash']=[$message,$type]; }
function bill(int $id): ?array {
    return one("SELECT b.*,t.name,t.email,COALESCE((SELECT SUM(amount) FROM payments p WHERE p.billing_id=b.id AND p.voided_at IS NULL),0) paid FROM billing b JOIN tenants t ON t.id=b.tenant_id WHERE b.id=?",[$id]);
}
function balance(array $b): int { return max(0,(int)$b['total_due']-(int)$b['paid']); }
function status(array $b): string { if ($b['carried_to']) return 'carried'; if(balance($b)===0) return 'paid'; if($b['due_date']<date('Y-m-d')) return 'overdue'; return $b['paid']>0?'partial':'unpaid'; }
function badge(string $s): string { return '<span class="badge '.e($s).'">'.e(ucwords(str_replace('_',' ',$s))).'</span>'; }
function notify(int $tenant, ?int $billing, string $type, string $subject,string $message,?string $key=null): void {
    $t=one('SELECT email FROM tenants WHERE id=?',[$tenant]);
    $insert=query('INSERT INTO notifications(tenant_id,billing_id,type,email,subject,message,dedup_key) VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE id=id',[$tenant,$billing,$type,$t['email'],$subject,$message,$key]);
    if($type==='overdue' && $insert->rowCount()===1) query('INSERT INTO audit_logs(user_id,action,record_type,record_id,details) VALUES(NULL,?,?,?,?)', ['Overdue statement reminder','billing',$billing,$message]);
}
function assigned(): array { return rows('SELECT a.*,t.name,r.number,r.rent,bs.label FROM tenant_assignments a JOIN tenants t ON t.id=a.tenant_id JOIN bed_spaces bs ON bs.id=a.bed_id JOIN rooms r ON r.id=bs.room_id WHERE a.end_date IS NULL ORDER BY t.name'); }
function available_beds(): array { return rows("SELECT bs.*,r.number FROM bed_spaces bs JOIN rooms r ON r.id=bs.room_id WHERE r.status='active' AND NOT EXISTS(SELECT 1 FROM tenant_assignments a WHERE a.bed_id=bs.id AND a.end_date IS NULL) ORDER BY r.number,bs.label"); }
function setting(string $key,string $default=''): string { $r=one('SELECT value FROM settings WHERE `key`=?',[$key]); return $r['value']??$default; }
try { db(); } catch(PDOException $error) {
    error_log('Dormitory MySQL connection failed: '.$error->getCode()); http_response_code(503);
    exit('<!doctype html><meta charset="utf-8"><title>Database connection</title><main style="max-width:600px;margin:15vh auto;font-family:Arial;line-height:1.7"><h1>Start XAMPP MySQL</h1><p>The system needs MySQL running. Check config.database.php for your connection settings.</p><p>First-time setup: <code>C:\\xampp\\php\\php.exe database/setup.php</code></p></main>');
}
