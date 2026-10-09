<?php
// Schedule this CLI command daily to queue overdue reminders and send emails.
if(PHP_SAPI!=='cli') { http_response_code(403); exit; }
require __DIR__.'/bootstrap.php';
require __DIR__.'/mailer.php';
require __DIR__.'/reminders.php';
$lock=fopen(dirname(__DIR__).'/storage/mail-worker.lock','c');
if(!flock($lock,LOCK_EX|LOCK_NB)) exit("Another mail worker is running.\n");
queue_due_reminders();
echo send_notifications().PHP_EOL;
flock($lock,LOCK_UN); fclose($lock);
