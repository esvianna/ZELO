<?php
if (!defined('ABSPATH')) {
    exit;
}

// Register API Routes
add_action('rest_api_init', function () {
    register_rest_route('zelo/v1', '/auth/login', [
        'methods'  => 'POST',
        'callback' => 'zelo_api_login',
        'permission_callback' => '__return_true', // Public endpoint
    ]);
});

/**
 * Handle Login Request
 */
function zelo_api_login($request) {
    $creds = [
        'user_login'    => $request->get_param('username'),
        'user_password' => $request->get_param('password'),
        'remember'      => true,
    ];

    // Authenticate
    $user = wp_signon($creds, false);

    if (is_wp_error($user)) {
        return new WP_Error(
            'zelo_auth_failed', 
            'Usuário ou senha inválidos.', 
            ['status' => 401]
        );
    }

    // Get User Data
    $avatar_url = get_avatar_url($user->ID);
    $roles = $user->roles;

    return rest_ensure_response([
        'success' => true,
        'user' => [
            'id' => $user->ID,
            'name' => $user->display_name,
            'email' => $user->user_email,
            'avatar' => $avatar_url,
            'roles' => $roles,
            'caps' => [
                'view_ops' => user_can( $user, 'zelo_view_ops' ),
                'checkin_ops' => user_can( $user, 'zelo_checkin_ops' ),
                'reallocate_ops' => user_can( $user, 'zelo_reallocate_volunteer' ),
                'manage_ops' => user_can( $user, 'zelo_manage_ops' ),
            ],
        ],
        'nonce' => wp_create_nonce('wp_rest'), // For future authorized requests
    ]);
}
