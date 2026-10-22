<section class="card login">
  <img class="login-logo" src="<?=e(app_url('/logo_prishtina.png'))?>" alt="Logoja e Komunës së Prishtinës">
  <h1>Hyrje në sistem</h1>
  <p>Hyni si zyrtar komunal ose si përfaqësues i biznesit.</p>
  <form method="post"><?=csrf_field()?>
    <label class="field">Email<input type="email" name="email" required autocomplete="username"></label>
    <label class="field">Fjalëkalimi<input type="password" name="password" required autocomplete="current-password"></label>
    <button type="submit">Hyr</button>
  </form>
  <p class="help"><a href="<?=e(app_url('/auth/reset-password.php'))?>">Keni harruar fjalëkalimin?</a></p>
</section>
