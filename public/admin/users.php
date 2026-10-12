<?php
require_once dirname(__DIR__,2).'/app/views.php'; $current=require_role(['admin']); $error=null;
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    try {
        $id=integer_input('id',1,PHP_INT_MAX); $role=text_input('role',1,20); $active=integer_input('active',0,1);
        if ($id===(int)$current['id']) throw new InvalidArgumentException('You cannot change your own role or disable your own account.');
        if (!in_array($role,['customer','staff','admin'],true)) throw new InvalidArgumentException('Choose a valid role.');
        db()->beginTransaction();
        // Serialize administrative changes, preserving at least one active admin.
        $admins=sql("SELECT id FROM users WHERE role='admin' AND active=1 ORDER BY id FOR UPDATE")->fetchAll();
        $target=sql('SELECT * FROM users WHERE id=? FOR UPDATE',[$id])->fetch();
        if (!$target) throw new InvalidArgumentException('User not found.');
        if ($target['role']==='admin' && $target['active'] && ($role!=='admin'||!$active) && count($admins)<=1) throw new InvalidArgumentException('At least one active administrator is required.');
        sql('UPDATE users SET role=?,active=? WHERE id=?',[$role,$active,$id]); db()->commit(); flash('User permissions updated.'); go('/admin/users.php');
    } catch (InvalidArgumentException $ex) { if (db()->inTransaction()) db()->rollBack(); $error=$ex->getMessage(); }
}
$users=sql('SELECT id,name,email,role,active,created_at FROM users ORDER BY id DESC')->fetchAll(); page_start('Manage users'); heading('ADMINISTRATION','The people behind every journey.'); ?>
<section class="container pb-5"><?php dashboard_nav('Users'); error_box($error); ?><p class="muted">Promote registered users to staff, manage roles or deactivate access. Deactivation preserves booking history.</p><div class="surface table-responsive"><table class="table align-middle"><thead><tr><th>Name</th><th>Email</th><th>Joined</th><th>Role and access</th></tr></thead><tbody><?php foreach ($users as $u): ?><tr><td><?= e($u['name']) ?></td><td><?= e($u['email']) ?></td><td><?= e(substr($u['created_at'],0,10)) ?></td><td><?php if ((int)$u['id']===(int)$current['id']): ?>Administrator (you)<?php else: ?><form class="action-row" method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><select class="form-select form-select-sm" name="role" aria-label="Role for <?= e($u['name']) ?>"><?php foreach (['customer','staff','admin'] as $r): ?><option value="<?= $r ?>" <?= $u['role']===$r?'selected':'' ?>><?= ucfirst($r) ?></option><?php endforeach; ?></select><select class="form-select form-select-sm" name="active" aria-label="Access for <?= e($u['name']) ?>"><option value="1" <?= $u['active']?'selected':'' ?>>Active</option><option value="0" <?= !$u['active']?'selected':'' ?>>Disabled</option></select><button class="btn btn-sm btn-outline-dark">Save</button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php page_end(); ?>
