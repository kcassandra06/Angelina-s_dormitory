<?php
require __DIR__.'/app/bootstrap.php';
require __DIR__.'/app/seed.php';
require __DIR__.'/app/features.php';
require __DIR__.'/app/report_charts.php';
require __DIR__.'/app/tenant_portal.php';
require __DIR__.'/app/activity.php';
require __DIR__.'/app/reminders.php';
require __DIR__.'/app/actions.php';
handle_action();
$u=user(); $page=(string)($_GET['page']??($u?'dashboard':'home'));
if($u) queue_due_reminders();
if($page==='verification' && $u && in_array($u['role'],['admin','staff'],true)) redirect('payments');
if(!$u && !in_array($page,['home','login','register','privacy','terms'],true)) redirect('login');
if($u && in_array($page,['home','login','register','privacy','terms'],true)) redirect('dashboard');
if(isset($_GET['proof'])) { if(!$u) redirect('login'); show_payment_proof($u); }
if(isset($_GET['activity_poll'])) { if(!$u || $u['must_change']) { http_response_code(403); exit; } header('Content-Type: application/json'); $items=activity_feed($u); echo json_encode(['unread'=>activity_unread($u,$items),'latest'=>$items[0]['id']??0]); exit; }
if(isset($_GET['payment_qr'])) { if(!$u) redirect('login'); show_payment_qr($u); }
if(isset($_GET['export'])) { if(!$u) redirect('login'); try { export_report($u); } catch(RuntimeException $error) { http_response_code(403); exit(e($error->getMessage())); } }
$access=['dashboard'=>['admin','staff','tenant'],'tenants'=>['admin'],'rooms'=>['admin'],'billing'=>['admin','staff','tenant'],'payments'=>['admin','staff','tenant'],'deposits'=>['admin','tenant'],'occupancy'=>['admin'],'notifications'=>['admin'],'audit'=>['admin'],'accounts'=>['admin'],'account'=>['admin','staff','tenant'],'receipt'=>['admin','staff','tenant'],'statement'=>['admin','staff','tenant'],'batch'=>['admin'],'reports'=>['admin'],'expenses'=>['admin'],'requests'=>['admin','tenant'],'announcements'=>['admin','staff','tenant'],'settings'=>['admin'],'ledger'=>['admin','tenant']];
$access+=['pay'=>['tenant'],'inbox'=>['admin','staff','tenant'],'profile'=>['tenant'],'support'=>['admin','staff','tenant']];
if($u && (!isset($access[$page]) || !in_array($u['role'],$access[$page],true))) { http_response_code(403); $page='forbidden'; }
if($u && $u['must_change'] && $page!=='account') redirect('account');
// Set the response status before the HTML layout can flush its output buffer.
if($u && in_array($page,['statement','receipt'],true)) {
    $table=$page==='statement'?'billing':'payments';
    $record=one('SELECT tenant_id FROM '.$table.' WHERE id=?',[(int)($_GET['id']??0)]);
    if(!$record || ($u['role']==='tenant' && (int)$record['tenant_id']!==(int)$u['tenant_id'])) http_response_code(404);
}
$titles=['dashboard'=>'Overview','tenants'=>'Residents','rooms'=>'Rooms & bed spaces','billing'=>'Billing statements','payments'=>'Payments','deposits'=>'Security deposits','occupancy'=>'Occupancy monitor','notifications'=>'Email notifications','audit'=>'Audit trail','accounts'=>'User accounts','account'=>'My account','receipt'=>'Payment receipt','statement'=>'Billing statement','forbidden'=>'Access restricted'];
$titles+=['batch'=>'Monthly billing run','reports'=>'Financial reports','expenses'=>'Operating expenses','requests'=>'Maintenance requests','announcements'=>'Notice board','settings'=>'Dormitory settings','ledger'=>'Account ledger'];
$titles['register']='Create tenant account';
$titles+=['home'=>"Angelina's Dormitory",'privacy'=>'Privacy Policy','terms'=>'Terms'];
$titles+=['pay'=>'Make a payment','inbox'=>'Notifications','profile'=>'My profile','support'=>'Help & inquiries'];
$nav=['dashboard'=>['dashboard','Overview'],'tenants'=>['tenants','Residents'],'rooms'=>['rooms','Rooms & Beds'],'billing'=>['billing','Billing Statements'],'payments'=>['payments','Payments'],'deposits'=>['deposits','Security Deposits'],'requests'=>['requests','Maintenance'],'announcements'=>['announcements','Notice Board'],'reports'=>['reports','Financial Reports'],'accounts'=>['accounts','User Accounts'],'audit'=>['audit','Audit Trail'],'settings'=>['settings','Settings']];
require __DIR__.'/app/views.php';
if($u && $u['role']==='staff') $nav=['dashboard'=>['dashboard','Overview'],'billing'=>['billing','Billing Statements'],'payments'=>['payments','Payments'],'announcements'=>['announcements','Notice Board']];
if($u && $u['role']==='tenant') {
    $nav=['dashboard'=>['dashboard','My Home'],'billing'=>['billing','My bills'],'payments'=>['payments','Payments & receipts'],'deposits'=>['deposits','My deposit'],'inbox'=>['notifications','Notifications'],'requests'=>['requests','Maintenance'],'support'=>['notifications','Help & inquiries'],'announcements'=>['announcements','Notice board'],'profile'=>['accounts','My profile']];
    $titles['dashboard']='My Home'; $titles['billing']='My bills'; $titles['payments']='Payments & receipts';
}
$activeSection=match($page){'batch','statement'=>'billing','expenses','ledger'=>'reports','notifications'=>'settings','receipt','pay'=>'payments',default=>$page};
require __DIR__.'/app/feature_views.php';
require __DIR__.'/app/tenant_views.php';
require __DIR__.'/app/operations_views.php';
require __DIR__.'/app/tenant_home.php';
require __DIR__.'/app/admin_workspace.php';
?><!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#152c35"><title><?=e($titles[$page]??'Sign in')?> · Angelina’s Dormitory</title><link rel="stylesheet" href="assets/style.css"><link rel="stylesheet" href="assets/redesign.css"><link rel="stylesheet" href="assets/registration.css"><link rel="stylesheet" href="assets/tenant.css"><link rel="stylesheet" href="assets/operations.css"><link rel="stylesheet" href="assets/public.css"><link rel="stylesheet" href="assets/green-theme.css"></head><body>
<?php if(!$u): ?>
<?php if(in_array($page,['login','register'],true)) require __DIR__.'/app/public_auth.php'; else require __DIR__.'/app/public_entry.php'; ?>
<?php else: ?>
<aside class="sidebar" id="sidebar"><a class="brand" href="index.php"><span class="brand-icon">A</span><span>ANGELINA’S<small>DORMITORY</small></span></a><span class="nav-heading">MANAGE YOUR HOME</span><nav><?php foreach($nav as $key=>$item): if(!in_array($u['role'],$access[$key],true)) continue; ?><a title="<?=e($item[1])?>" class="<?=$activeSection===$key?'selected':''?>" href="?page=<?=e($key)?>"><span><?=icon($item[0])?></span><b><?=e($item[1])?></b></a><?php endforeach; ?></nav><div class="sidebar-bottom"><?php if($u['role']!=='tenant'): ?><a class="sidebar-help" href="?page=support">Help &amp; Inquiries</a><?php endif; ?><a class="profile" href="?page=account"><span class="avatar"><?=e(strtoupper(substr($u['name'],0,1)))?></span><span><strong><?=e($u['name'])?></strong><small><?=e(ucfirst($u['role']))?> account</small></span></a><form method="post"><?=csrf()?><input type="hidden" name="action" value="logout"><button class="logout">↪ Sign out</button></form></div></aside><button type="button" class="sidebar-scrim" id="sidebar-scrim" aria-label="Close navigation"></button>
<div class="workspace"><header class="topbar"><div class="top-left"><button type="button" class="menu-toggle" id="menu-toggle" aria-label="Open navigation" aria-controls="sidebar" aria-expanded="false"><?=icon('menu')?></button><span class="breadcrumb">Workspace <span>/</span> <strong><?=e($titles[$page]??'')?></strong></span></div><div class="top-right"><?php activity_bell($u); ?><span class="date-label"><?=date('l, M d, Y')?></span><a class="header-avatar" href="?page=account" aria-label="My account"><?=e(strtoupper(substr($u['name'],0,1)))?></a></div></header><main class="content"><div class="page-heading"><div><?php workspace_back_link($page,$u); ?><span class="eyebrow">YOUR DORMITORY WORKSPACE</span><h1><?=e($titles[$page]??'')?></h1><p class="muted"><?=e(page_subtitle($page,$u['role']))?></p></div><div class="heading-actions"><?php if($u['role']==='admin' && in_array($page,['dashboard','billing'],true)): ?><a class="button primary" href="?page=batch"><?=icon('batch')?> Create monthly billing</a><?php elseif($u['role']==='staff' && $page==='dashboard'): ?><a class="button primary" href="?page=payments">Receive payment →</a><?php endif; ?><span class="role-label"><?=e(ucfirst($u['role']))?></span></div></div><?php show_flash(); try { admin_section_tabs($page,$u); render_page($page,$u); } catch(RuntimeException $error) { echo '<div class="notice error">'.e($error->getMessage()).'</div>'; } ?><footer class="content-footer"><?=e(setting('business_name','Angelina’s Dormitory'))?><span>A home, well managed. · <?=date('Y')?></span></footer></main></div>
<?php endif; ?><script src="assets/app.js" defer></script><script src="assets/portal.js" defer></script></body></html>
