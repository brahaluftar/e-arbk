<?php
declare(strict_types=1);
// Kept for old links and bookmarks: classification is now a tab of the business register.
$app=require dirname(__DIR__,3).'/bootstrap.php';
$app['auth']->requireStaff();
$filters=array_filter(business_filters($_GET,'UNCLASSIFIED'),static fn(string $v): bool => $v!=='');
redirect('/admin/businesses/index.php?'.http_build_query($filters));
