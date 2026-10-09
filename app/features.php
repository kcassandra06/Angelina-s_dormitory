<?php
function period_input(): string {
    $period=required('period');
    if(!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/',$period)) throw new RuntimeException('Choose a valid billing month.');
    return $period;
}
function parse_amount($value): int {
    $value=trim((string)$value);
    if(!preg_match('/^\d{1,8}(\.\d{1,2})?$/',$value)) throw new RuntimeException('Use positive amounts with at most two decimal places.');
    return (int)round((float)$value*100);
}
function create_statement(int $tenant,string $period,string $cutoff,string $due,int $rent,int $electricity,int $water,int $discount=0,int $adjustment=0,string $discountReason='',string $adjustmentReason=''): int {
    $u=require_role(['admin']);
    $a=one('SELECT * FROM tenant_assignments WHERE tenant_id=? AND end_date IS NULL',[$tenant]);
    if(!$a) throw new RuntimeException('Only actively assigned tenants can receive new monthly bills.');
    if(one("SELECT id FROM payment_submissions WHERE tenant_id=? AND status='pending'",[$tenant])) throw new RuntimeException('Review this tenant’s pending payment submissions before generating the next bill.');
    if($due<$cutoff) throw new RuntimeException('Due date must be on or after cutoff.');
    if($period<substr($a['start_date'],0,7)) throw new RuntimeException('Billing period cannot be before this stay began.');
    $latest=one('SELECT period FROM billing WHERE tenant_id=? ORDER BY period DESC LIMIT 1',[$tenant]);
    if($latest && $period<=$latest['period']) throw new RuntimeException('Use a new billing period after the latest existing period.');
    $old=rows('SELECT b.*,COALESCE((SELECT SUM(amount) FROM payments WHERE billing_id=b.id AND voided_at IS NULL),0) paid FROM billing b WHERE tenant_id=? AND carried_to IS NULL',[$tenant]);
    $prev=array_sum(array_map('balance',$old)); $total=$rent+$electricity+$water+$prev-$discount+$adjustment;
    if($total<0) throw new RuntimeException('Discounts cannot exceed charges and previous balance.');
    if($discount>0 && trim($discountReason)==='') throw new RuntimeException('Please provide a discount reason.');
    if($adjustment!==0 && trim($adjustmentReason)==='') throw new RuntimeException('Please provide an adjustment reason.');
    query('INSERT INTO billing(tenant_id,assignment_id,period,cutoff,due_date,rent,electricity,water,previous_balance,total_due,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?)',[$tenant,$a['id'],$period,$cutoff,$due,$rent,$electricity,$water,$prev,$total,$u['id']]); $id=(int)db()->lastInsertId();
    foreach($old as $b) if(balance($b)>0) query('UPDATE billing SET carried_to=? WHERE id=?',[$id,$b['id']]);
    foreach([['electricity',$electricity],['water',$water]] as $charge) query('INSERT INTO utility_charges(billing_id,type,amount) VALUES(?,?,?)',[$id,...$charge]);
    if($discount>0) query("INSERT INTO billing_adjustments(billing_id,type,amount,reason,created_by) VALUES(?,'discount',?,?,?)",[$id,$discount,$discountReason,$u['id']]);
    if($adjustment!==0) query("INSERT INTO billing_adjustments(billing_id,type,amount,reason,created_by) VALUES(?,'adjustment',?,?,?)",[$id,$adjustment,$adjustmentReason,$u['id']]);
    notify($tenant,$id,'billing','Your billing statement is ready','Billing period: '.$period.'. Amount due: '.money($total).'. Due date: '.$due.'.');
    audit('Created billing statement','billing',$id,'Previous balance '.money($prev)); return $id;
}
function feature_action(string $action,array $u): bool {
    switch($action) {
    case 'batch_billing':
        require_role(['admin']); $period=period_input(); $cutoff=date_input('cutoff'); $due=date_input('due_date');
        if($due<$cutoff) throw new RuntimeException('Due date must be on or after cutoff.');
        $selected=$_POST['selected']??[]; if(!is_array($selected)||!$selected) throw new RuntimeException('Select at least one resident to bill.');
        $count=0; $skipped=0;
        foreach(array_unique(array_map('intval',$selected)) as $tenant) {
            $a=one('SELECT a.*,r.rent FROM tenant_assignments a JOIN bed_spaces b ON b.id=a.bed_id JOIN rooms r ON r.id=b.room_id WHERE a.tenant_id=? AND a.end_date IS NULL',[$tenant]);
            if(!$a) throw new RuntimeException('A selected tenant is no longer active. Reload the batch.');
            $last=one('SELECT period FROM billing WHERE tenant_id=? ORDER BY period DESC LIMIT 1',[$tenant]);
            if($last && $last['period']>=$period) { $skipped++; continue; }
            create_statement($tenant,$period,$cutoff,$due,(int)$a['rent'],parse_amount($_POST['electricity'][$tenant]??0),parse_amount($_POST['water'][$tenant]??0)); $count++;
        }
        audit('Generated monthly billing batch','billing',null,"$count created; $skipped skipped (existing/newer statements).");
        $_SESSION['action_message']="$count statements created. $skipped skipped because they already have this or a later billing period."; return true;
    case 'void_payment':
        require_role(['admin']); $id=(int)required('payment_id'); $p=one('SELECT * FROM payments WHERE id=?',[$id]);
        if(!$p || $p['voided_at']) throw new RuntimeException('Payment is missing or already voided.');
        $b=bill((int)$p['billing_id']);
        if($b['carried_to']) throw new RuntimeException('This bill has been carried forward. Apply a documented correction on the current statement instead.');
        $reason=required('reason');
        query('UPDATE payments SET voided_at=UTC_TIMESTAMP(),voided_by=?,void_reason=? WHERE id=?',[$u['id'],$reason,$id]);
        notify((int)$p['tenant_id'],(int)$p['billing_id'],'correction','Payment receipt corrected','Receipt AD-'.str_pad((string)$id,6,'0',STR_PAD_LEFT).' was voided. Reason: '.$reason.'. Updated balance: '.money(balance($b)+$p['amount']).'.');
        audit('Voided payment; original retained','payments',$id,$reason); return true;
    case 'expense':
        require_role(['admin']); $category=required('category');
        if(!in_array($category,['Utilities','Maintenance','Supplies','Salaries','Other'],true)) throw new RuntimeException('Invalid expense category.');
        $amount=cents('amount'); $date=date_input('expense_date');
        if($amount<=0 || $date>date('Y-m-d')) throw new RuntimeException('Use a positive expense and a date no later than today.');
        $key=trim($_POST['idempotency_key']??''); if($key && one('SELECT id FROM expenses WHERE idempotency_key=?',[$key])) { $_SESSION['action_message']='This expense is already recorded.'; return true; }
        $method=$_POST['payment_method']??'Cash'; if(!in_array($method,['Cash','GCash','Bank transfer'],true)) throw new RuntimeException('Choose a valid expense payment method.');
        query('INSERT INTO expenses(category,description,amount,expense_date,reference,recorded_by,vendor,payment_method,idempotency_key) VALUES(?,?,?,?,?,?,?,?,?)',[$category,required('description'),$amount,$date,trim($_POST['reference']??''),$u['id'],trim($_POST['vendor']??''),$method,$key?:null]);
        audit('Recorded operating expense','expenses',(int)db()->lastInsertId()); return true;
    case 'void_expense':
        require_role(['admin']); $id=(int)required('expense_id');$reason=required('reason');$expense=one('SELECT * FROM expenses WHERE id=? AND voided_at IS NULL',[$id]);if(!$expense)throw new RuntimeException('This expense was already voided or does not exist.');query('UPDATE expenses SET voided_at=UTC_TIMESTAMP(),voided_by=?,void_reason=? WHERE id=?',[$u['id'],$reason,$id]);audit('Voided operating expense','expenses',$id,$reason);return true;
    case 'request':
        require_role(['tenant']);
        $a=one('SELECT bs.room_id FROM tenant_assignments a JOIN bed_spaces bs ON bs.id=a.bed_id WHERE a.tenant_id=? AND a.end_date IS NULL',[$u['tenant_id']]);
        if(!$a) throw new RuntimeException('A current room assignment is needed to create a maintenance request.');
        $priority=required('priority'); if(!in_array($priority,['normal','urgent'],true)) throw new RuntimeException('Invalid priority.');
        query('INSERT INTO maintenance_requests(tenant_id,room_id,title,description,priority) VALUES(?,?,?,?,?)',[$u['tenant_id'],$a['room_id'],required('title'),required('description'),$priority]);
        audit('Submitted maintenance request','maintenance_requests',(int)db()->lastInsertId()); return true;
    case 'request_update':
        require_role(['admin']); $status=required('status'); $id=(int)required('request_id');
        if(!in_array($status,['open','in_progress','resolved'],true)||!one('SELECT id FROM maintenance_requests WHERE id=?',[$id])) throw new RuntimeException('Invalid request or status.');
        query('UPDATE maintenance_requests SET status=?,admin_note=?,updated_at=UTC_TIMESTAMP() WHERE id=?',[$status,required('admin_note'),$id]);
        audit('Updated maintenance request','maintenance_requests',$id,$status.' - '.required('admin_note')); return true;
    case 'announcement':
        require_role(['admin']); $expires=trim($_POST['expires_on']??'')!==''?date_input('expires_on'):null;
        if($expires && $expires<date('Y-m-d')) throw new RuntimeException('Choose an expiry date today or later.');
        query('INSERT INTO announcements(title,message,expires_on,created_by) VALUES(?,?,?,?)',[required('title'),required('message'),$expires,$u['id']]);
        audit('Published dormitory announcement','announcements',(int)db()->lastInsertId()); return true;
    case 'edit_announcement':
        require_role(['admin']);$id=(int)required('announcement_id');
        if(!one('SELECT id FROM announcements WHERE id=?',[$id]))throw new RuntimeException('Notice not found.');
        $expires=trim($_POST['expires_on']??'')!==''?date_input('expires_on'):null;
        $active=required('active');if(!in_array($active,['0','1'],true))throw new RuntimeException('Choose a valid publication status.');
        if($active==='1'&&$expires&&$expires<date('Y-m-d'))throw new RuntimeException('Published notices need an expiry date today or later.');
        query('UPDATE announcements SET title=?,message=?,expires_on=?,active=? WHERE id=?',[required('title'),required('message'),$expires,(int)$active,$id]);
        audit('Edited dormitory notice','announcements',$id);return true;
    case 'archive_announcement':
        require_role(['admin']); $id=(int)required('announcement_id'); query('UPDATE announcements SET active=0 WHERE id=?',[$id]); audit('Archived announcement','announcements',$id); return true;
    case 'settings':
        require_role(['admin']); $cutoff=(int)required('cutoff_day'); $due=(int)required('due_day');
        if($cutoff<1||$due<$cutoff||$due>31) throw new RuntimeException('Use day numbers 1–31, with the due day on or after cutoff.');
        foreach(['business_name'=>required('business_name'),'business_contact'=>trim($_POST['business_contact']??''),'business_address'=>trim($_POST['business_address']??''),'cutoff_day'=>(string)$cutoff,'due_day'=>(string)$due] as $k=>$v) query('INSERT INTO settings(`key`,value) VALUES(?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',[$k,$v]);
        audit('Updated dormitory settings'); return true;
    default:return portal_action($action,$u);
    }
}
function billing_date(string $period,string $key): string {
    $day=min((int)setting($key,$key==='cutoff_day'?'15':'28'),(int)date('t',strtotime($period.'-01')));
    return $period.'-'.str_pad((string)$day,2,'0',STR_PAD_LEFT);
}
function report_dates(): array {
    $from=(string)($_GET['from']??date('Y-m-01')); $to=(string)($_GET['to']??date('Y-m-t'));
    foreach([$from,$to] as $value) { $date=DateTime::createFromFormat('!Y-m-d',$value); if(!$date || $date->format('Y-m-d')!==$value) throw new RuntimeException('Invalid report date.'); }
    if($from>$to) throw new RuntimeException('Start date must be on or before end date.'); return [$from,$to];
}
function aging_rows(): array {
    return rows('SELECT b.*,t.name,t.contact,t.email,COALESCE((SELECT SUM(amount) FROM payments WHERE billing_id=b.id AND voided_at IS NULL),0) paid FROM billing b JOIN tenants t ON t.id=b.tenant_id WHERE b.carried_to IS NULL ORDER BY b.due_date,t.name');
}
function export_report(array $u): never {
    require_role(['admin']);
    if($u['must_change']) redirect('account');
    try { [$from,$to]=report_dates(); } catch(RuntimeException $e) { http_response_code(422); exit(e($e->getMessage())); }
    $type=$_GET['export']??''; $data=[];
    if($type==='collections') {
        $head=['Receipt','Tenant','Payer','Date','Method','Reference','Amount (PHP)','Received by'];
        foreach(rows('SELECT p.*,t.name,u.name receiver FROM payments p JOIN tenants t ON t.id=p.tenant_id JOIN users u ON u.id=p.received_by WHERE p.voided_at IS NULL AND p.payment_date BETWEEN ? AND ? ORDER BY p.payment_date,p.id',[$from,$to]) as $p) $data[]=['AD-'.$p['id'],$p['name'],$p['payer'],$p['payment_date'],$p['method'],$p['reference'],number_format($p['amount']/100,2,'.',''),$p['receiver']];
    } elseif($type==='aging') {
        $head=['Bill','Tenant','Contact','Period','Due date','Balance (PHP)','Days past due','Status'];
        foreach(aging_rows() as $b) if(balance($b)>0) $data[]=[$b['id'],$b['name'],$b['contact'],$b['period'],$b['due_date'],number_format(balance($b)/100,2,'.',''),max(0,(int)(new DateTime($b['due_date']))->diff(new DateTime('today'))->format('%r%a')),status($b)];
    } elseif($type==='expenses') {
        $head=['Expense','Date','Category','Description','Reference','Amount (PHP)','Payee','Payment method'];
        foreach(rows('SELECT * FROM expenses WHERE voided_at IS NULL AND expense_date BETWEEN ? AND ? ORDER BY expense_date,id',[$from,$to]) as $x) $data[]=[$x['id'],$x['expense_date'],$x['category'],$x['description'],$x['reference'],number_format($x['amount']/100,2,'.',''),$x['vendor'],$x['payment_method']];
    } else { http_response_code(400); exit('Invalid export.'); }
    audit('Exported financial report','reports',null,$type.' '.$from.' to '.$to);
    header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="dormitory-'.$type.'-'.date('Ymd').'.csv"');
    $out=fopen('php://output','w'); fwrite($out,"\xEF\xBB\xBF"); fputcsv($out,$head);
    foreach($data as $row) { $row=array_map(function($v) { $v=(string)($v??''); return preg_match('/^[=+@\-\t\r]/',$v)?"'".$v:$v; },$row); fputcsv($out,$row); }
    fclose($out); exit;
}
