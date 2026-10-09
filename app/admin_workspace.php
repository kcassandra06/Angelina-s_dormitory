<?php
function admin_section_tabs(string $page,array $u): void {
    if($u['role']!=='admin') return;
    $tabs=match($page) {
        'reports','expenses','ledger'=>['reports'=>'Analytics','expenses'=>'Expenses','ledger'=>'Account Ledger'],
        'settings','notifications'=>['settings'=>'Dormitory & Billing','notifications'=>'Email / Notifications'],
        'billing','batch'=>['billing'=>'Statements','batch'=>'Create Monthly Billing'],
        default=>[]
    };
    if(!$tabs) return;
    echo '<nav class="section-tabs no-print" aria-label="Section navigation">';
    foreach($tabs as $key=>$label) echo '<a '.($page===$key?'aria-current="page"':'').' href="?page='.$key.'">'.e($label).'</a>';
    echo '</nav>';
}
function dormitory_metrics(): array {
    $active=(int)query("SELECT COUNT(*) FROM tenants WHERE status='active'")->fetchColumn();
    $occupied=(int)query('SELECT COUNT(*) FROM tenant_assignments WHERE end_date IS NULL')->fetchColumn();
    $capacity=(int)query('SELECT COUNT(*) FROM bed_spaces')->fetchColumn();
    $available=count(available_beds());
    return [$active,$occupied,$available,$capacity?round($occupied/$capacity*100,1):0,$capacity];
}
function admin_dashboard(array $u): void {
    $bills=aging_rows();$outstanding=0;$overdue=[];
    foreach($bills as $b){$outstanding+=balance($b);if(status($b)==='overdue')$overdue[]=$b;}
    $collected=(int)query('SELECT COALESCE(SUM(amount),0) FROM payments WHERE voided_at IS NULL AND payment_date BETWEEN ? AND ?',[date('Y-m-01'),date('Y-m-t')])->fetchColumn();
    $pending=(int)query("SELECT COUNT(*) FROM payment_submissions WHERE status='pending'")->fetchColumn();
    $maintenance=(int)query("SELECT COUNT(*) FROM maintenance_requests WHERE status!='resolved'")->fetchColumn();
    $notices=rows('SELECT * FROM announcements WHERE active=1 AND (expires_on IS NULL OR expires_on>=?) ORDER BY id DESC LIMIT 3',[date('Y-m-d')]);
    [$active,$occupied,$available,$rate,$capacity]=dormitory_metrics();
    echo '<section class="welcome-banner"><div><span class="eyebrow">'.e(date('l, F j')).'</span><h2>Welcome back, '.e(explode(' ',$u['name'])[0]).'.</h2><p>Collections, accommodation, and the work that needs your attention.</p></div><div class="welcome-art">'.icon('rooms').'<span>Angelina&#8217;s Dormitory</span></div></section>';
    $groups=[
        'Financial overview'=>[['Collected this month',money($collected),date('F Y').' confirmed payments','payments'],['Outstanding balance',money($outstanding),'Current statements; carried balances counted once','billing'],['Overdue statements',count($overdue),'Unsettled statements past their due date','billing'],['Pending payments',$pending,'Awaiting payment verification','payments']],
        'Dormitory overview'=>[['Active residents',$active,'Includes residents awaiting assignment','tenants'],['Occupied beds',$occupied,'Of '.$capacity.' total beds','rooms'],['Available beds',$available,'Vacant beds in active rooms','rooms'],['Occupancy rate',$rate.'%','Occupied beds / all registered beds','rooms']]
    ];
    foreach($groups as $heading=>$stats){echo '<h2 class="dashboard-section-title">'.e($heading).'</h2><div class="stats admin-kpis">';foreach($stats as [$label,$value,$hint,$page])echo '<a class="stat" href="?page='.$page.'"><div><span>'.e($label).'</span><i class="stat-icon">'.icon($page).'</i></div><strong>'.e($value).'</strong><small>'.e($hint).'</small></a>';echo '</div>';}
    echo '<section class="panel"><div class="panel-heading"><div><h2>Quick actions</h2><p class="muted">Start a task in the section where it is managed.</p></div></div><div class="admin-actions">';
    foreach([['batch','Create monthly billing'],['tenants#add-resident','Add resident'],['payments#record-payment','Record payment'],['tenants#assign-resident','Assign room / bed']] as [$page,$label])echo '<a class="button" href="?page='.$page.'">'.e($label).' &rarr;</a>';
    echo '</div></section><div class="dashboard-grid"><section class="panel"><div class="panel-heading"><div><h2>Needs attention</h2><p class="muted">Follow through from statement to payment and receipt.</p></div></div><div class="admin-alerts">';
    foreach([['billing',count($overdue),'Overdue bills'],['payments',$pending,'Pending payment verification'],['requests',$maintenance,'Open maintenance requests']] as [$page,$count,$label])echo '<a href="?page='.$page.'"><span>'.e($label).'</span><strong>'.$count.'</strong><span>&rarr;</span></a>';
    echo '</div>';foreach(array_slice($overdue,0,4) as $b)echo '<div class="history-row"><a href="?page=statement&id='.$b['id'].'">'.e($b['name']).' &middot; '.e($b['period']).'</a><strong>'.money(balance($b)).'</strong></div>';echo '</section><section class="panel"><div class="panel-heading"><div><h2>Important notices</h2><p class="muted">Published information visible to residents.</p></div><a href="?page=announcements">Notice Board &rarr;</a></div>';
    if(!$notices)echo '<p class="empty">No published notices.</p>';foreach($notices as $notice)echo '<article class="dashboard-notice"><h3>'.e($notice['title']).'</h3><p>'.nl2br(e($notice['message'])).'</p></article>';echo '</section></div>';
    section_start('Recent confirmed payments','Pending submissions do not change balances. Every confirmed payment has a receipt.');payments_table(array_slice(payment_rows($u),0,5));echo '</section>';
    echo '<a class="button" href="?page=reports">View financial charts and account reports &rarr;</a>';
}

function workspace_back_link(string $page,array $u): void {
    if(in_array($page,['dashboard','statement','receipt'],true))return;
    [$target,$label]=match($page){
        'batch'=>['billing','Billing Statements'],
        'expenses','ledger'=>['reports','Financial Reports'],
        'notifications'=>['settings','Settings'],
        'pay'=>['billing','My bills'],
        'tenants'=>isset($_GET['tenant_id'])?['tenants','Residents']:['dashboard','Overview'],
        'rooms'=>isset($_GET['bed_id'])?['rooms','Rooms & Beds']:['dashboard','Overview'],
        default=>['dashboard',$u['role']==='tenant'?'My Home':'Overview']
    };
    if($u['role']==='tenant'&&$page==='ledger')[$target,$label]=['billing','My bills'];
    echo '<a class="workspace-back no-print" href="?page='.e($target).'">&larr; Back to '.e($label).'</a>';
}
