<?php
/**
 * Novidades / blog — Posts WP na PWA (ZELO#26).
 *
 * @package Zelo_Assistente
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ZELO_NEWS_META_IN_APP', '_zelo_in_app' );
define( 'ZELO_NEWS_META_NOTIFY', '_zelo_as_notification' );
define( 'ZELO_NEWS_META_PRIORITY', '_zelo_notification_priority' );
define( 'ZELO_NEWS_META_CAROUSEL', '_zelo_carousel' );

/**
 * @return bool
 */
function zelo_rest_is_logged_in() {
	return is_user_logged_in();
}

/**
 * Texto plano para JSON (título, resumo) — decodifica entidades HTML e normaliza travessões.
 *
 * @param string $text Raw text.
 * @return string
 */
function zelo_news_plain_text( $text ) {
	$text = wp_strip_all_tags( (string) $text );
	$prev = '';
	while ( $prev !== $text ) {
		$prev = $text;
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}
	// Travessões tipográficos → hífen ASCII (evita &#8211; / – no frontend).
	$text = str_replace(
		array( '–', '—', '−', '&#8211;', '&#8212;', '&ndash;', '&mdash;' ),
		'-',
		$text
	);
	return $text;
}

/**
 * @param WP_Post $post Post.
 * @return array<string, mixed>
 */
function zelo_news_format_list_item( $post ) {
	$thumb_id = get_post_thumbnail_id( $post->ID );
	$image    = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'medium_large' ) : '';
	$excerpt  = has_excerpt( $post ) ? $post->post_excerpt : wp_trim_words( wp_strip_all_tags( $post->post_content ), 32, '…' );
	$priority = get_post_meta( $post->ID, ZELO_NEWS_META_PRIORITY, true );
	if ( $priority !== 'important' ) {
		$priority = 'normal';
	}

	$plain_excerpt = wp_strip_all_tags( $excerpt );

	return array(
		'id'               => (int) $post->ID,
		'slug'             => $post->post_name,
		'title'            => zelo_news_plain_text( get_post_field( 'post_title', $post->ID ) ),
		'excerpt'          => zelo_news_plain_text( $plain_excerpt ),
		'featured_image'   => $image ? $image : '',
		'published_at'     => get_post_time( 'c', true, $post ),
		'as_notification'  => get_post_meta( $post->ID, ZELO_NEWS_META_NOTIFY, true ) === '1',
		'priority'         => $priority,
	);
}

/**
 * @param WP_Post $post Post.
 * @return array<string, mixed>
 */
function zelo_news_format_detail( $post ) {
	$item                   = zelo_news_format_list_item( $post );
	$item['content_html']   = wp_kses_post( apply_filters( 'the_content', $post->post_content ) );
	$author                 = get_userdata( (int) $post->post_author );
	$item['author_name']    = $author ? zelo_news_plain_text( $author->display_name ) : '';
	return $item;
}

/**
 * Remove transients de listagem /news (chaves com sufixo hash).
 */
function zelo_news_delete_list_transients() {
	global $wpdb;

	$like_v1       = $wpdb->esc_like( '_transient_zelo_news_list_v1_' ) . '%';
	$like_v1_to    = $wpdb->esc_like( '_transient_timeout_zelo_news_list_v1_' ) . '%';
	$like_value    = $wpdb->esc_like( '_transient_zelo_news_list_v2_' ) . '%';
	$like_timeout  = $wpdb->esc_like( '_transient_timeout_zelo_news_list_v2_' ) . '%';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
			$like_v1,
			$like_v1_to,
			$like_value,
			$like_timeout
		)
	);
}

/**
 * @param array<string, mixed> $args Query args.
 * @return WP_Query
 */
function zelo_news_query( $args = array() ) {
	$meta_query = array(
		'relation' => 'AND',
		array(
			'key'   => ZELO_NEWS_META_IN_APP,
			'value' => '1',
		),
	);

	if ( ! empty( $args['notifications_only'] ) ) {
		$meta_query[] = array(
			'key'   => ZELO_NEWS_META_NOTIFY,
			'value' => '1',
		);
	}

	if ( ! empty( $args['carousel_only'] ) ) {
		$meta_query[] = array(
			'key'   => ZELO_NEWS_META_CAROUSEL,
			'value' => '1',
		);
		$meta_query[] = array(
			'key'     => '_thumbnail_id',
			'compare' => 'EXISTS',
		);
	}

	$defaults = array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'posts_per_page' => 20,
		'paged'          => 1,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'meta_query'     => $meta_query,
	);

	if ( isset( $args['paged'] ) ) {
		$defaults['paged'] = max( 1, (int) $args['paged'] );
	}
	if ( isset( $args['per_page'] ) ) {
		$defaults['posts_per_page'] = min( 50, max( 1, (int) $args['per_page'] ) );
	}

	return new WP_Query( $defaults );
}

/**
 * Invalida cache de listagem ao gravar post.
 *
 * @param int $post_id Post ID.
 */
function zelo_news_bust_cache( $post_id ) {
	if ( wp_is_post_revision( $post_id ) ) {
		return;
	}
	$post = get_post( $post_id );
	if ( ! $post || $post->post_type !== 'post' ) {
		return;
	}
	zelo_news_delete_list_transients();
}

add_action( 'save_post', 'zelo_news_bust_cache' );

/**
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function zelo_get_news_list( $request ) {
	$page               = max( 1, (int) $request->get_param( 'page' ) );
	$per_page           = min( 50, max( 1, (int) $request->get_param( 'per_page' ) ?: 20 ) );
	$notifications_only = rest_sanitize_boolean( $request->get_param( 'notifications_only' ) );
	$carousel_only      = rest_sanitize_boolean( $request->get_param( 'carousel_only' ) );

	if ( $carousel_only ) {
		$per_page = min( 8, max( 1, $per_page ) );
		$page     = 1;
	}

	$cache_key = 'zelo_news_list_v2_' . md5( wp_json_encode( array( $page, $per_page, $notifications_only, $carousel_only ) ) );
	$cached    = get_transient( $cache_key );
	if ( is_array( $cached ) ) {
		return rest_ensure_response( $cached );
	}

	$query = zelo_news_query(
		array(
			'paged'              => $page,
			'per_page'           => $per_page,
			'notifications_only' => $notifications_only,
			'carousel_only'      => $carousel_only,
		)
	);

	$items = array();
	foreach ( $query->posts as $post ) {
		$items[] = zelo_news_format_list_item( $post );
	}

	$response = array(
		'items'    => $items,
		'page'     => $page,
		'per_page' => $per_page,
		'total'    => (int) $query->found_posts,
	);

	set_transient( $cache_key, $response, 10 * MINUTE_IN_SECONDS );

	return rest_ensure_response( $response );
}

/**
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function zelo_get_news_item( $request ) {
	$id   = (int) $request['id'];
	$post = get_post( $id );

	if ( ! $post || $post->post_type !== 'post' || $post->post_status !== 'publish' ) {
		return new WP_Error( 'zelo_news_not_found', __( 'Novidade não encontrada.', 'zelo-assistente' ), array( 'status' => 404 ) );
	}

	if ( get_post_meta( $id, ZELO_NEWS_META_IN_APP, true ) !== '1' ) {
		return new WP_Error( 'zelo_news_not_found', __( 'Novidade não encontrada.', 'zelo-assistente' ), array( 'status' => 404 ) );
	}

	return rest_ensure_response( zelo_news_format_detail( $post ) );
}

function zelo_register_news_routes() {
	register_rest_route(
		'zelo/v1',
		'/news',
		array(
			'methods'             => 'GET',
			'callback'            => 'zelo_get_news_list',
			'permission_callback' => 'zelo_rest_can_view_ops',
			'args'                => array(
				'page'               => array(
					'type'              => 'integer',
					'default'           => 1,
					'sanitize_callback' => 'absint',
				),
				'per_page'           => array(
					'type'              => 'integer',
					'default'           => 20,
					'sanitize_callback' => 'absint',
				),
				'notifications_only' => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'carousel_only'      => array(
					'type'    => 'boolean',
					'default' => false,
				),
			),
		)
	);

	register_rest_route(
		'zelo/v1',
		'/news/(?P<id>\d+)',
		array(
			'methods'             => 'GET',
			'callback'            => 'zelo_get_news_item',
			'permission_callback' => 'zelo_rest_can_view_ops',
			'args'                => array(
				'id' => array(
					'type'              => 'integer',
					'required'          => true,
					'sanitize_callback' => 'absint',
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'zelo_register_news_routes' );

function zelo_news_add_meta_boxes() {
	add_meta_box(
		'zelo_news_app',
		__( 'Zelo — App móvel', 'zelo-assistente' ),
		'zelo_news_render_meta_box',
		'post',
		'side',
		'default'
	);
}
add_action( 'add_meta_boxes', 'zelo_news_add_meta_boxes' );

/**
 * @param WP_Post $post Post.
 */
function zelo_news_render_meta_box( $post ) {
	wp_nonce_field( 'zelo_news_save_meta', 'zelo_news_meta_nonce' );

	$in_app    = get_post_meta( $post->ID, ZELO_NEWS_META_IN_APP, true ) === '1';
	$notify    = get_post_meta( $post->ID, ZELO_NEWS_META_NOTIFY, true ) === '1';
	$carousel  = get_post_meta( $post->ID, ZELO_NEWS_META_CAROUSEL, true ) === '1';
	$priority = get_post_meta( $post->ID, ZELO_NEWS_META_PRIORITY, true );
	if ( $priority !== 'important' ) {
		$priority = 'normal';
	}
	?>
	<p>
		<label>
			<input type="checkbox" name="zelo_in_app" value="1" <?php checked( $in_app ); ?> />
			<?php esc_html_e( 'Publicar na PWA', 'zelo-assistente' ); ?>
		</label>
	</p>
	<p>
		<label>
			<input type="checkbox" name="zelo_as_notification" value="1" <?php checked( $notify ); ?> <?php disabled( ! $in_app ); ?> id="zelo_as_notification" />
			<?php esc_html_e( 'Mostrar como notificação (sino)', 'zelo-assistente' ); ?>
		</label>
	</p>
	<p>
		<label>
			<input type="checkbox" name="zelo_carousel" value="1" <?php checked( $carousel ); ?> <?php disabled( ! $in_app ); ?> id="zelo_carousel" />
			<?php esc_html_e( 'Destaque no carrossel da home', 'zelo-assistente' ); ?>
		</label>
	</p>
	<p>
		<label for="zelo_notification_priority"><?php esc_html_e( 'Destaque', 'zelo-assistente' ); ?></label>
		<select name="zelo_notification_priority" id="zelo_notification_priority" class="widefat">
			<option value="normal" <?php selected( $priority, 'normal' ); ?>><?php esc_html_e( 'Normal', 'zelo-assistente' ); ?></option>
			<option value="important" <?php selected( $priority, 'important' ); ?>><?php esc_html_e( 'Importante', 'zelo-assistente' ); ?></option>
		</select>
	</p>
	<p class="description">
		<?php esc_html_e( 'Conteúdo em português. Visível apenas a utilizadores logados na app. Carrossel exige imagem destacada.', 'zelo-assistente' ); ?>
	</p>
	<script>
	(function () {
		var inApp = document.querySelector('input[name="zelo_in_app"]');
		var notify = document.getElementById('zelo_as_notification');
		var carousel = document.getElementById('zelo_carousel');
		if (!inApp) return;
		function syncInAppDeps() {
			var on = inApp.checked;
			if (notify) {
				notify.disabled = !on;
				if (!on) notify.checked = false;
			}
			if (carousel) {
				carousel.disabled = !on;
				if (!on) carousel.checked = false;
			}
		}
		inApp.addEventListener('change', syncInAppDeps);
		syncInAppDeps();
	})();
	</script>
	<?php
}

/**
 * @param int $post_id Post ID.
 */
function zelo_news_save_meta_box( $post_id ) {
	if ( ! isset( $_POST['zelo_news_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['zelo_news_meta_nonce'] ) ), 'zelo_news_save_meta' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$post = get_post( $post_id );
	if ( ! $post || $post->post_type !== 'post' ) {
		return;
	}

	$in_app = ! empty( $_POST['zelo_in_app'] );
	update_post_meta( $post_id, ZELO_NEWS_META_IN_APP, $in_app ? '1' : '0' );

	$notify = $in_app && ! empty( $_POST['zelo_as_notification'] );
	update_post_meta( $post_id, ZELO_NEWS_META_NOTIFY, $notify ? '1' : '0' );

	$carousel = $in_app && ! empty( $_POST['zelo_carousel'] );
	update_post_meta( $post_id, ZELO_NEWS_META_CAROUSEL, $carousel ? '1' : '0' );

	$priority = isset( $_POST['zelo_notification_priority'] ) ? sanitize_key( wp_unslash( $_POST['zelo_notification_priority'] ) ) : 'normal';
	if ( $priority !== 'important' ) {
		$priority = 'normal';
	}
	update_post_meta( $post_id, ZELO_NEWS_META_PRIORITY, $priority );
}
add_action( 'save_post', 'zelo_news_save_meta_box' );
