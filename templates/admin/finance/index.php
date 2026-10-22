<div class="page-head"><div><h1>Financat</h1><p>Përmbledhja e faturimit, pagesave dhe detyrimeve të papaguara.</p></div>
  <div class="page-actions"><a class="btn" href="<?=e(app_url('/admin/finance/payments.php'))?>">Regjistro pagesa</a><a class="btn btn--secondary" href="<?=e(app_url('/admin/finance/invoices.php?year='.$year))?>">Faturat <?=$year?></a></div></div>
<?php $summaryAction='/admin/finance/index.php'; require __DIR__.'/summary.php'; ?>
<section class="card" aria-labelledby="obligations-title">
  <div class="card-head"><div><h2 id="obligations-title">Detyrimet e papaguara</h2><p>Raporti për Drejtorinë e Financave: klientët dhe shumat e papaguara për secilin klient.</p></div></div>
  <dl class="totals">
    <div><dt>Klientë me borxh</dt><dd><?=number_format($obligations['clients'],0,',','.')?></dd></div>
    <div><dt>Fatura të papaguara</dt><dd><?=number_format($obligations['invoices'],0,',','.')?></dd></div>
    <div><dt>Me afat të kaluar</dt><dd><?=e(money($obligations['overdue']))?></dd></div>
    <div class="is-strong"><dt>Gjithsej e papaguar</dt><dd><?=e(money($obligations['outstanding']))?></dd></div>
  </dl>
  <form method="get" action="<?=e(app_url('/admin/finance/obligations-export.php'))?>" class="form-grid disclosure">
    <label class="field">Vitet fiskale<select name="year"><option value="">Të gjitha vitet</option><?php for($y=(int)date('Y');$y>=(int)date('Y')-10;$y--):?><option value="<?=$y?>">Deri në vitin <?=$y?></option><?php endfor;?></select></label>
    <div class="form-actions"><button>Shkarko klientët (Excel)</button></div>
  </form>
</section>
