<div class="page-head"><div><h1>Bizneset e mia</h1><p>Bizneset e aprovuara nga Komuna dhe gjendja e detyrimeve.</p></div><div class="page-actions"><a class="btn" href="<?=e(app_url('/portal/invoices.php'))?>">Faturat</a></div></div>
<?php if($businesses===[]):?><section class="card empty">Ende nuk është aprovuar asnjë lidhje me biznes. Dërgoni kërkesë më poshtë.</section><?php else:?>
<section class="kpis">
  <?php foreach($businesses as $business): $debt=(float)$business['outstanding'];?>
  <div class="kpi <?=$debt>0?'kpi--danger':'kpi--success'?>"><span><?=e($business['legal_name'])?></span><strong><?=e(money($debt))?></strong><small>NRB <?=e($business['registration_number'])?> · <?=$debt>0?'detyrime të papaguara':'pa detyrime'?><?=(int)$business['unit_count']>0?' · '.(int)$business['unit_count'].' njësi':''?></small></div>
  <?php endforeach;?>
</section>
<?php endif;?>
<section class="card">
  <h2>Shto biznes</h2>
  <p class="help">Shënoni numrin unik të biznesit. Qasja aktivizohet pasi zyrtari komunal ta verifikojë kërkesën.</p>
  <form method="post" action="<?=e(app_url('/portal/business-request.php'))?>" class="form-grid disclosure"><?=csrf_field()?><label class="field">Numri unik i biznesit (NRBIZ)<input name="registration_number" maxlength="50" required autocomplete="off" inputmode="numeric"></label><div class="form-actions"><button>Dërgo për aprovim</button></div></form>
</section>
<?php if($requests!==[]):?>
<section class="card"><h2>Kërkesat e mia</h2>
  <div class="table-scroll"><table class="table">
    <thead><tr><th>Biznesi</th><th>Statusi</th><th>Data</th><th>Shënimi</th></tr></thead>
    <tbody><?php foreach($requests as $request):?><tr>
      <td><span class="cell-title"><?=e($request['legal_name'])?></span><span class="cell-sub">NRB <?=e($request['registration_number'])?></span></td>
      <td data-label="Statusi"><?=badge('access',$request['status_code'])?></td>
      <td data-label="Data"><?=e(datetime_sq($request['requested_at']))?></td>
      <td data-label="Shënimi"><?=e($request['review_note']??'—')?></td>
    </tr><?php endforeach;?></tbody>
  </table></div>
</section>
<?php endif;?>
