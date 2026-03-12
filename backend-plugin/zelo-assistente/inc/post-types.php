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
    $new_columns['zelo_thumbnail'] = __( 'Foto', 'zelo-assistente' );
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
        case 'zelo_thumbnail':
            if ( has_post_thumbnail( $post_id ) ) {
                echo get_the_post_thumbnail( $post_id, array( 50, 50 ), array( 'style' => 'border-radius: 4px; border: 1px solid #ccd0d4;' ) );
            } else {
                echo '<div style="width: 50px; height: 50px; background: #f0f0f1; border-radius: 4px; border: 1px dashed #ccd0d4; display: flex; align-items: center; justify-content: center; color: #a7aaad;"><span class="dashicons dashicons-format-image"></span></div>';
            }
            break;
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

// Filter by Category and Image Status in Admin List
add_action( 'restrict_manage_posts', 'zelo_admin_locais_filters' );
function zelo_admin_locais_filters( $post_type ) {
    if ( 'zelo_local' !== $post_type ) {
        return;
    }

    // Category Filter
    $current_type = isset( $_GET['zelo_type_filter'] ) ? sanitize_key( $_GET['zelo_type_filter'] ) : '';
    $categories = zelo_get_categories_map();

    echo '<select name="zelo_type_filter">';
    echo '<option value="">' . esc_html__( 'Todas as categorias', 'zelo-assistente' ) . '</option>';
    foreach ( $categories as $slug => $cat ) {
        printf(
            '<option value="%s" %s>%s</option>',
            esc_attr( $slug ),
            selected( $current_type, $slug, false ),
            esc_html( $cat['label'] )
        );
    }
    echo '</select>';

    // Image Filter
    $current_img = isset( $_GET['zelo_img_filter'] ) ? sanitize_key( $_GET['zelo_img_filter'] ) : '';
    echo '<select name="zelo_img_filter">';
    echo '<option value="">' . esc_html__( 'Imagens: Todas', 'zelo-assistente' ) . '</option>';
    echo '<option value="with" ' . selected( $current_img, 'with', false ) . '>' . esc_html__( 'Com Imagem', 'zelo-assistente' ) . '</option>';
    echo '<option value="without" ' . selected( $current_img, 'without', false ) . '>' . esc_html__( 'Sem Imagem', 'zelo-assistente' ) . '</option>';
    echo '</select>';
}

add_filter( 'parse_query', 'zelo_admin_locais_filter_query' );
function zelo_admin_locais_filter_query( $query ) {
    global $pagenow;
    if ( is_admin() && 'edit.php' === $pagenow && 'zelo_local' === $query->query['post_type'] ) {
        
        $meta_query = array();

        // Type filter
        if ( isset( $_GET['zelo_type_filter'] ) && ! empty( $_GET['zelo_type_filter'] ) ) {
            $meta_query[] = array(
                'key'   => '_zelo_type',
                'value' => sanitize_key( $_GET['zelo_type_filter'] ),
            );
        }

        // Image filter
        if ( isset( $_GET['zelo_img_filter'] ) && ! empty( $_GET['zelo_img_filter'] ) ) {
            if ( 'with' === $_GET['zelo_img_filter'] ) {
                $meta_query[] = array(
                    'key'     => '_thumbnail_id',
                    'compare' => 'EXISTS',
                );
            } elseif ( 'without' === $_GET['zelo_img_filter'] ) {
                $meta_query[] = array(
                    'key'     => '_thumbnail_id',
                    'compare' => 'NOT EXISTS',
                );
            }
        }

        if ( ! empty( $meta_query ) ) {
            $query->query_vars['meta_query'] = $meta_query;
        }
    }
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
