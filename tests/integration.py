"""Standard-library HTTP regression suite. Uses a running PHP server and isolated MySQL DB.
Set GLOBETREK_TEST=1 and DB_* to the same database used by the server. See README.
"""
import datetime as dt
import html
import http.cookiejar
import json
import os
from pathlib import Path
import re
import subprocess
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[1]
BASE = os.environ.get('TEST_BASE_URL', 'http://127.0.0.1:8080')
PHP = os.environ.get('PHP_BIN', 'php')
assert os.environ.get('GLOBETREK_TEST') == '1', 'Set GLOBETREK_TEST=1 for your isolated test database.'
fixture = json.loads(subprocess.check_output([PHP, str(ROOT / 'tests/fixtures.php')], env=os.environ))
checks = 0

def check(condition, message):
    global checks
    assert condition, message
    checks += 1
    print('PASS:', message)

class Client:
    def __init__(self):
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

    def request(self, path, data=None):
        body = urllib.parse.urlencode(data).encode() if data is not None else None
        try:
            response = self.opener.open(BASE + path, body, timeout=15)
        except urllib.error.HTTPError as error:
            response = error
        return response.status, response.read().decode(), response.geturl(), response.headers

    def get(self, path):
        return self.request(path)

    def post(self, path, data, form=None):
        form = self.get(path)[1] if form is None else form
        return self.request(path, dict(data, csrf=token(form)))

def token(body, name='csrf'):
    found = re.search(r'name="' + name + r'" value="([^"]+)"', body)
    assert found, f'Missing form token {name}'
    return html.unescape(found.group(1))

def login(role):
    client = Client()
    result = client.post('/login.php', fixture[role])
    check(result[0] == 200 and result[2].endswith('/dashboard.php'), role + ' login')
    return client

guest = Client()
for path in ['/', '/packages.php', '/about.php', '/contact.php', '/login.php', '/register.php']:
    check(guest.get(path)[0] == 200, 'public page ' + path)
check(guest.get('/package-details.php?id=999999999')[0] == 404, 'unknown package is 404')
check(guest.get('/dashboard.php')[2].endswith('/login.php'), 'guest redirected from dashboard')
check(guest.request('/register.php', {'name':'No CSRF'})[0] == 419, 'CSRF rejection')
check(guest.get('/logout.php')[0] == 405, 'logout is POST-only')
check('No adventures found' in guest.get('/packages.php?q=%27+OR+1%3D1--')[1], 'search treats SQL as data')
check('No adventures found' in guest.get('/packages.php?q=does-not-exist-012345')[1], 'empty search state')
registered = Client()
email = 'registration.' + fixture['suffix'] + '@example.test'
result = registered.post('/register.php', {'name':'Registered Traveller','email':email,'password':'Registration-pass-42','password_confirmation':'Registration-pass-42','role':'admin'})
check(result[2].endswith('/dashboard.php'), 'customer registration')
check(registered.get('/admin/users.php')[0] == 403, 'registration cannot assign admin role')
check('already registered' in Client().post('/register.php', {'name':'Duplicate','email':email,'password':'Registration-pass-42','password_confirmation':'Registration-pass-42'})[1], 'duplicate email handled')
customer, other, staff, admin = [login(r) for r in ['customer','other','staff','admin']]
for path in ['/staff/packages.php','/staff/bookings.php','/staff/queries.php','/admin/users.php','/admin/reports.php']:
    check(customer.get(path)[0] == 403, 'customer denied ' + path)
check(staff.get('/admin/users.php')[0] == 403, 'staff denied admin users')
check(staff.get('/admin/reports.php')[0] == 403, 'staff denied admin reports')
path = '/package-details.php?id=' + str(fixture['package_id'])
future = (dt.date.today() + dt.timedelta(days=30)).isoformat()
form = customer.get(path)[1]
data = {'travel_date': future, 'travellers': 2, 'request_token': token(form,'request_token'), 'price_cents':1, 'total_cents':1}
bad = customer.post(path, dict(data, travel_date='2020-01-01'), form)
check('Choose a valid travel date' in bad[1], 'past travel date rejected')
check('valid travellers' in customer.post(path, dict(data, travellers=0), form)[1], 'zero travellers rejected')
booked = customer.post(path, data, form)
check(booked[2].endswith('/dashboard.php') and 'LKR 24,690' in booked[1], 'booking uses trusted server price')
booking_id = int(token(booked[1], 'booking_id'))
check(booked[1].count('name="booking_id"') == 1, 'one booking created')
check('expired' in customer.post(path, data, form)[1], 'duplicate form submission rejected')
other_form = other.get(path)[1]
check('not enough spaces' in other.post(path, {'travel_date':future,'travellers':1,'request_token':token(other_form,'request_token')}, other_form)[1], 'departure capacity enforced')
check('cannot be cancelled' in other.post('/dashboard.php', {'booking_id':booking_id})[1], 'customer cannot cancel another customer booking')
check('Test adventure ' + fixture['suffix'] not in other.get('/dashboard.php')[1], 'booking ownership isolation')
check('status-confirmed' in staff.post('/staff/bookings.php', {'id':booking_id,'status':'confirmed'})[1], 'staff confirms booking')
check('future trip cannot' in staff.post('/staff/bookings.php', {'id':booking_id,'status':'completed'})[1], 'future booking cannot be completed')
check('status-cancelled' in customer.post('/dashboard.php', {'booking_id':booking_id})[1], 'customer cancellation')
check('transition is not allowed' in staff.post('/staff/bookings.php', {'id':booking_id,'status':'confirmed'})[1], 'cancelled booking cannot be reopened')
other_form = other.get(path)[1]
check(other.post(path, {'travel_date':future,'travellers':1,'request_token':token(other_form,'request_token')}, other_form)[2].endswith('/dashboard.php'), 'cancellation releases capacity')
query = customer.post('/contact.php', {'subject':'<script>alert(1)</script>','message':'Can you arrange a vegetarian lunch for the trip?'})
check('&lt;script&gt;' in query[1] and '<script>alert(1)</script>' not in query[1], 'enquiry HTML escaped')
staff_queries = staff.get('/staff/queries.php')[1]
query_id = token(staff_queries, 'id')
check(staff.post('/staff/queries.php', {'id':query_id,'response':'Yes, vegetarian lunch can be arranged.'})[0] == 200, 'staff reply saved')
check('vegetarian lunch can be arranged' in customer.get('/contact.php')[1], 'customer sees enquiry reply')
check('vegetarian lunch can be arranged' not in other.get('/contact.php')[1], 'enquiry ownership isolation')
package = {'title':'CRUD ' + fixture['suffix'],'destination':'Negombo','category':'Beach','description':'A complete description for a test tour package.','itinerary':'Day one: Walk along the beach.','inclusions':'Guide and local lunch','duration_days':1,'capacity':5,'price':'10000.50','image_url':'https://images.unsplash.com/photo-1510414842594-a61c69b5ae57'}
result = staff.post('/staff/package-edit.php',package)
check(result[2].endswith('/staff/packages.php'), 'staff creates package')
row = re.search(r'<tr><td><strong>CRUD ' + fixture['suffix'] + r'</strong>.*?</tr>', result[1], re.S).group(0)
pid = int(re.search(r'package-edit.php\?id=(\d+)',row).group(1))
check('saved' in staff.post('/staff/package-edit.php?id='+str(pid),dict(package,title='Edited '+fixture['suffix']))[1], 'staff edits package')
check('image must be a valid HTTPS' in staff.post('/staff/package-edit.php?id='+str(pid),dict(package,image_url='javascript:alert(1)'))[1], 'unsafe image scheme rejected')
staff.post('/staff/packages.php',{'id':pid,'action':'archive'})
check(guest.get('/package-details.php?id='+str(pid))[0] == 404, 'archived package hidden')
staff.post('/staff/packages.php',{'id':pid,'action':'restore'})
check(guest.get('/package-details.php?id='+str(pid))[0] == 200, 'package restored')
staff.post('/staff/packages.php',{'id':pid,'action':'delete'})
check(guest.get('/package-details.php?id='+str(pid))[0] == 404, 'unbooked package deleted')
check('cannot be deleted' in staff.post('/staff/packages.php',{'id':fixture['package_id'],'action':'delete'})[1], 'booking history protects packages')
check('cannot change your own role' in admin.post('/admin/users.php',{'id':fixture['admin']['id'],'role':'customer','active':0})[1], 'admin self-demotion blocked')
admin.post('/admin/users.php',{'id':fixture['other']['id'],'role':'staff','active':1})
check(other.get('/staff/packages.php')[0] == 200, 'role change applies to existing session')
admin.post('/admin/users.php',{'id':fixture['other']['id'],'role':'customer','active':0})
check(other.get('/dashboard.php')[2].endswith('/login.php'), 'disabled user session loses access')
check(admin.get('/admin/reports.php')[0] == 200, 'admin reports rendered')
csv = admin.get('/admin/reports.php?from='+future+'&to='+future+'&export=csv')
check('text/csv' in csv[3].get('Content-Type','') and fixture['suffix'] in csv[1], 'filtered CSV export')
check(admin.get('/admin/reports.php?from=not-a-date')[0] == 422, 'report date validation')
check('frame-ancestors' in guest.get('/')[3].get('Content-Security-Policy',''), 'security headers present')
check(customer.post('/logout.php',{},form=customer.get('/dashboard.php')[1])[2].endswith('/'), 'logout succeeds')
check(customer.get('/dashboard.php')[2].endswith('/login.php'), 'logout revokes session')
print(f'\n{checks} integration checks passed.')
