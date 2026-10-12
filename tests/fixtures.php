<?php
// Only run against an isolated development/test database. No production data is removed.
if (PHP_SAPI !== 'cli' || getenv('GLOBETREK_TEST') !== '1') { exit(1); }
require_once dirname(__DIR__).'/app/bootstrap.php';
$suffix=bin2hex(random_bytes(6)); $password=bin2hex(random_bytes(12)); $accounts=[];
foreach (['customer','other','staff','admin'] as $role) {
    $email=$role.'.'.$suffix.'@example.test'; $dbRole=$role==='other'?'customer':$role;
    sql('INSERT INTO users(name,email,password_hash,role) VALUES(?,?,?,?)', ['Test '.$role,$email,password_hash($password,PASSWORD_DEFAULT),$dbRole]);
    $accounts[$role]=['id'=>(int)db()->lastInsertId(),'email'=>$email,'password'=>$password];
}
sql('INSERT INTO tour_packages(title,destination,category,description,itinerary,inclusions,duration_days,capacity,price_cents,image_url) VALUES(?,?,?,?,?,?,?,?,?,?)', ['Test adventure '.$suffix,'Test destination','Nature','A test adventure for automated integration checks.','Day 1: Test the booking workflow.','Guide and transfers',1,2,1234500,'https://images.unsplash.com/photo-1546708973-b339540b5162?auto=format&fit=crop&w=800&q=80']);
$accounts['package_id']=(int)db()->lastInsertId(); $accounts['suffix']=$suffix;
echo json_encode($accounts,JSON_THROW_ON_ERROR);
