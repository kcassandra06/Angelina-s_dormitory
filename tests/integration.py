"""End-to-end checks against a separate disposable database, never live tenant data."""
from pathlib import Path
from urllib.request import build_opener, HTTPCookieProcessor, Request
from urllib.parse import urlencode
from urllib.error import HTTPError
import http.cookiejar
import os
import re
import json
import uuid
import datetime
import base64
import subprocess
import tempfile
import time

ROOT = Path(__file__).resolve().parents[1]
BASE = 'http://localhost:8081/'
PHP = r'C:\xampp\php\php.exe'

class Client:
    def __init__(self):
        self.opener = build_opener(HTTPCookieProcessor(http.cookiejar.CookieJar()))
    def get(self, path=''):
        try:
            with self.opener.open(BASE + path) as r:
                return r.status, r.read().decode(errors='replace')
        except HTTPError as e:
            return e.code, e.read().decode()
    def post(self, action, page='dashboard', **data):
        status, html = self.get('index.php?page=' + page)
        token = re.search(r'name="csrf" value="([^"]+)"', html)
        assert token, (page, status, html[:300])
        payload = {'csrf': token[1], 'action': action, 'return_page': page, **data}
        try:
            with self.opener.open(Request(BASE+'index.php', urlencode(payload).encode())) as r:
                html = r.read().decode()
                assert 'Warning:' not in html and 'Fatal error' not in html, html[:400]
                return html
        except HTTPError as e:
            return e.read().decode()
    def login(self, role, password):
        return self.post('login', 'login', username=role, role=role, password=password)
    def upload(self, action, page, filename, content, upload_field="proof", **data):
        code, html = self.get('index.php?page='+page)
        token = re.search(r'name="csrf" value="([^"]+)"', html)[1]
        boundary = 'TestBoundary'+uuid.uuid4().hex
        payload = {'csrf':token,'action':action,'return_page':page,**data}
        body = b''
        for name,value in payload.items():
            body += ('--'+boundary+'\r\nContent-Disposition: form-data; name="'+name+'"\r\n\r\n'+str(value)+'\r\n').encode()
        body += ('--'+boundary+'\r\nContent-Disposition: form-data; name="'+upload_field+'"; filename="'+filename+'"\r\nContent-Type: application/octet-stream\r\n\r\n').encode()+content+b'\r\n'
        body += ('--'+boundary+'--\r\n').encode()
        with self.opener.open(Request(BASE+'index.php',body,{'Content-Type':'multipart/form-data; boundary='+boundary})) as r:
            return r.read().decode()

class MysqlTestDatabase:
    def __init__(self, env):
        self.env = env
        self.result = []
    def execute(self, sql, params=()):
        result = subprocess.run([PHP, 'tests/db.php'], input=json.dumps({'sql':sql, 'params':list(params)}), cwd=ROOT,env=self.env,capture_output=True,text=True,check=True)
        def native(v):
            return int(v) if isinstance(v,str) and re.fullmatch(r'-?\d+',v) else v
        self.result = [tuple(native(v) for v in row) for row in json.loads(result.stdout)]
        return self
    def fetchone(self): return self.result[0] if self.result else None
    def fetchall(self): return self.result
    def commit(self): pass
    def close(self): pass

with tempfile.TemporaryDirectory(prefix='dormitory-test-',dir=ROOT/'storage') as temp:
    dbname = 'angelinas_test_' + uuid.uuid4().hex[:12]
    env = {**os.environ, 'DORMITORY_DB_NAME':dbname}
    subprocess.run([PHP,'database/setup.php'],cwd=ROOT,env=env,check=True,capture_output=True,text=True)
    log = open(Path(temp)/'server.log','w+')
    server = subprocess.Popen([PHP,'-d','upload_tmp_dir='+temp,'-S','localhost:8081','router.php'],cwd=ROOT,env=env,stdout=log,stderr=log,creationflags=subprocess.CREATE_NO_WINDOW)
    con = None
    try:
        admin = Client()
        for attempt in range(30):
            try:
                code, html = admin.get()
                if code == 200: break
            except OSError: pass
            time.sleep(.1)
        assert 'Manage Your Dormitory' in html
        assert 'incorrect' in admin.login('admin','wrong-password')
        assert 'Set a new personal password' in admin.login('admin','Admin@12345')
        assert 'at least 10' in admin.post('password','account', current_password='Admin@12345',new_password='short',confirm_password='short')
        admin.post('password','account',current_password='Admin@12345',new_password='AdminTesting@2026',confirm_password='AdminTesting@2026')
        for page in ['dashboard','tenants','rooms','billing','payments','deposits','occupancy','notifications','audit','accounts','account','statement&id=1','batch','reports','expenses','requests','announcements','settings','ledger']:
            code, html = admin.get('index.php?page='+page)
            assert code == 200 and 'Warning:' not in html and 'Fatal error' not in html, (page,code,html[:200])
        html=admin.get('index.php?page=dashboard')[1]
        assert all(label in html for label in ['Collected this month','Outstanding balance','Overdue statements','Pending payments','Active residents','Occupied beds','Available beds','Occupancy rate'])
        for page,selected in [('batch','billing'),('expenses','reports'),('ledger','reports'),('notifications','settings')]:
            body=admin.get('index.php?page='+page)[1]
            assert re.search(r'class="selected" href="\?page='+selected+r'"',body),page
        directory=admin.get('index.php?page=tenants')[1]
        assert all(label in directory for label in ['Current balance','Payment status','Move-in date','Billing History'])
        con = MysqlTestDatabase(env)
        visitor=Client()
        assert 'Manage Your Dormitory' in visitor.get()[1]
        assert visitor.get('index.php?page=privacy')[0]==200
        assert visitor.get('index.php?page=terms')[0]==200
        contact=visitor.post('public_contact','home',name='Website Visitor',email='visitor@example.com',message='A question about room availability.',website='')
        assert 'received by the dormitory office' in contact
        assert 'Website Visitor' in admin.get('index.php?page=support')[1]
        assert 'New website inquiry' in admin.get('index.php?page=inbox')[1]
        before=con.execute("SELECT COUNT(*) FROM audit_logs WHERE record_type='public_contact'").fetchone()[0]
        visitor.post('public_contact','home',name='Invalid',email='invalid',message='Hello')
        assert con.execute("SELECT COUNT(*) FROM audit_logs WHERE record_type='public_contact'").fetchone()[0]==before

        today = time.strftime('%Y-%m-%d')
        html = admin.post('payment','payments',billing_id=1,payer='Parent Santos',amount='7000',method='GCash',payment_date=today,reference='TOO-MUCH',idempotency_key='overpayment')
        assert 'no greater' in html
        assert con.execute('SELECT COUNT(*) FROM payments').fetchone()[0] == 0
        html = admin.post('payment','payments',billing_id=1,payer='Parent Santos',amount='5000',method='GCash',payment_date=today,reference='GC-123',idempotency_key='first-payment')
        assert 'Payment confirmation' in html and '₱5,000.00' in html
        admin.post('payment','payments',billing_id=1,payer='Parent Santos',amount='5000',method='GCash',payment_date=today,reference='GC-123',idempotency_key='first-payment')
        assert con.execute('SELECT COUNT(*) FROM payments').fetchone()[0] == 1
        con.execute("UPDATE billing SET due_date='2020-01-01' WHERE id=1"); con.commit()
        assert 'Overdue' in admin.get('index.php?page=statement&id=1')[1]
        admin.post('reminders','notifications')
        admin.post('reminders','notifications')
        assert con.execute("SELECT COUNT(*) FROM notifications WHERE type='overdue'").fetchone()[0] == 1
        html = admin.post('payment','payments',billing_id=1,payer='Parent Santos',amount='100',method='GCash',payment_date=today,reference='GC-123',idempotency_key='duplicate-reference')
        assert 'already been recorded' in html
        current = con.execute('SELECT period FROM billing WHERE id=1').fetchone()[0]
        y,m = map(int,current.split('-')); nxt = f'{y+1 if m==12 else y:04d}-{1 if m==12 else m+1:02d}'
        admin.post('bill','billing',tenant_id=1,period=nxt,cutoff=nxt+'-15',due_date=nxt+'-28',rent='5000',electricity='800',water='300',discount='200',discount_reason='Scholarship',adjustment='500',adjustment_reason='Additional dorm charge')
        latest = con.execute('SELECT id,previous_balance,total_due FROM billing ORDER BY id DESC LIMIT 1').fetchone()
        assert latest == (2,110000,750000), latest
        assert con.execute('SELECT carried_to FROM billing WHERE id=1').fetchone()[0] == 2
        html = admin.post('payment','payments',billing_id=1,payer='Parent',amount='100',method='Personal Payment',payment_date=today,reference='',idempotency_key='old-bill')
        assert 'no greater' in html
        admin.post('payment','payments',billing_id=2,payer='Parent',amount='3000',method='Personal Payment',payment_date=today,reference='',idempotency_key='second-payment')
        admin.post('payment','payments',billing_id=2,payer='Parent',amount='2000',method='Personal Payment',payment_date=today,reference='',idempotency_key='third-payment')
        assert con.execute('SELECT SUM(amount) FROM payments WHERE billing_id=2').fetchone()[0] == 500000
        admin.post('adjust_bill','billing',billing_id=2,type='discount',amount='300',reason='Courtesy discount')
        assert con.execute('SELECT total_due FROM billing WHERE id=2').fetchone()[0] == 720000
        admin.post('payment','payments',billing_id=2,payer='Parent',amount='2200',method='Personal Payment',payment_date=today,reference='',idempotency_key='final-payment')
        assert 'Paid' in admin.get('index.php?page=statement&id=2')[1]
        html=admin.post('adjust_bill','billing',billing_id=2,type='discount',amount='1',reason='Invalid below paid')
        assert 'less than payments' in html
        staff=Client(); staff.login('staff','Staff@12345'); staff.post('password','account',current_password='Staff@12345',new_password='StaffTesting@2026',confirm_password='StaffTesting@2026')
        assert staff.get('index.php?page=tenants')[0]==403
        assert 'do not have access' in staff.post('room','payments',number='BLOCKED',capacity=4,rent=1000)
        tenant=Client(); tenant.login('tenant','Tenant@12345'); tenant.post('password','account',current_password='Tenant@12345',new_password='TenantTesting@2026',confirm_password='TenantTesting@2026')
        assert tenant.get('index.php?page=accounts')[0]==403
        assert tenant.get('index.php?page=receipt&id=1')[0]==200
        paid_home=tenant.get('index.php?page=dashboard')[1]
        assert '<h2>\u20b17,200.00</h2>' in paid_home
        assert 'Your account is settled' in paid_home
        assert 'View Bill Details' in paid_home and 'Pay Now' not in paid_home
        assert 'id="room-details"' in tenant.get('index.php?page=profile')[1]

        assert 'do not have access' in tenant.post('payment','payments',billing_id=2,amount=1)
        tenant.post('request','requests',title='Leaking tap',description='Water drips from the room sink.',priority='urgent')
        assert con.execute('SELECT tenant_id,room_id,status FROM maintenance_requests WHERE id=1').fetchone()==(1,2,'open')
        admin.post('request_update','requests',request_id=1,status='resolved',admin_note='Tap replaced.')
        assert 'Tap replaced.' in tenant.get('index.php?page=requests')[1]
        admin.post('announcement','announcements',title='Water schedule',message='Water service resumes at 5 PM.',expires_on='')
        assert 'Water schedule' in tenant.get('index.php?page=announcements')[1]
        assert 'Water schedule' in tenant.get('index.php?page=dashboard')[1]
        assert 'Water schedule' in staff.get('index.php?page=announcements')[1]
        admin.post('expense','expenses',category='Maintenance',description='=SUM(1,2)',amount=500,expense_date=today,reference='INV-1')
        assert con.execute('SELECT amount FROM expenses').fetchone()==(50000,)
        assert '₱500.00' in admin.get('index.php?page=reports')[1]
        assert "'=SUM(1,2)" in admin.get('index.php?export=expenses')[1]
        assert tenant.get('index.php?export=collections')[0]==403
        admin.post('scan','occupancy',room_id=2,detected_count=4,source='manual',notes='Verify count')
        assert con.execute('SELECT detected_count,registered_count,capacity FROM occupancy_scans').fetchone()==(4,1,6)
        admin.post('moveout','tenants',tenant_id=1,move_out=today)
        assert con.execute('SELECT status FROM tenants WHERE id=1').fetchone()[0]=='moved_out'
        assert con.execute('SELECT COUNT(*) FROM tenant_assignments WHERE end_date IS NULL').fetchone()[0]==0
        admin.post('refund','deposits',deposit_id=1,refund_amount=4500,refunded_date=today,deduction_reason='500 cleaning deduction')
        assert con.execute('SELECT refund_amount FROM deposits WHERE id=1').fetchone()[0]==450000
        new_registration=dict(name='Another Tenant',email='other@example.com',contact='09170000001',move_in=today,bed_id=5,username='another',temporary_password='OtherTesting@2026',confirm_password='OtherTesting@2026')
        assert 'do not match' in admin.post('tenant','accounts',**{**new_registration,'confirm_password':'Different@2026'})
        assert con.execute('SELECT COUNT(*) FROM tenants').fetchone()[0]==1
        assert 'already taken' in admin.post('tenant','accounts',**{**new_registration,'username':'admin'})
        assert '3–50 characters' in admin.post('tenant','accounts',**{**new_registration,'username':'bad username'})
        assert 'do not have access' in staff.post('tenant','payments',**new_registration)
        assert 'do not have access' in tenant.post('tenant_account','billing',tenant_id=1,username='blocked',temporary_password='BlockedTesting@2026',confirm_password='BlockedTesting@2026')
        assert 'Tenant registered and account created' in admin.post('tenant','accounts',**new_registration)
        assert con.execute('SELECT COUNT(*) FROM tenants').fetchone()[0]==2
        other=Client(); other.post('login','login',username='another',role='tenant',password='OtherTesting@2026'); other.post('password','account',current_password='OtherTesting@2026',new_password='OtherChanged@2026',confirm_password='OtherChanged@2026')
        assert other.get('index.php?page=statement&id=1')[0]==404
        assert other.get('index.php?page=receipt&id=1')[0]==404
        assert 'Maria Santos' not in other.get('index.php?page=billing')[1]
        assert 'Leaking tap' not in other.get('index.php?page=requests')[1]
        assert 'Leaking tap' not in other.post('request_update','requests',request_id=1,status='open',admin_note='Unauthorized')
        assert con.execute('SELECT COUNT(*) FROM payments').fetchone()[0]==4
        admin.post('bill','billing',tenant_id=2,period=current,cutoff=current+'-15',due_date=current+'-28',rent=5000,electricity=0,water=0,discount=0,adjustment=0)
        staff.post('payment','payments',billing_id=3,payer='New Tenant',amount=1000,method='Personal Payment',payment_date=today,reference='',idempotency_key='staff-received')
        assert con.execute('SELECT amount,received_by,tenant_id FROM payments WHERE billing_id=3').fetchone()==(100000,2,2)
        assert 'Another Tenant' in other.get('index.php?page=receipt&id=5')[1]
        assert 'Balance when payment was received' in other.get('index.php?page=receipt&id=5')[1]
        assert 'do not have access' in staff.post('void_payment','payments',payment_id=5,reason='Staff cannot void')
        admin.post('void_payment','payments',payment_id=5,reason='Incorrect amount entered')
        assert con.execute('SELECT void_reason FROM payments WHERE id=5').fetchone()[0]=='Incorrect amount entered'
        assert 'Voided' in other.get('index.php?page=receipt&id=5')[1]
        assert '₱5,000.00' in other.get('index.php?page=ledger&tenant_id=1')[1]
        assert 'AD-5' not in admin.get('index.php?export=collections')[1]
        staff.post('payment','payments',billing_id=3,payer='New Tenant',amount=1000,method='Personal Payment',payment_date=today,reference='',idempotency_key='corrected-staff-payment')
        assert 'AD-6' in admin.get('index.php?export=collections')[1]
        admin.post('settings','settings',business_name='Test Residence',business_contact='09170000000',business_address='Test street',cutoff_day=15,due_day=31)
        assert 'Test Residence' in other.get('index.php?page=receipt&id=6')[1]
        html=admin.post('batch_billing','batch',period=nxt,cutoff=nxt+'-15',due_date=nxt+'-28',**{'selected[]':2,'electricity[2]':'125.50','water[2]':'80'})
        assert '1 statements created' in html
        newbill=con.execute('SELECT previous_balance,total_due,electricity,water FROM billing WHERE tenant_id=2 AND period=?',[nxt]).fetchone()
        assert newbill==(400000,920550,12550,8000),newbill
        html=admin.post('batch_billing','batch',period=nxt,cutoff=nxt+'-15',due_date=nxt+'-28',**{'selected[]':2,'electricity[2]':'125.50','water[2]':'80'})
        assert '0 statements created. 1 skipped' in html
        assert 'carried forward' in admin.post('void_payment','payments',payment_id=6,reason='Cannot change a carried bill')
        assert 'Test Residence' in other.get('index.php?page=ledger')[1]
        admin.post('send_mail','notifications')
        assert con.execute("SELECT COUNT(*) FROM notifications WHERE status='sent'").fetchone()[0]==0
        for path in ['storage/dormitory.sqlite','app/bootstrap.php','database/schema.sql','config.example.php','tests/integration.py','%2e%2e/config.local.php']:
            assert admin.get(path)[0]==403, path
        req=Request(BASE+'index.php',urlencode({'action':'room','number':'CSRF','capacity':4,'rent':1000}).encode())
        try: admin.opener.open(req); raise AssertionError('Missing CSRF was accepted')
        except HTTPError as ex: assert ex.code==419
        assert con.execute("SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=?",[dbname]).fetchone()[0]>15
        try:
            con.execute("INSERT INTO tenant_assignments(tenant_id,bed_id,start_date) VALUES(2,5,?)",[today])
            raise AssertionError('Duplicate active assignment was accepted')
        except subprocess.CalledProcessError: pass
        try:
            con.execute("INSERT INTO bed_spaces(room_id,label) VALUES(999999,'invalid')")
            raise AssertionError('Foreign key violation was accepted')
        except subprocess.CalledProcessError: pass
        assert con.execute('SELECT COUNT(*) FROM audit_logs').fetchone()[0]>15
        assert 'already has an account' in admin.post('tenant_account','accounts',tenant_id=2,username='duplicated',temporary_password='DuplicateTesting@2026',confirm_password='DuplicateTesting@2026')
        con.execute('INSERT INTO tenants(name,email,contact,move_in) VALUES(?,?,?,?)',['Existing Resident','existing@example.com','09171111111',today])
        existing_id=con.execute('SELECT id FROM tenants WHERE email=?',['existing@example.com']).fetchone()[0]
        assert 'Existing Resident' in admin.get('index.php?page=tenants')[1]
        assert 'Create login for an existing tenant' not in admin.get('index.php?page=accounts')[1]
        assert 'Tenant account created' in admin.post('tenant_account','tenants',tenant_id=existing_id,username='existing.resident',temporary_password='ExistingTesting@2026',confirm_password='ExistingTesting@2026')
        assert con.execute('SELECT tenant_id,must_change FROM users WHERE username=?',['existing.resident']).fetchone()==(existing_id,1)
        existing=Client()
        assert 'Set a new personal password' in existing.post('login','login',username='existing.resident',role='tenant',password='ExistingTesting@2026')
        assert con.execute('SELECT COUNT(*) FROM tenants').fetchone()[0]==3
        assert con.execute('SELECT COUNT(*) FROM users WHERE tenant_id=?',[existing_id]).fetchone()[0]==1
        signup=Client()
        assert 'Get Started' in signup.get()[1]
        assert signup.get('index.php?page=register')[0]==200
        details=dict(name='Self Registered',email='signup@example.com',contact='09172222222',username='self.registered',temporary_password='PersonalTesting@2026',confirm_password='PersonalTesting@2026')
        before=con.execute('SELECT COUNT(*) FROM tenants').fetchone()[0]
        assert 'do not match' in signup.post('register','register',**{**details,'confirm_password':'Different@2026'})
        assert con.execute('SELECT COUNT(*) FROM tenants').fetchone()[0]==before
        assert 'already taken' in signup.post('register','register',**{**details,'username':'admin'})
        assert 'already has a tenant record' in signup.post('register','register',**{**details,'email':'maria@example.com'})
        assert 'Your tenant account is ready' in signup.post('register','register',**details,role='admin',tenant_id=1,active=0)
        new_id=con.execute('SELECT tenant_id FROM users WHERE username=?',['self.registered']).fetchone()[0]
        assert con.execute('SELECT role,must_change,active FROM users WHERE username=?',['self.registered']).fetchone()==('tenant',0,1)
        assert con.execute('SELECT COUNT(*) FROM tenant_assignments WHERE tenant_id=?',[new_id]).fetchone()[0]==0
        assert 'Not assigned yet' in signup.post('login','login',username='self.registered',role='tenant',password='PersonalTesting@2026')
        assert signup.get('index.php?page=accounts')[0]==403
        assert 'Your room must be assigned' in signup.get('index.php?page=requests')[1]
        assert 'value="request"' not in signup.get('index.php?page=requests')[1]
        assert signup.get('index.php?page=statement&id=1')[0]==404
        bed_screen=admin.get('index.php?page=rooms&bed_id=1')[1]
        assert 'SELECTED BED' in bed_screen and 'Assign a resident' in bed_screen and 'value="1" selected' in bed_screen
        assert 'Assign room' in admin.get('index.php?page=tenants')[1]
        admin.post('assign_tenant','rooms',tenant_id=new_id,bed_id=1,move_in=today)
        assert 'Occupied by' in admin.get('index.php?page=rooms&bed_id=1')[1]
        admin.post('assign_tenant','rooms',tenant_id=2,bed_id=1,move_in=today)
        assert con.execute('SELECT COUNT(*) FROM tenant_assignments WHERE bed_id=1 AND end_date IS NULL').fetchone()[0]==1
        assert con.execute('SELECT bed_id FROM tenant_assignments WHERE tenant_id=?',[new_id]).fetchone()[0]==1
        assert 'Room 101' in signup.get('index.php?page=dashboard')[1]
        # Complete tenant-to-staff payment journey on the second tenant's current bill.
        current_bill=con.execute('SELECT id FROM billing WHERE tenant_id=2 AND period=?',[nxt]).fetchone()[0]
        assert 'Pay Now' in other.get('index.php?page=dashboard')[1]
        for label in ['CURRENT STATEMENT','YOUR NEXT STEP','MY ROOM','RECENT PAYMENTS','NOTICE BOARD','Move-in date']:
            assert label in other.get('index.php?page=dashboard')[1]
        assert 'Payment verification' not in admin.get('index.php?page=dashboard')[1]
        assert 'Incoming tenant payments' in admin.get('index.php?page=payments')[1]
        assert 'Incoming tenant payments' in staff.get('index.php?page=payments')[1]

        # Due notices are scoped, deduplicated, and available without SMTP.
        original_due=con.execute('SELECT due_date FROM billing WHERE id=?',[current_bill]).fetchone()[0]
        previous_notices=con.execute("SELECT COUNT(*) FROM notifications WHERE billing_id=? AND dedup_key LIKE 'due-%'",[current_bill]).fetchone()[0]
        for delta,subject in [(7,'Upcoming payment due'),(3,'Upcoming payment due'),(1,'Upcoming payment due'),(0,'Payment due today'),(-1,'Payment overdue')]:
            due=(datetime.date.today()+datetime.timedelta(days=delta)).isoformat()
            con.execute('UPDATE billing SET due_date=? WHERE id=?',[due,current_bill])
            other.get('index.php?page=inbox')
            count=con.execute("SELECT COUNT(*) FROM notifications WHERE billing_id=? AND dedup_key LIKE 'due-%'",[current_bill]).fetchone()[0]
            assert count==previous_notices+1
            previous_notices=count
            assert subject in other.get('index.php?page=inbox')[1]
            assert con.execute("SELECT COUNT(*) FROM notifications WHERE billing_id=? AND dedup_key LIKE 'due-%'",[current_bill]).fetchone()[0]==count
        con.execute('UPDATE billing SET due_date=? WHERE id=?',[original_due,current_bill])
        prepared=other.get('index.php?page=pay')[1]
        assert 'value="9205.50"' in prepared,prepared[-2500:]
        assert 'Paano' not in prepared
        report=admin.get('index.php?page=reports')[1]
        for chart in ['Monthly collections','Payment methods','Current statement status','Outstanding balances by age']:
            assert chart in report
        assert 'Payment activity' in staff.get('index.php?page=dashboard')[1]

        assert 'not configured yet' in other.get('index.php?page=pay')[1]
        admin.post('payment_settings','settings',gcash_name='Test Receiving Account',gcash_number='09171234567',office_hours='Monday to Friday, 9 AM–5 PM',payment_instructions='Include your tenant name in the payment note.')
        assert '09171234567' in other.get('index.php?page=pay')[1]
        submission=dict(billing_id=current_bill,amount='2500',payer='Test Parent',payment_date=today,reference='TENANT-GCASH-1',submission_key='tenant-submit-one',notes='First partial payment')
        assert 'one of your own' in tenant.post('submit_payment','pay',**submission)
        assert 'Payment submitted for verification' in other.post('submit_payment','pay',**submission)
        assert con.execute('SELECT COUNT(*) FROM payments WHERE billing_id=?',[current_bill]).fetchone()[0]==0
        assert 'already in your history' in other.post('submit_payment','pay',**submission)
        assert con.execute('SELECT COUNT(*) FROM payment_submissions').fetchone()[0]==1
        assert 'after pending submissions' in other.post('submit_payment','pay',**{**submission,'amount':'8000','reference':'OVER-PENDING','submission_key':'over-pending'})
        assert 'pending verification' in staff.post('payment','payments',billing_id=current_bill,amount=2500,payer='Test Parent',method='GCash',payment_date=today,reference='TENANT-GCASH-1',idempotency_key='manual-duplicate-submission')
        y2,m2=map(int,nxt.split('-')); later=f'{y2+1 if m2==12 else y2:04d}-{1 if m2==12 else m2+1:02d}'
        assert 'pending payment submissions' in admin.post('bill','billing',tenant_id=2,period=later,cutoff=later+'-15',due_date=later+'-28',rent=5000,electricity=0,water=0,discount=0,adjustment=0)
        assert 'verified receipt of the funds' in staff.post('review_submission','payments',submission_id=1,decision='approved',review_note='Checked')
        assert 'Explain why' in staff.post('review_submission','payments',submission_id=1,decision='rejected',review_note='')
        staff.post('review_submission','payments',submission_id=1,decision='rejected',review_note='Please provide the screenshot to verify the reference.')
        assert 'Please provide the screenshot' in other.get('index.php?page=payments')[1]
        assert con.execute('SELECT COUNT(*) FROM payments WHERE billing_id=?',[current_bill]).fetchone()[0]==0
        assert 'valid JPG, PNG, or WebP' in other.upload('submit_payment','pay','proof.png',b'<?php echo "bad"; ?>',**{**submission,'submission_key':'invalid-proof'})
        png=base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jWZQAAAAASUVORK5CYII=')
        assert 'Payment submitted for verification' in other.upload('submit_payment','pay','proof.png',png,**{**submission,'submission_key':'corrected-proof'})
        admin.upload('payment_settings','settings','official-qr.png',png,upload_field='gcash_qr',gcash_name='Angelina',gcash_number='09171234567')
        assert 'SCAN PAYMENT QR' in other.get('index.php?page=pay')[1]
        assert other.get('index.php?payment_qr=1')[0]==200
        assert 'valid JPG, PNG, or WebP' in admin.upload('payment_settings','settings','bad.png',b'bad',upload_field='gcash_qr',gcash_name='Angelina',gcash_number='09171234567')
        qrfile=con.execute("SELECT value FROM settings WHERE `key`='gcash_qr'").fetchone()[0]
        admin.post('payment_settings','settings',gcash_name='Angelina',gcash_number='',remove_qr='1')
        assert other.get('index.php?payment_qr=1')[0]==404
        (ROOT/'storage'/'payment-proofs'/qrfile).unlink()

        assert con.execute('SELECT status FROM payment_submissions WHERE id=2').fetchone()[0]=='pending'
        assert other.get('index.php?proof=2')[0]==200
        assert tenant.get('index.php?proof=2')[0]==404
        assert staff.get('index.php?proof=2')[0]==200
        assert 'do not have access' in other.post('review_submission','pay',submission_id=2,decision='approved',verified=1)
        assert 'Payment verified' in staff.post('review_submission','payments',submission_id=2,decision='approved',verified=1,review_note='Funds verified in test account.')
        pid=con.execute('SELECT payment_id FROM payment_submissions WHERE id=2').fetchone()[0]
        assert con.execute('SELECT amount,balance_after,received_by FROM payments WHERE id=?',[pid]).fetchone()==(250000,670550,2)
        for viewer in [admin,staff,other]:
            assert 'TENANT-GCASH-1' in viewer.get('index.php?page=payments')[1]
            assert '6,705.50' in viewer.get('index.php?page=statement&id='+str(current_bill))[1]
        assert 'already reviewed' in staff.post('review_submission','payments',submission_id=2,decision='approved',verified=1)
        assert con.execute('SELECT COUNT(*) FROM payments WHERE billing_id=?',[current_bill]).fetchone()[0]==1
        assert 'Payment confirmation' in other.get('index.php?page=receipt&id='+str(pid))[1]
        assert 'GCash payment verified' in other.get('index.php?page=inbox')[1]
        inbox=other.get('index.php?page=inbox')[1]
        assert 'href="?page=receipt&amp;id='+str(pid)+'"' in inbox
        for viewer in [admin,staff,other]:
            assert 'notification-bell' in viewer.get('index.php?page=dashboard')[1]
            assert viewer.get('index.php?page=inbox')[0]==200
            assert json.loads(viewer.get('index.php?activity_poll=1')[1])['unread']>0
            viewer.post('read_notifications','inbox',seen_through=json.loads(viewer.get('index.php?activity_poll=1')[1])['latest'])
            assert json.loads(viewer.get('index.php?activity_poll=1')[1])['unread']==0
        assert tenant.get('index.php?page=receipt&id='+str(pid))[0]==404

        assert 'GCash payment verified' not in tenant.get('index.php?page=inbox')[1]
        statement=admin.get('index.php?page=statement&id='+str(current_bill))[1]
        assert 'STATEMENT OF ACCOUNT' in statement and 'Payments applied' in statement
        receipt=other.get('index.php?page=receipt&id='+str(pid))[1]
        assert 'PAYMENT RECEIPT' in receipt and 'Transaction reference' in receipt
        # Operating expense idempotency and audited correction preserve the original.
        expense=dict(category='Supplies',description='Replacement bulbs',amount='123.45',expense_date=today,reference='INV-TEST',vendor='Test Hardware',payment_method='GCash',idempotency_key='expense-test')
        admin.post('expense','expenses',**expense);admin.post('expense','expenses',**expense)
        expid=con.execute("SELECT id FROM expenses WHERE idempotency_key='expense-test'").fetchone()[0]
        assert con.execute("SELECT COUNT(*) FROM expenses WHERE idempotency_key='expense-test'").fetchone()[0]==1
        assert 'Test Hardware' in admin.get('index.php?page=expenses')[1]
        assert 'not have access' in staff.post('void_expense','payments',expense_id=expid,reason='Not authorized')
        admin.post('void_expense','expenses',expense_id=expid,reason='Invoice duplicated in paperwork')
        assert con.execute('SELECT void_reason FROM expenses WHERE id=?',[expid]).fetchone()[0]=='Invoice duplicated in paperwork'
        assert 'Replacement bulbs' not in admin.get('index.php?export=expenses')[1]
        assert 'Voided' in admin.get('index.php?page=expenses')[1]

        other.post('submit_payment','pay',**{**submission,'amount':'500','reference':'CANCEL-GCASH','submission_key':'cancel-test'})
        assert 'Only your own pending' in tenant.post('cancel_submission','payments',submission_id=3)
        other.post('cancel_submission','payments',submission_id=3)
        assert con.execute('SELECT status FROM payment_submissions WHERE id=3').fetchone()[0]=='cancelled'
        assert 'already reviewed or cancelled' in staff.post('review_submission','payments',submission_id=3,decision='approved',verified=1)
        other.post('inquiry','support',category='Billing',subject='Question about electricity',message='Please explain the electricity charge.')
        assert 'Question about electricity' in staff.get('index.php?page=support')[1]
        assert 'Question about electricity' not in tenant.get('index.php?page=support')[1]
        staff.post('reply_inquiry','support',inquiry_id=1,reply='The electricity charge is shown separately in your statement.')
        assert 'shown separately' in other.get('index.php?page=support')[1]
        assert 'shown separately' in other.get('index.php?page=inbox')[1]
        other.post('profile_update','profile',name='Updated Resident',email='updated@example.com',contact='09173333333',tenant_id=1)
        assert con.execute('SELECT name,email FROM tenants WHERE id=2').fetchone()==('Updated Resident','updated@example.com')
        assert con.execute('SELECT name FROM tenants WHERE id=1').fetchone()[0]=='Maria Santos (Demo)'
        assert 'linked to another tenant' in other.post('profile_update','profile',name='Updated Resident',email='maria@example.com',contact='09173333333')
        for page in ['pay','payments','inbox','support','profile','dashboard']:
            code,body=other.get('index.php?page='+page)
            assert code==200 and 'Warning:' not in body and 'Fatal error' not in body,(page,body[:200])
        # Notices can be corrected and archived without replacing their identity.
        admin.post('announcement','announcements',title='Notice edit test',message='Original notice',expires_on='')
        notice_id=con.execute('SELECT id FROM announcements WHERE title=?',['Notice edit test']).fetchone()[0]
        admin.post('edit_announcement','announcements',announcement_id=notice_id,title='Updated notice',message='Corrected information',expires_on='',active='1')
        assert con.execute('SELECT title,message,active FROM announcements WHERE id=?',[notice_id]).fetchone()==('Updated notice','Corrected information',1)
        admin.post('edit_announcement','announcements',announcement_id=notice_id,title='Updated notice',message='Corrected information',expires_on='',active='0')
        assert 'Updated notice' not in tenant.get('index.php?page=announcements')[1]
        con.close()
        print('PASS: MySQL core flows plus tenant onboarding, payment guide, GCash submission, validated private proof uploads, staff approval/rejection, receipt and balance updates, duplicate/overpayment/carry protection, cancellation, tenant inbox, inquiries, profile updates, and tenant privacy.')
    finally:
        if con:
            for (filename,) in con.execute('SELECT proof_file FROM payment_submissions WHERE proof_file IS NOT NULL').fetchall():
                if re.fullmatch(r'[a-f0-9]{48}\.(png|jpg|webp)',filename):
                    artifact=(ROOT/'storage'/'payment-proofs'/filename).resolve()
                    assert artifact.parent==(ROOT/'storage'/'payment-proofs').resolve()
                    artifact.unlink(missing_ok=True)
        if con: con.close()
        server.terminate(); server.wait(timeout=10); log.close()
        subprocess.run([PHP,'tests/db.php','--drop-test-database'],cwd=ROOT,env=env,check=True)
