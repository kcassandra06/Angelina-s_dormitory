<?php
function activity_feed(array $u): array {
    if($u['role']==='tenant') {
        $items=rows('SELECT id,subject title,message,created_at,billing_id,type FROM notifications WHERE tenant_id=? ORDER BY id DESC LIMIT 100',[$u['tenant_id']]);
        foreach($items as &$item) {
            $item['url']=$item['billing_id']?'?page=statement&id='.$item['billing_id']:'?page=support';
            if($item['type']==='receipt' && preg_match('/AD-(\d+)/',$item['title'],$m)) {
                $p=one('SELECT id FROM payments WHERE id=? AND tenant_id=?',[(int)$m[1],$u['tenant_id']]);
                if($p) $item['url']='?page=receipt&id='.$p['id'];
            }
        }
        return $items;
    }
    $scope=$u['role']==='staff'?" AND (a.record_type!='tenant_inquiries' OR EXISTS(SELECT 1 FROM tenant_inquiries q WHERE q.id=a.record_id AND q.category IN ('Payment','Billing')))":'';
    $types=$u['role']==='admin'?"'payments','payment_submissions','billing','tenant_inquiries','tenants','maintenance_requests','announcements','expenses','deposits','users','public_contact'":"'payments','payment_submissions','billing','tenant_inquiries'";
    $items=rows("SELECT a.id,a.action title,a.details message,a.created_at,a.record_type,a.record_id,u.name actor FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id WHERE a.record_type IN (".$types.")".$scope." ORDER BY a.id DESC LIMIT 100");
    foreach($items as &$item) {
        $item['message']=($item['actor']?:'System').($item['message']?' · '.$item['message']:'');
        $item['url']=match($item['record_type']) { 'payments'=>'?page=receipt&id='.$item['record_id'],'payment_submissions'=>'?page=payments','billing'=>$item['record_id']?'?page=statement&id='.$item['record_id']:'?page=billing','tenants'=>'?page=tenants&tenant_id='.$item['record_id'],'maintenance_requests'=>'?page=requests','announcements'=>'?page=announcements','expenses'=>'?page=expenses','deposits'=>'?page=deposits','users'=>'?page=accounts',default=>'?page=support' };
    }
    return $items;
}
function activity_unread(array $u,array $items): int {
    $seen=(int)setting('activity_seen_'.$u['id'],'0'); return count(array_filter($items,fn($item)=>(int)$item['id']>$seen));
}
function activity_bell(array $u): void {
    $items=activity_feed($u); $count=activity_unread($u,$items);
    echo '<a class="notification-bell" href="?page=inbox" aria-label="Notifications, '.$count.' unread"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg><span class="bell-count"'.($count?'':' hidden').'>'.$count.'</span></a>';
}
function activity_view(array $u): void {
    $items=activity_feed($u); section_start('Notifications',$u['role']==='tenant'?'Your bills, payment receipts, reminders, and replies.':'New payment submissions, confirmed receipts, billing changes, and tenant inquiries.');
    form_start('read_notifications','inbox','inline-form'); echo '<input type="hidden" name="seen_through" value="'.($items[0]['id']??0).'">'; submit('Mark displayed notifications as read');
    if(!$items) echo '<p class="empty">You are all caught up. New updates will appear here.</p>';
    $seen=(int)setting('activity_seen_'.$u['id'],'0');
    foreach($items as $item) echo '<a class="activity-item '.((int)$item['id']>$seen?'unread':'').'" href="'.e($item['url']).'"><div><span class="eyebrow">'.e(local_time($item['created_at'])).'</span><h3>'.e($item['title']).'</h3><p>'.nl2br(e($item['message'])).'</p></div><span>View →</span></a>';
    echo '</section>';
}
