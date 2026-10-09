<?php
// Shared by signed-in pages and the scheduled email worker.
function queue_due_reminders(): void {
    $today=new DateTimeImmutable('today');
    foreach(rows('SELECT b.*,COALESCE((SELECT SUM(amount) FROM payments WHERE billing_id=b.id AND voided_at IS NULL),0) paid FROM billing b WHERE carried_to IS NULL AND due_date<=?',[$today->modify('+7 days')->format('Y-m-d')]) as $b) {
        $amount=balance($b); if($amount<=0) continue;
        $days=(int)$today->diff(new DateTimeImmutable($b['due_date']))->format('%r%a');
        $stage=$days<0?'overdue-'.$today->format('Y-m-d'):($days===0?'today':($days<=1?'one-day':($days<=3?'three-days':'seven-days')));
        $subject=$days<0?'Payment overdue':($days===0?'Payment due today':'Upcoming payment due');
        notify((int)$b['tenant_id'],(int)$b['id'],$days<0?'overdue':'due_reminder',$subject,'Your statement for '.$b['period'].' has an outstanding balance of '.money($amount).'. Due date: '.$b['due_date'].'. Open your statement or Make a payment to review the prepared amount and payment instructions. Pending submissions are awaiting verification and do not reduce this balance.','due-'.$b['id'].'-'.$b['due_date'].'-'.$stage.'-'.$amount);
    }
}
