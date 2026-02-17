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
		'supports'              => array( 'title', 'editor' ), // editor for 'observacoes'
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
