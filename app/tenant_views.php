<?php
function portal_view(string $page,array $u): bool {
    switch($page) {
    case 'pay':pay_view($u);return true;
    case 'inbox':activity_view($u);return true;
    case 'profile':profile_view($u);return true;
    case 'support':support_view($u);return true;
    default:return false;
    }
}
function tenant_dashboard(array $u): void { tenant_home($u); }
function pay_view(array $u): void {
    $draft=$_SESSION['payment_draft']??[];
    section_start('Choose payment method','Select how you want to pay. Submit digital payment details only after completing the transfer.');
    select_field('Mode of payment','payment_choice',[''=>'Select a payment method','gcash'=>'GCash - account number','qr'=>'QR payment - scan code','office'=>'Office payment - cash'], $draft['payment_channel']??'');
    echo '<div id="office-payment-panel" data-payment-panel="office" hidden><h3>Pay at the dormitory office</h3><p>Bring your statement or provide your name and billing period. Admin or staff receives the payment and issues your receipt. No online submission is needed for cash.</p><p>'.e(setting('business_address')).'<br>'.e(setting('business_contact')).'<br>'.e(setting('office_hours')?:'Contact the office for payment hours.').'</p><a class="button" href="?page=payments">View confirmed receipts</a></div>';
    echo '<div data-payment-panel="gcash qr" hidden>';
    echo '<div class="payment-workspace"><div class="qr-scanner" data-qr-panel hidden><span class="eyebrow">SCAN PAYMENT QR</span><h3>Camera or QR image</h3><label class="camera-choice">Camera<select id="qr-camera"><option value="environment">Rear / default camera</option><option value="user">Front camera</option></select></label><div class="qr-preview-wrap"><video id="qr-video" autoplay playsinline muted hidden></video></div><canvas id="qr-canvas" hidden></canvas><div class="scanner-controls"><button type="button" id="qr-start" class="primary">Start scanner</button><button type="button" id="qr-stop" disabled>Stop camera</button></div><label class="qr-file">Choose a QR image<input type="file" id="qr-file" accept="image/png,image/jpeg,image/webp"></label><p id="qr-status" role="status">Scan the official dormitory payment QR. On a phone, use a secure HTTPS address for camera access. QR-image scanning is available without camera permission.</p><div id="qr-details" class="scan-result" hidden></div><div id="qr-raw" class="qr-payload" hidden><pre id="qr-result" hidden></pre></div><p class="muted">Scanning reads the QR details. Complete and confirm the transfer in GCash, then submit your reference below.</p></div><div class="payment-recipient"><small>OFFICIAL RECEIVING ACCOUNT</small><strong>'.e(setting('gcash_name')?:'Not configured').'</strong><span>'.e(setting('gcash_number')?:'Ask the office for the verified account number.').'</span>';
    if(setting('gcash_number')) echo '<button type="button" data-copy="'.e(setting('gcash_number')).'">Copy number</button>';
    if(setting('gcash_qr')) echo '<img id="official-payment-qr" data-qr-panel hidden src="?payment_qr=1" alt="Official GCash QR"><a class="button" data-qr-panel hidden href="?payment_qr=1" download="Angelina-GCash-QR">Save official QR</a>';
    else echo '<p class="notice">The official QR is not configured yet. Admin can upload it in Settings.</p>';
    echo '</div></div></div></section><script src="assets/vendor/jsQR.js"></script>';
    $opts=[];$meta=[]; foreach(billing_rows($u) as $b) if(!$b['carried_to'] && balance($b)>0) { $available=max(0,balance($b)-pending_amount((int)$b['id'])); if($available>0) { $opts[$b['id']]=$b['period'].' · Balance '.money(balance($b)).' · Available to submit '.money($available); $meta[$b['id']]=$available; } }
    echo '<div data-digital-submission hidden>';
    section_start('Submit completed payment','Admin or staff confirms receipt of the funds in Payments. Your receipt appears after confirmation.');
    if(!$opts) echo '<p class="empty">No amount is available to submit. Your statements may be fully paid, not yet created, or covered by pending submissions. <a href="?page=payments">Check payment status →</a></p>';
    else {
        echo '<form method="post" enctype="multipart/form-data" class="form-grid">'.csrf().'<input type="hidden" name="payment_channel" value="gcash"><input type="hidden" name="action" value="submit_payment"><input type="hidden" name="return_page" value="pay"><input type="hidden" name="submission_key" value="'.bin2hex(random_bytes(16)).'"><input type="hidden" name="MAX_FILE_SIZE" value="2097152"><input type="hidden" id="payment-limits" value="'.e(json_encode($meta)).'">';
        $draft=$_SESSION['payment_draft']??[]; $selected=(int)($draft['billing_id']??$_GET['bill_id']??array_key_first($opts)); if(!isset($opts[$selected])) $selected=array_key_first($opts);
        select_field('Your current statement','billing_id',$opts,$selected); amount_field('Amount sent (₱)','amount',$draft['amount']??number_format($meta[$selected]/100,2,'.','')); field('Payer’s full name','payer','text',$draft['payer']??$u['name'],true,'maxlength="200"'); field('Payment date','payment_date','date',$draft['payment_date']??date('Y-m-d'),true,'max="'.date('Y-m-d').'"'); field('GCash reference number','reference','text',$draft['reference']??'',true,'maxlength="150"');
        field('Payment screenshot (optional, up to 2 MB)','proof','file','',false,'accept="image/jpeg,image/png,image/webp"'); field('Notes (optional)','notes','text',$draft['notes']??'',false); submit('Submit for verification');
    } echo '</section></div>';
}
function submissions_table(array $u,string $scope='all'): void {
    $pendingOnly=$scope==='pending'; $all=submitted_payments($u); if($scope==='history') $all=array_values(array_filter($all,fn($s)=>in_array($s['status'],['rejected','cancelled'],true))); if($pendingOnly) $all=array_values(array_filter($all,fn($s)=>$s['status']==='pending')); echo '<div class="table-wrap"><table><thead><tr><th>Submission / resident</th><th>Statement / date</th><th>Amount</th><th>Reference / proof</th><th>Status</th><th>Next step</th></tr></thead><tbody>'; if(!$all) empty_row(6,$pendingOnly?'No incoming payments waiting for confirmation.':'No submitted payments yet.');
    foreach($all as $s) {
        echo '<tr><td>#'.$s['id'].'<small>'.e($s['name']).'</small></td><td>'.e($s['period']).'<small>'.e($s['payment_date']).'</small></td><td>'.money($s['amount']).'</td><td>'.e($s['reference']).($s['proof_file']?'<small><a target="_blank" rel="noopener" href="?proof='.$s['id'].'">View payment proof ↗</a></small>':'').'</td><td>'.badge($s['status']).($s['review_note']?'<small>'.e($s['review_note']).'</small>':'').'</td><td>';
        if($s['payment_id']) echo '<a href="?page=receipt&id='.$s['payment_id'].'">View verified receipt →</a>';
        elseif($s['status']==='pending' && $u['role']==='tenant') { echo '<small>Waiting for staff verification. Balance unchanged.</small>'; form_start('cancel_submission','payments','inline-form'); echo '<input type="hidden" name="submission_id" value="'.$s['id'].'"><div class="form-actions"><button data-confirm="Cancel this submission? This does not reverse a GCash transfer.">Cancel submission</button></div></form>'; }
        elseif($s['status']==='pending') { echo '<details><summary>Confirm / decline</summary><p>Payer: '.e($s['payer']).'<br>'.e($s['notes']).'</p>'; form_start('review_submission','payments'); echo '<input type="hidden" name="submission_id" value="'.$s['id'].'">'; select_field('Decision','decision',['approved'=>'Confirm received payment','rejected'=>'Decline with reason']); field('Review note (required if declined)','review_note','text','',false); echo '<label class="verification-check"><input type="checkbox" name="verified" value="1">I verified that the funds were received in the official account.</label>'; submit('Confirm decision'); echo '</details>'; }
        else echo $u['role']==='tenant'?'<a href="?page=support">Ask for help →</a>':'<small>No further action</small>';
        echo '</td></tr>';
    } echo '</tbody></table></div>';
}
function tenant_payment_history(array $u): void {
    echo '<div class="report-actions"><a class="button primary" href="?page=pay">Make a payment / Submit GCash details →</a><a class="button" href="?page=billing">View my bills</a></div>';
    section_start('Submitted payments','Pending submissions are separate from verified receipts. Check staff feedback if a submission is declined.'); submissions_table($u); echo '</section>';
    section_start('Confirmed payment receipts','Payments recorded or verified by staff. Voided receipts stay in your history and do not count as paid.'); payments_table(payment_rows($u)); echo '</section>';
}
function profile_view(array $u): void {
    tenant_room_card(tenant_current_room($u),true);
    $t=one('SELECT * FROM tenants WHERE id=?',[$u['tenant_id']]); section_start('My contact details','Keep your email and contact number current so the dormitory can reach you.'); form_start('profile_update','profile'); field('Full name','name','text',$t['name'],true,'maxlength="200"'); field('Email','email','email',$t['email'],true,'maxlength="254"'); field('Contact number','contact','tel',$t['contact'],true,'maxlength="80"'); submit('Save my details'); echo '<p class="muted">Username: '.e($u['username']).' · Account: Tenant</p><a class="button" href="?page=account">Change password →</a></section>';
    section_start('My room history'); echo '<div class="table-wrap"><table><thead><tr><th>Room</th><th>Bed</th><th>Move-in</th><th>Until</th></tr></thead><tbody>'; $all=rows('SELECT a.*,r.number,b.label FROM tenant_assignments a JOIN bed_spaces b ON b.id=a.bed_id JOIN rooms r ON r.id=b.room_id WHERE tenant_id=? ORDER BY a.id DESC',[$u['tenant_id']]); if(!$all) empty_row(4,'Your room is not assigned yet. Send a room-assignment inquiry if you need an update.'); foreach($all as $a) echo '<tr><td>Room '.e($a['number']).'</td><td>'.e($a['label']).'</td><td>'.e($a['start_date']).'</td><td>'.e($a['end_date']??'Current').'</td></tr>'; echo '</tbody></table></div></section>';
}
function support_view(array $u): void {
    if($u['role']==='admin') {
        section_start('Website inquiries','Messages submitted through the public contact form. Use the provided email address to contact the sender.');
        $messages=rows("SELECT * FROM audit_logs WHERE record_type='public_contact' ORDER BY id DESC LIMIT 100");
        if(!$messages)echo '<p class="empty">No website inquiries yet.</p>';
        foreach($messages as $entry){$m=json_decode($entry['details'],true);if(!is_array($m))continue;echo '<article class="request-card"><h3>'.e($m['name']).'</h3><a href="mailto:'.e($m['email']).'">'.e($m['email']).'</a><p>'.nl2br(e($m['message'])).'</p><small>'.e(local_time($entry['created_at'])).'</small></article>';}
        echo '</section>';
    }

    if($u['role']==='tenant') { section_start('Ask the dormitory','Use this for account and billing questions. For room repairs, open Maintenance.'); form_start('inquiry','support'); $cats=['Billing','Payment','Room assignment','Deposit','Other']; select_field('Topic','category',array_combine($cats,$cats)); field('Subject','subject'); textarea_field('Your question','message'); submit('Send inquiry'); echo '<a href="?page=requests">Need a repair? Submit a maintenance request →</a></section>'; }
    $where=$u['role']==='tenant'?'WHERE q.tenant_id=? ':($u['role']==='staff'?"WHERE q.category IN ('Payment','Billing') ":'');
    $all=rows('SELECT q.*,t.name,u.name responder FROM tenant_inquiries q JOIN tenants t ON t.id=q.tenant_id LEFT JOIN users u ON u.id=q.replied_by '.$where."ORDER BY (q.status='open') DESC,q.id DESC",$u['role']==='tenant'?[$u['tenant_id']]:[]);
    section_start($u['role']==='tenant'?'My inquiries':'Tenant inquiries'); if(!$all) echo '<p class="empty">No inquiries yet.</p>';
    foreach($all as $q) { echo '<article class="request-card"><div class="request-heading"><div><span class="eyebrow">'.e($q['category']).' · '.e($q['name']).'</span><h3>'.e($q['subject']).'</h3></div>'.badge($q['status']).'</div><p>'.nl2br(e($q['message'])).'</p><small>'.e(local_time($q['created_at'])).'</small>'; if($q['reply']) echo '<div class="response-note"><strong>'.e($q['responder']).'</strong><p>'.nl2br(e($q['reply'])).'</p></div>'; if($u['role']!=='tenant') { form_start('reply_inquiry','support'); echo '<input type="hidden" name="inquiry_id" value="'.$q['id'].'">'; textarea_field('Reply','reply',$q['reply']??''); submit('Send reply'); } echo '</article>'; } echo '</section>';
}
function payment_settings_view(): void {
    echo '<p class="notice">Upload the payment QR exported from the dormitory’s actual GCash account. Confirm its recipient in GCash before publishing it to tenants.</p>';
    section_start('Tenant payment instructions','These details appear under Make a payment. Enter the dormitory’s real receiving account.'); echo '<form method="post" enctype="multipart/form-data" class="form-grid">'.csrf().'<input type="hidden" name="action" value="payment_settings"><input type="hidden" name="return_page" value="settings">'; field('GCash account name','gcash_name','text',setting('gcash_name'),false); field('GCash number','gcash_number','tel',setting('gcash_number'),false,'pattern="09[0-9]{9}" maxlength="11"'); field('Office / payment hours','office_hours','text',setting('office_hours'),false); field('Additional payment instructions','payment_instructions','text',setting('payment_instructions'),false); field('Official GCash payment QR (JPG, PNG, WebP; max 2 MB)','gcash_qr','file','',false,'accept="image/jpeg,image/png,image/webp"'); if(setting('gcash_qr')) echo '<div class="gcash-qr"><img src="?payment_qr=1" alt="Current payment QR"><label><input type="checkbox" name="remove_qr" value="1">Remove current QR</label></div>'; submit('Save payment instructions'); echo '</section>';
}
