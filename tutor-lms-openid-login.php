<?php
/**
 * Plugin Name: Tutor LMS OpenID Login Button
 * Description: Adds an OpenID Connect Generic login button to the Tutor LMS login and student registration forms.
 * Version: 0.1.2
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

	/** @var bool Whether the button has already been rendered on this request. */
	private static $button_rendered = false;

	public static function register(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

		// Tutor LMS template hooks (preferred).
		add_action( 'tutor_load_template_before', array( __CLASS__, 'maybe_render_on_template' ), 10, 2 );

		// Fallback hooks (older/newer Tutor versions sometimes expose these).
		add_action( 'tutor_login_form_before', array( __CLASS__, 'maybe_render_on_login_form' ) );
		add_action( 'tutor_login_form_end', array( __CLASS__, 'maybe_render_on_login_form' ) );
		add_action( 'tutor_login_form_after', array( __CLASS__, 'maybe_render_on_login_form' ) );
		add_action( 'tutor_before_student_reg_form', array( __CLASS__, 'maybe_render_on_registration_form' ) );
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

		self::render_button();
	}

	/**
	 * Render via Tutor login-form hooks (when available).
	 */
	public static function maybe_render_on_login_form(): void {
		if ( is_user_logged_in() ) {
			return;
		}

		self::render_button();
	}

	/**
	 * Render via Tutor student registration form hook (preferred).
	 */
	public static function maybe_render_on_registration_form(): void {
		if ( is_user_logged_in() ) {
			return;
		}

		self::render_button();
	}

	private static function render_button(): void {
		if ( self::$button_rendered ) {
			return;
		}

		// Only render if OpenID Connect Generic is present (it provides the shortcode).
		if ( ! shortcode_exists( 'openid_connect_generic_login_button' ) ) {
			return;
		}

		$redirect_to = self::current_url();
		$redirect_to = apply_filters( 'tutor_lms_openid_login_redirect_to', $redirect_to );

		// Build the button using the upstream plugin's shortcode so we don't couple to internals.
		$shortcode = sprintf(
			'[openid_connect_generic_login_button redirect_to="%s"]',
			esc_attr( $redirect_to )
		);

		echo self::wrap_html( do_shortcode( $shortcode ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		self::$button_rendered = true;
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

