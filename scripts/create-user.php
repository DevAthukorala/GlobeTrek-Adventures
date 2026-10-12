<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
require_once dirname(__DIR__).'/app/bootstrap.php';
[$script,$role,$email,$name]=array_pad($argv,4,'');
if (!in_array($role,['customer','staff','admin'],true)||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>190||strlen($name)<2||strlen($name)>100) {fwrite(STDERR,"Usage: php scripts/create-user.php <customer|staff|admin> <email> <name>\n");exit(1);}
$password=getenv('GLOBETREK_USER_PASSWORD');
if ($password===false) {echo "Password (12–72 characters; input is visible in this terminal): ";$password=rtrim((string)fgets(STDIN),"\r\n");}
if (strlen($password)<12||strlen($password)>72) {fwrite(STDERR,"Password must be 12–72 characters.\n");exit(1);}
if (sql('SELECT id FROM users WHERE email=?',[strtolower($email)])->fetchColumn()) {fwrite(STDERR,"This email is already registered. No changes made.\n");exit(1);}
sql('INSERT INTO users (name,email,password_hash,role) VALUES (?,?,?,?)',[$name,strtolower($email),password_hash($password,PASSWORD_DEFAULT),$role]);
echo "Account created. No default credentials are shipped with this application.\n";
