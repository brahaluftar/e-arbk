<section class="card login">
  <h1>Rivendos fjalëkalimin</h1>
  <p>Shkruani emailin e llogarisë. Nëse llogaria është aktive, do të merrni një lidhje konfirmimi.</p>
  <form method="post" action="<?=e(app_url('/auth/reset-password.php'))?>"><?=csrf_field()?>
    <label class="field">Email<input type="email" name="email" maxlength="254" required autocomplete="email"></label>
    <button type="submit">Dërgo lidhjen</button>
  </form>
  <p class="help"><a href="<?=e(app_url('/auth/login.php'))?>">Kthehu te hyrja</a></p>
</section>
