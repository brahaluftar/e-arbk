<div class="page-head"><div><h1>Lejet e punës</h1><p>Lejet elektronike të bizneseve tuaja. Leja lëshohet kur biznesi nuk ka detyrime të papaguara.</p></div></div>
<?php if($permits===[]):?><section class="card empty">Nuk ka leje pune.</section><?php else:?>
<div class="table-scroll"><table class="table">
  <thead><tr><th>Numri serik</th><th>Vlefshmëria</th><th>Statusi</th><th><span class="visually-hidden">Shkarko</span></th></tr></thead>
  <tbody><?php foreach($permits as $permit):?><tr>
    <td><span class="cell-title"><?=e($permit['serial_number'])?></span><span class="cell-sub"><?=e($permit['business_name'])?> · lëshuar <?=e(date_sq($permit['issued_on']))?></span></td>
    <td data-label="Vlefshmëria"><?=e(date_sq($permit['valid_from']).' – '.date_sq($permit['valid_until']))?></td>
    <td data-label="Statusi"><?=badge('permit',$permit['status_code'])?></td>
    <td class="actions"><a class="btn btn--secondary btn--small" href="<?=e(app_url('/portal/permit-pdf.php?id='.(int)$permit['id']))?>">Shkarko lejen</a></td>
  </tr><?php endforeach;?></tbody>
</table></div>
<?php endif;?>
