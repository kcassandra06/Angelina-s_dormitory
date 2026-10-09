<?php
function icon(string $name): string {
    $paths=[
        'dashboard'=>'<rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/>',
        'tenants'=>'<circle cx="9" cy="8" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M17 5a3 3 0 0 1 0 6m1 4a5 5 0 0 1 3 5"/>',
        'rooms'=>'<path d="M3 21V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v16M8 21v-5h8v5M7 7h2m6 0h2M7 11h2m6 0h2"/>',
        'billing'=>'<path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3Z M9 7h6M9 11h6M9 15h3"/>',
        'batch'=>'<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18M8 15h3m-3 3h7"/>',
        'payments'=>'<rect x="3" y="5" width="18" height="14" rx="3"/><path d="M3 10h18M7 15h3"/>',
        'deposits'=>'<path d="m12 3 8 4v6c0 5-8 8-8 8s-8-3-8-8V7l8-4Z m-4 9 3 3 5-6"/>',
        'reports'=>'<path d="M3 3v18h18M7 16v-4m5 4V7m5 9v-6"/>',
        'expenses'=>'<path d="M20 8V5a2 2 0 0 0-2-2H6a3 3 0 0 0 0 6h14v11H6a3 3 0 0 1-3-3V6m13 8h5v4h-5v-4Z"/>',
        'requests'=>'<path d="m14 5 4 4 3-3a7 7 0 0 1-9 9l-7 7-3-3 7-7a7 7 0 0 1 9-9l-4 2Z"/>',
        'announcements'=>'<path d="m3 10 14-6v16L3 14v-4Z m4 5 2 6h3l-2-5M21 8v8"/>',
        'occupancy'=>'<rect x="3" y="6" width="13" height="12" rx="2"/><path d="m16 10 5-3v10l-5-3"/>',
        'notifications'=>'<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'accounts'=>'<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'audit'=>'<path d="M8 3h12v18H4V7m4-4v4H4m4 4h8m-8 4h8m-8 3h5"/>',
        'settings'=>'<circle cx="12" cy="12" r="3"/><path d="m9 3-1 3-3 1-2 3 2 2-1 3 3 3 3-1 2 2 3-2 1-3 3-1 2-3-2-2 1-3-3-3-3 1-2-2-3 2Z"/>',
        'ledger'=>'<path d="M5 3h14v18H5V3Zm4 4h6m-6 4h6m-6 4h6m-6 3h3"/>',
        'menu'=>'<path d="M4 6h16M4 12h16M4 18h16"/>',
    ];
    return '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($paths[$name]??$paths['billing']).'</svg>';
}
function textarea_field(string $label,string $name,string $value=''): void { echo '<label class="form-wide">'.e($label).'<textarea name="'.e($name).'" rows="3" required maxlength="1000">'.e($value).'</textarea></label>'; }
function feature_view(string $page,array $u): bool {
    switch($page) {
    case 'batch':batch_view(); return true;
    case 'reports':reports_view(); return true;
    case 'expenses':expenses_view(); return true;
    case 'requests':requests_view($u); return true;
    case 'announcements':announcements_view($u); return true;
    case 'settings':settings_view(); return true;
    case 'ledger':ledger_view($u); return true;
    default:return false;
    }
}
function batch_view(): void {
    $tenants=assigned(); $all=aging_rows(); $balances=[];
    foreach($all as $b) $balances[$b['tenant_id']]=($balances[$b['tenant_id']]??0)+balance($b);
    section_start('One billing run. Every resident.','Room rent is filled automatically. Review each resident’s utility charges before generating.');
    if(!$tenants) { echo '<p class="empty">Register an active resident to start a billing run.</p></section>'; return; }
    form_start('batch_billing','batch'); field('Billing month','period','month',date('Y-m'),true,'data-billing-period'); field('Cutoff date','cutoff','date',billing_date(date('Y-m'),'cutoff_day')); field('Due date','due_date','date',billing_date(date('Y-m'),'due_day'));
    echo '<div class="form-wide table-wrap"><table class="batch-table"><thead><tr><th><input type="checkbox" id="select-all" checked aria-label="Select all residents"></th><th>Resident / room</th><th>Monthly rent</th><th>Electricity (₱)</th><th>Water (₱)</th><th>Previous balance</th><th>Estimated total</th></tr></thead><tbody>';
    foreach($tenants as $t) {
        $prev=$balances[$t['tenant_id']]??0; echo '<tr data-batch-row data-rent="'.$t['rent'].'" data-previous="'.$prev.'"><td><input type="checkbox" name="selected[]" value="'.$t['tenant_id'].'" checked aria-label="Bill '.e($t['name']).'"></td><td><strong>'.e($t['name']).'</strong><small>Room '.e($t['number']).' / '.e($t['label']).'</small></td><td>'.money($t['rent']).'</td><td><input type="number" min="0" step="0.01" name="electricity['.$t['tenant_id'].']" value="0" aria-label="Electricity for '.e($t['name']).'"></td><td><input type="number" min="0" step="0.01" name="water['.$t['tenant_id'].']" value="0" aria-label="Water for '.e($t['name']).'"></td><td>'.money($prev).'</td><td><strong data-batch-total>'.money($prev+$t['rent']).'</strong></td></tr>';
    }
    echo '</tbody></table></div><div class="bill-preview form-wide"><div><span>Estimated billing run total</span><strong id="batch-grand-total">—</strong></div><p>Existing or later statements are skipped. Previous balances are carried once. No late fees are added.</p></div>'; submit('Generate selected statements'); echo '</section>';
}
function reports_view(): void {
    [$from,$to]=report_dates(); echo '<div class="report-print"><div class="report-title"><h2>'.e(setting('business_name')).' - Financial report</h2><p>'.e($from).' through '.e($to).' - Amounts in PHP</p></div>'; $payments=rows('SELECT p.*,t.name,u.name receiver FROM payments p JOIN tenants t ON t.id=p.tenant_id JOIN users u ON u.id=p.received_by WHERE p.voided_at IS NULL AND p.payment_date BETWEEN ? AND ? ORDER BY p.payment_date DESC,p.id DESC',[$from,$to]);
    $collected=array_sum(array_column($payments,'amount')); $expenses=(int)query('SELECT COALESCE(SUM(amount),0) FROM expenses WHERE voided_at IS NULL AND expense_date BETWEEN ? AND ?',[$from,$to])->fetchColumn();
    $cash=0;$gcash=0; foreach($payments as $p) { if($p['method']==='GCash') $gcash+=$p['amount']; else $cash+=$p['amount']; }
    section_start('Choose a reporting window','Collections use payment dates. Security deposits and voided payments are excluded.');
    echo '<form method="get" class="report-filter"><input type="hidden" name="page" value="reports">'; field('From','from','date',$from); field('Through','to','date',$to); echo '<button class="primary">Apply dates</button><a class="button" href="?page=reports">This month</a></form></section>';
    echo '<div class="stats">'; foreach([['Payments collected',$collected],['GCash collections',$gcash],['Personal payments',$cash],['Operating expenses',$expenses],['Net operating cash flow',$collected-$expenses]] as $s) echo '<article class="stat"><div><span>'.e($s[0]).'</span></div><strong>'.money($s[1]).'</strong><small>'.e($from).' – '.e($to).'</small></article>'; echo '</div>';
    echo '<div class="notice">Collections include confirmed rent and utility payments. Pending submissions, security deposits, and voided records are excluded. Net operating cash flow is collections less recorded operating expenses.</div>';
    [$activeResidents,$occupiedBeds,$availableBeds,$occupancyRate,$totalBeds]=dormitory_metrics();
    echo '<div class="operation-summary"><div><span>Occupied beds</span><strong>'.$occupiedBeds.' / '.$totalBeds.'</strong></div><div><span>Available beds</span><strong>'.$availableBeds.'</strong></div><div><span>Occupancy rate</span><strong>'.$occupancyRate.'%</strong></div></div>';
    $liveOutstanding=0;$liveOverdue=0;foreach(aging_rows() as $balanceRow){$liveOutstanding+=balance($balanceRow);if(status($balanceRow)==='overdue')$liveOverdue+=balance($balanceRow);}
    echo '<div class="operation-summary"><div><span>Current outstanding balance</span><strong>'.money($liveOutstanding).'</strong></div><div><span>Current overdue balance</span><strong>'.money($liveOverdue).'</strong></div><div><span>Active residents</span><strong>'.$activeResidents.'</strong></div></div>';
    collection_charts($from,$to,$cash,$gcash);
    $expenseCategories=[];foreach(rows('SELECT category,SUM(amount) amount FROM expenses WHERE voided_at IS NULL AND expense_date BETWEEN ? AND ? GROUP BY category',[$from,$to]) as $expense)$expenseCategories[$expense['category']]=(int)$expense['amount'];report_bar_chart('Operating expenses by category',$expenseCategories?:['No expenses'=>0],'Recorded costs in the selected period, excluding voided expenses.');
    $params='&from='.urlencode($from).'&to='.urlencode($to);
    echo '<div class="report-actions no-print"><button type="button" data-print>Print report</button><a class="button" href="?export=collections'.$params.'">'.icon('reports').' Export collections CSV</a><a class="button" href="?export=expenses'.$params.'">Export expenses CSV</a><a class="button" href="?export=aging">Export outstanding balances CSV</a></div>';
    section_start('Outstanding balance aging','Current balances grouped by the due date on the current statement, including carried balances.');
    $buckets=['Not yet due'=>0,'1–30 days'=>0,'31–60 days'=>0,'61–90 days'=>0,'90+ days'=>0];$current=[];
    foreach(aging_rows() as $b) { if(balance($b)===0) continue; $days=(int)(new DateTime($b['due_date']))->diff(new DateTime('today'))->format('%r%a'); $key=$days<=0?'Not yet due':($days<=30?'1–30 days':($days<=60?'31–60 days':($days<=90?'61–90 days':'90+ days'))); $buckets[$key]+=balance($b); $b['days']=max(0,$days);$current[]=$b; }
    echo '<div class="aging-grid">'; foreach($buckets as $label=>$value) echo '<div><span>'.e($label).'</span><strong>'.money($value).'</strong></div>'; echo '</div>';
    report_bar_chart('Outstanding balances by age',$buckets,'Live receivables on current statements. Carried balances are counted once.');
    echo '<div class="table-wrap"><table><thead><tr><th>Resident</th><th>Period</th><th>Due date</th><th>Days overdue</th><th>Balance</th><th>Status</th></tr></thead><tbody>'; if(!$current) empty_row(6,'No outstanding balances.');
    foreach($current as $b) echo '<tr><td>'.e($b['name']).'<small>'.e($b['contact']).'</small></td><td><a href="?page=statement&id='.$b['id'].'">'.e($b['period']).'</a></td><td>'.e($b['due_date']).'</td><td>'.$b['days'].'</td><td>'.money(balance($b)).'</td><td>'.badge(status($b)).'</td></tr>'; echo '</tbody></table></div></section>';
    section_start('Collections register',count($payments).' payments · operating expenses '.money($expenses)); echo '<div class="table-wrap"><table><thead><tr><th>Receipt</th><th>Resident</th><th>Date</th><th>Method</th><th>Amount</th><th>Received by</th></tr></thead><tbody>'; if(!$payments) empty_row(6);
    foreach($payments as $p) echo '<tr><td><a href="?page=receipt&id='.$p['id'].'">AD-'.$p['id'].'</a></td><td>'.e($p['name']).'</td><td>'.e($p['payment_date']).'</td><td>'.e($p['method']).'</td><td>'.money($p['amount']).'</td><td>'.e($p['receiver']).'</td></tr>'; echo '</tbody></table></div></section>';
    echo '</div>';
}
function expenses_view(): void { operating_expenses(); }
function requests_view(array $u): void {
    if($u['role']==='tenant') { section_start('Room maintenance','Request a repair for your assigned room.'); if(one('SELECT id FROM tenant_assignments WHERE tenant_id=? AND end_date IS NULL',[$u['tenant_id']])) { form_start('request','requests'); field('Short title','title'); select_field('Priority','priority',['normal'=>'Normal','urgent'=>'Urgent']); textarea_field('What happened?','description'); submit('Submit request'); } else echo '<p>Your room must be assigned before you can request a repair. For account or assignment questions, <a href="?page=support">contact the office</a>.</p>'; echo '</section>'; }
    section_start($u['role']==='tenant'?'My requests':'Resident maintenance requests','Room information is saved when each request is submitted.');
    $requests=rows('SELECT m.*,t.name,r.number FROM maintenance_requests m JOIN tenants t ON t.id=m.tenant_id JOIN rooms r ON r.id=m.room_id '.($u['role']==='tenant'?'WHERE m.tenant_id=? ':'').'ORDER BY m.id DESC',$u['role']==='tenant'?[$u['tenant_id']]:[]);
    if(!$requests) echo '<p class="empty">No maintenance requests yet.</p>';
    foreach($requests as $m) { echo '<article class="request-card"><div class="request-heading"><div><span class="eyebrow">ROOM '.e($m['number']).' · '.e($m['name']).'</span><h3>'.e($m['title']).'</h3></div><div>'.badge($m['priority']).' '.badge($m['status']).'</div></div><p>'.nl2br(e($m['description'])).'</p><small>Submitted '.e($m['created_at']).' UTC</small>'; if($m['admin_note']) echo '<div class="response-note"><strong>Dormitory response</strong><p>'.nl2br(e($m['admin_note'])).'</p></div>';
        if($u['role']==='admin') { echo '<details><summary>Update request</summary>'; form_start('request_update','requests'); echo '<input type="hidden" name="request_id" value="'.$m['id'].'">'; select_field('Status','status',['open'=>'Open','in_progress'=>'In progress','resolved'=>'Resolved'],$m['status']); textarea_field('Response / work completed','admin_note',$m['admin_note']??''); submit('Update request'); echo '</details><details><summary>Request history</summary>';foreach(rows("SELECT a.*,u.name FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id WHERE a.record_type='maintenance_requests' AND a.record_id=? ORDER BY a.id DESC",[$m['id']]) as $event)echo '<div class="history-row"><div><strong>'.e($event['action']).'</strong><small>'.e($event['details']).'</small></div><span>'.e($event['name']??'System').' / '.e(local_time($event['created_at'])).'</span></div>';echo '</details>'; } echo '</article>';
    } echo '</section>';
}
function announcements_view(array $u): void {
    if($u['role']==='admin') { section_start('Post an announcement','Visible to all active accounts until its expiry or archive date.'); form_start('announcement','announcements'); field('Title','title'); field('Expires on (optional)','expires_on','date','',false); textarea_field('Message','message'); submit('Publish announcement'); echo '</section>'; }
    section_start('Dormitory notice board');
    $all=rows('SELECT a.*,u.name FROM announcements a JOIN users u ON u.id=a.created_by '.($u['role']==='admin'?'':"WHERE a.active=1 AND (a.expires_on IS NULL OR a.expires_on>=?) ").'ORDER BY a.id DESC',$u['role']==='admin'?[]:[date('Y-m-d')]); if(!$all) echo '<p class="empty">All quiet here. New announcements will appear on this board.</p>';
    foreach($all as $a) { echo '<article class="announcement-card"><div class="request-heading"><h3>'.e($a['title']).'</h3>'.badge(!$a['active']?'archived':($a['expires_on'] && $a['expires_on']<date('Y-m-d')?'expired':'active')).'</div><p>'.nl2br(e($a['message'])).'</p><small>'.e($a['name']).' · '.e($a['created_at']).' UTC'.($a['expires_on']?' · Until '.e($a['expires_on']):'').'</small>'; if($u['role']==='admin') { echo '<details><summary>Edit / publish notice</summary>';form_start('edit_announcement','announcements');echo '<input type="hidden" name="announcement_id" value="'.$a['id'].'">';field('Title','title','text',$a['title']);field('Expires on (optional)','expires_on','date',$a['expires_on']??'',false);textarea_field('Message','message',$a['message']);select_field('Publication status','active',['1'=>'Published','0'=>'Archived'],$a['active']);submit('Save notice');echo '</details>'; } if($u['role']==='admin' && $a['active']) { form_start('archive_announcement','announcements','inline-form'); echo '<input type="hidden" name="announcement_id" value="'.$a['id'].'">'; submit('Archive'); } echo '</article>'; } echo '</section>';
}
function settings_view(): void {
    payment_settings_view();
    section_start('Dormitory details','These appear on receipts and statements.'); form_start('settings','settings'); field('Business name','business_name','text',setting('business_name')); field('Contact number','business_contact','text',setting('business_contact'),false); field('Address','business_address','text',setting('business_address'),false); field('Default cutoff day','cutoff_day','number',setting('cutoff_day','15'),true,'min="1" max="31"'); field('Default due day','due_day','number',setting('due_day','28'),true,'min="1" max="31"'); echo '<p class="form-wide muted">For short months, day 29–31 is capped at month end. Defaults apply to new bills; existing bills stay unchanged.</p>'; submit('Save settings'); echo '</section>';
    section_start('Database connection'); echo '<div class="database-status"><span class="live-dot"></span><div><strong>XAMPP MySQL connected</strong><p>'.e(database_config()['database']).' · '.e(query('SELECT VERSION()')->fetchColumn()).'</p></div></div><p class="muted">Connection settings: config.database.php. Back up or export this database through phpMyAdmin.</p></section>';
}
function ledger_view(array $u): void {
    $tenant=$u['role']==='tenant'?(int)$u['tenant_id']:(int)($_GET['tenant_id']??array_key_first(tenant_options())??0);
    if($u['role']==='admin') { section_start('Choose a resident'); echo '<form method="get" class="report-filter"><input type="hidden" name="page" value="ledger">'; select_field('Resident','tenant_id',tenant_options(),$tenant); echo '<button class="primary">View ledger</button></form></section>'; }
    $t=one('SELECT * FROM tenants WHERE id=?',[$tenant]); if(!$t) { echo '<p class="empty">Select a resident with an account.</p>'; return; }
    $entries=[];
    foreach(rows('SELECT * FROM billing WHERE tenant_id=?',[$tenant]) as $b) $entries[]=['date'=>$b['created_at'],'order'=>0,'id'=>$b['id'],'text'=>'Statement '.$b['period'].' · charges after adjustments','debit'=>$b['total_due']-$b['previous_balance'],'credit'=>0,'page'=>'statement','state'=>''];
    foreach(rows('SELECT * FROM payments WHERE tenant_id=?',[$tenant]) as $p) { $entries[]=['date'=>$p['created_at'],'order'=>1,'id'=>$p['id'],'text'=>'Receipt AD-'.$p['id'].' · '.$p['method'],'debit'=>0,'credit'=>$p['amount'],'page'=>'receipt','state'=>$p['voided_at']?'Voided payment; reversal below':'']; if($p['voided_at']) $entries[]=['date'=>$p['voided_at'],'order'=>2,'id'=>$p['id'],'text'=>'Payment reversal · '.$p['void_reason'],'debit'=>$p['amount'],'credit'=>0,'page'=>'receipt','state'=>'']; }
    usort($entries,fn($a,$b)=>[$a['date'],$a['order'],$a['id']]<=>[$b['date'],$b['order'],$b['id']]);
    section_start($t['name'].' · account ledger','Net statement charges exclude carried balances to avoid counting them twice. Amounts reflect current corrections; deposits are tracked separately.'); echo '<div class="table-wrap"><table><thead><tr><th>Recorded (UTC)</th><th>Transaction</th><th>Charges / debits</th><th>Payments / credits</th><th>Running balance</th></tr></thead><tbody>'; $running=0; if(!$entries) empty_row(5);
    foreach($entries as $entry) { $running+=$entry['debit']-$entry['credit']; echo '<tr><td>'.e($entry['date']).'</td><td><a href="?page='.$entry['page'].'&id='.$entry['id'].'">'.e($entry['text']).'</a><small>'.e($entry['state']).'</small></td><td>'.($entry['debit']?money($entry['debit']):'—').'</td><td>'.($entry['credit']?money($entry['credit']):'—').'</td><td><strong>'.money($running).'</strong></td></tr>'; } echo '</tbody></table></div><div class="ledger-total"><span>Current account balance</span><strong>'.money($running).'</strong></div></section>';
}
function dashboard_extras(array $u): void {
    if(in_array($u['role'],['admin','staff'],true)) {
        $pending=one("SELECT COUNT(*) count,COALESCE(SUM(amount),0) amount FROM payment_submissions WHERE status='pending'");
        $confirmed=(int)query('SELECT COALESCE(SUM(amount),0) FROM payments WHERE voided_at IS NULL AND payment_date=?',[date('Y-m-d')])->fetchColumn();
        echo '<div class="attention-banner"><div>'.icon('payments').'<strong>Payment activity</strong><span>'.$pending['count'].' awaiting verification · '.money($pending['amount']).' pending · '.money($confirmed).' confirmed today</span></div><a href="?page=payments">Review submissions →</a><a href="?page=payments">Confirmed payments →</a></div>';
    }
    $notices=rows('SELECT * FROM announcements WHERE active=1 AND (expires_on IS NULL OR expires_on>=?) ORDER BY id DESC LIMIT 2',[date('Y-m-d')]);
    if($notices) { echo '<div class="dashboard-notices">'; foreach($notices as $a) echo '<a href="?page=announcements">'.icon('announcements').'<div><strong>'.e($a['title']).'</strong><small>'.e(mb_strimwidth($a['message'],0,100,'…')).'</small></div><span>Read notice →</span></a>'; echo '</div>'; }
    if($u['role']==='admin') {
        $overdue=array_filter(aging_rows(),fn($b)=>status($b)==='overdue');
        $open=(int)query("SELECT COUNT(*) FROM maintenance_requests WHERE status!='resolved'")->fetchColumn();
        echo '<div class="attention-banner"><div>'.icon('reports').'<strong>Your attention, in one place.</strong><span>'.count($overdue).' overdue statements · '.$open.' open maintenance requests</span></div><a href="?page=reports">Review balances →</a></div>';
    }
}
