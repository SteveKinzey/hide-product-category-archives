<?php
/**
 * Plugin Name: Hide Product Category Archives
 * Description: Adds a per-product-category setting to hide a product category archive (301 redirect to Shop) while keeping products visible elsewhere.
 * Version: 1.2.0
 * Author: Stephen Kinzey, Ph.D.
 * Author URI: https://sk-america.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: hide-product-category-archives
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Hide_Product_Category_Archives {

	const META_KEY  = '_hpc_hide_product_cat_archive';
	const NONCE_KEY = 'hpc_hide_archive_nonce';
	const NONCE_ACT = 'hpc_hide_archive_save';

	public static function init(): void {

		// i18n (optional; you can add .mo/.po later)
		add_action( 'plugins_loaded', [ __CLASS__, 'load_textdomain' ] );

		// Admin UI fields
		add_action( 'product_cat_add_form_fields',  [ __CLASS__, 'add_term_field' ] );
		add_action( 'product_cat_edit_form_fields', [ __CLASS__, 'edit_term_field' ], 10, 2 );

		// Save
		add_action( 'created_product_cat', [ __CLASS__, 'save_term_meta' ], 10, 2 );
		add_action( 'edited_product_cat',  [ __CLASS__, 'save_term_meta' ], 10, 2 );

		// Redirect hidden archives
		add_action( 'template_redirect', [ __CLASS__, 'maybe_redirect_hidden_archive' ], 1 );

		// Plugins page Settings link
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), [ __CLASS__, 'plugin_action_links' ] );

		// Categories list column + toggle
		add_filter( 'manage_edit-product_cat_columns', [ __CLASS__, 'add_admin_column' ] );
		add_filter( 'manage_product_cat_custom_column', [ __CLASS__, 'render_admin_column' ], 10, 3 );
		add_action( 'admin_init', [ __CLASS__, 'handle_admin_toggle' ] );
	}

	public static function load_textdomain(): void {
		load_plugin_textdomain(
			'hide-product-category-archives',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages'
		);
	}

	/* =========================
	 * Plugins page "Settings" link
	 * ========================= */
	public static function plugin_action_links( array $links ): array {

		$url = admin_url( 'edit-tags.php?taxonomy=product_cat&post_type=product' );

		array_unshift(
			$links,
			'<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'hide-product-category-archives' ) . '</a>'
		);

		return $links;
	}

	/* =========================
	 * Term fields
	 * ========================= */
	public static function add_term_field(): void {
		?>
		<div class="form-field term-hide-archive-wrap">
			<label for="hpc_hide_archive">
				<?php esc_html_e( 'Hide category archive (redirect to Shop)', 'hide-product-category-archives' ); ?>
			</label>
			<input type="checkbox" name="hpc_hide_archive" id="hpc_hide_archive" value="1" />
			<p class="description">
				<?php esc_html_e( 'If checked, visitors who open this product category archive will be redirected to the Shop page.', 'hide-product-category-archives' ); ?>
			</p>
			<?php wp_nonce_field( self::NONCE_ACT, self::NONCE_KEY ); ?>
		</div>
		<?php
	}

	public static function edit_term_field( WP_Term $term, string $taxonomy ): void {
		$value = (int) get_term_meta( $term->term_id, self::META_KEY, true );
		?>
		<tr class="form-field term-hide-archive-wrap">
			<th scope="row">
				<label for="hpc_hide_archive">
					<?php esc_html_e( 'Hide category archive (redirect to Shop)', 'hide-product-category-archives' ); ?>
				</label>
			</th>
			<td>
				<label>
					<input type="checkbox" name="hpc_hide_archive" id="hpc_hide_archive" value="1" <?php checked( 1, $value ); ?> />
					<?php esc_html_e( 'Redirect this category archive to the Shop page.', 'hide-product-category-archives' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Products remain visible elsewhere unless you exclude them from other categories.', 'hide-product-category-archives' ); ?>
				</p>
				<?php wp_nonce_field( self::NONCE_ACT, self::NONCE_KEY ); ?>
			</td>
		</tr>
		<?php
	}

	public static function save_term_meta( int $term_id ): void {

		if ( ! current_user_can( 'manage_product_terms' ) ) {
			return;
		}

		if ( ! isset( $_POST[ self::NONCE_KEY ] ) || ! wp_verify_nonce( $_POST[ self::NONCE_KEY ], self::NONCE_ACT ) ) {
			return;
		}

		$hide = isset( $_POST['hpc_hide_archive'] ) ? 1 : 0;
		update_term_meta( $term_id, self::META_KEY, $hide );
	}

	/* =========================
	 * Frontend redirect
	 * ========================= */
	public static function maybe_redirect_hidden_archive(): void {

		if ( is_admin() ) {
			return;
		}

		// Only product category archives
		if ( ! is_tax( 'product_cat' ) ) {
			return;
		}

		$term = get_queried_object();
		if ( ! ( $term instanceof WP_Term ) || empty( $term->term_id ) ) {
			return;
		}

		$hide = (int) get_term_meta( $term->term_id, self::META_KEY, true );
		if ( 1 !== $hide ) {
			return;
		}

		// Redirect target: Shop page
		$target = function_exists( 'wc_get_page_permalink' )
			? wc_get_page_permalink( 'shop' )
			: home_url( '/' );

		if ( empty( $target ) ) {
			$target = home_url( '/' );
		}

		wp_safe_redirect( $target, 301 );
		exit;
	}

	/* =========================
	 * Admin column + quick toggle
	 * ========================= */
	public static function add_admin_column( array $columns ): array {
		$columns['hpc_hidden_archive'] = esc_html__( 'Hidden archive', 'hide-product-category-archives' );
		return $columns;
	}

	public static function render_admin_column( string $content, string $column_name, int $term_id ): string {

		if ( 'hpc_hidden_archive' !== $column_name ) {
			return $content;
		}

		$hidden = (int) get_term_meta( $term_id, self::META_KEY, true );

		$label = $hidden
			? esc_html__( 'Yes', 'hide-product-category-archives' )
			: esc_html__( 'No', 'hide-product-category-archives' );

		$toggle_url = wp_nonce_url(
			add_query_arg(
				[
					'hpc_hide_archive_toggle' => 1,
					'term_id'                 => $term_id,
					'new_value'               => $hidden ? 0 : 1,
				],
				admin_url( 'edit-tags.php?taxonomy=product_cat&post_type=product' )
			),
			'hpc_hide_archive_toggle_' . $term_id
		);

		$action_text = $hidden
			? esc_html__( 'Unhide', 'hide-product-category-archives' )
			: esc_html__( 'Hide', 'hide-product-category-archives' );

		return '<strong>' . esc_html( $label ) . '</strong><br><a href="' . esc_url( $toggle_url ) . '">' . esc_html( $action_text ) . '</a>';
	}

	public static function handle_admin_toggle(): void {

		if ( ! is_admin() ) {
			return;
		}

		if ( ! isset( $_GET['hpc_hide_archive_toggle'], $_GET['term_id'], $_GET['new_value'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_product_terms' ) ) {
			return;
		}

		$term_id   = absint( $_GET['term_id'] );
		$new_value = (int) $_GET['new_value'];

		if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'hpc_hide_archive_toggle_' . $term_id ) ) {
			return;
		}

		update_term_meta( $term_id, self::META_KEY, $new_value ? 1 : 0 );

		// Back to categories list (clean URL)
		wp_safe_redirect( admin_url( 'edit-tags.php?taxonomy=product_cat&post_type=product' ) );
		exit;
	}
}

Hide_Product_Category_Archives::init();

// WP-CLI commands: wp hpc hide|unhide|status|list <slug>
if ( defined( 'WP_CLI' ) && WP_CLI ) {

	class HPC_WP_CLI_Command {

		/**
		 * Hide a WooCommerce product category archive (redirect to Shop).
		 *
		 * ## OPTIONS
		 *
		 * <slug>
		 * : Product category slug.
		 *
		 * ## EXAMPLES
		 *
		 * wp hpc hide dekit
		 */
		public function hide( $args ) {
			$this->set_hidden( $args[0], 1 );
		}

		/**
		 * Unhide a WooCommerce product category archive.
		 *
		 * ## OPTIONS
		 *
		 * <slug>
		 * : Product category slug.
		 *
		 * ## EXAMPLES
		 *
		 * wp hpc unhide dekit
		 */
		public function unhide( $args ) {
			$this->set_hidden( $args[0], 0 );
		}

		/**
		 * Show hidden status for a product category slug.
		 *
		 * ## OPTIONS
		 *
		 * <slug>
		 * : Product category slug.
		 *
		 * ## EXAMPLES
		 *
		 * wp hpc status dekit
		 */
		public function status( $args ) {
			$slug = (string) $args[0];
			$term = get_term_by( 'slug', $slug, 'product_cat' );

			if ( ! $term || is_wp_error( $term ) ) {
				WP_CLI::error( 'Category not found: ' . $slug );
			}

			$hidden = (int) get_term_meta( $term->term_id, Hide_Product_Category_Archives::META_KEY, true );

			WP_CLI::success( sprintf(
				'%s (%s) => hidden_archive=%s',
				$term->name,
				$term->slug,
				$hidden ? 'yes' : 'no'
			) );
		}

		/**
		 * List all hidden product category archives.
		 *
		 * ## EXAMPLES
		 *
		 * wp hpc list
		 */
		public function list() {
			$terms = get_terms( [
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
			] );

			if ( is_wp_error( $terms ) ) {
				WP_CLI::error( $terms->get_error_message() );
			}

			$rows = [];
			foreach ( $terms as $term ) {
				$hidden = (int) get_term_meta( $term->term_id, Hide_Product_Category_Archives::META_KEY, true );
				if ( $hidden ) {
					$rows[] = [
						'id'   => $term->term_id,
						'slug' => $term->slug,
						'name' => $term->name,
					];
				}
			}

			if ( empty( $rows ) ) {
				WP_CLI::success( 'No hidden category archives found.' );
				return;
			}

			WP_CLI\Utils\format_items( 'table', $rows, [ 'id', 'slug', 'name' ] );
		}

		private function set_hidden( string $slug, int $value ): void {
			$term = get_term_by( 'slug', $slug, 'product_cat' );

			if ( ! $term || is_wp_error( $term ) ) {
				WP_CLI::error( 'Category not found: ' . $slug );
			}

			update_term_meta( $term->term_id, Hide_Product_Category_Archives::META_KEY, $value ? 1 : 0 );

			WP_CLI::success( sprintf(
				'%s (%s) => hidden_archive=%s',
				$term->name,
				$term->slug,
				$value ? 'yes' : 'no'
			) );
		}
	}

	WP_CLI::add_command( 'hpc', 'HPC_WP_CLI_Command' );
}