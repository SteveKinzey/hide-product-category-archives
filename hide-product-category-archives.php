<?php
/**
 * Plugin Name: Hide Product Category Archives
 * Description: Hide selected WooCommerce product category archives by redirecting them to the Shop page, while keeping products visible elsewhere.
 * Version: 1.0.0
 * Author: Stephen Kinzey, Ph.D.
 * Author URI: https://sk-america.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: hide-product-category-archives
 * Domain Path: /languages
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) exit;

final class HPC_Hide_Product_Category_Archives {

	const META_KEY  = '_hpc_hide_product_cat_archive';
	const NONCE_KEY = 'hpc_hide_archive_nonce';
	const NONCE_ACT = 'hpc_hide_archive_save';

	public static function init(): void {

		add_action( 'plugins_loaded', [ __CLASS__, 'load_textdomain' ] );

		// Term fields
		add_action( 'product_cat_add_form_fields',  [ __CLASS__, 'add_term_field' ] );
		add_action( 'product_cat_edit_form_fields', [ __CLASS__, 'edit_term_field' ], 10, 2 );
		add_action( 'created_product_cat', [ __CLASS__, 'save_term_meta' ], 10, 2 );
		add_action( 'edited_product_cat',  [ __CLASS__, 'save_term_meta' ], 10, 2 );

		// Redirect hidden archives
		add_action( 'template_redirect', [ __CLASS__, 'maybe_redirect_hidden_archive' ], 1 );

		// Admin column + quick toggle
		add_filter( 'manage_edit-product_cat_columns', [ __CLASS__, 'add_admin_column' ] );
		add_filter( 'manage_product_cat_custom_column', [ __CLASS__, 'render_admin_column' ], 10, 3 );
		add_action( 'admin_init', [ __CLASS__, 'handle_admin_toggle' ] );

		// Bulk actions
		add_filter( 'bulk_actions-edit-product_cat', [ __CLASS__, 'add_bulk_actions' ] );
		add_filter( 'handle_bulk_actions-edit-product_cat', [ __CLASS__, 'handle_bulk_actions' ], 10, 3 );
		add_action( 'admin_notices', [ __CLASS__, 'bulk_action_notice' ] );

		// Plugin list "Settings" link
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), [ __CLASS__, 'plugin_action_links' ] );
	}

	public static function load_textdomain(): void {
		load_plugin_textdomain(
			'hide-product-category-archives',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages'
		);
	}

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
			<label for="hpc_hide_archive"><?php esc_html_e( 'Hide category archive (redirect to Shop)', 'hide-product-category-archives' ); ?></label>
			<input type="checkbox" name="hpc_hide_archive" id="hpc_hide_archive" value="1" />
			<p class="description"><?php esc_html_e( 'If checked, visitors who open this product category archive will be redirected to the Shop page.', 'hide-product-category-archives' ); ?></p>
			<?php wp_nonce_field( self::NONCE_ACT, self::NONCE_KEY ); ?>
		</div>
		<?php
	}

	public static function edit_term_field( WP_Term $term, string $taxonomy ): void {
		$value = (int) get_term_meta( $term->term_id, self::META_KEY, true );
		?>
		<tr class="form-field term-hide-archive-wrap">
			<th scope="row">
				<label for="hpc_hide_archive"><?php esc_html_e( 'Hide category archive (redirect to Shop)', 'hide-product-category-archives' ); ?></label>
			</th>
			<td>
				<label>
					<input type="checkbox" name="hpc_hide_archive" id="hpc_hide_archive" value="1" <?php checked( 1, $value ); ?> />
					<?php esc_html_e( 'Redirect this category archive to the Shop page.', 'hide-product-category-archives' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'Products remain visible elsewhere unless you exclude them from other categories.', 'hide-product-category-archives' ); ?></p>
				<?php wp_nonce_field( self::NONCE_ACT, self::NONCE_KEY ); ?>
			</td>
		</tr>
		<?php
	}

	public static function save_term_meta( int $term_id ): void {

		if ( ! current_user_can( 'manage_product_terms' ) ) return;

		if ( ! isset( $_POST[ self::NONCE_KEY ] ) || ! wp_verify_nonce( $_POST[ self::NONCE_KEY ], self::NONCE_ACT ) ) return;

		$hide = isset( $_POST['hpc_hide_archive'] ) ? 1 : 0;
		update_term_meta( $term_id, self::META_KEY, $hide );
	}

	/* =========================
	 * Redirect hidden archives
	 * ========================= */

	public static function maybe_redirect_hidden_archive(): void {

		if ( is_admin() ) return;
		if ( ! is_tax( 'product_cat' ) ) return;

		$term = get_queried_object();
		if ( ! ( $term instanceof WP_Term ) || empty( $term->term_id ) ) return;

		$hide = (int) get_term_meta( $term->term_id, self::META_KEY, true );
		if ( 1 !== $hide ) return;

		// Attempt to send user back to where they came from
		$referrer = wp_get_referer();

		// Validate referrer:
		// - Must exist
		// - Must be internal
		// - Must NOT be this same category archive (avoid loops)
		if (
			$referrer &&
			0 === strpos( $referrer, home_url() ) &&
			! is_tax( 'product_cat' )
		) {
			$target = $referrer;
		} else {
			// Fallback: Shop page
			$target = function_exists( 'wc_get_page_permalink' )
				? wc_get_page_permalink( 'shop' )
				: home_url( '/' );
		}

		// Final safety fallback
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

		if ( 'hpc_hidden_archive' !== $column_name ) return $content;

		$hidden = (int) get_term_meta( $term_id, self::META_KEY, true );
		$label  = $hidden ? esc_html__( 'Yes', 'hide-product-category-archives' ) : esc_html__( 'No', 'hide-product-category-archives' );

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

		$action_text = $hidden ? esc_html__( 'Unhide', 'hide-product-category-archives' ) : esc_html__( 'Hide', 'hide-product-category-archives' );

		return '<strong>' . esc_html( $label ) . '</strong><br><a href="' . esc_url( $toggle_url ) . '">' . esc_html( $action_text ) . '</a>';
	}

	public static function handle_admin_toggle(): void {

		if ( ! is_admin() ) return;
		if ( ! isset( $_GET['hpc_hide_archive_toggle'], $_GET['term_id'], $_GET['new_value'] ) ) return;
		if ( ! current_user_can( 'manage_product_terms' ) ) return;

		$term_id   = absint( $_GET['term_id'] );
		$new_value = (int) $_GET['new_value'];

		if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'hpc_hide_archive_toggle_' . $term_id ) ) return;

		update_term_meta( $term_id, self::META_KEY, $new_value ? 1 : 0 );

		wp_safe_redirect( admin_url( 'edit-tags.php?taxonomy=product_cat&post_type=product' ) );
		exit;
	}

	/* =========================
	 * Bulk actions
	 * ========================= */

	public static function add_bulk_actions( array $actions ): array {
		$actions['hpc_hide_archives']   = esc_html__( 'Hide category archives', 'hide-product-category-archives' );
		$actions['hpc_unhide_archives'] = esc_html__( 'Unhide category archives', 'hide-product-category-archives' );
		return $actions;
	}

	public static function handle_bulk_actions( string $redirect_url, string $action, array $term_ids ): string {

		if ( ! current_user_can( 'manage_product_terms' ) ) return $redirect_url;
		if ( ! in_array( $action, [ 'hpc_hide_archives', 'hpc_unhide_archives' ], true ) ) return $redirect_url;

		$new_value = ( $action === 'hpc_hide_archives' ) ? 1 : 0;

		foreach ( $term_ids as $term_id ) {
			update_term_meta( (int) $term_id, self::META_KEY, $new_value );
		}

		return add_query_arg(
			[
				'hpc_bulk_updated' => count( $term_ids ),
				'hpc_bulk_action'  => $action,
			],
			$redirect_url
		);
	}

	public static function bulk_action_notice(): void {

		if ( ! is_admin() ) return;
		if ( empty( $_GET['hpc_bulk_updated'] ) || empty( $_GET['hpc_bulk_action'] ) ) return;

		$count  = (int) $_GET['hpc_bulk_updated'];
		$action = sanitize_text_field( $_GET['hpc_bulk_action'] );
		if ( $count <= 0 ) return;

		$message = ( $action === 'hpc_hide_archives' )
			? sprintf( _n( '%d category archive hidden.', '%d category archives hidden.', $count, 'hide-product-category-archives' ), $count )
			: sprintf( _n( '%d category archive unhidden.', '%d category archives unhidden.', $count, 'hide-product-category-archives' ), $count );

		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}
}

HPC_Hide_Product_Category_Archives::init();