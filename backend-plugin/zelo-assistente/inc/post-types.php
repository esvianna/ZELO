<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function zelo_register_post_type_locais() {
	$labels = array(
		'name'                  => _x( 'Locais', 'Post Type General Name', 'zelo-assistente' ),
		'singular_name'         => _x( 'Local', 'Post Type Singular Name', 'zelo-assistente' ),
		'menu_name'             => __( 'Locais Zelo', 'zelo-assistente' ),
		'name_admin_bar'        => __( 'Local', 'zelo-assistente' ),
		'archives'              => __( 'Arquivos de Locais', 'zelo-assistente' ),
		'attributes'            => __( 'Atributos de Local', 'zelo-assistente' ),
		'parent_item_colon'     => __( 'Local Pai:', 'zelo-assistente' ),
		'all_items'             => __( 'Todos os Locais', 'zelo-assistente' ),
		'add_new_item'          => __( 'Adicionar Novo Local', 'zelo-assistente' ),
		'add_new'               => __( 'Adicionar Novo', 'zelo-assistente' ),
		'new_item'              => __( 'Novo Local', 'zelo-assistente' ),
		'edit_item'             => __( 'Editar Local', 'zelo-assistente' ),
		'update_item'           => __( 'Atualizar Local', 'zelo-assistente' ),
		'view_item'             => __( 'Ver Local', 'zelo-assistente' ),
		'view_items'            => __( 'Ver Locais', 'zelo-assistente' ),
		'search_items'          => __( 'Procurar Local', 'zelo-assistente' ),
		'not_found'             => __( 'Não encontrado', 'zelo-assistente' ),
		'not_found_in_trash'    => __( 'Não encontrado no Lixo', 'zelo-assistente' ),
		'featured_image'        => __( 'Imagem Destacada', 'zelo-assistente' ),
		'set_featured_image'    => __( 'Definir imagem destacada', 'zelo-assistente' ),
		'remove_featured_image' => __( 'Remover imagem destacada', 'zelo-assistente' ),
		'use_featured_image'    => __( 'Usar como imagem destacada', 'zelo-assistente' ),
		'insert_into_item'      => __( 'Inserir no local', 'zelo-assistente' ),
		'uploaded_to_this_item' => __( 'Enviado para este local', 'zelo-assistente' ),
		'items_list'            => __( 'Lista de locais', 'zelo-assistente' ),
		'items_list_navigation' => __( 'Navegação da lista de locais', 'zelo-assistente' ),
		'filter_items_list'     => __( 'Filtrar lista de locais', 'zelo-assistente' ),
	);
	$args = array(
		'label'                 => __( 'Local', 'zelo-assistente' ),
		'description'           => __( 'Locais de interesse para o evento Zelo', 'zelo-assistente' ),
		'labels'                => $labels,
		'supports'              => array( 'title', 'editor', 'thumbnail' ), // editor for 'observacoes', thumbnail for photos
		'taxonomies'            => array(),
		'hierarchical'          => false,
		'public'                => false, // Not public on frontend directly
		'show_ui'               => true,
		'show_in_menu'          => true,
		'menu_position'         => 5,
		'menu_icon'             => 'dashicons-location',
		'show_in_admin_bar'     => true,
		'show_in_nav_menus'     => false,
		'can_export'            => true,
		'has_archive'           => false,
		'exclude_from_search'   => true,
		'publicly_queryable'    => false,
		'capability_type'       => 'post',
	);
	register_post_type( 'zelo_local', $args );
}
add_action( 'init', 'zelo_register_post_type_locais', 0 );

// Add Columns to Admin List
add_filter( 'manage_zelo_local_posts_columns', 'zelo_set_custom_edit_zelo_local_columns' );
function zelo_set_custom_edit_zelo_local_columns($columns) {
    $new_columns = array();
    $new_columns['cb'] = $columns['cb'];
    $new_columns['title'] = $columns['title'];
    $new_columns['zelo_category'] = __( 'Categoria', 'zelo-assistente' );
    $new_columns['zelo_address'] = __( 'Endereço', 'zelo-assistente' );
    $new_columns['zelo_phone'] = __( 'Telefone', 'zelo-assistente' );
    $new_columns['date'] = $columns['date'];
    return $new_columns;
}

add_action( 'manage_zelo_local_posts_custom_column', 'zelo_custom_zelo_local_column', 10, 2 );
function zelo_custom_zelo_local_column( $column, $post_id ) {
    switch ( $column ) {
        case 'zelo_category':
            $type = get_post_meta( $post_id, '_zelo_type', true );
            echo esc_html( ucfirst( $type ) );
            break;
        case 'zelo_address':
            echo esc_html( get_post_meta( $post_id, '_zelo_address', true ) );
            break;
        case 'zelo_phone':
            echo esc_html( get_post_meta( $post_id, '_zelo_phone', true ) );
            break;
    }
}

// Button "Limpar todos os locais" above the list (outside the list table form to avoid nested forms)
add_action( 'all_admin_notices', 'zelo_render_clear_all_locais_button' );
function zelo_render_clear_all_locais_button() {
	$screen = get_current_screen();
	if ( ! $screen || $screen->id !== 'edit-zelo_local' || $screen->post_type !== 'zelo_local' ) {
		return;
	}
	$count = wp_count_posts( 'zelo_local' );
	$total = (int) $count->publish + (int) $count->draft + (int) $count->trash + (int) $count->private;
	if ( $total === 0 ) {
		return;
	}
	?>
	<div class="notice" style="margin-top: 10px; margin-bottom: 0;">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline;" onsubmit="return confirm('<?php echo esc_js( __( 'Remover TODOS os locais do banco? Esta ação não pode ser desfeita.', 'zelo-assistente' ) ); ?>');">
			<input type="hidden" name="action" value="zelo_clear_all_locais">
			<?php wp_nonce_field( 'zelo_clear_all_locais' ); ?>
			<input type="submit" class="button" value="<?php echo esc_attr( sprintf( __( 'Limpar todos os locais (%d)', 'zelo-assistente' ), $total ) ); ?>" style="color: #b32d2e;">
		</form>
		<span style="margin-left: 8px; color: #646970;"><?php esc_html_e( 'Remove todos os locais do banco de dados. Use antes de um novo teste de importação.', 'zelo-assistente' ); ?></span>
	</div>
	<?php
}

add_action( 'admin_post_zelo_clear_all_locais', 'zelo_handle_clear_all_locais' );
function zelo_handle_clear_all_locais() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sem permissão.', 'zelo-assistente' ) );
	}
	if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'zelo_clear_all_locais' ) ) {
		wp_die( esc_html__( 'Link de segurança inválido.', 'zelo-assistente' ) );
	}
	$posts = get_posts( array(
		'post_type'      => 'zelo_local',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	) );
	$deleted = 0;
	foreach ( $posts as $post_id ) {
		if ( wp_delete_post( (int) $post_id, true ) ) {
			$deleted++;
		}
	}
	$redirect = add_query_arg( 'zelo_cleared', $deleted, admin_url( 'edit.php?post_type=zelo_local' ) );
	wp_safe_redirect( $redirect );
	exit;
}

add_action( 'admin_notices', 'zelo_clear_all_locais_notice' );
function zelo_clear_all_locais_notice() {
	if ( ! isset( $_GET['zelo_cleared'] ) || ! isset( $_GET['post_type'] ) || $_GET['post_type'] !== 'zelo_local' ) {
		return;
	}
	$count = (int) $_GET['zelo_cleared'];
	if ( $count === 0 ) {
		return;
	}
	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( _n( '%d local removido.', '%d locais removidos.', $count, 'zelo-assistente' ), $count ) ) . '</p></div>';
}
