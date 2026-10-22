<?php
$tabs=[''=>'Të gjitha','UNCLASSIFIED'=>'Pa klasifikim','AMBIGUOUS'=>'Të paqarta','NO_RELATION'=>'Pa relacion','MANUAL'=>'Manuale','AUTO'=>'Automatike'];
$active=array_filter($filters,static fn(string $v): bool => $v!=='');
$query=static fn(array $changes): string => http_build_query(array_filter(array_merge($filters,$changes),static fn($v): bool => $v!==''&&$v!==null));
$returnQuery=$query(['page'=>$result['page']]);
$chipLabels=['q'=>'Kërkimi','nace'=>'NACE','atk_status'=>'Statusi','pronare_grua'=>'Pronare grua','pronar_veteran'=>'Pronar veteran'];
?>
<div class="page-head">
  <div><h1>Regjistri i bizneseve</h1><p><?=number_format($result['total'],0,',','.')?> biznese · statusi i bashkuar ARBK/ATK dhe klasifikimi komunal.</p></div>
  <div class="page-actions"><a class="btn btn--secondary" href="<?=e(app_url('/admin/businesses/export.php?'.$query([])))?>">Eksporto Excel</a></div>
</div>
<nav class="tabs" aria-label="Klasifikimi">
  <?php foreach($tabs as $code=>$label):?><a href="?<?=e($query(['classification'=>$code,'page'=>null]))?>"<?=($filters['classification']??'')===$code?' aria-current="page"':''?>><?=e($label)?></a><?php endforeach;?>
</nav>
<section class="card filters">
  <?=filter_toggle(count(array_diff_key($active,['classification'=>1])))?>
  <div class="filters-body">
    <form method="get" class="form-grid">
      <input type="hidden" name="classification" value="<?=e($filters['classification'])?>">
      <label class="field">Emri ose NRB<input name="q" value="<?=e($filters['q'])?>" maxlength="120" inputmode="search"></label>
      <label class="field">Kodi NACE<input name="nace" value="<?=e($filters['nace'])?>" maxlength="20" inputmode="numeric"></label>
      <label class="field">Statusi<select name="atk_status"><option value="">Të gjitha</option><?php foreach(App\Support\Labels::options('status') as $code=>$label):?><option value="<?=e($code)?>" <?=$filters['atk_status']===$code?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></label>
      <label class="field">Pronare grua<select name="pronare_grua"><option value="">Të gjitha</option><option value="1" <?=$filters['pronare_grua']==='1'?'selected':''?>>Po</option><option value="0" <?=$filters['pronare_grua']==='0'?'selected':''?>>Jo</option></select></label>
      <label class="field">Pronar veteran<select name="pronar_veteran"><option value="">Të gjitha</option><option value="1" <?=$filters['pronar_veteran']==='1'?'selected':''?>>Po</option><option value="0" <?=$filters['pronar_veteran']==='0'?'selected':''?>>Jo</option></select></label>
      <div class="form-actions"><button>Kërko</button><a class="btn btn--secondary" href="?<?=e($query(['q'=>'','nace'=>'','atk_status'=>'','pronare_grua'=>'','pronar_veteran'=>'','page'=>null]))?>">Pastro</a></div>
    </form>
    <?php $chips=array_intersect_key($active,$chipLabels); if($chips!==[]):?><div class="chips"><?php foreach($chips as $key=>$value):?><a class="chip" href="?<?=e($query([$key=>'','page'=>null]))?>" aria-label="Hiq filtrin <?=e($chipLabels[$key])?>"><?=e($chipLabels[$key].': '.($key==='atk_status'?label('status',$value):(in_array($key,['pronare_grua','pronar_veteran'],true)?label('yes_no',$value):$value)))?> ✕</a><?php endforeach;?></div><?php endif;?>
  </div>
</section>
<?php if($result['rows']===[]):?><section class="card empty">Nuk u gjet asnjë biznes me këto filtra.</section><?php else:?>
<div class="table-scroll"><table class="table">
  <thead><tr><th>Biznesi</th><th>NACE</th><th>Klasifikimi</th><th class="num">Tarifa</th><th>Statusi</th><th class="num">Njësi</th><th><span class="visually-hidden">Veprime</span></th></tr></thead>
  <tbody>
  <?php foreach($result['rows'] as $row):
    $state=$row['assignment_method']??((int)$row['candidate_count']>1?'AMBIGUOUS':((int)$row['candidate_count']===0?'NO_RELATION':'UNCLASSIFIED'));
    $tariff=$row['effective_tariff']??$row['applied_tariff'];?>
    <tr>
      <td><a class="cell-title" href="<?=e(app_url('/admin/businesses/view.php?id='.(int)$row['business_id'].'&return='.rawurlencode($returnQuery)))?>"><?=e($row['legal_name'])?></a><span class="cell-sub">NRB <?=e($row['registration_number'])?><?=($row['phone_number']??'')!==''?' · '.e($row['phone_number']):''?></span></td>
      <td data-label="NACE"><?=e($row['arbk_nace_code']??'—')?></td>
      <td data-label="Klasifikimi"><?=badge('classification',$state)?><?php if($row['nace_category']):?><span class="cell-sub"><?=e($row['nace_category'])?></span><?php endif;?></td>
      <td data-label="Tarifa" class="num"><?=$tariff!==null?e(money($tariff)):'—'?></td>
      <td data-label="Statusi"><?=badge('status',$row['atk_status'])?></td>
      <td data-label="Njësi" class="num"><?=number_format((int)$row['unit_count'],0,',','.')?></td>
      <td class="actions"><a class="btn btn--secondary btn--small" href="<?=e(app_url('/admin/businesses/view.php?id='.(int)$row['business_id'].'&return='.rawurlencode($returnQuery)))?>">Hap</a></td>
    </tr>
  <?php endforeach;?>
  </tbody>
</table></div>
<?php endif;?>
<nav class="pagination" aria-label="Faqet"><span><?=number_format($result['total'],0,',','.')?> rezultate · Faqja <?=$result['page']?> / <?=$result['pages']?></span><span class="cluster"><?php if($result['page']>1):?><a class="btn btn--secondary btn--small" href="?<?=e($query(['page'=>$result['page']-1]))?>">← Para</a><?php endif;?><?php if($result['page']<$result['pages']):?><a class="btn btn--secondary btn--small" href="?<?=e($query(['page'=>$result['page']+1]))?>">Pas →</a><?php endif;?></span></nav>
