<?php
require_once dirname(__DIR__,2).'/app/views.php'; require_role(['staff','admin']); $id=(int)($_GET['id']??0); $error=null;
$p=$id?sql('SELECT * FROM tour_packages WHERE id=?',[$id])->fetch():[];
if ($id && !$p) { http_response_code(404); exit('Package not found.'); }
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    try {
        $title=text_input('title',3,150); $destination=text_input('destination',2,100); $category=text_input('category',1,50);
        if (!in_array($category,['Nature','Culture','Beach','Wildlife'],true)) throw new InvalidArgumentException('Choose a valid category.');
        $description=text_input('description',20,10000); $itinerary=text_input('itinerary',10,10000); $inclusions=text_input('inclusions',5,5000);
        $days=integer_input('duration_days',1,90); $capacity=integer_input('capacity',1,100); $price=text_input('price',1,12);
        if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/',$price) || (float)$price<=0) throw new InvalidArgumentException('Enter a positive price with at most two decimal places.');
        $cents=(int)round((float)$price*100); $image=image_url(text_input('image_url',10,1000));
        $values=[$title,$destination,$category,$description,$itinerary,$inclusions,$days,$capacity,$cents,$image];
        db()->beginTransaction();
        if ($id) {
            sql('SELECT id FROM tour_packages WHERE id=? FOR UPDATE',[$id]);
            $max=(int)sql("SELECT COALESCE(MAX(reserved),0) FROM (SELECT SUM(travellers) reserved FROM bookings WHERE package_id=? AND travel_date>=? AND status IN ('pending','confirmed') GROUP BY travel_date) AS reservations",[$id,date('Y-m-d')])->fetchColumn();
            if ($capacity<$max) throw new InvalidArgumentException('Capacity cannot be lower than the '.$max.' spaces already reserved on a future date.');
            $values[]=$id; sql('UPDATE tour_packages SET title=?,destination=?,category=?,description=?,itinerary=?,inclusions=?,duration_days=?,capacity=?,price_cents=?,image_url=? WHERE id=?',$values);
        } else sql('INSERT INTO tour_packages (title,destination,category,description,itinerary,inclusions,duration_days,capacity,price_cents,image_url) VALUES (?,?,?,?,?,?,?,?,?,?)',$values);
        db()->commit(); flash('Your adventure has been saved.'); go('/staff/packages.php');
    } catch (InvalidArgumentException $ex) { if (db()->inTransaction()) db()->rollBack(); $error=$ex->getMessage(); }
}
$v=$_SERVER['REQUEST_METHOD']==='POST'?$_POST:$p;
page_start($id?'Edit package':'Add package'); heading('STAFF WORKSPACE',$id?'Refine the experience.':'Create a new adventure.'); ?>
<section class="container pb-5"><?php dashboard_nav('Packages'); ?><div class="surface editor-form"><?php error_box($error); ?><form method="post"><?= csrf_field() ?><div class="row"><div class="col-md-6"><?php field('Package title','title','text',$v['title']??'','required maxlength="150"'); ?></div><div class="col-md-6"><?php field('Destination','destination','text',$v['destination']??'','required maxlength="100"'); ?></div></div><div class="row"><div class="col-md-4"><label class="form-label" for="category">Category</label><select class="form-select mb-3" id="category" name="category"><?php foreach (['Nature','Culture','Beach','Wildlife'] as $cat): ?><option <?= ($v['category']??'')===$cat?'selected':'' ?>><?= e($cat) ?></option><?php endforeach; ?></select></div><div class="col-md-4"><?php field('Duration (days)','duration_days','number',$v['duration_days']??3,'required min="1" max="90"'); ?></div><div class="col-md-4"><?php field('Capacity per departure date','capacity','number',$v['capacity']??12,'required min="1" max="100"'); ?></div></div><?php field('Price per person (LKR)','price','number',$v['price']??(isset($p['price_cents'])?number_format((int)$p['price_cents']/100,2,'.',''):''),'required min="0.01" max="99999999.99" step="0.01"'); field('Image URL (HTTPS)','image_url','url',$v['image_url']??'','required maxlength="1000"'); area('Description','description',$v['description']??'',10000); area('Itinerary (one day per paragraph)','itinerary',$v['itinerary']??'',10000); area('Inclusions','inclusions',$v['inclusions']??''); ?><button class="btn btn-primary" type="submit">Save adventure</button> <a class="btn btn-outline-dark" href="/staff/packages.php">Cancel</a></form></div></section>
<?php page_end(); ?>
