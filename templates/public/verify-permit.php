<div class="page-head"><div><h1>Verifikimi i Lejes së Punës</h1><p>Regjistri elektronik i Komunës së Prishtinës.</p></div></div>
<?php if($permit===null):?>
<section class="card"><div class="status-banner status-banner--bad">Leja nuk u gjet ose kodi nuk është valid.</div></section>
<?php else: $valid=(int)$permit['is_valid']===1;?>
<section class="card stack">
  <div class="status-banner <?=$valid?'status-banner--ok':'status-banner--bad'?>"><?=$valid?'✓ Leja është e vlefshme':'✗ Leja nuk është e vlefshme'?></div>
  <h2><?=e($permit['serial_number'])?></h2>
  <dl class="dl-grid">
    <div><dt>Biznesi</dt><dd><?=e($permit['business_name_snapshot'])?></dd></div>
    <div><dt>Adresa</dt><dd><?=e($permit['business_address_snapshot']??'—')?></dd></div>
    <div><dt>Kodi NACE</dt><dd><?=e($permit['registered_nace_code_snapshot']??'—')?></dd></div>
    <div><dt>Veprimtaria</dt><dd><?=e($permit['nace_description_snapshot']??'—')?></dd></div>
    <div><dt>Lëshuar</dt><dd><?=e(date_sq($permit['issued_on']))?></dd></div>
    <div><dt>Vlefshmëria</dt><dd><?=e(date_sq($permit['valid_from']).' – '.date_sq($permit['valid_until']))?></dd></div>
    <div><dt>Statusi</dt><dd><?=badge('permit',$permit['status_code']==='ACTIVE'&&!$valid?'EXPIRED':$permit['status_code'])?></dd></div>
  </dl>
</section>
<?php endif;?>
