<?php
require_once dirname(__DIR__).'/app/bootstrap.php';
if ($_SERVER['REQUEST_METHOD']!=='POST') { http_response_code(405); header('Allow: POST'); exit('Use the logout button to end your session.'); }
verify_csrf(); $_SESSION=[]; session_destroy(); setcookie(session_name(),'',time()-3600,'/'); go('/');
