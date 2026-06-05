<?php
/**
 * Plugin Name: Tutor LMS OpenID Login Button
 * Description: Adds an OpenID Connect Generic login button to Tutor LMS login, registration, and login modal forms.
 * Version: 0.1.4
 * Author: Summer Hill Media
 * Requires at least: 5.0
 * Requires PHP: 7.4
 *
 * @package TutorLmsOpenidLogin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Tutor_LMS_OpenID_Login_Button {
	const VERSION = '0.1.0';

	/** @var string[] Contexts that have already rendered the button on this request. */
	private static $button_rendered_contexts = array();

	public static function register(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

		// Tutor LMS template hooks (preferred).
		add_action( 'tutor_load_template_before', array( __CLASS__, 'maybe_render_on_template' ), 10, 2 );

		// Login form + modal (views/modal/login.php loads templates/login-form.php).
		add_action( 'tutor_before_login_form', array( __CLASS__, 'maybe_render_on_login_form' ) );

		// Fallback hooks (older/newer Tutor versions sometimes expose these).
		add_action( 'tutor_login_form_before', array( __CLASS__, 'maybe_render_on_login_form' ) );
		add_action( 'tutor_login_form_end', array( __CLASS__, 'maybe_render_on_login_form' ) );
		add_action( 'tutor_login_form_after', array( __CLASS__, 'maybe_render_on_login_form' ) );
		add_action( 'tutor_before_student_reg_form', array( __CLASS__, 'maybe_render_on_registration_form' ) );

		// Custom theme modals may render login-form.php outside the modal; fix placement in the browser.
		add_action( 'wp_footer', array( __CLASS__, 'maybe_fixup_login_modal_button' ), 20 );
	}

	public static function enqueue_assets(): void {
		if ( is_user_logged_in() ) {
			return;
		}

		wp_register_style(
			'tutor-lms-openid-login',
			plugins_url( 'assets/tutor-lms-openid-login.css', __FILE__ ),
			array(),
			self::VERSION
		);
		wp_enqueue_style( 'tutor-lms-openid-login' );
	}

	/**
	 * Render within the Tutor template system.
	 *
	 * @param string $template Template slug (e.g. global.login).
	 * @param mixed  $vars     Variables passed to template.
	 */
	public static function maybe_render_on_template( $template, $vars ): void {
		unset( $vars );

		if ( is_user_logged_in() ) {
			return;
		}

		// Login page only; registration uses tutor_before_student_reg_form for placement above the form.
		if ( 'global.login' !== $template ) {
			return;
		}

		self::render_button( 'login' );
	}

	/**
	 * Render via Tutor login-form hooks (login page and login modal).
	 */
	public static function maybe_render_on_login_form(): void {
		if ( is_user_logged_in() ) {
			return;
		}

		self::render_button( 'login' );
	}

	/**
	 * Render via Tutor student registration form hook (preferred).
	 */
	public static function maybe_render_on_registration_form(): void {
		if ( is_user_logged_in() ) {
			return;
		}

		self::render_button( 'registration' );
	}

	/**
	 * Place the login button inside custom Tutor login modals when PHP hooks render it elsewhere.
	 */
	public static function maybe_fixup_login_modal_button(): void {
		if ( is_user_logged_in() || ! shortcode_exists( 'openid_connect_generic_login_button' ) ) {
			return;
		}

		$has_login_button = in_array( 'login', self::$button_rendered_contexts, true );

		echo '<script id="tutor-lms-openid-login-modal-fix">' . self::login_modal_fixup_script() . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		if ( $has_login_button ) {
			return;
		}

		$fallback_html = self::get_button_html( 'login-modal' );
		if ( '' === $fallback_html ) {
			return;
		}

		echo '<div id="tutor-lms-openid-login-modal-fallback" hidden style="display:none;">' . $fallback_html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * @param string $context Unique render context (e.g. login, registration).
	 */
	private static function render_button( string $context = 'default' ): void {
		$html = self::get_button_html( $context );
		if ( '' === $html ) {
			return;
		}

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * @param string $context Unique render context (e.g. login, registration).
	 */
	private static function get_button_html( string $context = 'default' ): string {
		if ( in_array( $context, self::$button_rendered_contexts, true ) ) {
			return '';
		}

		if ( ! shortcode_exists( 'openid_connect_generic_login_button' ) ) {
			return '';
		}

		$redirect_to = self::current_url();
		$redirect_to = apply_filters( 'tutor_lms_openid_login_redirect_to', $redirect_to );

		$shortcode = sprintf(
			'[openid_connect_generic_login_button redirect_to="%s"]',
			esc_attr( $redirect_to )
		);

		$html = self::wrap_html( do_shortcode( $shortcode ) );
		if ( '' === $html ) {
			return '';
		}

		self::$button_rendered_contexts[] = $context;
		return $html;
	}

	private static function login_modal_fixup_script(): string {
		return <<<'JS'
document.addEventListener('DOMContentLoaded', function() {
	var modal = document.querySelector('.tutor-login-modal, .tutor-modal.tutor-login-modal');
	if (!modal) {
		return;
	}

	var form = modal.querySelector('#tutor-login-form');
	if (!form) {
		return;
	}

	if (modal.querySelector('.tutor-lms-openid-login')) {
		return;
	}

	var orphan = modal.previousElementSibling;
	if (orphan && orphan.classList && orphan.classList.contains('tutor-lms-openid-login')) {
		form.parentNode.insertBefore(orphan, form);
		return;
	}

	var fallback = document.getElementById('tutor-lms-openid-login-modal-fallback');
	if (!fallback || !fallback.firstElementChild) {
		return;
	}

	form.parentNode.insertBefore(fallback.firstElementChild, form);
});
JS;
	}

	private static function current_url(): string {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		$request_uri = is_string( $request_uri ) ? $request_uri : '/';

		// Preserve current path + query so users return to the same page after SSO.
		return esc_url_raw( home_url( $request_uri ) );
	}

	private static function wrap_html( string $button_html ): string {
		if ( '' === trim( $button_html ) ) {
			return '';
		}

		return sprintf(
			'<div class="tutor-lms-openid-login">%s</div>',
			$button_html
		);
	}
}

add_action( 'plugins_loaded', array( 'Tutor_LMS_OpenID_Login_Button', 'register' ) );

