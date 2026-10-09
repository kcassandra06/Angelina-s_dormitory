<?php
// Notifications remain queued until configured. No credentials are stored in the database.
function smtp_send(array $cfg, array $n): void {
    $host=$cfg['smtp_host']; $port=(int)($cfg['smtp_port']??587); $enc=$cfg['smtp_encryption']??'tls';
    if(!in_array($enc,['tls','ssl'],true) || !extension_loaded('openssl')) throw new RuntimeException('Enable the PHP OpenSSL extension to use encrypted SMTP.');
    $sock=stream_socket_client(($enc==='ssl'?'ssl://':'tcp://').$host.':'.$port,$errno,$errstr,15);
    if(!$sock) throw new RuntimeException('Cannot connect to the SMTP server.');
    stream_set_timeout($sock,15);
    $read=function(array $expected) use($sock) { $response=''; do { $line=fgets($sock,4096); if($line===false) throw new RuntimeException('SMTP connection timed out or closed.'); $response.=$line; } while(isset($line[3]) && $line[3]==='-'); if(!in_array((int)substr($response,0,3),$expected,true)) throw new RuntimeException('SMTP server rejected the request ('.(int)substr($response,0,3).').'); };
    $cmd=function(string $line,array $codes) use($sock,$read) { fwrite($sock,$line."\r\n"); $read($codes); };
    try {
        $read([220]); $cmd('EHLO localhost',[250]);
        if($enc==='tls') { $cmd('STARTTLS',[220]); if(!stream_socket_enable_crypto($sock,true,STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new RuntimeException('TLS negotiation failed.'); $cmd('EHLO localhost',[250]); }
        $cmd('AUTH LOGIN',[334]); $cmd(base64_encode($cfg['smtp_user']),[334]); $cmd(base64_encode($cfg['smtp_password']),[235]);
        $from=$cfg['smtp_from']?:$cfg['smtp_user']; if(!filter_var($from,FILTER_VALIDATE_EMAIL)||!filter_var($n['email'],FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Invalid sender or recipient email.');
        $cmd('MAIL FROM:<'.$from.'>',[250]); $cmd('RCPT TO:<'.$n['email'].'>',[250,251]); $cmd('DATA',[354]);
        $subject='=?UTF-8?B?'.base64_encode($n['subject']).'?=';
        $message='From: Angelina Dormitory <'.$from.">\r\nTo: ".$n['email']."\r\nSubject: ".$subject."\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\nDate: ".date(DATE_RFC2822)."\r\n\r\n".chunk_split(base64_encode($n['message']))."\r\n.";
        $cmd($message,[250]); $cmd('QUIT',[221]);
    } finally { fclose($sock); }
}
function send_notifications(): string {
    $file=dirname(__DIR__).'/config.local.php'; $cfg=is_file($file)?require $file:[];
    if(empty($cfg['smtp_host'])||empty($cfg['smtp_user'])||empty($cfg['smtp_password'])) return 'SMTP is not configured. Notifications are safely queued.';
    $sent=0; $failed=0;
    foreach(rows("SELECT * FROM notifications WHERE status IN ('queued','failed') ORDER BY id LIMIT 20") as $n) {
        try { smtp_send($cfg,$n); query("UPDATE notifications SET status='sent',sent_at=?,error=NULL WHERE id=?",[date('Y-m-d H:i:s'),$n['id']]); audit('Sent email notification','notifications',(int)$n['id']); $sent++; }
        catch(Throwable $e) { query("UPDATE notifications SET status='failed',error=? WHERE id=?",[$e->getMessage(),$n['id']]); $failed++; }
    }
    return "$sent sent, $failed failed.";
}
