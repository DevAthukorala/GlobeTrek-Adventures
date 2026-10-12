<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
require_once dirname(__DIR__).'/app/bootstrap.php';
db()->exec(file_get_contents(dirname(__DIR__).'/database.sql'));
if ((int)sql('SELECT COUNT(*) FROM tour_packages')->fetchColumn()===0) {
    foreach (require __DIR__.'/seed-data.php' as $p) sql('INSERT INTO tour_packages (title,destination,category,description,itinerary,inclusions,duration_days,capacity,price_cents,image_url) VALUES (?,?,?,?,?,?,?,?,?,?)',$p);
}
echo "Schema ready. Sample packages seeded only if the catalogue was empty.\n";
echo "Create your administrator with: php scripts/create-user.php admin email@example.com \"Your Name\"\n";
