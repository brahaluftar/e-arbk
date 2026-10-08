<?php
declare(strict_types=1);
$app=require dirname(__DIR__,3).'/bootstrap.php';
$user=$app['auth']->requireUser();$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);
if(!$id){http_response_code(400);exit('Invalid business ID.');}
$repository=new App\Repository\BusinessRepository($app['pdo']);$business=$repository->find($id);
if($business===null){http_response_code(404);exit('Business not found.');}
$categories=$repository->validCategories($id);
$returnPath=business_list_path($_GET['return']??'');$returnQuery=(string)(parse_url($returnPath,PHP_URL_QUERY)??'');
render('admin/businesses/view',(string)$business['Emri'],compact('business','categories','returnQuery','returnPath'));
