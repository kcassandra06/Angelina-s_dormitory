<?php
function tenant_credentials(bool $temporary=true): array {
    $username=required('username');
    if(!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,49}$/',$username)) throw new RuntimeException('Username must be 3–50 characters using letters, numbers, dots, underscores, or hyphens.');
    if(one('SELECT id FROM users WHERE username=?',[$username])) throw new RuntimeException('That username is already taken. Choose another username.');
    $password=required('temporary_password');
    if(strlen($password)<10 || strlen($password)>72) throw new RuntimeException(($temporary?'Temporary password':'Password').' must be between 10 and 72 characters.');
    if($password!==($_POST['confirm_password']??'')) throw new RuntimeException('The passwords do not match.');
    return [$username,$password];
}
function handle_action(): void {
    if ($_SERVER['REQUEST_METHOD']!=='POST') return;
    $action=(string)($_POST['action']??'');
    $return=(string)($_POST['return_page']??'dashboard');
    if($return==='verification') $return='payments';
    if($action==='register') $return='register';
    if (!hash_equals(token(),(string)($_POST['csrf']??''))) { http_response_code(419); exit('Your session expired. Reload this page and try again.'); }
    try {
        if($action==='public_contact') {
            $return='home';$identity=hash('sha256','public-contact|'.($_SERVER['REMOTE_ADDR']??'local'));
            query('DELETE FROM login_attempts WHERE attempted_at < ?',[time()-900]);
            if((int)query('SELECT COUNT(*) FROM login_attempts WHERE identity=?',[$identity])->fetchColumn()>=5)throw new RuntimeException('Too many messages. Please try again in 15 minutes.');
            $name=required('name');$email=required('email');$message=required('message');
            if(strlen($name)>200||strlen($email)>254||!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Please enter your name and a valid email address.');
            if(!empty($_POST['website']))throw new RuntimeException('Unable to submit this message.');
            query('INSERT INTO login_attempts(identity,attempted_at) VALUES(?,?)',[$identity,time()]);
            audit('New website inquiry','public_contact',null,json_encode(['name'=>$name,'email'=>$email,'message'=>$message],JSON_UNESCAPED_UNICODE));
            flash('Your message has been received by the dormitory office.');header('Location: index.php?page=home#contact');exit;
        }
        if($action==='register') {
            if(user()) throw new RuntimeException('Sign out before registering a new account.');
            $_SESSION['signup_values']=array_intersect_key($_POST,array_flip(['name','email','contact','username']));
            $identity=hash('sha256','registration|'.($_SERVER['REMOTE_ADDR']??'local'));
            query('DELETE FROM login_attempts WHERE attempted_at < ?',[time()-900]);
            if((int)query('SELECT COUNT(*) FROM login_attempts WHERE identity=?',[$identity])->fetchColumn()>=8) throw new RuntimeException('Too many registration attempts. Try again in 15 minutes.');
            query('INSERT INTO login_attempts(identity,attempted_at) VALUES(?,?)',[$identity,time()]);
            db()->beginTransaction(); query("SELECT value FROM settings WHERE `key`='seeded' FOR UPDATE");
            [$username,$password]=tenant_credentials(false); $name=required('name'); $email=required('email'); $contact=required('contact');
            if(strlen($name)>200 || strlen($email)>254 || strlen($contact)>80) throw new RuntimeException('Your name, email, or contact number is too long.');
            if(!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter a valid email address.');
            if(one('SELECT id FROM tenants WHERE email=?',[$email])) throw new RuntimeException('This email already has a tenant record. Sign in with your existing account or contact the dormitory for help.');
            query('INSERT INTO tenants(name,email,contact,move_in) VALUES(?,?,?,?)',[$name,$email,$contact,date('Y-m-d')]); $id=(int)db()->lastInsertId();
            query("INSERT INTO users(username,password_hash,role,name,tenant_id,must_change) VALUES(?,?,'tenant',?,?,0)",[$username,password_hash($password,PASSWORD_DEFAULT),$name,$id]);
            audit('Tenant self-registered','tenants',$id);
            db()->commit(); unset($_SESSION['signup_values']); $_SESSION['registered_username']=$username;
            flash('Your tenant account is ready. Sign in using your username and password. The dormitory will assign your room and bed.'); redirect('login');
        }
        if ($action==='login') {
            $identity=hash('sha256',strtolower(trim($_POST['username']??'')).'|'.($_SERVER['REMOTE_ADDR']??'local'));
            query('DELETE FROM login_attempts WHERE attempted_at < ?',[time()-900]);
            if ((int)query('SELECT COUNT(*) FROM login_attempts WHERE identity=?',[$identity])->fetchColumn()>=8) throw new RuntimeException('Too many attempts. Try again in 15 minutes.');
            $u=one('SELECT * FROM users WHERE username=? AND role=? AND active=1',[trim($_POST['username']??''),$_POST['role']??'']);
            if (!$u || !password_verify($_POST['password']??'',$u['password_hash'])) {
                query('INSERT INTO login_attempts(identity,attempted_at) VALUES(?,?)',[$identity,time()]);
                throw new RuntimeException('The username, password, or selected role is incorrect.');
            }
            session_regenerate_id(true); $_SESSION['uid']=$u['id']; $_SESSION['csrf']=bin2hex(random_bytes(32));
            query('DELETE FROM login_attempts WHERE identity=?',[$identity]); audit('Logged in');
            redirect($u['must_change']?'account':'dashboard');
        }
        $u=require_role(['admin','staff','tenant']);
        if ($action==='logout') { audit('Logged out'); $_SESSION=[]; session_regenerate_id(true); redirect('login'); }
        if ($u['must_change'] && $action!=='password') throw new RuntimeException('Set your own password before continuing.');
        db()->beginTransaction();
        // Serialize financial and assignment changes with an InnoDB row lock.
        query("SELECT value FROM settings WHERE `key`='seeded' FOR UPDATE");
        switch ($action) {
        case 'password':
            if(!password_verify($_POST['current_password']??'',$u['password_hash'])) throw new RuntimeException('Current password is incorrect.');
            $pw=required('new_password');
            if(strlen($pw)<10 || $pw!==($_POST['confirm_password']??'') || password_verify($pw,$u['password_hash'])) throw new RuntimeException('Use a different password of at least 10 characters and confirm it correctly.');
            query('UPDATE users SET password_hash=?,must_change=0 WHERE id=?',[password_hash($pw,PASSWORD_DEFAULT),$u['id']]); audit('Changed own password'); break;
        case 'room':
            require_role(['admin']); $capacity=(int)required('capacity');
            if($capacity<1||$capacity>30) throw new RuntimeException('Room capacity must be between 1 and 30.');
            query('INSERT INTO rooms(number,capacity,rent) VALUES(?,?,?)',[required('number'),$capacity,cents('rent')]); $id=(int)db()->lastInsertId();
            for($i=1;$i<=$capacity;$i++) query('INSERT INTO bed_spaces(room_id,label) VALUES(?,?)',[$id,'Bed '.$i]); audit('Created room','rooms',$id); break;
        case 'room_update':
            require_role(['admin']); $id=(int)required('room_id'); $st=required('status');
            if(!in_array($st,['active','maintenance'],true)) throw new RuntimeException('Invalid room status.');
            if($st==='maintenance' && one('SELECT a.id FROM tenant_assignments a JOIN bed_spaces b ON b.id=a.bed_id WHERE b.room_id=? AND a.end_date IS NULL',[$id])) throw new RuntimeException('Transfer active tenants before marking this room for maintenance.');
            query('UPDATE rooms SET rent=?,status=? WHERE id=?',[cents('rent'),$st,$id]); audit('Updated room','rooms',$id); break;
        case 'tenant':
            require_role(['admin']); $email=required('email');
            if(!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter a valid email address.');
            [$username,$password]=tenant_credentials();
            $bed=(int)required('bed_id');
            if(!one("SELECT b.id FROM bed_spaces b JOIN rooms r ON r.id=b.room_id WHERE b.id=? AND r.status='active' AND NOT EXISTS(SELECT 1 FROM tenant_assignments a WHERE a.bed_id=b.id AND a.end_date IS NULL)",[$bed])) throw new RuntimeException('This bed is no longer available.');
            $date=date_input('move_in'); if($date>date('Y-m-d')) throw new RuntimeException('Move-in cannot be in the future.');
            query('INSERT INTO tenants(name,email,contact,move_in) VALUES(?,?,?,?)',[required('name'),$email,required('contact'),$date]); $id=(int)db()->lastInsertId();
            query('INSERT INTO tenant_assignments(tenant_id,bed_id,start_date) VALUES(?,?,?)',[$id,$bed,$date]);
            query('INSERT INTO users(username,password_hash,role,name,tenant_id) VALUES(?,?,?,?,?)',[$username,password_hash($password,PASSWORD_DEFAULT),'tenant',required('name'),$id]);
            audit('Registered tenant, created individual account, and assigned bed','tenants',$id);
            $_SESSION['action_message']='Tenant registered and account created. Username: '.$username.'. Share the temporary password privately; the tenant must change it on first login.'; break;
        case 'tenant_account':
            require_role(['admin']); $id=(int)required('tenant_id');
            $tenant=one("SELECT * FROM tenants WHERE id=? AND status='active'",[$id]);
            if(!$tenant) throw new RuntimeException('Choose an active tenant to create an account.');
            if(one('SELECT id FROM users WHERE tenant_id=?',[$id])) throw new RuntimeException('This tenant already has an account. Use password reset or enable access instead.');
            [$username,$password]=tenant_credentials();
            query("INSERT INTO users(username,password_hash,role,name,tenant_id) VALUES(?,?,'tenant',?,?)",[$username,password_hash($password,PASSWORD_DEFAULT),$tenant['name'],$id]);
            audit('Created account for existing tenant','users',(int)db()->lastInsertId(),'Linked tenant #'.$id);
            $_SESSION['action_message']='Tenant account created. Username: '.$username.'. The tenant must change the temporary password on first login.'; break;
        case 'tenant_update':
            require_role(['admin']); $id=(int)required('tenant_id'); $email=required('email');
            if(!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter a valid email.');
            query('UPDATE tenants SET name=?,email=?,contact=? WHERE id=?',[required('name'),$email,required('contact'),$id]);
            query('UPDATE users SET name=? WHERE tenant_id=?',[required('name'),$id]); audit('Updated tenant details','tenants',$id); break;
        case 'transfer':
            require_role(['admin']); $id=(int)required('tenant_id'); $a=one('SELECT * FROM tenant_assignments WHERE tenant_id=? AND end_date IS NULL',[$id]);
            if(!$a) throw new RuntimeException('No active assignment.');
            $date=date_input('transfer_date'); if($date<$a['start_date']||$date>date('Y-m-d')) throw new RuntimeException('Transfer date must be within the active stay.');
            $bed=(int)required('bed_id'); if(!one("SELECT b.id FROM bed_spaces b JOIN rooms r ON r.id=b.room_id WHERE b.id=? AND r.status='active' AND NOT EXISTS(SELECT 1 FROM tenant_assignments a WHERE a.bed_id=b.id AND a.end_date IS NULL)",[$bed])) throw new RuntimeException('Selected bed is unavailable.');
            query('UPDATE tenant_assignments SET end_date=? WHERE id=?',[$date,$a['id']]);
            query('INSERT INTO tenant_assignments(tenant_id,bed_id,start_date) VALUES(?,?,?)',[$id,$bed,$date]); audit('Transferred tenant','tenants',$id); break;
        case 'assign_tenant':
            require_role(['admin']); $id=(int)required('tenant_id'); $bed=(int)required('bed_id'); $date=date_input('move_in');
            if(!one("SELECT id FROM tenants WHERE id=? AND status='active'",[$id]) || one('SELECT id FROM tenant_assignments WHERE tenant_id=? AND end_date IS NULL',[$id])) throw new RuntimeException('Choose an active tenant without a bed assignment.');
            if($date>date('Y-m-d')) throw new RuntimeException('Move-in cannot be in the future.');
            if(!one("SELECT b.id FROM bed_spaces b JOIN rooms r ON r.id=b.room_id WHERE b.id=? AND r.status='active' AND NOT EXISTS(SELECT 1 FROM tenant_assignments a WHERE a.bed_id=b.id AND a.end_date IS NULL)",[$bed])) throw new RuntimeException('This bed is no longer available.');
            query('INSERT INTO tenant_assignments(tenant_id,bed_id,start_date) VALUES(?,?,?)',[$id,$bed,$date]);
            query('UPDATE tenants SET move_in=? WHERE id=?',[$date,$id]); audit('Assigned room to registered tenant','tenants',$id); break;
        case 'moveout':
            require_role(['admin']); $id=(int)required('tenant_id'); $a=one('SELECT * FROM tenant_assignments WHERE tenant_id=? AND end_date IS NULL',[$id]);
            $date=date_input('move_out'); if(!$a || $date<$a['start_date']||$date>date('Y-m-d')) throw new RuntimeException('Move-out date must be within the active stay.');
            query("UPDATE tenants SET status='moved_out',move_out=? WHERE id=?",[$date,$id]);
            query('UPDATE tenant_assignments SET end_date=? WHERE id=?',[$date,$a['id']]); audit('Recorded move-out; history retained','tenants',$id); break;
        case 'bill':
            require_role(['admin']);
            $statementId=create_statement((int)required('tenant_id'),period_input(),date_input('cutoff'),date_input('due_date'),cents('rent'),cents('electricity'),cents('water'),cents('discount'),cents('adjustment',true),trim($_POST['discount_reason']??''),trim($_POST['adjustment_reason']??'')); $return='statement&id='.$statementId; break;
        case 'adjust_bill':
            require_role(['admin']); $id=(int)required('billing_id'); $b=bill($id);
            if(!$b || $b['carried_to']) throw new RuntimeException('Carried statements cannot be adjusted; adjust the current statement.');
            $type=required('type'); if(!in_array($type,['discount','adjustment'],true)) throw new RuntimeException('Invalid adjustment type.');
            $amount=cents('amount',$type==='adjustment'); if(!$amount) throw new RuntimeException('Adjustment cannot be zero.');
            $delta=$type==='discount'?-$amount:$amount;
            if($b['total_due']+$delta<$b['paid']) throw new RuntimeException('The revised total cannot be less than payments already received.');
            query('INSERT INTO billing_adjustments(billing_id,type,amount,reason,created_by) VALUES(?,?,?,?,?)',[$id,$type,$amount,required('reason'),$u['id']]);
            query('UPDATE billing SET total_due=total_due+? WHERE id=?',[$delta,$id]);
            notify((int)$b['tenant_id'],$id,'billing','Billing statement updated','Your revised total is '.money($b['total_due']+$delta).'. Reason: '.required('reason'));
            audit('Adjusted billing statement','billing',$id,required('reason')); break;
        case 'payment':
            require_role(['admin','staff']); $key=required('idempotency_key'); if(one('SELECT id FROM payments WHERE idempotency_key=?',[$key])) break;
            $id=(int)required('billing_id'); $b=bill($id); $amt=cents('amount');
            if(!$b||$b['carried_to']||$amt<=0||$amt>balance($b)) throw new RuntimeException('Payment must be positive and no greater than the current bill balance.');
            $method=required('method'); if(!in_array($method,['GCash','Personal Payment'],true)) throw new RuntimeException('Invalid payment method.');
            $ref=trim($_POST['reference']??''); if($method==='GCash' && $ref==='') throw new RuntimeException('GCash reference is required.');
            $date=date_input('payment_date'); if($date>date('Y-m-d')) throw new RuntimeException('Payment date cannot be in the future.');
            if($method==='GCash' && one("SELECT id FROM payments WHERE method='GCash' AND reference=?",[$ref])) throw new RuntimeException('This GCash reference has already been recorded.');
            if($method==='GCash' && one("SELECT id FROM payment_submissions WHERE reference=? AND status='pending'",[$ref])) throw new RuntimeException('This reference is pending verification. Confirm the existing submission in Payments instead of recording it twice.');
            query('INSERT INTO payments(billing_id,tenant_id,amount,method,payer,payment_date,reference,received_by,idempotency_key,balance_after) VALUES(?,?,?,?,?,?,?,?,?,?)',[$id,$b['tenant_id'],$amt,$method,required('payer'),$date,$ref,$u['id'],$key,balance($b)-$amt]); $pid=(int)db()->lastInsertId();
            notify((int)$b['tenant_id'],$id,'receipt','Payment receipt AD-'.str_pad((string)$pid,6,'0',STR_PAD_LEFT),'Payment received: '.money($amt).' via '.$method.'. Remaining balance: '.money(balance($b)-$amt).'. Reference: '.$ref.'.');
            audit('Recorded payment and generated receipt','payments',$pid); $return='receipt&id='.$pid; break;
        case 'deposit':
            require_role(['admin']); $amt=cents('amount'); if($amt<=0) throw new RuntimeException('Deposit must be positive.');
            $date=date_input('received_date'); if($date>date('Y-m-d')) throw new RuntimeException('Deposit date cannot be in the future.');
            query('INSERT INTO deposits(tenant_id,amount,received_date,recorded_by) VALUES(?,?,?,?)',[(int)required('tenant_id'),$amt,$date,$u['id']]); audit('Recorded security deposit','deposits',(int)db()->lastInsertId()); break;
        case 'refund':
            require_role(['admin']); $id=(int)required('deposit_id'); $d=one('SELECT d.*,t.status FROM deposits d JOIN tenants t ON t.id=d.tenant_id WHERE d.id=?',[$id]); $amt=cents('refund_amount');
            if(!$d || $d['refunded_date'] || $d['status']!=='moved_out'||$amt>$d['amount']) throw new RuntimeException('Refund requires a moved-out tenant and cannot exceed an unsettled deposit.');
            $date=date_input('refunded_date'); $t=one('SELECT move_out FROM tenants WHERE id=?',[$d['tenant_id']]);
            if($date<$t['move_out']||$date>date('Y-m-d')) throw new RuntimeException('Refund date must fall between move-out and today.');
            query('UPDATE deposits SET refund_amount=?,deduction_reason=?,refunded_date=? WHERE id=?',[$amt,required('deduction_reason'),$date,$id]); audit('Settled security deposit','deposits',$id,required('deduction_reason')); break;
        case 'scan':
            require_role(['admin']); $id=(int)required('room_id'); $r=one('SELECT * FROM rooms WHERE id=?',[$id]); $count=filter_var($_POST['detected_count']??null,FILTER_VALIDATE_INT);
            if(!$r || $count===false || $count<0 || $count>100) throw new RuntimeException('Invalid room or occupancy count.');
            $source=required('source'); if(!in_array($source,['camera','manual'],true)) throw new RuntimeException('Invalid scan source.');
            $registered=(int)query('SELECT COUNT(*) FROM tenant_assignments a JOIN bed_spaces b ON b.id=a.bed_id WHERE b.room_id=? AND a.end_date IS NULL',[$id])->fetchColumn();
            query('INSERT INTO occupancy_scans(room_id,detected_count,registered_count,capacity,source,notes,scanned_by) VALUES(?,?,?,?,?,?,?)',[$id,$count,$registered,$r['capacity'],$source,trim($_POST['notes']??''),$u['id']]); audit('Recorded occupancy count','occupancy_scans',(int)db()->lastInsertId()); break;
        case 'staff':
            require_role(['admin']); $pw=required('temporary_password'); if(strlen($pw)<10) throw new RuntimeException('Password must have at least 10 characters.');
            query("INSERT INTO users(username,password_hash,role,name) VALUES(?,?,'staff',?)",[required('username'),password_hash($pw,PASSWORD_DEFAULT),required('name')]); audit('Created staff account','users',(int)db()->lastInsertId()); break;
        case 'toggle_user':
            require_role(['admin']); $id=(int)required('user_id'); if($id===$u['id']) throw new RuntimeException('You cannot disable your own account.');
            query('UPDATE users SET active=1-active WHERE id=?',[$id]); audit('Changed account access','users',$id); break;
        case 'reset_password':
            require_role(['admin']); $id=(int)required('user_id'); if($id===$u['id']) throw new RuntimeException('Use your account page to change your own password.');
            $pw=required('temporary_password'); if(strlen($pw)<10) throw new RuntimeException('Password must have at least 10 characters.');
            query('UPDATE users SET password_hash=?,must_change=1 WHERE id=?',[password_hash($pw,PASSWORD_DEFAULT),$id]); audit('Reset account password','users',$id); break;
        case 'reminders':
            require_role(['admin']);
            queue_due_reminders();
            audit('Prepared due-date reminders'); break;
        case 'send_mail':
            require_role(['admin']); db()->commit(); require_once __DIR__.'/mailer.php'; $counts=send_notifications(); flash($counts.' Review delivery status below.'); redirect('notifications');
        default: if(!feature_action($action,$u)) throw new RuntimeException('Unknown action.');
        }
        if($action==='submit_payment') $return='payments';
        db()->commit(); flash($_SESSION['action_message']??'Saved successfully.'); unset($_SESSION['action_message']);
        if(preg_match('/^(receipt|statement)&id=(\d+)$/',$return,$target)) { header('Location: index.php?page='.$target[1].'&id='.(int)$target[2]); exit; }
        redirect($return);
    } catch(Throwable $ex) {
        if(db()->inTransaction()) db()->rollBack();
        if(isset($GLOBALS['new_payment_proof']) && is_file($GLOBALS['new_payment_proof'])) unlink($GLOBALS['new_payment_proof']);
        $msg=$ex instanceof PDOException ? (($ex->errorInfo[1]??0)===1062?'That account, room, billing period, reference, or assignment already exists.':'Database operation failed. No changes were saved.') : $ex->getMessage();
        flash($msg,'error'); redirect($action==='login'?'login':$return);
    }
}
