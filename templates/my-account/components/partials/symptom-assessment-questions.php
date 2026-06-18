<?php
/**
 * Two symptom assessment questions (0–10 scale) for Alkukysely and Edistymisen seuranta.
 *
 * @package BalanceTesting
 */

defined( 'ABSPATH' ) || exit;

$bt_scale_questions = [
	[
		'name'  => 'oireiden_voimakkuus',
		'label' => __( 'I. Oireiden voimakkuus asteikolla 0–10 (0 = ei oireita, 10 = voimakkain mahdollinen oire)', 'balance-testing' ),
	],
	[
		'name'  => 'vaikutus_toimintakykyyn',
		'label' => __( 'II. Vaikutus toimintakykyyn asteikolla 0–10 (0 = ei haittaa, 10 = suurin mahdollinen haitta)', 'balance-testing' ),
	],
];
?>
<div class="bt-form-group bt-symptom-assessment">
	<h3 class="bt-label"><?php echo esc_html__( 'Arvioi huimaus- ja epätasapaino-oireitasi viimeisen kahden viikon ajalta:', 'balance-testing' ); ?></h3>
	<div class="balance-scale-input root-wrapper">
		<?php foreach ( $bt_scale_questions as $bt_question ) : ?>
			<div class="bt-item-single">
				<label><?php echo esc_html( $bt_question['label'] ); ?></label>
				<div class="value-group">
					<div class="bt-radio-groups">
						<?php for ( $i = 0; $i <= 10; $i++ ) : ?>
							<?php
							$input_id = $bt_question['name'] . '_' . $i;
							?>
							<div class="bt-radio-group-single-styled">
								<input type="radio" value="<?php echo esc_attr( (string) $i ); ?>" name="<?php echo esc_attr( $bt_question['name'] ); ?>" id="<?php echo esc_attr( $input_id ); ?>">
								<label for="<?php echo esc_attr( $input_id ); ?>"><?php echo esc_html( (string) $i ); ?></label>
							</div>
						<?php endfor; ?>
					</div>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
</div>
