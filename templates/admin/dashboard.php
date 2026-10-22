<?php $list=static fn(array $query): string => app_url('/admin/businesses/index.php'.($query===[]?'':'?'.http_build_query($query)));?>
<div class="page-head"><div><h1>Paneli</h1><p>Gjendja e regjistrit të bizneseve dhe e klasifikimit NACE.</p></div></div>
<section class="kpis" aria-label="Treguesit kryesorë">
  <a class="kpi" href="<?=e($list([]))?>"><strong><?=number_format((int)($stats['total']??0),0,',','.')?></strong><span>Biznese gjithsej</span></a>
  <a class="kpi kpi--success" href="<?=e($list(['atk_status'=>'ACTIVE']))?>"><strong><?=number_format((int)($stats['active']??0),0,',','.')?></strong><span>Aktive</span></a>
  <a class="kpi kpi--warning" href="<?=e($list(['classification'=>'UNCLASSIFIED']))?>"><strong><?=number_format((int)($stats['unclassified']??0),0,',','.')?></strong><span>Pa klasifikim</span></a>
  <a class="kpi kpi--danger" href="<?=e($list(['classification'=>'AMBIGUOUS']))?>"><strong><?=number_format((int)($stats['ambiguous']??0),0,',','.')?></strong><span>Klasifikime të paqarta</span></a>
</section>
<details class="more"><summary>Më shumë tregues</summary>
<section class="kpis">
  <a class="kpi kpi--danger" href="<?=e($list(['atk_status'=>'DEACTIVATED']))?>"><strong><?=number_format((int)($stats['deactivated']??0),0,',','.')?></strong><span>Joaktive (ATK, pasive, të shuara)</span></a>
  <a class="kpi kpi--warning" href="<?=e($list(['atk_status'=>'NEEDS_REVIEW']))?>"><strong><?=number_format((int)($stats['needs_review']??0),0,',','.')?></strong><span>Për shqyrtim</span></a>
  <a class="kpi kpi--success" href="<?=e($list(['classification'=>'AUTO']))?>"><strong><?=number_format((int)($stats['automatic']??0),0,',','.')?></strong><span>Klasifikime automatike</span></a>
  <a class="kpi kpi--info" href="<?=e($list(['classification'=>'MANUAL']))?>"><strong><?=number_format((int)($stats['manual']??0),0,',','.')?></strong><span>Klasifikime manuale</span></a>
  <a class="kpi" href="<?=e($list(['classification'=>'NO_RELATION']))?>"><strong><?=number_format((int)($stats['no_relation']??0),0,',','.')?></strong><span>Pa relacion në NACE_LIST</span></a>
</section>
</details>
<?php if (is_array($financialStats)) { $summaryAction='/admin/dashboard.php'; require __DIR__.'/finance/summary.php'; } ?>
