<?php $total=array_sum(array_map(static fn(array $i): float => (float)$i['outstanding'],$invoices));?>
<div class="page-head"><div><h1>Faturat e mia</h1><p>Paguani duke përdorur UNIREF-in e secilës faturë.</p></div></div>
<dl class="totals card"><div class="<?=$total>0?'is-strong':''?>"><dt>Gjithsej e papaguar</dt><dd><?=e(money($total))?></dd></div><div><dt>Fatura</dt><dd><?=count($invoices)?></dd></div></dl>
<?php if($invoices===[]):?><section class="card empty">Nuk ka fatura.</section><?php else:?>
<div class="table-scroll"><table class="table">
  <thead><tr><th>Fatura</th><th>UNIREF</th><th>Data</th><th class="num">Shuma</th><th class="num">Mbetja</th><th>Statusi</th><th>PDF</th></tr></thead>
  <tbody><?php foreach($invoices as $invoice):?><tr>
    <td><span class="cell-title"><?=e($invoice['invoice_number'])?></span><span class="cell-sub"><?=e($invoice['business_name'])?> · <?=(int)$invoice['fiscal_year']?></span></td>
    <td data-label="UNIREF"><code><?=e($invoice['uniref']??'—')?></code></td>
    <td data-label="Data"><?=e(date_sq($invoice['issued_on']))?><span class="cell-sub">Afati <?=e(date_sq($invoice['due_on']))?></span></td>
    <td data-label="Shuma" class="num"><?=e(money($invoice['amount']))?></td>
    <td data-label="Mbetja" class="num"><?=e(money($invoice['outstanding']))?></td>
    <td data-label="Statusi"><?=badge('invoice',$invoice['status_code'])?></td>
    <td data-label="PDF"><?php if($invoice['uniref']):?><div class="actions"><?php foreach(App\Billing\InvoiceTranslations::LANGUAGES as $code=>$name):?><a class="btn btn--secondary btn--small" href="<?=e(app_url('/portal/invoice-pdf.php?id='.(int)$invoice['id'].'&lang='.$code))?>" title="<?=e($name)?>"><?=e(strtoupper($code))?></a><?php endforeach;?></div><?php else:?>—<?php endif;?></td>
  </tr><?php endforeach;?></tbody>
</table></div>
<?php endif;?>
