<?php
require_once dirname(__DIR__,2).'/app/views.php'; require_role(['staff','admin']); $error=null;
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    try {
        $id=integer_input('id',1,PHP_INT_MAX); $action=text_input('action',1,20);
        if (!in_array($action,['archive','restore','delete'],true)) throw new InvalidArgumentException('Invalid action.');
        db()->beginTransaction();
        if (!sql('SELECT id FROM tour_packages WHERE id=? FOR UPDATE',[$id])->fetchColumn()) throw new InvalidArgumentException('Package not found.');
        if ($action==='delete') {
            if (sql('SELECT COUNT(*) FROM bookings WHERE package_id=?',[$id])->fetchColumn()) throw new InvalidArgumentException('Packages with booking history cannot be deleted. Archive the package instead.');
            sql('DELETE FROM tour_packages WHERE id=?',[$id]);
        } else sql('UPDATE tour_packages SET active=? WHERE id=?',[$action==='restore'?1:0,$id]);
        db()->commit(); flash('Package updated.'); go('/staff/packages.php');
    } catch (InvalidArgumentException $ex) { if (db()->inTransaction()) db()->rollBack(); $error=$ex->getMessage(); }
}
$packages=sql('SELECT * FROM tour_packages ORDER BY id DESC')->fetchAll(); page_start('Manage packages'); heading('STAFF WORKSPACE','Curate the next adventure.'); ?>
<section class="container pb-5"><?php dashboard_nav('Packages'); error_box($error); ?><div class="section-top mb-4"><h2>Tour packages</h2><a class="btn btn-primary" href="/staff/package-edit.php">Add package +</a></div><div class="surface table-responsive"><table class="table align-middle"><thead><tr><th>Package</th><th>Price / person</th><th>Capacity / date</th><th>Visibility</th><th>Actions</th></tr></thead><tbody><?php foreach ($packages as $p): ?><tr><td><strong><?= e($p['title']) ?></strong><br><small><?= e($p['destination']) ?> · <?= (int)$p['duration_days'] ?> days</small></td><td><?= money($p['price_cents']) ?></td><td><?= (int)$p['capacity'] ?></td><td><?= status_badge($p['active']?'active':'archived') ?></td><td><div class="action-row"><a class="btn btn-sm btn-outline-dark" href="/staff/package-edit.php?id=<?= (int)$p['id'] ?>">Edit</a><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn btn-sm btn-outline-dark" name="action" value="<?= $p['active']?'archive':'restore' ?>"><?= $p['active']?'Archive':'Restore' ?></button></form><form method="post" data-confirm="Permanently delete this package? Packages with bookings must be archived instead."><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn btn-sm btn-outline-danger" name="action" value="delete">Delete</button></form></div></td></tr><?php endforeach; ?></tbody></table><?php if (!$packages): ?><p>Add your first adventure to get started.</p><?php endif; ?></div></section>
<?php page_end(); ?>
