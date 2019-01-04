<table class="table table-bordered table-striped table-bill">
	<tr><th>仮のボーナス金額</th><td class="text-right"><?php echo bvsl_format_print( bvsl_get_bonus_tentative() ); ?></td></tr>
	<tr><th>仮の年収</th><td class="text-right"><?php echo bvsl_format_print( bvsl_get_year_earn_bonus_tentative() ); ?></td></tr>
	<tr><th>給与所得控除</th><td class="text-right"><?php echo bvsl_format_print( bvsl_get_kyuuyo_syotoku_koujyo() ); ?></td></tr>
</table>
