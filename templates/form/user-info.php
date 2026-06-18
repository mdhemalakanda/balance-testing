<form method="post" class="bt-form customer_question_form">
    <?php wp_nonce_field('user_question', 'user_question', true, true); ?>
    <input type="hidden" name="user_question_form_submit">
    <div class="bt-form-group">
        <label class="bt-label" for="etunimi"><?php echo esc_html__('Etunimi', 'balance-testing'); ?></label>
        <input placeholder="Etunimi" type="text" name="etunimi" id="etunimi">
    </div>
    <div class="bt-form-group">
        <label class="bt-label"  for="ika"><?php echo esc_html__('Ikä', 'balance-testing'); ?></label>
        <input placeholder="Ikä" type="number" name="ika" id="ika">
    </div>
    <div class="bt-form-group">
        <label for="tasapaino-asteikolla" class="bt-label"><?php echo esc_html__('Tasapaino asteikolla', 'balance-testing'); ?></label>
        <div class="balance-scale-input root-wrapper">
            <div class="balance-scale"></div>
            <input type="hidden" name="asteikolla">
        </div>
        <h4 class="bt-label"><?php echo esc_html__('Mahdollinen huimausoire. Arvioisin seuraavien oireideni voimakkuutta asteikolla ', 'balance-testing'); ?></h4>
        <div class="symptoms-input">
            <div class="balance-scale-2"></div>
            <input type="hidden" name="huimausoire">
        </div>
    </div>
    <div class="bt-form-group">
        <label for="diagnosis_info" class="bt-label" ><?php echo esc_html__("Oletko saanut huimaukseen liittyviä diagnooseja? Kirjoita diagnoosit sekä arvioidut vuosiluvut alle. Esim. Hyvänlaatuinen asentohuimaus, 2015 ja 2021.", 'balance-testing'); ?></label>
        <textarea name="diagnosis_info" id="diagnosis_info" placeholder="Kirjoita vastauksesi tähän"></textarea>
    </div>
    <button type="submit"><?php echo esc_html__('Lähetä lomake', 'balance-testing'); ?></button>
</form>