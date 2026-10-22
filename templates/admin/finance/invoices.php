<?php
$base=['year'=>$year,'per_page'=>$result['per_page'],'q'=>$query,'status'=>$status]+($jobId?['job'=>$jobId]:[]);
$link=static fn(array $changes): string => '?'.http_build_query(array_filter(array_merge($base,$changes),static fn($v): bool => $v!==''&&$v!==null));
$activeFilters=count(array_filter(['q'=>$query,'status'=>$status,'job'=>$jobId]));
?>
<div class="page-head"><div><h1>Faturat <?=$year?></h1><p><?=number_format($result['total'],0,',','.')?> fatura<?=$jobId?' të job-it #'.(int)$jobId:''?>.</p></div>
  <div class="page-actions"><a class="btn btn--secondary" href="#fature-manuale">+ Faturë manuale</a></div></div>
<section class="card filters">
  <?=filter_toggle($activeFilters)?>
  <div class="filters-body"><form method="get" class="form-grid">
    <label class="field">Viti fiskal<input type="number" name="year" min="2000" max="2100" value="<?=$year?>"></label>
    <label class="field">Kërko<input name="q" value="<?=e($query)?>" placeholder="UNIREF, numër, biznes, NRB"></label>
    <label class="field">Statusi<select name="status"><option value="">Të gjitha</option><?php foreach(App\Repository\InvoiceRepository::STATUS_FILTERS as $code):?><option value="<?=e($code)?>" <?=$status===$code?'selected':''?>><?=e(label('invoice',$code))?></option><?php endforeach;?></select></label>
    <label class="field">Rreshta<select name="per_page"><?php foreach([20,30,50,100] as $size):?><option value="<?=$size?>" <?=$result['per_page']===$size?'selected':''?>><?=$size?></option><?php endforeach;?></select></label>
    <?=hidden_fields(['job'=>$jobId])?>
    <div class="form-actions"><button>Kërko</button></div>
  </form></div>
</section>
<dl class="totals card">
  <div><dt>Faturuar</dt><dd><?=e(money($result['totals']['amount']))?></dd></div>
  <div><dt>Paguar</dt><dd><?=e(money($result['totals']['paid']))?></dd></div>
  <div class="is-strong"><dt>E papaguar</dt><dd><?=e(money($result['totals']['outstanding']))?></dd></div>
</dl>
<?php if($result['rows']===[]):?><section class="card empty">Nuk u gjetën fatura.</section><?php else:?>
<div class="table-scroll"><table class="table">
  <thead><tr><th>Fatura</th><th>Biznesi</th><th>Data</th><th class="num">Shuma</th><th class="num">Mbetja</th><th>Statusi</th><th><span class="visually-hidden">Veprime</span></th></tr></thead>
  <tbody><?php foreach($result['rows'] as $invoice): $overdue=(float)$invoice['outstanding']>0&&$invoice['due_on']!==null&&substr((string)$invoice['due_on'],0,10)<date('Y-m-d');?>
    <tr>
      <td><a class="cell-title" href="<?=e(app_url('/admin/finance/invoice.php?id='.(int)$invoice['id']))?>"><?=e($invoice['invoice_number'])?></a><?php if($invoice['uniref']&&$invoice['uniref']!==$invoice['invoice_number']):?><span class="cell-sub"><?=e($invoice['uniref'])?></span><?php endif;?></td>
      <td data-label="Biznesi"><?=e($invoice['business_name_snapshot']??$invoice['legal_name'])?><span class="cell-sub">NRB <?=e($invoice['registration_number'])?></span></td>
      <td data-label="Data"><?=e(date_sq($invoice['issued_on']))?><span class="cell-sub">Afati <?=e(date_sq($invoice['due_on']))?></span></td>
      <td data-label="Shuma" class="num"><?=e(money($invoice['amount']))?></td>
      <td data-label="Mbetja" class="num"><?=e(money($invoice['outstanding']))?></td>
      <td data-label="Statusi"><?=badge('invoice',$invoice['status_code'])?><?php if($overdue):?> <?=badge('invoice','OVERDUE')?><?php endif;?></td>
      <td class="actions"><div class="actions"><a class="btn btn--secondary btn--small" href="<?=e(app_url('/admin/finance/invoice.php?id='.(int)$invoice['id']))?>">Hap</a><?php if($invoice['uniref']):?><a class="btn btn--secondary btn--small" href="<?=e(app_url('/admin/finance/invoice-pdf.php?id='.(int)$invoice['id']))?>">PDF</a><?php endif;?></div></td>
    </tr>
  <?php endforeach;?></tbody>
</table></div>
<?php endif;?>
<nav class="pagination" aria-label="Faqet"><span>Faqja <?=$result['page']?> / <?=$result['pages']?></span><span class="cluster"><?php if($result['page']>1):?><a class="btn btn--secondary btn--small" href="<?=e($link(['page'=>$result['page']-1]))?>">← Para</a><?php endif;?><?php if($result['page']<$result['pages']):?><a class="btn btn--secondary btn--small" href="<?=e($link(['page'=>$result['page']+1]))?>">Pas →</a><?php endif;?></span></nav>
<?php if($bulkParts>0):?>
<section class="card"><h2>PDF masiv i vitit <?=$year?></h2><p class="help">Faturat ndahen në pjesë me nga <?=App\Billing\InvoiceDocumentService::BULK_PAGE_SIZE?> faqe.</p>
  <div class="actions"><?php for($part=1;$part<=$bulkParts;$part++):?><a class="btn btn--secondary btn--small" href="<?=e(app_url('/admin/finance/bulk-pdf.php?year='.$year.'&part='.$part))?>">Pjesa <?=$part?></a><?php endfor;?></div></section>
<?php endif;?>
<section class="card" id="fature-manuale">
  <h2>Faturë manuale</h2>
  <p class="help">Për biznese aktive pa faturë për vitin <?=$year?>. Fatura merr UNIREF automatikisht; numri i jashtëm është opsional.</p>
  <form method="get" class="cluster disclosure"><?=hidden_fields(['year'=>$year])?><label class="field">Kërko biznesin<input name="business_q" maxlength="120" value="<?=e($businessQuery)?>" required placeholder="Emri ose NRB"></label><button class="btn--secondary">Kërko</button></form>
  <?php if($businesses!==[]):?>
  <form method="post" action="<?=e(app_url('/admin/finance/save-invoice.php'))?>" class="form-grid disclosure"><?=csrf_field()?><input type="hidden" name="fiscal_year" value="<?=$year?>">
    <label class="field span-all">Biznesi<select name="business_id" required><?php foreach($businesses as $business):?><option value="<?=(int)$business['id']?>"><?=e($business['registration_number'].' · '.$business['legal_name'].($business['estimated_tariff']!==null?' · '.money($business['estimated_tariff']):''))?></option><?php endforeach;?></select></label>
    <label class="field">Shuma (€)<input name="amount" type="number" min="0.01" step="0.01" required value="<?=e($businesses[0]['estimated_tariff']??'')?>"></label>
    <label class="field">Data e faturës<input name="issued_on" type="date" required value="<?=e(date('Y')===(string)$year?date('Y-m-d'):$year.'-01-01')?>"></label>
    <label class="field">Afati i pagesës<input name="due_on" type="date"></label>
    <label class="field">Numër i jashtëm<small>Opsional</small><input name="invoice_number" maxlength="80"></label>
    <label class="field span-all">Shënim<input name="notes" maxlength="500"></label>
    <div class="form-actions"><button>Regjistro faturën</button></div>
  </form>
  <?php elseif($businessQuery!==''):?><p class="empty">Nuk u gjet biznes aktiv pa faturë për këtë vit.</p><?php endif;?>
</section>
