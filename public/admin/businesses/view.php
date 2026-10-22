<?php
declare(strict_types=1);
$app=require dirname(__DIR__,3).'/bootstrap.php';
$user=$app['auth']->requireStaff();$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);
if(!$id){http_response_code(400);exit('Invalid business ID.');}
$repository=new App\Repository\BusinessRepository($app['pdo']);$business=$repository->find($id);
if($business===null){http_response_code(404);exit('Business not found.');}
$categories=$repository->validCategories($id);
$units=$repository->units($id);
$permits=$repository->permits($id);
$invoiceRepository=new App\Repository\InvoiceRepository($app['pdo']);
$invoices=$invoiceRepository->forBusiness($id);
$debt=$invoiceRepository->debtOfBusiness($id);
$permitBlock=(new App\Permitting\PermitService($app['pdo'],$app['config'],new App\Service\AuditLogger($app['pdo']),new App\Document\DocumentStore(dirname(__DIR__,3).'/var/documents')))->blockingReason($id);
$returnPath=business_list_path($_GET['return']??'');$returnQuery=(string)(parse_url($returnPath,PHP_URL_QUERY)??'');
$canEdit=App\Security\Auth::canEdit($user);$isAdmin=$user['role_code']==='ADMIN';
render('admin/businesses/view',(string)$business['Emri'],compact('business','categories','units','permits','invoices','debt','permitBlock','returnQuery','returnPath','canEdit','isAdmin'));
