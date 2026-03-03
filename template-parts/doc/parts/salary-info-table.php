<?php
/**
 * 給与明細 - 支給分・発行日・Staff No. テーブル。
 *
 * frame-salary.php / frame-salary-pdf.php 共通パーツ。
 *
 * @package Bill_Vektor_Salary
 */

global $post;
?>
<table class="bill-info-table">
<tbody>
	<tr>
		<th>支給分</th>
		<td>
		<?php
		$terms = get_the_terms( get_the_ID(), 'salary-term' );
		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
			echo esc_html( $terms[0]->name );
		}
		?>
		</td>
	</tr>
	<tr>
		<th>発行日</th>
		<td><?php echo get_the_date(); ?></td>
	</tr>
	<?php if ( $post->salary_staff_number ) : ?>
	<tr>
		<th>Staff No.</th>
		<td><?php echo esc_html( $post->salary_staff_number ); ?></td>
	</tr>
	<?php endif; ?>
</tbody>
</table>
