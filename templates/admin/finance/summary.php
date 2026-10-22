<?php
$year = $financialStats['year'];
$months = $financialStats['monthly'];
$maxValue = max(1.0, ...array_map(static fn(array $month): float => max((float)$month['invoiced'],(float)$month['paid']), $months));
$chartTop = 26; $chartBottom = 190; $chartHeight = $chartBottom - $chartTop;
$monthLabels = ['Jan','Shk','Mar','Pri','Maj','Qer','Kor','Gus','Sht','Tet','Nën','Dhj'];
$cards = [
    ['estimated_income','Të hyra të vlerësuara','success',number_format($financialStats['priced_businesses'],0,',','.').' biznese dhe '.number_format($financialStats['priced_units'],0,',','.').' njësi me tarifë · '.number_format($financialStats['unpriced_businesses'],0,',','.').' pa tarifë'],
    ['invoiced','Gjithsej faturuar','info','Fatura të vitit fiskal '.$year.' (pa të anuluarat)'],
    ['to_be_invoiced','Për t’u faturuar','warning','Vlerësim pa faturë të krijuar'],
    ['paid','Gjithsej paguar','success','Pagesa me datë në '.$year.' (pa stornot)'],
    ['to_be_paid','Për t’u paguar','danger','Bilanci i faturave të vitit '.$year],
];
?>
<section class="card" aria-labelledby="finance-summary-title">
  <div class="card-head">
    <div><h2 id="finance-summary-title">Përmbledhje financiare · <?=$year?></h2><p>Bizneset aktive të regjistruara deri në vitin e zgjedhur dhe njësitë me faturim automatik.</p></div>
    <form method="get" action="<?=e(app_url($summaryAction ?? '/admin/finance/index.php'))?>" class="cluster"><label class="field">Viti fiskal<select name="year"><?php foreach($financialStats['years'] as $option):?><option value="<?=(int)$option?>" <?=$option===$year?'selected':''?>><?=(int)$option?></option><?php endforeach;?></select></label><button class="btn--secondary">Shfaq</button></form>
  </div>
  <div class="kpis">
    <?php foreach ($cards as [$key,$label,$tone,$note]): ?>
      <div class="kpi kpi--<?=e($tone)?>"><span><?=e($label)?></span><strong><?=e(money($financialStats[$key]))?></strong><small><?=e($note)?></small></div>
    <?php endforeach; ?>
  </div>
  <p class="help">Vlerësimi përdor të njëjtin rregull si faturimi vjetor: tarifën me lirim, pastaj tarifën e regjistruar (sipas konfigurimit <code>BILLING_TARIFF_SOURCES</code>).</p>
  <div class="chart-head"><h3>Faturimi dhe pagesat mujore</h3><div class="chart-legend"><span><i class="legend-invoiced"></i>Faturuar</span><span><i class="legend-paid"></i>Paguar</span></div></div>
  <svg class="chart-svg" viewBox="0 0 760 230" role="img" aria-labelledby="chart-title"><title id="chart-title">Faturimi dhe pagesat mujore për <?=$year?></title>
    <?php for($grid=0;$grid<=4;$grid++):$y=$chartTop+($chartHeight/4)*$grid;$value=$maxValue*(1-$grid/4);?><line x1="62" y1="<?=$y?>" x2="748" y2="<?=$y?>" class="chart-grid"/><text x="56" y="<?=$y+4?>" text-anchor="end" class="chart-axis"><?=number_format($value,0,',','.')?></text><?php endfor;?>
    <?php foreach($months as $index=>$month):$x=72+$index*56;$inv=(float)$month['invoiced'];$paidMonth=(float)$month['paid'];$invHeight=$chartHeight*$inv/$maxValue;$paidHeight=$chartHeight*$paidMonth/$maxValue;?><rect x="<?=$x?>" y="<?=$chartBottom-$invHeight?>" width="17" height="<?=$invHeight?>" rx="3" class="bar-invoiced"><title><?=e($monthLabels[$index])?> faturuar: <?=e(money($inv))?></title></rect><rect x="<?=$x+20?>" y="<?=$chartBottom-$paidHeight?>" width="17" height="<?=$paidHeight?>" rx="3" class="bar-paid"><title><?=e($monthLabels[$index])?> paguar: <?=e(money($paidMonth))?></title></rect><text x="<?=$x+18?>" y="215" text-anchor="middle" class="chart-axis"><?=e($monthLabels[$index])?></text><?php endforeach;?>
  </svg>
  <ul class="chart-list">
    <?php foreach($months as $index=>$month): $invWidth=round(100*(float)$month['invoiced']/$maxValue,2);$paidWidth=round(100*(float)$month['paid']/$maxValue,2);?><li><strong><?=e($monthLabels[$index])?></strong><svg class="hbar" viewBox="0 0 100 9" preserveAspectRatio="none" aria-hidden="true"><rect class="bar-invoiced" width="<?=$invWidth?>" height="4" rx="1"/><rect class="bar-paid" y="5" width="<?=$paidWidth?>" height="4" rx="1"/></svg><span class="values">Faturuar <?=e(money($month['invoiced']))?> · Paguar <?=e(money($month['paid']))?></span></li><?php endforeach;?>
  </ul>
</section>
