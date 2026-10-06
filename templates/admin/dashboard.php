<section class="hero"><h1>Paneli operativ</h1><p>Regjistri ARBK, tarifat e vlerësuara dhe proceset e sinkronizimit e klasifikimit.</p></section>
<section class="metrics">
<?php foreach(['total'=>'Biznese gjithsej','active'=>'Aktive','deactivated'=>'Të çaktivizuara nga ATK','unclassified'=>'Pa klasifikim','automatic'=>'Klasifikime automatike','manual'=>'Klasifikime manuale','ambiguous'=>'Klasifikime të paqarta'] as $key=>$label):?><a class="metric" href="<?=e(app_url('/admin/businesses/classification.php'.($key==='ambiguous'?'?classification=AMBIGUOUS':'')))?>"><strong><?=number_format((int)($stats[$key]??0))?></strong><span><?=e($label)?></span></a><?php endforeach;?></section>
<?php if (is_array($financialStats)): ?>
<section class="finance-section" aria-labelledby="finance-heading">
	<div class="section-heading"><div><h2 id="finance-heading">Përmbledhje financiare</h2><p>Vlerësim orientues për bizneset aktive me tarifë të regjistruar.</p></div><span class="data-period">Të dhënat aktuale</span></div>
	<div class="finance-metrics">
		<article class="finance-metric estimate">
			<span class="finance-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="2.5" y="6" width="19" height="13" rx="2"/><path d="M2.5 10h19M7 15h3"/><circle cx="16.5" cy="13" r="2"/></svg></span>
			<span class="finance-label">Të hyra të vlerësuara</span>
			<strong><?=number_format((float)($financialStats['estimated_income']??0),2,'.',',')?> €</strong>
			<small><?=number_format((int)($financialStats['priced_businesses']??0))?> me tarifë · <?=number_format((int)($financialStats['unpriced_businesses']??0))?> pa tarifë</small>
		</article>
		<?php foreach ([
			['label'=>'Faturuar','icon'=>'<path d="M6 2.5h9l4 4V21H6z"/><path d="M14.5 2.5V7H19M9 12h7M9 16h7"/>'],
			['label'=>'Për faturim','icon'=>'<path d="M6 2.5h9l4 4V21H6z"/><path d="M14.5 2.5V7H19M9 12h7M9 16h4"/><circle cx="17.5" cy="17.5" r="3.5"/><path d="M17.5 15.7v2l1.2.8"/>'],
			['label'=>'Paguar','icon'=>'<circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16.5 9"/>'],
		] as $card): ?>
			<article class="finance-metric untracked">
				<span class="finance-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><?=$card['icon']?></svg></span>
				<span class="finance-label"><?=e($card['label'])?></span>
				<strong class="untracked-value">Nuk regjistrohet</strong>
				<small>Nuk ka të dhëna faturash ose pagesash në sistem.</small>
			</article>
		<?php endforeach; ?>
	</div>
	<p class="finance-note">Vlerësimi mbledh tarifën me lirim kur ekziston, përndryshe tarifën e regjistruar ose snapshot-in e klasifikimit. Nuk zëvendëson faturimin kontabël.</p>
</section>
<?php endif; ?>
<section class="panel"><h2>Veprimet e fazës 2</h2><p>Sinkronizimi ATK dhe klasifikimi automatik ekzekutohen si procese CLI të rinisshme: <code>php bin/sync-atk.php</code> dhe <code>php bin/classify-nace.php</code>. Klasifikimi automatik pranon vetëm një përputhje të vetme.</p></section>
