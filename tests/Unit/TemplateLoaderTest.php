<?php
/**
 * Template loader and shell tests.
 *
 * @package MyECardExtensions
 */

namespace MyECardExtensions\Tests\Unit;

use MyECardExtensions\CardData;
use MyECardExtensions\TemplateLoader;
use MyECardExtensions\Tests\TestCase;
use MyECardExtensions\ThemeRegistry;
use WP_Post;

/**
 * Covers request interception, theme resolution, fallback and shell rendering.
 */
final class TemplateLoaderTest extends TestCase {

	/**
	 * Build a loader backed by the render fixture theme.
	 *
	 * @return TemplateLoader
	 */
	private function loader(): TemplateLoader {
		return new TemplateLoader(
			new ThemeRegistry( array( dirname( __DIR__ ) . '/fixtures/render-themes' ), '0.1.0' )
		);
	}

	/**
	 * Create and register a card.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $props   Extra post properties.
	 * @return WP_Post
	 */
	private function card( int $post_id = 50, array $props = array() ): WP_Post {
		$post = new WP_Post(
			array_merge(
				array(
					'ID'            => $post_id,
					'post_type'     => 'ecard_digital_card',
					'post_title'    => 'Jane Doe',
					'post_name'     => 'jane-doe',
					'post_status'   => 'publish',
					'post_password' => '',
					'post_content'  => '<p>Welcome</p>',
				),
				$props
			)
		);

		$GLOBALS['my_ecard_test_posts'][ $post_id ] = $post;

		return $post;
	}

	/**
	 * Mark the current request as a singular view of a post.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function request_for( int $post_id ): void {
		$GLOBALS['my_ecard_test_singular']   = true;
		$GLOBALS['my_ecard_test_queried_id'] = $post_id;
	}

	/**
	 * Assign a theme to a card and give it some data.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $theme   Theme id.
	 * @return void
	 */
	private function assign_theme( int $post_id, string $theme ): void {
		update_post_meta( $post_id, '_my_ecard_theme', $theme );
		update_post_meta( $post_id, '_ecard_designation', 'Architect' );
		update_post_meta( $post_id, '_ecard_company_name', 'ACME' );
		update_post_meta( $post_id, '_ecard_email', 'jane@example.com' );
	}

	/**
	 * Absolute shell path.
	 *
	 * @return string
	 */
	private function shell(): string {
		return MY_ECARD_PATH . 'templates/shell.php';
	}

	/**
	 * Render the shell and return its output.
	 *
	 * @return string
	 */
	private function render_shell(): string {
		ob_start();
		include MY_ECARD_PATH . 'templates/shell.php';

		return (string) ob_get_clean();
	}

	/**
	 * A non-singular request is never intercepted.
	 *
	 * @return void
	 */
	public function test_non_singular_request_is_ignored(): void {
		$card = $this->card();
		$this->assign_theme( $card->ID, 'render' );

		$this->assert_same( 'original.php', $this->loader()->filter_template( 'original.php' ) );
	}

	/**
	 * Singular requests for other post types are never intercepted.
	 *
	 * @return void
	 */
	public function test_other_post_type_is_ignored(): void {
		$page = new WP_Post(
			array(
				'ID'        => 51,
				'post_type' => 'page',
			)
		);

		$GLOBALS['my_ecard_test_posts'][51] = $page;
		$this->request_for( 51 );

		$this->assert_same( 'page.php', $this->loader()->filter_template( 'page.php' ) );
	}

	/**
	 * A published card with a registered theme switches to the addon shell.
	 *
	 * @return void
	 */
	public function test_valid_theme_switches_to_shell(): void {
		$card = $this->card();
		$this->assign_theme( $card->ID, 'render' );
		$this->request_for( $card->ID );

		$loader  = $this->loader();
		$result  = $loader->filter_template( 'original.php' );
		$context = $loader->context();

		$this->assert_same( $this->shell(), $result );
		$this->assert_not_null( $context );
		$this->assert_same( TemplateLoader::MODE_CARD, $context['mode'] );
		$this->assert_same( $card->ID, $context['post_id'] );
		$this->assert_same( 'render', $context['theme']['id'] );
		$this->assert_contains( 'render-themes/render/template.php', $context['template'] );
		$this->assert_same( 'Jane Doe', $context['card']->title );
		$this->assert_same( $context, get_query_var( TemplateLoader::QUERY_VAR ) );
	}

	/**
	 * A card without an assigned theme keeps the ECard template.
	 *
	 * @return void
	 */
	public function test_unassigned_theme_falls_back(): void {
		$card = $this->card();
		$this->request_for( $card->ID );

		$this->assert_same( 'original.php', $this->loader()->filter_template( 'original.php' ) );
	}

	/**
	 * An unregistered theme id keeps the ECard template.
	 *
	 * @return void
	 */
	public function test_unknown_theme_falls_back(): void {
		$card = $this->card();
		$this->assign_theme( $card->ID, 'ghost' );
		$this->request_for( $card->ID );

		$this->assert_same( 'original.php', $this->loader()->filter_template( 'original.php' ) );
	}

	/**
	 * Draft cards keep the ECard template.
	 *
	 * @return void
	 */
	public function test_draft_card_falls_back(): void {
		$card = $this->card( 52, array( 'post_status' => 'draft' ) );
		$this->assign_theme( $card->ID, 'render' );
		$this->request_for( $card->ID );

		$this->assert_same( 'original.php', $this->loader()->filter_template( 'original.php' ) );
	}

	/**
	 * Private cards keep the ECard template.
	 *
	 * @return void
	 */
	public function test_private_card_falls_back(): void {
		$card = $this->card( 53, array( 'post_status' => 'private' ) );
		$this->assign_theme( $card->ID, 'render' );
		$this->request_for( $card->ID );

		$this->assert_same( 'original.php', $this->loader()->filter_template( 'original.php' ) );
	}

	/**
	 * Password-protected cards render only the core password form.
	 *
	 * @return void
	 */
	public function test_password_card_uses_password_context(): void {
		$card = $this->card( 54, array( 'post_password' => 'secret' ) );
		$this->assign_theme( $card->ID, 'render' );
		$this->request_for( $card->ID );

		$loader  = $this->loader();
		$result  = $loader->filter_template( 'original.php' );
		$context = $loader->context();

		$this->assert_same( $this->shell(), $result );
		$this->assert_same( TemplateLoader::MODE_PASSWORD, $context['mode'] );
		$this->assert_null( $context['card'] );
		$this->assert_same( array(), $context['theme'] );
		$this->assert_same( TemplateLoader::MODE_PASSWORD, get_query_var( TemplateLoader::QUERY_VAR )['mode'] );
	}

	/**
	 * Feeds, embeds, AJAX, previews and admin requests are ignored.
	 *
	 * @return void
	 */
	public function test_non_frontend_requests_are_ignored(): void {
		$card = $this->card();
		$this->assign_theme( $card->ID, 'render' );
		$this->request_for( $card->ID );

		$flags = array(
			'my_ecard_test_is_feed',
			'my_ecard_test_is_embed',
			'my_ecard_test_doing_ajax',
			'my_ecard_test_is_preview',
			'my_ecard_test_is_admin',
		);

		foreach ( $flags as $flag ) {
			$GLOBALS[ $flag ] = true;

			$this->assert_same( 'original.php', $this->loader()->filter_template( 'original.php' ), $flag );

			$GLOBALS[ $flag ] = false;
		}
	}

	/**
	 * A theme template removed after discovery falls back instead of warning.
	 *
	 * @return void
	 */
	public function test_runtime_missing_template_falls_back(): void {
		$base = '/tmp/opencode/my-ecard-loader-' . getmypid();
		$dir  = $base . '/gone';

		if ( ! is_dir( $dir ) && ! mkdir( $dir, 0777, true ) && ! is_dir( $dir ) ) {
			$this->fail( 'Unable to create the temporary theme directory.' );
			return;
		}

		file_put_contents(
			$dir . '/theme.json',
			'{"id":"gone","name":"Gone","template":"template.php"}'
		);
		file_put_contents( $dir . '/template.php', '<?php' );

		$registry = new ThemeRegistry( array( $base ), '0.1.0' );

		$this->assert_key( 'gone', $registry->all() );

		unlink( $dir . '/template.php' );

		$card = $this->card();
		$this->assign_theme( $card->ID, 'gone' );
		$this->request_for( $card->ID );

		$this->assert_same( 'original.php', ( new TemplateLoader( $registry ) )->filter_template( 'original.php' ) );

		unlink( $dir . '/theme.json' );
		rmdir( $dir );
		rmdir( $base );
	}

	/**
	 * A filter returning an invalid context triggers the fallback.
	 *
	 * @return void
	 */
	public function test_invalid_filtered_context_falls_back(): void {
		$card = $this->card();
		$this->assign_theme( $card->ID, 'render' );
		$this->request_for( $card->ID );

		add_filter(
			'my_ecard_render_context',
			static function () {
				return array( 'mode' => 'card' );
			}
		);

		$loader = $this->loader();

		$this->assert_same( 'original.php', $loader->filter_template( 'original.php' ) );
		$this->assert_null( $loader->context() );
	}

	/**
	 * An exception during resolution never produces a fatal error.
	 *
	 * @return void
	 */
	public function test_context_filter_exception_falls_back(): void {
		$card = $this->card();
		$this->assign_theme( $card->ID, 'render' );
		$this->request_for( $card->ID );

		add_filter(
			'my_ecard_render_context',
			static function () {
				throw new \RuntimeException( 'boom' );
			}
		);

		$loader = $this->loader();

		$this->assert_same( 'original.php', $loader->filter_template( 'original.php' ) );
		$this->assert_null( $loader->context() );
	}

	/**
	 * The shell runs the WordPress document hooks exactly once.
	 *
	 * @return void
	 */
	public function test_shell_renders_wordpress_hooks_and_card_data(): void {
		$card = $this->card();
		$this->assign_theme( $card->ID, 'render' );
		$this->request_for( $card->ID );

		$this->loader()->filter_template( 'original.php' );

		$html = $this->render_shell();

		$this->assert_contains( '<!DOCTYPE html>', $html );
		$this->assert_contains( 'lang="en-US"', $html );
		$this->assert_contains( 'UTF-8', $html );
		$this->assert_same( 1, substr_count( $html, '<!--my-ecard-test-head-->' ) );
		$this->assert_same( 1, substr_count( $html, '<!--my-ecard-test-body-open-->' ) );
		$this->assert_same( 1, substr_count( $html, '<!--my-ecard-test-footer-->' ) );
		$this->assert_contains( 'single-ecard_digital_card', $html );
		$this->assert_contains( 'data-my-ecard-shell', $html );
		$this->assert_contains( 'data-my-ecard-template="render"', $html );
		$this->assert_contains( 'Jane Doe', $html );
		$this->assert_contains( 'Architect', $html );
		$this->assert_contains( 'jane@example.com', $html );
		$this->assert_contains( 'Render Fixture', $html );
		$this->assert_contains( 'fixture-footer|ACME', $html );

		$this->assert_true( strpos( $html, '<!--my-ecard-test-head-->' ) < strpos( $html, '<!--my-ecard-test-body-open-->' ) );
		$this->assert_true( strpos( $html, '<!--my-ecard-test-body-open-->' ) < strpos( $html, 'data-my-ecard-template="render"' ) );
		$this->assert_true( strpos( $html, 'data-my-ecard-template="render"' ) < strpos( $html, '<!--my-ecard-test-footer-->' ) );
	}

	/**
	 * The password shell renders the core form and leaks no card data.
	 *
	 * @return void
	 */
	public function test_password_shell_hides_card_data(): void {
		$card = $this->card( 55, array( 'post_password' => 'secret' ) );
		$this->assign_theme( $card->ID, 'render' );
		$this->request_for( $card->ID );

		$this->loader()->filter_template( 'original.php' );

		$html = $this->render_shell();

		$this->assert_contains( 'data-my-ecard-password', $html );
		$this->assert_contains( 'post-password-form', $html );
		$this->assert_contains( '<!--my-ecard-test-head-->', $html );
		$this->assert_contains( '<!--my-ecard-test-footer-->', $html );
		$this->assert_false( strpos( $html, 'data-my-ecard-template="render"' ) );
		$this->assert_false( strpos( $html, 'jane@example.com' ) );
		$this->assert_false( strpos( $html, 'Architect' ) );
		$this->assert_false( strpos( $html, 'ACME' ) );
	}

	/**
	 * The shell is silent when no context was resolved.
	 *
	 * @return void
	 */
	public function test_shell_without_context_is_empty(): void {
		$this->assert_same( '', $this->render_shell() );
	}

	/**
	 * Template functions are safe without a resolved context.
	 *
	 * @return void
	 */
	public function test_template_functions_are_safe_without_context(): void {
		$this->assert_same( array(), \MyECardExtensions\my_ecard_context() );
		$this->assert_null( \MyECardExtensions\my_ecard_card() );
		$this->assert_same( array(), \MyECardExtensions\my_ecard_theme() );

		ob_start();
		\MyECardExtensions\my_ecard_template_part( 'parts/footer.php' );
		$output = (string) ob_get_clean();

		$this->assert_same( '', $output );
	}

	/**
	 * Partials cannot escape the theme directory.
	 *
	 * @return void
	 */
	public function test_template_partial_rejects_traversal(): void {
		$card = $this->card();
		$this->assign_theme( $card->ID, 'render' );
		$this->request_for( $card->ID );

		$this->loader()->filter_template( 'original.php' );

		ob_start();
		\MyECardExtensions\my_ecard_template_part( '../../../../etc/passwd' );
		$traversal = (string) ob_get_clean();

		ob_start();
		\MyECardExtensions\my_ecard_template_part( 'parts/missing.php' );
		$missing = (string) ob_get_clean();

		$this->assert_same( '', $traversal );
		$this->assert_same( '', $missing );
	}

	/**
	 * CardData is exposed as the normalized card inside the theme.
	 *
	 * @return void
	 */
	public function test_theme_template_receives_card_data(): void {
		$card = $this->card();
		$this->assign_theme( $card->ID, 'render' );
		$this->request_for( $card->ID );

		$loader  = $this->loader();
		$loader->filter_template( 'original.php' );
		$context = $loader->context();

		ob_start();
		\MyECardExtensions\my_ecard_render_theme_template( $context );
		$html = (string) ob_get_clean();

		$this->assert_contains( 'data-my-ecard-template="render"', $html );
		$this->assert_contains( 'Jane Doe', $html );
		$this->assert_contains( 'jane@example.com', $html );
		$this->assert_contains( 'fixture-footer', $html );
	}
}
