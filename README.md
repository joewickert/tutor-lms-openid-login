# Tutor LMS OpenID Login Button

This plugin injects the **OpenID Connect Generic** login button into the **Tutor LMS** login form/block so students can start SSO from the Tutor login UI.

## Requirements

- Tutor LMS (free or pro)
- OpenID Connect Generic (`daggerhart-openid-connect-generic`)

## Install

1. Copy the folder `tutor-lms-openid-login/` into your WordPress site at:
   - `wp-content/plugins/tutor-lms-openid-login/`
2. Activate **Tutor LMS OpenID Login Button** in **Plugins**.

## What it does

- Hooks into Tutor’s template hook `tutor_load_template_before` and targets `global.login`.
- Falls back to `tutor_login_form_*` hooks if your Tutor LMS version provides them.
- Renders the upstream shortcode `[openid_connect_generic_login_button]` so the OpenID plugin remains the source of truth for the auth URL.
- Sets `redirect_to` to the current URL so users return to the same page after SSO.

## Customization

### Change the post-login redirect

Add this in a small mu-plugin or your theme:

```php
add_filter( 'tutor_lms_openid_login_redirect_to', function( $url ) {
	return home_url( '/dashboard/' );
} );
```

