<?php
require_once dirname(__DIR__).'/app/views.php';
if (user()) go('/dashboard.php'); $error=null;
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    try {
        $name=text_input('name',2,100); $email=strtolower(text_input('email',3,190)); $password=password_input(12,72);
        if (!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Enter a valid email address.');
        if ($password !== ($_POST['password_confirmation']??'')) throw new InvalidArgumentException('Your passwords do not match.');
        sql('INSERT INTO users (name,email,password_hash) VALUES (?,?,?)',[$name,$email,password_hash($password,PASSWORD_DEFAULT)]);
        session_regenerate_id(true); $_SESSION['user_id']=(int)db()->lastInsertId(); unset($_SESSION['csrf']); flash('Welcome to GlobeTrek. Your next adventure awaits.'); go('/dashboard.php');
    } catch (InvalidArgumentException $ex) { $error=$ex->getMessage(); } catch (PDOException $ex) { if ($ex->getCode()!=='23000') throw $ex; $error='This email is already registered. Please log in.'; }
}
page_start('Create your account'); ?>
<section class="auth-layout"><div class="auth-story"><p class="eyebrow">YOUR NEXT CHAPTER</p><h1>Some journeys<br>change everything.</h1><p>Let's find yours.</p></div><div class="auth-form"><p class="eyebrow">WELCOME TO GLOBETREK</p><h2>Adventure starts here.</h2><p class="muted">Create an account to book and manage your trips.</p><?php error_box($error); ?><form method="post"><?= csrf_field() ?><?php field('Full name','name','text',$_POST['name']??'','required maxlength="100" autocomplete="name"'); field('Email address','email','email',$_POST['email']??'','required maxlength="190" autocomplete="email"'); field('Password (12–72 characters)','password','password','','required minlength="12" maxlength="72" autocomplete="new-password"'); field('Confirm password','password_confirmation','password','','required minlength="12" maxlength="72" autocomplete="new-password"'); ?><button class="btn btn-primary w-100" type="submit">Create account ↗</button></form><p class="mt-4">Already exploring with us? <a href="/login.php">Log in</a></p></div></section>
<?php page_end(); ?>
