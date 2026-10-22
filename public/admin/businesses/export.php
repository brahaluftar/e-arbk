<?php
declare(strict_types=1);
$app=require dirname(__DIR__,3).'/bootstrap.php';
$app['auth']->requireStaff();
$filters=business_filters($_GET);
$columns=[
    'REGULATION_ID'=>'ID','NRBIZ'=>'Numri i biznesit','Emri'=>'Emri ligjor','EMRI_TREGTAR'=>'Emri tregtar','MENAXHERI'=>'Menaxheri','NUMRI_TELEFONIT'=>'Telefoni','EMAIL'=>'Emaili','NUMRI_NJESIVE'=>'Numri i njësive','Lloji'=>'Lloji i biznesit','Qyteti'=>'Qyteti','Statusi'=>'Statusi ARBK','Pasiv'=>'Pasiv','date_pasivizimit'=>'Data e pasivizimit',
    'NACE_CODE_REG'=>'Kodi NACE ARBK','NACEPERSHKRIMI'=>'Përshkrimi NACE','SEKTORI'=>'Sektori','NR_PUNETOREVE'=>'Nr. punëtorëve','MADHESIA'=>'Madhësia','TOTAL_M'=>'Total M','TOTAL_F'=>'Total F','Viti'=>'Viti','MUAJI'=>'Muaji','DATA_SHUARJES'=>'Data e shuarjes',
    'ATK_MBYLLUR'=>'ATK mbyllur','ATK_DATEMBYLLJE'=>'Data e mbylljes ATK','normalized_atk_status'=>'Statusi','effective_tariff'=>'Tarifa efektive','NACE_CODE_TARIFF'=>'Kodi NACE tarifor','nace_veprimtaria_tariff'=>'Veprimtaria tarifore','NACE_REG_TARIFF'=>'Tarifa NACE','nace_category'=>'Kategoria komunale','tariff_snapshot'=>'Tarifa e aplikuar','assignment_method'=>'Metoda e klasifikimit','assigned_by'=>'Relacionin manual e bëri',
    'pronare_grua'=>'Pronare grua','pronesia_grua'=>'Pronësia grua (%)','pronar_veteran'=>'Pronar veteran','perqindja_veteran'=>'Pronësia veteran (%)','tarifa_me_lirim'=>'Tarifa me lirim',
];
$temporary=tempnam(sys_get_temp_dir(),'arbk_export_');if($temporary===false){http_response_code(500);exit('Export failed.');}
try{
    set_time_limit(0);session_write_close();$rows=(new App\Repository\BusinessRepository($app['pdo']))->export($filters);(new App\Export\XlsxWriter())->write($temporary,$columns,$rows,'Bizneset');
    $filename='bizneset_'.date('Y-m-d_H-i-s').'.xlsx';header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');header('Content-Disposition: attachment; filename="'.$filename.'"');header('Content-Length: '.filesize($temporary));header('Cache-Control: private, no-store');readfile($temporary);
}finally{if(is_file($temporary))unlink($temporary);}
