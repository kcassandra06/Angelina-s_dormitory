<?php
function show_flash(): void { if(isset($_SESSION['flash'])) { [$m,$type]=$_SESSION['flash']; unset($_SESSION['flash']); echo '<div role="alert" class="notice '.e($type).'">'.e($m).'</div>'; } }
function page_subtitle(string $p,string $role): string { return match($p) { 'dashboard'=>$role==='tenant'?'Your room, billing, and dormitory account at a glance.':'A clear picture of your dormitory, all in one place.', 'tenants'=>'Manage residents and preserve every stay’s history.', 'rooms'=>'Every room. Every bed. Everything accounted for.', 'billing'=>'Monthly charges, adjustments, and outstanding balances.', 'payments'=>'Receive payments and keep a reliable transaction history.', 'deposits'=>'Keep security deposits separate from monthly rent.', 'occupancy'=>'Compare people detected with registered room occupancy.', 'notifications'=>'Review queued messages and email delivery status.', 'audit'=>'A record of important changes and who made them.', 'accounts'=>'Individual credentials and access for each user.', 'account'=>'Manage your password and account security.', 'batch'=>'Make monthly billing a little more effortless.', 'reports'=>'Track collections, expenses, and balances that need attention.', 'expenses'=>'An organized record of what it takes to run your home.', 'requests'=>'Small repairs. Clear updates. A more comfortable home.', 'announcements'=>'Keep your dormitory community in the loop.', 'settings'=>'Your dormitory details and billing preferences.', 'ledger'=>'Every charge and payment, in one clear account history.', 'pay'=>'Payment instructions, official GCash QR, and submission details.', 'verification'=>'Verify tenant payments and issue their receipts.', 'inbox'=>'Your billing notices, confirmations, and replies.', 'profile'=>'Your contact details and room history.', 'support'=>'Ask questions and follow replies from the dormitory.', 'statement'=>'An itemized account statement with charges, confirmed payments, and the current balance.', 'receipt'=>'A printable acknowledgement of a confirmed payment.', default=>'Your dormitory account records.' }; }
function form_start(string $action,string $page,string $class='form-grid'): void { echo '<form method="post" class="'.e($class).'">'.csrf().'<input type="hidden" name="action" value="'.e($action).'"><input type="hidden" name="return_page" value="'.e($page).'">'; }
function field(string $label,string $name,string $type='text',$value='',bool $required=true,string $extra=''): void { echo '<label>'.e($label).'<input type="'.e($type).'" name="'.e($name).'" value="'.e($value).'" '.($required?'required ':'').$extra.'></label>'; }
function amount_field(string $label,string $name,$value='0',bool $signed=false): void { field($label,$name,'number',$value,true,'step="0.01" '.($signed?'':'min="0"')); }
function select_field(string $label,string $name,array $options,$selected=''): void { echo '<label>'.e($label).'<select name="'.e($name).'" required>'; foreach($options as $v=>$text) echo '<option value="'.e($v).'" '.((string)$selected===(string)$v?'selected':'').'>'.e($text).'</option>'; echo '</select></label>'; }
function submit(string $label='Save record'): void {
    if($label==='Create statement') {
        $balances=[]; foreach(aging_rows() as $b) $balances[$b['tenant_id']]=($balances[$b['tenant_id']]??0)+balance($b);
        $latest=[]; foreach(rows('SELECT tenant_id,MAX(period) period FROM billing GROUP BY tenant_id') as $b) $latest[$b['tenant_id']]=$b['period'];
        echo '<input type="hidden" id="tenant-latest-periods" value="'.e(json_encode($latest)).'">';
        echo '<input type="hidden" id="tenant-balances" value="'.e(json_encode($balances)).'"><input type="hidden" id="billing-defaults" value="'.e(json_encode(['cutoff'=>(int)setting('cutoff_day','15'),'due'=>(int)setting('due_day','28')])).'"><div class="bill-preview form-wide" id="statement-preview"><div><span>Estimated statement total</span><strong id="statement-total">—</strong></div><div class="preview-breakdown"><span id="preview-charges"></span><span id="preview-previous"></span><span id="preview-discount"></span></div><p>Calculated again on the server when saved. No late fees are added.</p></div>';
    }
    echo '<div class="form-actions"><button class="primary">'.e($label).' →</button></div></form>';
}
function section_start(string $title,string $hint=''): void { echo '<section class="panel"><div class="panel-heading"><div><h2>'.e($title).'</h2>'.($hint?'<p class="muted">'.e($hint).'</p>':'').'</div></div>'; }
function empty_row(int $cols,string $text='No records yet.'): void { echo '<tr><td colspan="'.$cols.'" class="empty">'.e($text).'</td></tr>'; }
function tenant_options(): array { $opts=[]; foreach(rows('SELECT id,name,status FROM tenants ORDER BY name') as $t) $opts[$t['id']]=$t['name'].($t['status']==='moved_out'?' (moved out)':''); return $opts; }
function bed_options(): array { $o=[]; foreach(available_beds() as $b) $o[$b['id']]='Room '.$b['number'].' / '.$b['label']; return $o; }
function billing_rows(array $u): array { return rows('SELECT b.*,t.name,r.number,bs.label,COALESCE((SELECT SUM(amount) FROM payments WHERE billing_id=b.id AND voided_at IS NULL),0) paid FROM billing b JOIN tenants t ON t.id=b.tenant_id JOIN tenant_assignments a ON a.id=b.assignment_id JOIN bed_spaces bs ON bs.id=a.bed_id JOIN rooms r ON r.id=bs.room_id '.($u['role']==='tenant'?'WHERE b.tenant_id=? ':'').'ORDER BY b.period DESC,b.id DESC',$u['role']==='tenant'?[$u['tenant_id']]:[]); }
function payment_rows(array $u): array { return rows('SELECT p.*,t.name,b.period,r.number,bs.label,u.name receiver FROM payments p JOIN tenants t ON t.id=p.tenant_id JOIN billing b ON b.id=p.billing_id JOIN tenant_assignments a ON a.id=b.assignment_id JOIN bed_spaces bs ON bs.id=a.bed_id JOIN rooms r ON r.id=bs.room_id JOIN users u ON u.id=p.received_by '.($u['role']==='tenant'?'WHERE p.tenant_id=? ':'').'ORDER BY p.id DESC',$u['role']==='tenant'?[$u['tenant_id']]:[]); }
function render_page(string $page,array $u): void {
    if(portal_view($page,$u)) return;
    if(feature_view($page,$u)) return;
    if($page==='forbidden') { echo '<div class="notice error">This page is unavailable for your role.</div>'; return; }
    if($page==='dashboard') { dashboard($u); return; }
    if($page==='tenants') { tenants_view(); return; }
    if($page==='rooms') { rooms_view(); return; }
    if($page==='billing') { billing_view($u); return; }
    if($page==='payments') { payments_view($u); return; }
    if($page==='deposits') { deposits_view($u); return; }
    if($page==='occupancy') { occupancy_view(); return; }
    if($page==='notifications') { notifications_view(); return; }
    if($page==='audit') {
        section_start('Activity log','The latest 250 events. Passwords are never logged.'); echo '<div class="table-wrap"><table><thead><tr><th>Time</th><th>User</th><th>Action</th><th>Record</th><th>Details</th></tr></thead><tbody>';
        foreach(rows('SELECT a.*,u.name FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.id DESC LIMIT 250') as $a) echo '<tr><td>'.e($a['created_at']).' UTC</td><td>'.e($a['name']??'System').'</td><td>'.e($a['action']).'</td><td>'.e($a['record_type']).' '.e($a['record_id']).'</td><td>'.e($a['details']).'</td></tr>';
        echo '</tbody></table></div></section>'; return;
    }
    if($page==='accounts') { accounts_view($u); return; }
    if($page==='account') {
        if($u['must_change']) echo '<div class="notice">Set a new personal password to activate your workspace. Temporary passwords must be changed on first sign-in.</div>';
        section_start('Account security',$u['username'].' · '.ucfirst($u['role'])); form_start('password','account'); field('Current password','current_password','password','',true,'autocomplete="current-password"'); field('New password (at least 10 characters)','new_password','password','',true,'minlength="10" autocomplete="new-password"'); field('Confirm new password','confirm_password','password','',true,'minlength="10" autocomplete="new-password"'); submit('Update password'); echo '</section>'; return;
    }
    if($page==='receipt') { receipt_view($u); return; }
    if($page==='statement') { statement_view($u); return; }
}
function dashboard(array $u): void {
    if($u['role']==='tenant') { tenant_dashboard($u); return; }
    if($u['role']==='admin') { admin_dashboard($u); return; }
    $bills=billing_rows($u); $due=0;$overdue=0;$paid=0;
    foreach($bills as $b) { if(!$b['carried_to']) { $due+=balance($b); if(status($b)==='overdue') $overdue++; } $paid+=$b['paid']; }
    $payments=payment_rows($u); $monthPaid=0; foreach($payments as $p) if(!$p['voided_at'] && substr($p['payment_date'],0,7)===date('Y-m')) $monthPaid+=$p['amount'];
    echo '<section class="welcome-banner"><div><span class="eyebrow">'.e(date('l, F j')).'</span><h2>Welcome back, '.e(explode(' ',$u['name'])[0]).'.</h2><p>'.($u['role']==='tenant'?'Your home, your account, all in one place.':'Here’s how your dormitory is doing today.').'</p></div><div class="welcome-art">'.icon('rooms').'<span>A home, well managed.</span></div></section>';
    dashboard_extras($u);
    if($u['role']==='tenant') {
        $a=one('SELECT r.number,bs.label FROM tenant_assignments a JOIN bed_spaces bs ON bs.id=a.bed_id JOIN rooms r ON r.id=bs.room_id WHERE a.tenant_id=? AND a.end_date IS NULL',[$u['tenant_id']]);
        $deposit=(int)query('SELECT COALESCE(SUM(amount),0) FROM deposits WHERE tenant_id=? AND refunded_date IS NULL',[$u['tenant_id']])->fetchColumn();
        $tenant=one('SELECT status FROM tenants WHERE id=?',[$u['tenant_id']]);
        $stats=[['Outstanding balance',money($due),'Across current statements','↗'],['Payments this month',money($monthPaid),'Thank you for your payments','↙'],['My bed space',$a?'Room '.$a['number']:($tenant['status']==='moved_out'?'Moved out':'Not assigned yet'),$a?$a['label']:($tenant['status']==='moved_out'?'History remains available':'Your account is ready; contact the dormitory'),'▦'],['Deposit held',money($deposit),'Separate from monthly rent','◇']];
    } else {
        $occupied=(int)query('SELECT COUNT(*) FROM tenant_assignments WHERE end_date IS NULL')->fetchColumn(); $capacity=(int)query("SELECT COALESCE(SUM(capacity),0) FROM rooms WHERE status='active'")->fetchColumn();
        $stats=[['Collected this month',money($monthPaid),'Payments received in '.date('F'),'↙'],['Outstanding balance',money($due),'Across current statements','↗'],['Active residents',(string)$occupied,$occupied.' of '.$capacity.' bed spaces occupied','♙'],['Overdue statements',(string)$overdue,'Balances needing a follow-up','◷']];
    }
    echo '<div class="stats">'; foreach($stats as $i=>$s) echo '<article class="stat"><div><span>'.e($s[0]).'</span><i class="stat-icon">'.e($s[3]).'</i></div><strong>'.e($s[1]).'</strong><small>'.e($s[2]).'</small></article>'; echo '</div>';
    echo '<div class="dashboard-grid"><section class="panel"><div class="panel-heading"><div><h2>'.($u['role']==='tenant'?'My billing':'Collection overview').'</h2><p class="muted">'.($u['role']==='tenant'?'Your latest statements':'Payments received over the last six months').'</p></div><span class="chip">'.date('Y').'</span></div>';
    if($u['role']!=='tenant') {
        $series=[]; for($i=5;$i>=0;$i--) { $d=(new DateTime('first day of this month'))->modify('-'.$i.' months'); $m=$d->format('Y-m'); $v=0; foreach($payments as $p) if(!$p['voided_at'] && substr($p['payment_date'],0,7)===$m) $v+=$p['amount']; $series[]=[$d->format('M'),$v]; }
        $max=max(1,...array_column($series,1)); echo '<div class="chart">'; foreach($series as [$label,$v]) echo '<div class="chart-column"><span>'.e(money($v)).'</span><div class="bar-track"><div class="bar" style="height:'.max(2,round($v/$max*100)).'%"></div></div><small>'.e($label).'</small></div>'; echo '</div>';
    } else { echo '<div class="table-wrap"><table><thead><tr><th>Period</th><th>Balance</th><th>Status</th></tr></thead><tbody>'; if(!$bills) empty_row(3); foreach(array_slice($bills,0,5) as $b) echo '<tr><td><a href="?page=statement&id='.$b['id'].'">'.e($b['period']).'</a></td><td>'.money(balance($b)).'</td><td>'.badge(status($b)).'</td></tr>'; echo '</tbody></table></div>'; }
    echo '</section><section class="panel room-summary"><div class="panel-heading"><div><h2>'.($u['role']==='tenant'?'Account notes':'Room availability').'</h2><p class="muted">'.($u['role']==='tenant'?'Good to know':'A quick look at your bed spaces').'</p></div></div>';
    if($u['role']==='tenant') echo '<div class="account-notes"><p>Payments can be made by GCash or personal payment and are recorded by dormitory staff.</p><p>Each payment has a printable receipt. Partial payments remain visible in your statement.</p><a class="text-link" href="?page=payments">View my receipts →</a></div>';
    else foreach(rows('SELECT r.*, (SELECT COUNT(*) FROM tenant_assignments a JOIN bed_spaces b ON b.id=a.bed_id WHERE b.room_id=r.id AND a.end_date IS NULL) occupied FROM rooms r ORDER BY number LIMIT 5') as $r) echo '<div class="room-progress"><div><strong>Room '.e($r['number']).'</strong><small>'.$r['occupied'].' / '.$r['capacity'].' occupied</small></div><div class="progress"><span style="width:'.($r['occupied']/$r['capacity']*100).'%"></span></div></div>';
    echo '</section></div>';
    section_start('Recent payments','Every payment has a receipt and a recorded receiver.'); payments_table(array_slice($payments,0,5)); echo '</section>';
    echo '<div class="quick-links"><a href="?page=billing"><span>▤</span><div><strong>Review billing</strong><small>Statements and outstanding balances</small></div>→</a><a href="?page=payments"><span>↗</span><div><strong>'.($u['role']==='tenant'?'Payment history':'Receive a payment').'</strong><small>GCash and personal payments</small></div>→</a></div>';
}
function tenants_view(): void { resident_directory(); }
function tenant_login_fields(): void {
    field('Tenant username','username','text','',true,'minlength="3" maxlength="50" pattern="[A-Za-z0-9][A-Za-z0-9._\-]{2,49}" autocomplete="off"');
    field('Temporary password','temporary_password','password','',true,'minlength="10" maxlength="72" autocomplete="new-password"');
    field('Confirm temporary password','confirm_password','password','',true,'minlength="10" maxlength="72" autocomplete="new-password"');
    echo '<p class="form-wide muted">Use an individual username and a password of at least 10 characters. The tenant signs in with the Tenant role and changes this password on first login.</p>';
}
function tenant_registration_form(string $page): void {
    echo '<div id="new-tenant-account"></div>';
    section_start('Create new tenant account','Register a resident, assign a bed, and create their personal login in one step.');
    $beds=bed_options();
    if(!$beds) { echo '<div class="notice">No available beds. Add a room or record a move-out before registering a new resident.</div></section>'; return; }
    form_start('tenant',$page);
    echo '<div class="form-wide"><h3>1. Resident details</h3></div>';
    field('Full name','name','text','',true,'maxlength="200"'); field('Email','email','email','',true,'maxlength="254"'); field('Contact number','contact','tel','',true,'maxlength="80"');
    field('Move-in date','move_in','date',date('Y-m-d'),true,'max="'.date('Y-m-d').'"'); select_field('Available room / bed','bed_id',$beds);
    echo '<div class="form-wide"><h3>2. Personal login</h3></div>'; tenant_login_fields(); submit('Create tenant & account'); echo '</section>';
}
function rooms_view(): void { room_inventory(); }
function billing_view(array $u): void {
    $bills=billing_rows($u); section_start('All statements','Carried balances are payable on the newest statement only.'); echo '<input class="table-search" placeholder="Search statements…" aria-label="Search statements" data-search="bill-table"><div class="table-wrap"><table id="bill-table"><thead><tr><th>Statement / tenant</th><th>Period</th><th>Due date</th><th>Total due</th><th>Paid</th><th>Balance</th><th>Status</th></tr></thead><tbody>';
    if(!$bills) empty_row(7); foreach($bills as $b) echo '<tr data-status="'.status($b).'"><td><a href="?page=statement&id='.$b['id'].'">AD-B'.str_pad((string)$b['id'],5,'0',STR_PAD_LEFT).'</a><small>'.e($b['name']).'</small></td><td>'.e($b['period']).'</td><td>'.e($b['due_date']).'</td><td>'.money($b['total_due']).'</td><td>'.money($b['paid']).'</td><td>'.money(balance($b)).($b['carried_to']?'<small>Moved to bill #'.$b['carried_to'].'</small>':'').'</td><td>'.badge(status($b)).'</td></tr>'; echo '</tbody></table></div></section>';
    if($u['role']!=='admin') return;
    section_start('Create a monthly statement','Outstanding balances from older statements are carried forward automatically.'); $opts=[]; $rents=[]; foreach(assigned() as $a) { $opts[$a['tenant_id']]=$a['name'].' · Room '.$a['number'].' / '.$a['label'].' · Rent '.money($a['rent']); $rents[$a['tenant_id']]=$a['rent']/100; }
    if(!$opts) echo '<p class="muted">Register an active tenant first.</p>'; else { form_start('bill','billing'); echo '<input type="hidden" id="tenant-rents" value="'.e(json_encode($rents)).'">'; select_field('Active tenant','tenant_id',$opts); field('Billing period','period','month',date('Y-m')); field('Cutoff date','cutoff','date',date('Y-m-15')); field('Due date','due_date','date',date('Y-m-t')); amount_field('Rent','rent',reset($rents)); amount_field('Electricity','electricity'); amount_field('Water','water'); amount_field('Discount','discount'); field('Discount reason','discount_reason','text','',false); amount_field('Adjustment (+ or −)','adjustment','0',true); field('Adjustment reason','adjustment_reason','text','',false); echo '<p class="muted form-wide">Amounts are in Philippine pesos. Check the selected room’s rent before saving.</p>'; submit('Create statement'); } echo '</section>';
}
function payments_table(array $payments): void {
    echo '<div class="table-wrap"><table><thead><tr><th>Tenant / payer</th><th>Room / period</th><th>Date</th><th>Method</th><th>Amount</th><th>Receipt</th></tr></thead><tbody>'; if(!$payments) empty_row(6,'No payments recorded yet.');
    foreach($payments as $p) echo '<tr class="'.($p['voided_at']?'voided-row':'').'"><td><strong>'.e($p['name']).'</strong><small>Payer: '.e($p['payer']).'</small></td><td>Room '.e($p['number']).' / '.e($p['label']).'<small>'.e($p['period']).'</small></td><td>'.e($p['payment_date']).'</td><td><span class="method">'.e($p['method']).'</span>'.($p['reference']?'<small>Reference: '.e($p['reference']).'</small>':'').'</td><td><strong>'.money($p['amount']).'</strong>'.($p['voided_at']?'<small>Voided</small>':'').'</td><td><a href="?page=receipt&id='.$p['id'].'">AD-'.str_pad((string)$p['id'],6,'0',STR_PAD_LEFT).' ↗</a></td></tr>'; echo '</tbody></table></div>';
}
function payments_view(array $u): void {
    if($u['role']==='tenant') { tenant_payment_history($u); return; }
    section_start('Incoming tenant payments','Confirm funds in the receiving account before accepting a submission. Confirmation updates the bill and generates the tenant receipt.');submissions_table($u,'pending');echo '</section>';
    echo '<details class="panel payment-office-entry" id="record-payment"><summary>Record an office payment / already verified transfer</summary>';
    if($u['role']!=='tenant') {
        section_start('Receive a payment','Multiple payments are supported. Overpayments and duplicate references are rejected.'); $opts=[]; foreach(billing_rows($u) as $b) if(!$b['carried_to']&&balance($b)>0) $opts[$b['id']]=$b['name'].' · Room '.$b['number'].' / '.$b['label'].' · '.$b['period'].' · Balance '.money(balance($b));
        if(!$opts) echo '<p class="muted">There are no outstanding statements.</p>'; else { form_start('payment','payments'); echo '<input type="hidden" name="idempotency_key" value="'.bin2hex(random_bytes(16)).'">'; select_field('Outstanding statement','billing_id',$opts); field('Payer’s full name','payer'); amount_field('Amount paid','amount'); select_field('Payment method','method',['Personal Payment'=>'Cash / office payment','GCash'=>'GCash - already verified'],'Personal Payment'); field('Payment date','payment_date','date',date('Y-m-d')); field('Reference (required for GCash)','reference','text','',false); submit('Record payment & receipt'); } echo '</section>';
    } echo '</details>'; if((int)query("SELECT COUNT(*) FROM payment_submissions WHERE status IN ('rejected','cancelled')")->fetchColumn()) { echo '<details class="panel"><summary>Declined / cancelled submission history</summary>'; submissions_table($u,'history'); echo '</details>'; } section_start('Confirmed payment history','Receipts show the tenant, original room assignment, payer, and receiver.'); payments_table(payment_rows($u)); echo '</section>';
}
function deposits_view(array $u): void {
    section_start('Deposit register','Refunds are recorded after move-out; deductions require a reason.'); echo '<div class="table-wrap"><table><thead><tr><th>Tenant</th><th>Received</th><th>Deposit</th><th>Refund</th><th>Status / action</th></tr></thead><tbody>';
    $ds=rows('SELECT d.*,t.name,t.status FROM deposits d JOIN tenants t ON t.id=d.tenant_id '.($u['role']==='tenant'?'WHERE d.tenant_id=? ':'').'ORDER BY d.id DESC',$u['role']==='tenant'?[$u['tenant_id']]:[]);
    if(!$ds) empty_row(5); foreach($ds as $d) { echo '<tr><td>'.e($d['name']).'</td><td>'.e($d['received_date']).'</td><td>'.money($d['amount']).'</td><td>'.($d['refunded_date']?money($d['refund_amount']).'<small>'.e($d['refunded_date']).' · '.e($d['deduction_reason']).'</small>':'—').'</td><td>'.badge($d['refunded_date']?'settled':'held'); if($u['role']==='admin'&&!$d['refunded_date']&&$d['status']==='moved_out') { echo '<details><summary>Settle deposit</summary>'; form_start('refund','deposits'); echo '<input type="hidden" name="deposit_id" value="'.$d['id'].'">'; amount_field('Refund amount','refund_amount',$d['amount']/100); field('Refund date','refunded_date','date',date('Y-m-d')); field('Settlement / deduction reason','deduction_reason'); submit('Record settlement'); echo '</details>'; } echo '</td></tr>'; } echo '</tbody></table></div></section>';
    if($u['role']==='admin') { section_start('Record a security deposit'); if(tenant_options()) { form_start('deposit','deposits'); select_field('Tenant','tenant_id',tenant_options()); amount_field('Amount received','amount',5000); field('Received date','received_date','date',date('Y-m-d')); submit('Record deposit'); } else echo '<p>Register a tenant first.</p>'; echo '</section>'; }
}
function occupancy_view(): void {
    section_start('Camera room check','Counts are estimates for verification; a difference does not prove unauthorized occupancy.'); echo '<div class="camera-layout"><div class="camera-frame"><video id="camera" autoplay muted playsinline></video><canvas id="camera-overlay"></canvas><div id="camera-placeholder">◎<strong>Room occupancy camera</strong><span>Open the camera to start a person count.</span></div></div><div class="camera-controls"><p class="muted">No facial recognition. Video stays in your browser; only counts are saved. Internet is needed to load the detection model. Use localhost or HTTPS.</p><button type="button" class="primary" id="start-camera">Open camera</button><button type="button" id="stop-camera">Stop camera</button><p id="camera-status" role="status">Camera is off.</p><div class="camera-count"><strong id="live-count">—</strong><span>people detected</span></div></div></div>';
    $opts=[]; foreach(rows('SELECT * FROM rooms ORDER BY number') as $r) $opts[$r['id']]='Room '.$r['number'].' · Capacity '.$r['capacity'];
    form_start('scan','occupancy'); select_field('Room','room_id',$opts); field('Detected count','detected_count','number',0,true,'min="0" max="100" id="detected-count"'); select_field('Count source','source',['manual'=>'Manual verification','camera'=>'Camera detection']); field('Notes / verification','notes','text','',false); submit('Save occupancy check'); echo '</section>';
    section_start('Scan history'); echo '<div class="table-wrap"><table><thead><tr><th>Scanned</th><th>Room</th><th>Detected</th><th>Registered</th><th>Capacity</th><th>Source</th><th>Notes</th></tr></thead><tbody>'; $scans=rows('SELECT s.*,r.number FROM occupancy_scans s JOIN rooms r ON r.id=s.room_id ORDER BY s.id DESC LIMIT 100'); if(!$scans) empty_row(7); foreach($scans as $s) echo '<tr><td>'.e($s['created_at']).' UTC</td><td>'.e($s['number']).'</td><td>'.$s['detected_count'].($s['detected_count']!==$s['registered_count']?'<small>Verify difference</small>':'').'</td><td>'.$s['registered_count'].'</td><td>'.$s['capacity'].'</td><td>'.e($s['source']).'</td><td>'.e($s['notes']).'</td></tr>'; echo '</tbody></table></div></section>';
}
function notifications_view(): void {
    echo '<div class="notice">Billing and receipt messages queue automatically. Configure SMTP in config.local.php and enable PHP OpenSSL to deliver them.</div><div class="action-row">'; form_start('reminders','notifications','inline-form'); submit('Prepare due-date reminders'); form_start('send_mail','notifications','inline-form'); submit('Send queued / retry failed (up to 20)'); echo '</div>';
    section_start('Email outbox','Sent means the mail server accepted the message, not a confirmed inbox delivery.'); echo '<div class="table-wrap"><table><thead><tr><th>Created</th><th>Recipient</th><th>Message</th><th>Type</th><th>Delivery</th></tr></thead><tbody>'; $ns=rows('SELECT * FROM notifications ORDER BY id DESC LIMIT 200'); if(!$ns) empty_row(5); foreach($ns as $n) echo '<tr><td>'.e($n['created_at']).' UTC</td><td>'.e($n['email']).'</td><td><strong>'.e($n['subject']).'</strong><small>'.e($n['message']).'</small></td><td>'.e($n['type']).'</td><td>'.badge($n['status']).'<small>'.e($n['error']??$n['sent_at']).'</small></td></tr>'; echo '</tbody></table></div></section>';
}
function accounts_view(array $u): void {
    section_start('Individual accounts'); echo '<div class="table-wrap"><table><thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Status</th><th>Manage</th></tr></thead><tbody>'; foreach(rows('SELECT * FROM users ORDER BY id') as $a) { echo '<tr><td>'.e($a['name']).'</td><td>'.e($a['username']).'</td><td>'.e($a['role']).'</td><td>'.badge($a['active']?'active':'disabled').($a['must_change']?'<small>Password change required</small>':'').'</td><td>'; if($a['id']!==$u['id']) { form_start('toggle_user','accounts','inline-form'); echo '<input type="hidden" name="user_id" value="'.$a['id'].'">'; submit($a['active']?'Disable access':'Enable access'); echo '<details><summary>Reset password</summary>'; form_start('reset_password','accounts'); echo '<input type="hidden" name="user_id" value="'.$a['id'].'">'; field('Temporary password','temporary_password','password','',true,'minlength="10"'); submit('Reset password'); echo '</details>'; } echo '</td></tr>'; } echo '</tbody></table></div></section>';
    section_start('Create staff account','Staff accounts can receive and record payments.'); form_start('staff','accounts'); field('Name','name'); field('Username','username'); field('Temporary password','temporary_password','password','',true,'minlength="10"'); submit('Create staff account'); echo '</section>';
}
function statement_view(array $u): void { professional_statement($u); }
function receipt_view(array $u): void { professional_receipt($u); }

function existing_resident_login(): void {
    echo '<div id="existing-tenant-account"></div>'; section_start('Create login for an existing tenant','Link an account to a resident already in the system without creating another tenant record.');
    $eligible=[]; foreach(rows("SELECT t.id,t.name FROM tenants t LEFT JOIN users u ON u.tenant_id=t.id WHERE t.status='active' AND u.id IS NULL ORDER BY t.name") as $t) $eligible[$t['id']]=$t['name'];
    if($eligible) { form_start('tenant_account','tenants'); select_field('Tenant without an account','tenant_id',$eligible); tenant_login_fields(); submit('Create tenant login'); }
    else echo '<p class="muted">All active tenants already have accounts. You can reset passwords or enable disabled accounts below.</p>'; echo '</section>';
}
