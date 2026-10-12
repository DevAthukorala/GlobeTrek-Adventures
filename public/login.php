<?php
require_once dirname(__DIR__).'/app/views.php';
if (user()) go('/dashboard.php'); $error=null;
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    try {
        $email=strtolower(text_input('email',3,190)); $password=text_input('password',1,200);
        $key=hash('sha256',$email.'|'.($_SERVER['REMOTE_ADDR']??'')); $now=time();
        // Database-backed throttling survives browser/session resets.
        sql('INSERT IGNORE INTO login_attempts (attempt_key,window_start) VALUES (?,?)',[$key,$now]);
        sql('UPDATE login_attempts SET attempts=0,window_start=? WHERE attempt_key=? AND window_start<?',[$now,$key,$now-900]);
        sql('UPDATE login_attempts SET attempts=attempts+1 WHERE attempt_key=?',[$key]);
        $attempt=sql('SELECT attempts FROM login_attempts WHERE attempt_key=?',[$key])->fetchColumn();
        if ($attempt>10) throw new InvalidArgumentException('Too many login attempts. Please try again in 15 minutes.');
        $u=sql('SELECT * FROM users WHERE email=? AND active=1',[$email])->fetch();
        $hash=$u['password_hash']??'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        if (!password_verify($password,$hash) || !$u) throw new InvalidArgumentException('Email or password is incorrect.');
        sql('DELETE FROM login_attempts WHERE attempt_key=?',[$key]);
        session_regenerate_id(true); $_SESSION['user_id']=$u['id']; unset($_SESSION['csrf']); go('/dashboard.php');
    } catch (InvalidArgumentException $ex) { $error=$ex->getMessage(); }
}
page_start('Welcome back'); ?>
<section class="auth-layout"><div class="auth-story"><p class="eyebrow">THE ISLAND IS CALLING</p><h1>A little further.<br>A little wilder.</h1><p>Your next adventure is waiting.</p></div><div class="auth-form"><p class="eyebrow">GOOD TO SEE YOU AGAIN</p><h2>Welcome back.</h2><p class="muted">Log in to continue your journey.</p><?php error_box($error); ?><form method="post"><?= csrf_field() ?><?php field('Email address','email','email',$_POST['email']??'','required maxlength="190" autocomplete="email"'); field('Password','password','password','','required maxlength="200" autocomplete="current-password"'); ?><button class="btn btn-primary w-100" type="submit">Log in ↗</button></form><p class="mt-4">New to GlobeTrek? <a href="/register.php">Create an account</a></p></div></section>
<?php page_end(); ?>
