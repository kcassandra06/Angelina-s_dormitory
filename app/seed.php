<?php
function seed(): void {
    if (one('SELECT id FROM users LIMIT 1')) return;
    db()->beginTransaction();
    try {
        query('INSERT INTO users(username,password_hash,role,name) VALUES(?,?,?,?)',['admin',password_hash('Admin@12345',PASSWORD_DEFAULT),'admin','Angelina • Administrator']);
        query('INSERT INTO users(username,password_hash,role,name) VALUES(?,?,?,?)',['staff',password_hash('Staff@12345',PASSWORD_DEFAULT),'staff','Payment Staff']);
        foreach ([['101',4,450000],['102',6,500000],['103',4,500000],['104',2,550000]] as $r) {
            query('INSERT INTO rooms(number,capacity,rent) VALUES(?,?,?)',$r); $id=(int)db()->lastInsertId();
            for($i=1;$i<=$r[1];$i++) query('INSERT INTO bed_spaces(room_id,label) VALUES(?,?)',[$id,'Bed '.$i]);
        }
        query('INSERT INTO tenants(name,email,contact,move_in) VALUES(?,?,?,?)',['Maria Santos (Demo)','maria@example.com','0917 000 0000',date('Y-m-01')]); $tid=(int)db()->lastInsertId();
        query('INSERT INTO users(username,password_hash,role,name,tenant_id) VALUES(?,?,?,?,?)',['tenant',password_hash('Tenant@12345',PASSWORD_DEFAULT),'tenant','Maria Santos',$tid]);
        query('INSERT INTO tenant_assignments(tenant_id,bed_id,start_date) VALUES(?,?,?)',[$tid,5,date('Y-m-01')]); $aid=(int)db()->lastInsertId();
        query('INSERT INTO billing(tenant_id,assignment_id,period,cutoff,due_date,rent,electricity,water,total_due,created_by) VALUES(?,?,?,?,?,?,?,?,?,1)',[$tid,$aid,date('Y-m'),date('Y-m-15'),date('Y-m-t'),500000,80000,30000,610000]);
        $bid=(int)db()->lastInsertId();
        query('INSERT INTO utility_charges(billing_id,type,amount) VALUES(?,?,?),(?,?,?)',[$bid,'electricity',80000,$bid,'water',30000]);
        query('INSERT INTO deposits(tenant_id,amount,received_date,recorded_by) VALUES(?,?,?,1)',[$tid,500000,date('Y-m-01')]);
        query("INSERT INTO settings(`key`,value) VALUES('seeded','1') ON DUPLICATE KEY UPDATE value='1'");
        query("INSERT INTO audit_logs(action,details) VALUES('System initialized','Initial rooms and clearly labeled demonstration tenant created.')");
        db()->commit();
    } catch(Throwable $e) { db()->rollBack(); throw $e; }
}
seed();
