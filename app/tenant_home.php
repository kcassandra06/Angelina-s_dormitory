<?php
function tenant_current_room(array $u): ?array {
    return one('SELECT r.number,r.rent,r.capacity,r.status,bs.label,a.start_date FROM tenant_assignments a JOIN bed_spaces bs ON bs.id=a.bed_id JOIN rooms r ON r.id=bs.room_id WHERE a.tenant_id=? AND a.end_date IS NULL',[$u['tenant_id']]);
}
function tenant_room_type(array $room): string { return (int)$room['capacity']===1?'Single (1 bed)':'Shared ('.$room['capacity'].' beds)'; }
function tenant_room_card(?array $room,bool $full=false): void {
    echo '<section class="panel home-room-card"'.($full?' id="room-details"':'').'><span class="eyebrow">MY ROOM</span><h2>'.($room?'Room '.e($room['number']):'Room not assigned').'</h2>';
    if($room){echo '<dl class="room-facts">';foreach(['Room'=>$room['number'],'Bed'=>$room['label'],'Room Type'=>tenant_room_type($room),'Monthly Rent'=>money($room['rent']),'Move-in date'=>date('F j, Y',strtotime($room['start_date'])),'Status'=>ucfirst($room['status'])] as $label=>$value)echo '<div><dt>'.e($label).'</dt><dd>'.e($value).'</dd></div>';echo '</dl>';if($full)echo '<p class="muted">Room type reflects the number of configured bed spaces. Monthly statements retain the rent and room recorded for that billing period.</p>';else echo '<a class="button" href="?page=profile#room-details">View Room Details &rarr;</a>';}
    else echo '<p>'.($full?'Your current room information will appear after assignment.':'Admin will confirm your room and bed.').'</p><a class="button" href="?page=support">Ask about room assignment</a>';
    echo '</section>';
}
function tenant_home(array $u): void {
    $t=one('SELECT * FROM tenants WHERE id=?',[$u['tenant_id']]);$room=tenant_current_room($u);
    $bills=array_values(array_filter(billing_rows($u),fn($b)=>!$b['carried_to']));$bill=$bills[0]??null;$due=$bill?balance($bill):0;
    $pending=$bill?one("SELECT COUNT(*) count,COALESCE(SUM(amount),0) amount FROM payment_submissions WHERE tenant_id=? AND billing_id=? AND status='pending'",[$u['tenant_id'],$bill['id']]):['count'=>0,'amount'=>0];
    $payments=array_values(array_filter(payment_rows($u),fn($p)=>!$p['voided_at']));usort($payments,fn($a,$b)=>strcmp($b['payment_date'],$a['payment_date'])?:((int)$b['id']<=>(int)$a['id']));
    echo '<section class="welcome-banner home-account"><div><span class="eyebrow">MY DORMITORY ACCOUNT</span><h2>Welcome, '.e(explode(' ',$t['name'])[0]).'!</h2>';
    if($room)echo '<p class="home-room-label">Room '.e($room['number']).' &bull; '.e($room['label']).'</p><div class="welcome-room-meta"><span>'.e(tenant_room_type($room)).'</span><span>Move-in date: '.e(date('F j, Y',strtotime($room['start_date']))).'</span></div>';
    else echo '<p>'.($t['status']==='moved_out'?'Your stay has ended. Bills and receipts remain available.':'Not assigned yet. Admin will confirm your accommodation.').'</p>';
    echo '</div><a class="button" href="?page=profile">My Profile</a></section><div class="tenant-home-grid"><section class="panel home-current-bill"><span class="eyebrow">CURRENT STATEMENT</span>';
    if($bill){
        $period=date('F Y',strtotime($bill['period'].'-01'));$days=(int)(new DateTimeImmutable('today'))->diff(new DateTimeImmutable($bill['due_date']))->format('%r%a');
        echo '<h2>'.money($due>0?$due:$bill['total_due']).'</h2>'.($due>0?'<span class="outstanding-label">Outstanding</span>':'').'<p>'.e($period).' &bull; '.badge(status($bill)).'</p><p class="home-due">Due: '.e(date('F j, Y',strtotime($bill['due_date']))).'</p><div class="home-bill-lines">';
        $adjustments=rows('SELECT type,amount,reason FROM billing_adjustments WHERE billing_id=? ORDER BY id',[$bill['id']]);$other=0;$discount=0;foreach($adjustments as $adjustment){if($adjustment['type']==='discount')$discount+=(int)$adjustment['amount'];else $other+=(int)$adjustment['amount'];}
        foreach(['Rent'=>$bill['rent'],'Electricity'=>$bill['electricity'],'Water'=>$bill['water'],'Other charges'=>$other,'Previous balance'=>$bill['previous_balance']] as $label=>$amount)echo '<div><span>'.$label.'</span><strong>'.money($amount).'</strong></div>';
        if($discount)echo '<div><span>Discounts</span><strong>-'.money($discount).'</strong></div>';
        echo '<div class="home-total"><span>Total</span><strong>'.money($bill['total_due']).'</strong></div>';
        if($due>0&&$bill['paid']>0)echo '<div><span>Confirmed payments</span><strong>'.money($bill['paid']).'</strong></div>';
        echo '</div><div class="home-actions">';if($due>0&&(int)$pending['amount']<$due)echo '<a class="button primary" href="?page=pay&bill_id='.$bill['id'].'">Pay Now &rarr;</a>';elseif($pending['count'])echo '<a class="button primary" href="?page=payments">Track Submitted Payment &rarr;</a>';
        echo '<a class="button'.($due===0?' primary':'').'" href="?page=statement&id='.$bill['id'].'">View Bill Details &rarr;</a></div>';
    }else echo '<h2>No statement yet</h2><p>'.($room?'Your first bill will appear when the admin prepares it.':'Your bill will be prepared after room assignment.').'</p><a class="button" href="?page=support">Contact the office</a>';
    echo '</section><section class="panel home-next-step"><span class="eyebrow">YOUR NEXT STEP</span>';
    if(!$room&&$t['status']==='active')echo '<h3>Wait for room assignment</h3><p>Your account is ready. Admin assigns your room before creating your monthly bill.</p><a href="?page=support">Ask about your room &rarr;</a>';
    elseif(!$bill)echo '<h3>Wait for your statement</h3><p>You will receive a notification when the admin prepares your rent and utility charges.</p>';
    elseif($pending['count'])echo '<h3>Payment confirmation pending</h3><p>'.money($pending['amount']).' submitted in '.$pending['count'].' payment(s). Admin or staff confirms receipt of the funds before updating your bill and issuing your receipt.</p><a href="?page=payments">Track Payment &rarr;</a>';
    elseif($due>0)echo '<h3>'.($days<0?'Your payment is overdue':($days===0?'Payment is due today':($days<=7?'Payment is due soon':'Your bill is ready'))).'</h3><p>Your '.e($period).' statement has an outstanding balance of '.money($due).'.</p><a class="button primary" href="?page=pay&bill_id='.$bill['id'].'">Pay Bill &rarr;</a>';
    else echo '<h3>Your account is settled</h3><p>No outstanding balance remains. Your next statement will appear when the admin prepares the next billing period.</p><a href="?page=payments">View Payment Receipts &rarr;</a>';
    $latest=one('SELECT * FROM payment_submissions WHERE tenant_id=? ORDER BY id DESC LIMIT 1',[$u['tenant_id']]);if($latest&&$latest['status']==='rejected'&&$due>0&&$bill&&(int)$latest['billing_id']===(int)$bill['id'])echo '<div class="notice"><strong>Submission needs correction</strong><p>'.e($latest['review_note']).'</p><a href="?page=payments">View feedback</a></div>';
    echo '</section></div><div class="tenant-home-grid home-detail-grid">';tenant_room_card($room);
    echo '<section class="panel home-payments"><span class="eyebrow">RECENT PAYMENTS</span><h2>Your latest transactions</h2>';
    if(!$payments)echo '<p class="empty">No confirmed payments yet. Submitted payments appear under Payments &amp; receipts until confirmed.</p>';
    foreach(array_slice($payments,0,3) as $p)echo '<a class="home-payment-row" href="?page=receipt&id='.$p['id'].'"><div><strong>'.e(date('F Y',strtotime($p['period'].'-01'))).' Rent &amp; Utilities</strong><small>'.e(date('M j, Y',strtotime($p['payment_date']))).' &bull; AD-'.str_pad((string)$p['id'],6,'0',STR_PAD_LEFT).'</small></div><div><strong>'.money($p['amount']).'</strong><span class="badge paid">Paid</span></div></a>';
    echo '<p class="muted payment-caption">Each entry is a confirmed payment. Partial payments may leave a balance on the current bill.</p><a class="button" href="?page=payments">View All Payments &rarr;</a></section></div><section class="panel home-notice-board"><div class="panel-heading"><div><span class="eyebrow">NOTICE BOARD</span><h2>Important dormitory announcements</h2></div><a class="button" href="?page=announcements">View All Notices &rarr;</a></div>';
    $notices=rows('SELECT * FROM announcements WHERE active=1 AND (expires_on IS NULL OR expires_on>=?) ORDER BY id DESC LIMIT 3',[date('Y-m-d')]);
    if(!$notices)echo '<p class="empty">No active announcements. New notices published by the dormitory will appear here.</p>';
    foreach($notices as $notice)echo '<article class="home-notice"><span class="notice-mark" aria-hidden="true">'.icon('announcements').'</span><div><h3>'.e($notice['title']).'</h3><small>Posted '.e(local_time($notice['created_at'])).($notice['expires_on']?' &bull; Until '.e(date('M j, Y',strtotime($notice['expires_on']))):'').'</small><p>'.nl2br(e($notice['message'])).'</p></div></article>';
    echo '</section>';
}
