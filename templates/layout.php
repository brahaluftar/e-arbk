<?php declare(strict_types=1); $assetVersion=(string) max(filemtime(dirname(__DIR__).'/public/assets/app.css'),filemtime(dirname(__DIR__).'/public/assets/dashboard.css'),filemtime(dirname(__DIR__).'/public/assets/finance.css')); ?>
<!doctype html><html lang="sq"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e($title)?> · Tarifat e bizneseve</title><link rel="stylesheet" href="<?=e(app_url('/assets/app.css?v='.$assetVersion))?>"><link rel="stylesheet" href="<?=e(app_url('/assets/dashboard.css?v='.$assetVersion))?>"><link rel="stylesheet" href="<?=e(app_url('/assets/finance.css?v='.$assetVersion))?>"></head>
<body><a class="skip" href="#main">Kalo te përmbajtja</a><header class="site-header"><a class="brand" href="<?=e(app_url($user&&$user['role_code']==='BUSINESS'?'/portal/index.php':'/admin/dashboard.php'))?>"><span class="mark">P</span><span><strong>Komuna e Prishtinës</strong><small>Tarifat e bizneseve</small></span></a><?php if($user):?><nav>
<?php if($user['role_code']==='BUSINESS'):?>
<a class="nav-button" href="<?=e(app_url('/portal/index.php'))?>">Bizneset e mia</a>
<a class="nav-button" href="<?=e(app_url('/portal/invoices.php'))?>">Faturat</a>
<a class="nav-button" href="<?=e(app_url('/portal/permits.php'))?>">Lejet e punës</a>
<a class="nav-button" href="<?=e(app_url('/portal/appeals.php'))?>">Ankesat</a>
<?php else:?>
<a class="nav-button" href="<?=e(app_url('/admin/dashboard.php'))?>"><svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="8" height="8" rx="1"/><rect x="13" y="3" width="8" height="5" rx="1"/><rect x="13" y="10" width="8" height="11" rx="1"/><rect x="3" y="13" width="8" height="8" rx="1"/></svg>Paneli</a>
<a class="nav-button" href="<?=e(app_url('/admin/businesses/index.php'))?>"><svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="7" width="18" height="14" rx="2"/><path d="M8 7V4h8v3M3 12h18M10 12v2h4v-2"/></svg>Bizneset</a>
<?php if(in_array($user['role_code'],['ADMIN','OFFICIAL'],true)):?><a class="nav-button" href="<?=e(app_url('/admin/business-access/index.php'))?>">Qasjet</a><?php endif;?>
<?php if(in_array($user['role_code'],['ADMIN','OFFICIAL'],true)):?><a class="nav-button" href="<?=e(app_url('/admin/appeals/index.php'))?>">Ankesat</a><?php endif;?>
<?php if($user['role_code']==='ADMIN'):?>
<a class="nav-button" href="<?=e(app_url('/admin/finance/index.php'))?>"><svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 20V4m0 16h18M7 15l4-4 3 2 5-6"/><circle cx="18.5" cy="7" r="1"/></svg>Financat</a>
<a class="nav-button" href="<?=e(app_url('/admin/finance/invoices.php?year='.date('Y')))?>">Faturat</a>
<a class="nav-button" href="<?=e(app_url('/admin/messages/index.php'))?>">Email raportet</a>
<a class="nav-button" href="<?=e(app_url('/admin/imports/index.php'))?>"><svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 15V3m-4 4 4-4 4 4M4 14v6h16v-6"/></svg>Importet</a>
<a class="nav-button" href="<?=e(app_url('/admin/users/index.php'))?>"><svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3"/><path d="M3 20v-1a6 6 0 0 1 12 0v1zM16 5a3 3 0 0 1 0 6M18 14a5 5 0 0 1 3 5v1h-4"/></svg>Përdoruesit</a>
<?php endif;?>
<?php endif;?>
<span class="nav-user"><?=e($user['full_name'])?> (<?=e($user['role_code'])?>)</span><form method="post" action="<?=e(app_url('/auth/logout.php'))?>"><input type="hidden" name="_csrf" value="<?=e($csrf->token())?>"><button class="nav-button nav-logout"><svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M10 17l5-5-5-5M15 12H3M12 3h7a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-7"/></svg>Dil</button></form></nav><?php endif;?></header>
<main id="main" class="shell"><?php foreach($flashes as $flash):?><div class="flash <?=e($flash['type']??'info')?>"><?=e($flash['message']??'')?></div><?php endforeach;?><?php require $templateFile;?></main><footer>Komuna e Prishtinës · Modul administrativ</footer></body></html>
