<?php
$year = $financialStats['year'];
$months = $financialStats['monthly'];
$maxValue = max(1.0, ...array_map(static fn(array $month): float => max((float)$month['invoiced'],(float)$month['paid']), $months));
$chartTop = 26;
$chartBottom = 190;
$chartHeight = $chartBottom - $chartTop;
$monthLabels = ['Jan','Shk','Mar','Pri','Maj','Qer','Kor','Gus','Sht','Tet','Nën','Dhj'];
?>
<section class="finance-summary">
    <div class="finance-toolbar"><div><h2>Përmbledhje financiare · <?=$year?></h2><p>Biznese aktive me regjistrim deri në vitin e zgjedhur.</p></div><form method="get" class="year-filter"><label for="finance-year">Viti fiskal</label><select id="finance-year" name="year" onchange="this.form.submit()"><?php foreach($financialStats['years'] as $option):?><option value="<?=(int)$option?>" <?=$option===$year?'selected':''?>><?=(int)$option?></option><?php endforeach;?></select></form></div>
    <div class="finance-metrics finance-metrics-five">
        <?php foreach ([
            ['key'=>'estimated_income','label'=>'Të hyra të vlerësuara','icon'=>'<rect x="2.5" y="6" width="19" height="13" rx="2"/><path d="M2.5 10h19M7 15h3"/><circle cx="16.5" cy="13" r="2"/>','class'=>'estimate'],
            ['key'=>'invoiced','label'=>'Gjithsej faturuar','icon'=>'<path d="M6 2.5h9l4 4V21H6z"/><path d="M14.5 2.5V7H19M9 12h7M9 16h7"/>','class'=>'invoiced'],
            ['key'=>'to_be_invoiced','label'=>'Për t’u faturuar','icon'=>'<path d="M6 2.5h9l4 4V21H6z"/><path d="M14.5 2.5V7H19M9 12h7M9 16h4"/><circle cx="17.5" cy="17.5" r="3.5"/><path d="M17.5 15.7v2l1.2.8"/>','class'=>'uninvoiced'],
            ['key'=>'paid','label'=>'Gjithsej paguar','icon'=>'<circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16.5 9"/>','class'=>'paid'],
            ['key'=>'to_be_paid','label'=>'Për t’u paguar','icon'=>'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2M8 2v3m8-3v3"/>','class'=>'due'],
        ] as $card): ?>
            <article class="finance-metric <?=$card['class']?>"><span class="finance-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><?=$card['icon']?></svg></span><span class="finance-label"><?=e($card['label'])?></span><strong><?=number_format((float)$financialStats[$card['key']],2,'.',',')?> €</strong>
            <?php if($card['key']==='estimated_income'):?><small><?=number_format($financialStats['priced_businesses'])?> me tarifë · <?=number_format($financialStats['unpriced_businesses'])?> pa tarifë</small><?php elseif($card['key']==='paid'):?><small>Pagesa të regjistruara gjatë <?=$year?></small><?php elseif($card['key']==='to_be_paid'):?><small>Bilanci i faturave të vitit <?=$year?></small><?php elseif($card['key']==='to_be_invoiced'):?><small>Vlerësim pa faturë të regjistruar</small><?php else:?><small>Fatura me datë lëshimi në <?=$year?></small><?php endif;?></article>
        <?php endforeach; ?>
    </div>
    <p class="finance-note">Vlerësimi përdor tarifën me lirim, pastaj tarifën e regjistruar ose snapshot-in e klasifikimit. Nuk është vlerësim historik i normave tarifore.</p>
    <section class="panel chart-panel"><div class="section-heading"><div><h2>Faturimi dhe pagesat mujore</h2><p>Faturat sipas datës së lëshimit dhe pagesat sipas datës së pranimit.</p></div><div class="chart-legend"><span><i class="legend-invoiced"></i>Faturuar</span><span><i class="legend-paid"></i>Paguar</span></div></div>
        <div class="chart-scroll"><svg class="finance-chart" viewBox="0 0 760 230" role="img" aria-labelledby="chart-title chart-description" preserveAspectRatio="xMidYMid meet"><title id="chart-title">Faturimi dhe pagesat mujore për <?=$year?></title><desc id="chart-description">Grafik me shtylla mujore për faturat e lëshuara dhe pagesat e pranuara gjatë vitit të zgjedhur.</desc>
            <?php for($grid=0;$grid<=4;$grid++):$y=$chartTop+($chartHeight/4)*$grid;$value=$maxValue*(1-$grid/4);?><line x1="52" y1="<?=$y?>" x2="748" y2="<?=$y?>" class="chart-grid"/><text x="46" y="<?=$y+4?>" text-anchor="end" class="chart-axis"><?=number_format($value,0,'.',',')?></text><?php endfor;?>
            <?php foreach($months as $index=>$month):$x=67+$index*56;$inv=(float)$month['invoiced'];$paidMonth=(float)$month['paid'];$invHeight=$chartHeight*$inv/$maxValue;$paidHeight=$chartHeight*$paidMonth/$maxValue;?><rect x="<?=$x?>" y="<?=$chartBottom-$invHeight?>" width="17" height="<?=$invHeight?>" rx="3" class="bar-invoiced"><title><?=e($monthLabels[$index])?> faturuar: <?=number_format($inv,2,'.',',')?> €</title></rect><rect x="<?=$x+20?>" y="<?=$chartBottom-$paidHeight?>" width="17" height="<?=$paidHeight?>" rx="3" class="bar-paid"><title><?=e($monthLabels[$index])?> paguar: <?=number_format($paidMonth,2,'.',',')?> €</title></rect><text x="<?=$x+18?>" y="215" text-anchor="middle" class="chart-axis"><?=e($monthLabels[$index])?></text><?php endforeach;?>
        </svg></div>
    </section>
    <a class="button finance-manage" href="<?=e(app_url('/admin/finance/index.php?year='.$year))?>"><svg class="button-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19V5m0 14h17M8 15l4-4 3 2 5-6"/></svg>Menaxho faturat dhe pagesat</a>
</section>
