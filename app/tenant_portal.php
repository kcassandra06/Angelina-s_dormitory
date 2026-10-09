<?php
function local_time(string $value): string { return (new DateTime($value,new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Manila'))->format('M j, Y · g:i A'); }
function pending_amount(int $bill): int { return (int)query("SELECT COALESCE(SUM(amount),0) FROM payment_submissions WHERE billing_id=? AND status='pending'",[$bill])->fetchColumn(); }
function submitted_payments(array $u): array {
    return rows('SELECT s.*,t.name,b.period,u.name reviewer FROM payment_submissions s JOIN tenants t ON t.id=s.tenant_id JOIN billing b ON b.id=s.billing_id LEFT JOIN users u ON u.id=s.reviewed_by '.($u['role']==='tenant'?'WHERE s.tenant_id=? ':'')."ORDER BY (s.status='pending') DESC,s.id DESC",$u['role']==='tenant'?[$u['tenant_id']]:[]);
}
function save_payment_proof(string $field='proof'): ?string {
    $file=$_FILES[$field]??null; if(!$file || $file['error']===UPLOAD_ERR_NO_FILE) return null;
    if($file['error']!==UPLOAD_ERR_OK) throw new RuntimeException('Proof could not be uploaded. Choose an image smaller than 2 MB.');
    if($file['size']>2*1024*1024) throw new RuntimeException('Payment proof must be smaller than 2 MB.');
    $type=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $extensions=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if(!isset($extensions[$type]) || !@getimagesize($file['tmp_name'])) throw new RuntimeException('Proof must be a valid JPG, PNG, or WebP image.');
    $dir=dirname(__DIR__).'/storage/payment-proofs'; if(!is_dir($dir)) mkdir($dir,0700,true);
    $filename=bin2hex(random_bytes(24)).'.'.$extensions[$type];
    if(!move_uploaded_file($file['tmp_name'],$dir.'/'.$filename)) throw new RuntimeException('Proof could not be saved. Try again.');
    $GLOBALS['new_payment_proof']=$dir.'/'.$filename; return $filename;
}
function show_payment_proof(array $u): never {
    if($u['must_change']) redirect('account');
    $s=one('SELECT tenant_id,proof_file FROM payment_submissions WHERE id=?',[(int)($_GET['proof']??0)]);
    if(!$s || !$s['proof_file'] || ($u['role']==='tenant' && (int)$s['tenant_id']!==(int)$u['tenant_id'])) { http_response_code(404); exit('Proof not found.'); }
    $file=dirname(__DIR__).'/storage/payment-proofs/'.basename($s['proof_file']);
    if(!is_file($file)) { http_response_code(404); exit('Proof not found.'); }
    header('Content-Type: '.(new finfo(FILEINFO_MIME_TYPE))->file($file)); header('Content-Disposition: inline'); header('Cache-Control: private, no-store'); readfile($file); exit;
}
function show_payment_qr(array $u): never {
    if($u['must_change']) redirect('account');
    $name=setting('gcash_qr');
    $file=dirname(__DIR__).'/storage/payment-proofs/'.basename($name);
    if(!$name || !is_file($file)) { http_response_code(404); exit('Payment QR not configured.'); }
    header('Content-Type: '.(new finfo(FILEINFO_MIME_TYPE))->file($file)); header('Cache-Control: private, no-store'); readfile($file); exit;
}
function portal_action(string $action,array $u): bool {
    switch($action) {
    case 'read_notifications':
        require_role(['admin','staff','tenant']); $items=activity_feed($u); $latest=min(max(0,(int)($_POST['seen_through']??0)),$items?(int)$items[0]['id']:0);
        query('INSERT INTO settings(`key`,value) VALUES(?,?) ON DUPLICATE KEY UPDATE value=GREATEST(CAST(value AS UNSIGNED),VALUES(value))',['activity_seen_'.$u['id'],(string)$latest]); return true;
    case 'submit_payment':
        require_role(['tenant']); $key=required('submission_key');
        if(one('SELECT id FROM payment_submissions WHERE submission_key=? AND tenant_id=?',[$key,$u['tenant_id']])) { $_SESSION['action_message']='This payment submission is already in your history.'; return true; }
        $_SESSION['payment_draft']=array_intersect_key($_POST,array_flip(['billing_id','amount','payer','payment_date','reference','notes','payment_channel']));
        if(!in_array($_POST['payment_channel']??'gcash',['gcash','qr'],true)) throw new RuntimeException('Office payments are received and recorded by staff. Choose a digital payment method for an online submission.');
        $id=(int)required('billing_id'); $b=bill($id); $amount=cents('amount');
        if(!$b || (int)$b['tenant_id']!==(int)$u['tenant_id']) throw new RuntimeException('Choose one of your own billing statements.');
        if($b['carried_to'] || $amount<=0 || $amount>balance($b)-pending_amount($id)) throw new RuntimeException('Use the current statement and an amount no greater than its balance after pending submissions.');
        $reference=required('reference'); if(strlen($reference)>150) throw new RuntimeException('The reference number is too long.');
        if(one("SELECT id FROM payment_submissions WHERE reference=? AND status IN ('pending','approved')",[$reference]) || one("SELECT id FROM payments WHERE method='GCash' AND reference=?",[$reference])) throw new RuntimeException('This reference has already been submitted or recorded.');
        $date=date_input('payment_date'); if($date>date('Y-m-d')) throw new RuntimeException('Payment date cannot be in the future.');
        $proof=save_payment_proof();
        query('INSERT INTO payment_submissions(tenant_id,billing_id,amount,payer,payment_date,reference,proof_file,notes,submission_key) VALUES(?,?,?,?,?,?,?,?,?)',[$u['tenant_id'],$id,$amount,required('payer'),$date,$reference,$proof,trim($_POST['notes']??''),$key]); $sid=(int)db()->lastInsertId();
        audit('Tenant submitted GCash payment for verification','payment_submissions',$sid);
        notify((int)$u['tenant_id'],$id,'submission','Payment submission received','Your '.money($amount).' GCash submission (reference '.$reference.') is pending staff verification. Your balance changes after verification.');
        unset($_SESSION['payment_draft']);
        $_SESSION['action_message']='Payment submitted for verification. Your balance stays unchanged until staff confirms the payment.'; return true;
    case 'cancel_submission':
        require_role(['tenant']); $id=(int)required('submission_id');
        $s=one("SELECT * FROM payment_submissions WHERE id=? AND tenant_id=? AND status='pending'",[$id,$u['tenant_id']]);
        if(!$s) throw new RuntimeException('Only your own pending submissions can be cancelled.');
        query("UPDATE payment_submissions SET status='cancelled' WHERE id=?",[$id]); audit('Tenant cancelled pending payment submission','payment_submissions',$id); return true;
    case 'review_submission':
        require_role(['admin','staff']); $id=(int)required('submission_id'); $decision=required('decision');
        $s=one("SELECT * FROM payment_submissions WHERE id=? AND status='pending'",[$id]);
        if(!$s) throw new RuntimeException('This submission was already reviewed or cancelled. Reload the list.');
        if(!in_array($decision,['approved','rejected'],true)) throw new RuntimeException('Invalid review decision.');
        $note=trim($_POST['review_note']??''); if($decision==='rejected' && $note==='') throw new RuntimeException('Explain why the payment could not be verified so the tenant can correct it.');
        $pid=null;
        if($decision==='approved') {
            if(empty($_POST['verified'])) throw new RuntimeException('Confirm that you verified receipt of the funds before approving.');
            $b=bill((int)$s['billing_id']);
            if(!$b || $b['carried_to'] || $s['amount']>balance($b)) throw new RuntimeException('The bill balance changed. Reject this submission with an explanation and contact the tenant.');
            if(one("SELECT id FROM payments WHERE method='GCash' AND reference=?",[$s['reference']])) throw new RuntimeException('This reference is already in recorded payments. Do not record it twice.');
            query("INSERT INTO payments(billing_id,tenant_id,amount,method,payer,payment_date,reference,received_by,idempotency_key,balance_after) VALUES(?,?,?,'GCash',?,?,?,?,?,?)",[$s['billing_id'],$s['tenant_id'],$s['amount'],$s['payer'],$s['payment_date'],$s['reference'],$u['id'],'submission-'.$id,balance($b)-$s['amount']]); $pid=(int)db()->lastInsertId();
            notify((int)$s['tenant_id'],(int)$s['billing_id'],'receipt','Payment receipt AD-'.str_pad((string)$pid,6,'0',STR_PAD_LEFT),'GCash payment verified: '.money($s['amount']).'. Remaining balance: '.money(balance($b)-$s['amount']).'. Reference: '.$s['reference'].'.');
            audit('Verified submitted payment and generated receipt','payments',$pid);
        } else notify((int)$s['tenant_id'],(int)$s['billing_id'],'submission','Payment submission needs correction','Reference '.$s['reference'].': '.$note.'. The bill balance is unchanged. Please contact the dormitory or submit corrected details.');
        query('UPDATE payment_submissions SET status=?,reviewed_by=?,review_note=?,reviewed_at=UTC_TIMESTAMP(),payment_id=? WHERE id=?',[$decision,$u['id'],$note,$pid,$id]);
        audit('Reviewed tenant payment submission','payment_submissions',$id,$decision.' · '.$note);
        $_SESSION['action_message']=$decision==='approved'?'Payment verified. The balance is updated and the receipt is available to the tenant.':'Submission declined. The reason is visible to the tenant.'; return true;
    case 'profile_update':
        require_role(['tenant']); $email=required('email'); $name=required('name'); $contact=required('contact');
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>254||strlen($name)>200||strlen($contact)>80) throw new RuntimeException('Check your name, email, and contact number.');
        if(one('SELECT id FROM tenants WHERE email=? AND id<>?',[$email,$u['tenant_id']])) throw new RuntimeException('This email is linked to another tenant.');
        query('UPDATE tenants SET name=?,email=?,contact=? WHERE id=?',[$name,$email,$contact,$u['tenant_id']]); query('UPDATE users SET name=? WHERE id=?',[$name,$u['id']]);
        audit('Tenant updated own contact details','tenants',(int)$u['tenant_id']); return true;
    case 'inquiry':
        require_role(['tenant']); $category=required('category');
        if(!in_array($category,['Billing','Payment','Room assignment','Deposit','Other'],true)) throw new RuntimeException('Invalid inquiry category.');
        query('INSERT INTO tenant_inquiries(tenant_id,category,subject,message) VALUES(?,?,?,?)',[$u['tenant_id'],$category,required('subject'),required('message')]); audit('Tenant sent inquiry','tenant_inquiries',(int)db()->lastInsertId()); return true;
    case 'reply_inquiry':
        require_role(['admin','staff']); $id=(int)required('inquiry_id'); $q=one('SELECT * FROM tenant_inquiries WHERE id=?',[$id]);
        if(!$q) throw new RuntimeException('Inquiry not found.');
        if($u['role']==='staff' && !in_array($q['category'],['Payment','Billing'],true)) throw new RuntimeException('The administrator handles this inquiry category.');
        $reply=required('reply'); query("UPDATE tenant_inquiries SET reply=?,status='resolved',replied_by=?,replied_at=UTC_TIMESTAMP() WHERE id=?",[$reply,$u['id'],$id]);
        notify((int)$q['tenant_id'],null,'inquiry','Reply to: '.$q['subject'],$reply); audit('Replied to tenant inquiry','tenant_inquiries',$id); return true;
    case 'payment_settings':
        require_role(['admin']); $name=trim($_POST['gcash_name']??''); $number=trim($_POST['gcash_number']??'');
        if(($name==='' && $number!=='')||($number!==''&&!preg_match('/^09\d{9}$/',$number))) throw new RuntimeException('Provide an account name and an 11-digit number starting with 09. Leave the number blank until confirmed.');
        $qr=save_payment_proof('gcash_qr');
        if($qr && !$name) throw new RuntimeException('Enter the account name for the payment QR.');
        if($qr || !empty($_POST['remove_qr'])) query('INSERT INTO settings(`key`,value) VALUES(?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',['gcash_qr',$qr??'']);
        foreach(['gcash_name'=>$name,'gcash_number'=>$number,'payment_instructions'=>trim($_POST['payment_instructions']??''),'office_hours'=>trim($_POST['office_hours']??'')] as $k=>$v) { if(strlen($v)>1000) throw new RuntimeException('Payment details are too long.'); query('INSERT INTO settings(`key`,value) VALUES(?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',[$k,$v]); }
        audit('Updated tenant payment instructions'); return true;
    default:return false;
    }
}
