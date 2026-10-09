<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(403); exit; }
require dirname(__DIR__).'/app/config.php';
$cfg=database_config(); $db=mysql_connection(false);
$db->exec('CREATE DATABASE IF NOT EXISTS `'.$cfg['database'].'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$db->exec('USE `'.$cfg['database'].'`'); $db->exec(file_get_contents(__DIR__.'/schema.sql'));
// Add expense controls to existing installations without replacing records.
$expenseColumns=['vendor'=>"VARCHAR(200) NOT NULL DEFAULT ''",'payment_method'=>"ENUM('Cash','GCash','Bank transfer') NOT NULL DEFAULT 'Cash'",'idempotency_key'=>'VARCHAR(100) UNIQUE NULL','voided_at'=>'DATETIME NULL','voided_by'=>'INT UNSIGNED NULL','void_reason'=>'VARCHAR(1000) NULL'];
foreach($expenseColumns as $column=>$definition) {
    $check=$db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?');$check->execute([$cfg['database'],'expenses',$column]);
    if(!(int)$check->fetchColumn()) $db->exec('ALTER TABLE expenses ADD COLUMN `'.$column.'` '.$definition);
}
$check=$db->prepare("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=? AND TABLE_NAME='expenses' AND CONSTRAINT_NAME='fk_expense_voider'");$check->execute([$cfg['database']]);
if(!(int)$check->fetchColumn())$db->exec('ALTER TABLE expenses ADD CONSTRAINT fk_expense_voider FOREIGN KEY(voided_by) REFERENCES users(id)');
$legacy=dirname(__DIR__).'/storage/dormitory.sqlite';
if(in_array('--migrate-sqlite',$argv,true) && is_file($legacy)) {
    if((int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn()) throw new RuntimeException('Migration refused: the destination contains accounts. Existing MySQL records were not changed.');
    $source=new PDO('sqlite:'.$legacy,null,null,[PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $tables=['tenants','users','rooms','bed_spaces','tenant_assignments','billing','billing_adjustments','utility_charges','payments','deposits','occupancy_scans','notifications','audit_logs','login_attempts','settings'];
    $db->beginTransaction(); $carry=[]; $counts=[];
    try {
        foreach($tables as $table) {
            $data=$source->query('SELECT * FROM `'.$table.'`')->fetchAll();
            foreach($data as $row) {
                if($table==='billing' && $row['carried_to']) { $carry[$row['id']]=$row['carried_to']; $row['carried_to']=null; }
                $columns=array_keys($row); $sql='INSERT INTO `'.$table.'` (`'.implode('`,`',$columns).'`) VALUES ('.implode(',',array_fill(0,count($columns),'?')).')';
                $db->prepare($sql)->execute(array_values($row));
            } $counts[$table]=count($data);
        }
        foreach($carry as $id=>$to) $db->prepare('UPDATE billing SET carried_to=? WHERE id=?')->execute([$to,$id]);
        $db->commit(); echo 'Migrated records: '.json_encode($counts).PHP_EOL;
    } catch(Throwable $e) { $db->rollBack(); throw $e; }
}
$defaults=['seeded'=>'1','business_name'=>"Angelina’s Dormitory",'business_contact'=>'','business_address'=>'','cutoff_day'=>'15','due_day'=>'28','gcash_name'=>'Angelina','gcash_number'=>'','payment_instructions'=>'','office_hours'=>''];
foreach($defaults as $key=>$value) $db->prepare('INSERT INTO settings(`key`,value) VALUES(?,?) ON DUPLICATE KEY UPDATE `key`=VALUES(`key`)')->execute([$key,$value]);
// Bootstrap and seed only after all schema and migration steps finish.
require dirname(__DIR__).'/app/bootstrap.php'; require dirname(__DIR__).'/app/seed.php';
echo 'MySQL database ready: '.$cfg['database'].PHP_EOL;
