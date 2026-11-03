<?php
use App\Repository\RevenueRepository;
use App\Support\NaceSections;

$url=static fn(array $params=[]): string => app_url('/admin/finance/revenue.php?'.http_build_query(array_filter(['year'=>$year]+$params,static fn($v): bool => $v!==null&&$v!=='')));
$scope=$mode==='months'?['view'=>'months','month'=>$month]:['section'=>$section,'division'=>$division,'nace'=>$nace];
$exportUrl=static fn(int $sheet=0): string => $url($scope+['export'=>1,'sheet'=>$sheet?:null]);
$monthNames=['Janar','Shkurt','Mars','Prill','Maj','Qershor','Korrik','Gusht','Shtator','Tetor','Nëntor','Dhjetor'];
$percent=static fn(float $part,float $whole): string => $whole>0?number_format(100*$part/$whole,1,',','.').' %':'—';
$capped=static fn(array $rows): bool => $limit!==null&&count($rows)>=$limit;
$crumbs=[['Të hyrat '.$year,$url($mode==='months'?['view'=>'months']:[])]];
if($mode==='months'){if($month!==null)$crumbs[]=[$monthNames[$month-1],null];}
else{
  if($section!==null)$crumbs[]=[($section===NaceSections::UNKNOWN?'':'Seksioni '.$section.' · ').NaceSections::name($section),$url(['section'=>$section])];
  if($division!==null)$crumbs[]=[$division===NaceSections::UNKNOWN?'Pa divizion':'Divizioni '.$division,$url(['division'=>$division])];
  if($nace!==null)$crumbs[]=[$nace===RevenueRepository::NO_CODE?'Pa kod NACE':'NACE '.$nace,null];
}
$crumbs[count($crumbs)-1][1]=null;
$level=$section===null?'Seksioni NACE':($division===null?'Divizioni':'Kodi NACE');
$next=static fn(array $group): array => $section===null?['section'=>$group['key']]:($division===null?['division'=>$group['key']]:['nace'=>$group['key']]);
?>
<div class="page-head"><div><h1>Të hyrat <?=$year?></h1><p>Klikoni një rresht për të zbritur në nivelin më të detajuar: seksioni NACE → divizioni → kodi → faturat dhe bizneset.</p></div>
  <div class="page-actions"><a class="btn btn--secondary" href="<?=e($exportUrl())?>">Shkarko Excel</a></div></div>
<section class="card">
  <form method="get" class="cluster"><label class="field">Viti fiskal<select name="year"><?php foreach($years as $option):?><option value="<?=(int)$option?>" <?=$option===$year?'selected':''?>><?=(int)$option?></option><?php endforeach;?></select></label><?=hidden_fields($mode==='months'?['view'=>'months']:[])?><button class="btn--secondary">Shfaq</button></form>
</section>
<nav class="tabs" aria-label="Pamja e të hyrave"><a href="<?=e($url())?>"<?=$mode==='nace'?' aria-current="page"':''?>>Sipas veprimtarisë (NACE)</a><a href="<?=e($url(['view'=>'months']))?>"<?=$mode==='months'?' aria-current="page"':''?>>Sipas muajve</a></nav>
<?php if(count($crumbs)>1):?><nav aria-label="Niveli"><ol class="crumbs"><?php foreach($crumbs as [$text,$href]):?><li><?php if($href!==null):?><a href="<?=e($href)?>"><?=e($text)?></a><?php else:?><span aria-current="page"><?=e($text)?></span><?php endif;?></li><?php endforeach;?></ol></nav><?php endif;?>

<?php if($mode==='nace'&&$nace===null): $totals=RevenueRepository::totals($groups);?>
<section class="kpis" aria-label="Totalet">
  <div class="kpi kpi--success"><span>Të hyra të vlerësuara</span><strong><?=e(money($totals['estimated']))?></strong><small><?=number_format((int)$totals['estimated_count'],0,',','.')?> biznese dhe njësi me tarifë</small></div>
  <div class="kpi kpi--info"><span>Faturuar</span><strong><?=e(money($totals['invoiced']))?></strong><small><?=number_format((int)$totals['invoice_count'],0,',','.')?> fatura</small></div>
  <div class="kpi kpi--warning"><span>Për t’u faturuar</span><strong><?=e(money($totals['to_be_invoiced']))?></strong><small>Vlerësim pa faturë</small></div>
  <div class="kpi kpi--success"><span>Paguar</span><strong><?=e(money($totals['paid']))?></strong><small><?=e($percent((float)$totals['paid'],(float)$totals['invoiced']))?> e të faturuarës</small></div>
  <div class="kpi kpi--danger"><span>Për t’u paguar</span><strong><?=e(money($totals['outstanding']))?></strong><small>Bilanci i faturave të <?=$year?></small></div>
</section>
<?php if($groups===[]):?><section class="card empty">Nuk ka të hyra të vlerësuara ose fatura për këtë zgjedhje.</section><?php else:?>
<div class="table-scroll"><table class="table">
  <thead><tr><th><?=e($level)?></th><th class="num">Me tarifë</th><th class="num">Vlerësuar</th><th class="num">Faturuar</th><th class="num">Për t’u faturuar</th><th class="num">Paguar</th><th class="num">Për t’u paguar</th></tr></thead>
  <tbody><?php foreach($groups as $group):?>
    <tr>
      <td data-label="<?=e($level)?>"><a class="cell-title" href="<?=e($url($next($group)))?>"><?=e($section===null||$division!==null?$group['key']:$group['label'])?></a><?php if($section===null||$division!==null):?><span class="cell-sub"><?=e($group['label'])?></span><?php endif;?></td>
      <td class="num" data-label="Me tarifë"><?=number_format((int)$group['estimated_count'],0,',','.')?></td>
      <td class="num" data-label="Vlerësuar"><?=e(money($group['estimated']))?></td>
      <td class="num" data-label="Faturuar"><?=e(money($group['invoiced']))?><span class="cell-sub"><?=number_format((int)$group['invoice_count'],0,',','.')?> fatura</span></td>
      <td class="num" data-label="Për t’u faturuar"><?=e(money($group['to_be_invoiced']))?></td>
      <td class="num" data-label="Paguar"><?=e(money($group['paid']))?><span class="cell-sub"><?=e($percent((float)$group['paid'],(float)$group['invoiced']))?></span></td>
      <td class="num" data-label="Për t’u paguar"><?=e(money($group['outstanding']))?></td>
    </tr><?php endforeach;?></tbody>
  <tfoot><tr><td data-label="Gjithsej">Gjithsej</td><td class="num" data-label="Me tarifë"><?=number_format((int)$totals['estimated_count'],0,',','.')?></td><td class="num" data-label="Vlerësuar"><?=e(money($totals['estimated']))?></td><td class="num" data-label="Faturuar"><?=e(money($totals['invoiced']))?></td><td class="num" data-label="Për t’u faturuar"><?=e(money($totals['to_be_invoiced']))?></td><td class="num" data-label="Paguar"><?=e(money($totals['paid']))?></td><td class="num" data-label="Për t’u paguar"><?=e(money($totals['outstanding']))?></td></tr></tfoot>
</table></div>
<?php endif;?>
<p class="help">“Paguar” këtu janë pagesat e regjistruara në faturat e vitit fiskal <?=$year?>, pavarësisht datës së pagesës. Pagesat sipas datës së arkëtimit gjenden te “Sipas muajve”.</p>

<?php elseif($mode==='nace'):?>
<?php if($codeRow!==null):?><section class="kpis" aria-label="Totalet e kodit">
  <div class="kpi kpi--success"><span>Vlerësuar</span><strong><?=e(money($codeRow['estimated']))?></strong><small><?=number_format((int)$codeRow['estimated_count'],0,',','.')?> biznese dhe njësi me tarifë</small></div>
  <div class="kpi kpi--info"><span>Faturuar</span><strong><?=e(money($codeRow['invoiced']))?></strong><small><?=number_format((int)$codeRow['invoice_count'],0,',','.')?> fatura</small></div>
  <div class="kpi kpi--warning"><span>Për t’u faturuar</span><strong><?=e(money($codeRow['to_be_invoiced']))?></strong><small><?=e($codeRow['label'])?></small></div>
  <div class="kpi kpi--success"><span>Paguar</span><strong><?=e(money($codeRow['paid']))?></strong><small><?=e($percent((float)$codeRow['paid'],(float)$codeRow['invoiced']))?> e të faturuarës</small></div>
  <div class="kpi kpi--danger"><span>Për t’u paguar</span><strong><?=e(money($codeRow['outstanding']))?></strong></div>
</section><?php endif;?>
<section class="card"><div class="card-head"><div><h2>Faturat</h2><?php if($capped($invoices)):?><p>Shfaqen <?=$limit?> faturat me mbetjen më të madhe; Excel-i i ka të gjitha.</p><?php endif;?></div><a class="btn btn--secondary" href="<?=e($exportUrl(0))?>">Excel</a></div>
<?php if($invoices===[]):?><p class="empty">Nuk ka fatura për këtë kod në <?=$year?>.</p><?php else:?>
<div class="table-scroll"><table class="table">
  <thead><tr><th>Fatura</th><th>Biznesi</th><th>Data</th><th class="num">Shuma</th><th class="num">Paguar</th><th class="num">Mbetja</th><th>Statusi</th></tr></thead>
  <tbody><?php foreach($invoices as $invoice):?><tr>
    <td data-label="Fatura"><a class="cell-title" href="<?=e(app_url('/admin/finance/invoice.php?id='.(int)$invoice['id']))?>"><?=e($invoice['invoice_number'])?></a></td>
    <td data-label="Biznesi"><a href="<?=e(app_url('/admin/businesses/view.php?id='.(int)$invoice['business_id']))?>"><?=e($invoice['business_name_snapshot'])?></a><span class="cell-sub">NRB <?=e($invoice['registration_number'])?><?=$invoice['unit_name']?' · '.e($invoice['unit_name']):''?></span></td>
    <td data-label="Data"><?=e(date_sq($invoice['issued_on']))?></td>
    <td class="num" data-label="Shuma"><?=e(money($invoice['amount']))?></td>
    <td class="num" data-label="Paguar"><?=e(money($invoice['paid_amount']))?></td>
    <td class="num" data-label="Mbetja"><?=e(money($invoice['outstanding']))?></td>
    <td data-label="Statusi"><?=badge('invoice',$invoice['status_code'])?></td>
  </tr><?php endforeach;?></tbody>
</table></div><?php endif;?></section>
<section class="card"><div class="card-head"><div><h2>Pa faturë ose të faturuar pjesërisht</h2><p>Biznese dhe njësi me tarifë për këtë kod, vlerësimi i të cilave nuk është faturuar plotësisht në <?=$year?>.<?php if($capped($notInvoiced)):?> Shfaqen <?=$limit?> të parat; Excel-i i ka të gjitha.<?php endif;?></p></div><a class="btn btn--secondary" href="<?=e($exportUrl(1))?>">Excel</a></div>
<?php if($notInvoiced===[]):?><p class="empty">Të gjitha bizneset me tarifë për këtë kod janë faturuar.</p><?php else:?>
<div class="table-scroll"><table class="table">
  <thead><tr><th>Biznesi</th><th class="num">Vlerësuar</th><th class="num">Faturuar</th><th class="num">Për t’u faturuar</th></tr></thead>
  <tbody><?php foreach($notInvoiced as $row):?><tr>
    <td data-label="Biznesi"><a class="cell-title" href="<?=e(app_url('/admin/businesses/view.php?id='.(int)$row['business_id']))?>"><?=e($row['legal_name'])?></a><span class="cell-sub">NRB <?=e($row['registration_number'])?><?=$row['unit_name']?' · Njësia '.e($row['unit_name']):''?></span></td>
    <td class="num" data-label="Vlerësuar"><?=e(money($row['estimated']))?></td>
    <td class="num" data-label="Faturuar"><?=e(money($row['invoiced']))?></td>
    <td class="num" data-label="Për t’u faturuar"><?=e(money($row['to_be_invoiced']))?></td>
  </tr><?php endforeach;?></tbody>
</table></div><?php endif;?></section>

<?php elseif($month===null): $sumInvoiced=array_sum(array_map(static fn(array $m): float => (float)$m['invoiced'],$months));$sumPaid=array_sum(array_map(static fn(array $m): float => (float)$m['paid'],$months));?>
<section class="kpis" aria-label="Totalet">
  <div class="kpi kpi--info"><span>Faturuar në <?=$year?></span><strong><?=e(money($sumInvoiced))?></strong><small>Fatura të vitit fiskal sipas muajit të lëshimit</small></div>
  <div class="kpi kpi--success"><span>Arkëtuar në <?=$year?></span><strong><?=e(money($sumPaid))?></strong><small>Pagesa sipas datës së pagesës (pa stornot)</small></div>
</section>
<div class="table-scroll"><table class="table">
  <thead><tr><th>Muaji</th><th class="num">Faturuar</th><th class="num">Fatura</th><th class="num">Arkëtuar</th><th class="num">Pagesa</th></tr></thead>
  <tbody><?php foreach($months as $row):?><tr>
    <td data-label="Muaji"><a class="cell-title" href="<?=e($url(['view'=>'months','month'=>(int)$row['month']]))?>"><?=e($monthNames[(int)$row['month']-1])?></a></td>
    <td class="num" data-label="Faturuar"><?=e(money($row['invoiced']))?></td>
    <td class="num" data-label="Fatura"><?=number_format((int)$row['invoice_count'],0,',','.')?></td>
    <td class="num" data-label="Arkëtuar"><?=e(money($row['paid']))?></td>
    <td class="num" data-label="Pagesa"><?=number_format((int)$row['payment_count'],0,',','.')?></td>
  </tr><?php endforeach;?></tbody>
  <tfoot><tr><td data-label="Gjithsej">Gjithsej</td><td class="num" data-label="Faturuar"><?=e(money($sumInvoiced))?></td><td class="num" data-label="Fatura"><?=number_format(array_sum(array_column($months,'invoice_count')),0,',','.')?></td><td class="num" data-label="Arkëtuar"><?=e(money($sumPaid))?></td><td class="num" data-label="Pagesa"><?=number_format(array_sum(array_column($months,'payment_count')),0,',','.')?></td></tr></tfoot>
</table></div>

<?php else: $sumPaid=array_sum(array_map(static fn(array $p): float => (float)$p['amount'],$payments));?>
<section class="card"><div class="card-head"><div><h2>Pagesat e arkëtuara · <?=e($monthNames[$month-1].' '.$year)?></h2><p><?=number_format(count($payments),0,',','.')?> pagesa<?=$capped($payments)?' (të parat '.$limit.'; Excel-i i ka të gjitha)':''?> · <?=e(money($sumPaid))?></p></div><a class="btn btn--secondary" href="<?=e($exportUrl(0))?>">Excel</a></div>
<?php if($payments===[]):?><p class="empty">Nuk ka pagesa në këtë muaj.</p><?php else:?>
<div class="table-scroll"><table class="table">
  <thead><tr><th>Data</th><th class="num">Shuma</th><th>Fatura</th><th>Biznesi</th><th>Burimi</th></tr></thead>
  <tbody><?php foreach($payments as $payment):?><tr>
    <td data-label="Data"><?=e(date_sq($payment['paid_on']))?></td>
    <td class="num" data-label="Shuma"><?=e(money($payment['amount']))?></td>
    <td data-label="Fatura"><a class="cell-title" href="<?=e(app_url('/admin/finance/invoice.php?id='.(int)$payment['invoice_id']))?>"><?=e($payment['invoice_number'])?></a><span class="cell-sub">Viti <?=(int)$payment['fiscal_year']?></span></td>
    <td data-label="Biznesi"><a href="<?=e(app_url('/admin/businesses/view.php?id='.(int)$payment['business_id']))?>"><?=e($payment['business_name_snapshot'])?></a><span class="cell-sub">NRB <?=e($payment['registration_number'])?></span></td>
    <td data-label="Burimi"><?=$payment['import_row_id']!==null?'Raporti i financave':'Manuale'?><?php if($payment['reference']):?><span class="cell-sub"><?=e($payment['reference'])?></span><?php endif;?></td>
  </tr><?php endforeach;?></tbody>
</table></div><?php endif;?></section>
<section class="card"><div class="card-head"><div><h2>Faturat e lëshuara · <?=e($monthNames[$month-1])?></h2><p>Faturat e vitit fiskal <?=$year?> të lëshuara në këtë muaj<?=$capped($invoices)?' (të parat '.$limit.'; Excel-i i ka të gjitha)':''?>.</p></div><a class="btn btn--secondary" href="<?=e($exportUrl(1))?>">Excel</a></div>
<?php if($invoices===[]):?><p class="empty">Nuk ka fatura të lëshuara në këtë muaj.</p><?php else:?>
<div class="table-scroll"><table class="table">
  <thead><tr><th>Fatura</th><th>Biznesi</th><th>Data</th><th class="num">Shuma</th><th class="num">Mbetja</th><th>Statusi</th></tr></thead>
  <tbody><?php foreach($invoices as $invoice):?><tr>
    <td data-label="Fatura"><a class="cell-title" href="<?=e(app_url('/admin/finance/invoice.php?id='.(int)$invoice['id']))?>"><?=e($invoice['invoice_number'])?></a></td>
    <td data-label="Biznesi"><a href="<?=e(app_url('/admin/businesses/view.php?id='.(int)$invoice['business_id']))?>"><?=e($invoice['business_name_snapshot'])?></a><span class="cell-sub">NRB <?=e($invoice['registration_number'])?><?=$invoice['unit_name']?' · '.e($invoice['unit_name']):''?></span></td>
    <td data-label="Data"><?=e(date_sq($invoice['issued_on']))?></td>
    <td class="num" data-label="Shuma"><?=e(money($invoice['amount']))?></td>
    <td class="num" data-label="Mbetja"><?=e(money($invoice['outstanding']))?></td>
    <td data-label="Statusi"><?=badge('invoice',$invoice['status_code'])?></td>
  </tr><?php endforeach;?></tbody>
</table></div><?php endif;?></section>
<?php endif;?>
