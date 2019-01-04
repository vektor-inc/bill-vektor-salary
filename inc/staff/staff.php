<?php

/*
-------------------------------------------
Add Post Type Receipt
-------------------------------------------
*/
add_action( 'init', 'bill_add_post_type_staff', 0 );
function bill_add_post_type_staff() {
	register_post_type(
		'staff',
		array(
			'labels'             => array(
				'name'         => 'スタッフ',
				'edit_item'    => 'スタッフの編集',
				'add_new_item' => 'スタッフの作成',
			),
			'public'             => false,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'has_archive'        => true,
			'supports'           => array( 'title' ),
			'menu_icon'          => 'dashicons-media-spreadsheet',
			'menu_position'      => 7,
		// 'show_in_rest'       => true,
		// 'rest_base'          => 'staff',
		)
	);
	register_taxonomy(
		'staff-cat',
		'staff',
		array(
			'hierarchical'          => true,
			'update_count_callback' => '_update_post_term_count',
			'label'                 => 'スタッフカテゴリー',
			'singular_label'        => 'スタッフカテゴリー',
			'public'                => true,
			'show_ui'               => true,
		)
	);
}
