<?php
/**
 * Admin Dashboard
 *
 * @package     WooCommerce\Admin
 * @version     2.1.0
 */

use Automattic\Jetpack\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WC_Admin_Dashboard', false ) ) :

	/**
	 * WC_Admin_Dashboard Class.
	 */
	class WC_Admin_Dashboard {

		/**
		 * Hook in tabs.
		 */
		public function __construct() {
			// Only hook in admin parts if the user has admin access.
			if ( current_user_can( 'view_woocommerce_reports' ) || current_user_can( 'manage_woocommerce' ) || current_user_can( 'publish_shop_orders' ) ) {
				// If on network admin, only load the widget that works in that context and skip the rest.
				if ( is_multisite() && is_network_admin() ) {
					add_action( 'wp_network_dashboard_setup', array( $this, 'register_network_order_widget' ) );
				} else {
					add_action( 'wp_dashboard_setup', array( $this, 'init' ) );
				}
			}
		}

		/**
		 * Init dashboard widgets.
		 */
		public function init() {
			// Reviews Widget.
			if ( current_user_can( 'publish_shop_orders' ) && post_type_supports( 'product', 'comments' ) ) {
				wp_add_dashboard_widget( 'woocommerce_dashboard_recent_reviews', __( 'WooCommerce Recent Reviews', 'woocommerce' ), array( $this, 'recent_reviews' ) );
			}
			wp_add_dashboard_widget( 'woocommerce_dashboard_status', __( 'WooCommerce Status', 'woocommerce' ), array( $this, 'status_widget' ) );

			// Network Order Widget.
			if ( is_multisite() && is_main_site() ) {
				$this->register_network_order_widget();
			}
		}

		/**
		 * Register the network order dashboard widget.
		 */
		public function register_network_order_widget() {
			wp_add_dashboard_widget( 'woocommerce_network_orders', __( 'WooCommerce Network Orders', 'woocommerce' ), array( $this, 'network_orders' ) );
		}

		/**
		 * Get top seller from DB.
		 *
		 * @return object
		 */
		private function get_top_seller() {
			global $wpdb;

			$query            = array();
			$query['fields']  = "SELECT SUM( order_item_meta.meta_value ) as qty, order_item_meta_2.meta_value as product_id
			FROM {$wpdb->posts} as posts";
			$query['join']    = "INNER JOIN {$wpdb->prefix}woocommerce_order_items AS order_items ON posts.ID = order_id ";
			$query['join']   .= "INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS order_item_meta ON order_items.order_item_id = order_item_meta.order_item_id ";
			$query['join']   .= "INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS order_item_meta_2 ON order_items.order_item_id = order_item_meta_2.order_item_id ";
			$query['where']   = "WHERE posts.post_type IN ( '" . implode( "','", wc_get_order_types( 'order-count' ) ) . "' ) ";
			$query['where']  .= "AND posts.post_status IN ( 'wc-" . implode( "','wc-", apply_filters( 'woocommerce_reports_order_statuses', array( 'completed', 'processing', 'on-hold' ) ) ) . "' ) ";
			$query['where']  .= "AND order_item_meta.meta_key = '_qty' ";
			$query['where']  .= "AND order_item_meta_2.meta_key = '_product_id' ";
			$query['where']  .= "AND posts.post_date >= '" . date( 'Y-m-01', current_time( 'timestamp' ) ) . "' ";
			$query['where']  .= "AND posts.post_date <= '" . date( 'Y-m-d H:i:s', current_time( 'timestamp' ) ) . "' ";
			$query['groupby'] = 'GROUP BY product_id';
			$query['orderby'] = 'ORDER BY qty DESC';
			$query['limits']  = 'LIMIT 1';

			return $wpdb->get_row( implode( ' ', apply_filters( 'woocommerce_dashboard_status_widget_top_seller_query', $query ) ) ); //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		/**
		 * Get sales report data.
		 *
		 * @return object
		 */
		private function get_sales_report_data() {
			include_once dirname( __FILE__ ) . '/reports/class-wc-report-sales-by-date.php';

			$sales_by_date                 = new WC_Report_Sales_By_Date();
			$sales_by_date->start_date     = strtotime( date( 'Y-m-01', current_time( 'timestamp' ) ) );
			$sales_by_date->end_date       = strtotime( date( 'Y-m-d', current_time( 'timestamp' ) ) );
			$sales_by_date->chart_groupby  = 'day';
			$sales_by_date->group_by_query = 'YEAR(posts.post_date), MONTH(posts.post_date), DAY(posts.post_date)';

			return $sales_by_date->get_report_data();
		}

		/**
		 * Show status widget.
		 */
		public function status_widget() {
			include_once dirname( __FILE__ ) . '/reports/class-wc-admin-report.php';

			$reports = new WC_Admin_Report();

			echo '<ul class="wc_status_list">';

			if ( current_user_can( 'view_woocommerce_reports' ) ) {
				$report_data = $this->get_sales_report_data();
				if ( $report_data ) {
					?>
				<li class="sales-this-month">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-reports&tab=orders&range=month' ) ); ?>">
					<?php echo $reports->sales_sparkline( '', max( 7, date( 'd', current_time( 'timestamp' ) ) ) ); // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped ?>
					<?php
						printf(
							/* translators: %s: net sales */
							esc_html__( '%s net sales this month', 'woocommerce' ),
							'<strong>' . wc_price( $report_data->net_sales ) . '</strong>'
						); // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped
					?>
					</a>
				</li>
					<?php
				}

				$top_seller = $this->get_top_seller();
				if ( $top_seller && $top_seller->qty ) {
					?>
				<li class="best-seller-this-month">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-reports&tab=orders&report=sales_by_product&range=month&product_ids=' . $top_seller->product_id ) ); ?>">
					<?php echo $reports->sales_sparkline( $top_seller->product_id, max( 7, date( 'd', current_time( 'timestamp' ) ) ), 'count' ); // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped ?>
					<?php
						printf(
							/* translators: 1: top seller product title 2: top seller quantity */
							esc_html__( '%1$s top seller this month (sold %2$d)', 'woocommerce' ),
							'<strong>' . get_the_title( $top_seller->product_id ) . '</strong>',
							$top_seller->qty
						); // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped
					?>
					</a>
				</li>
					<?php
				}
			}

			$this->status_widget_order_rows();
			$this->status_widget_stock_rows();

			do_action( 'woocommerce_after_dashboard_status_widget', $reports );
			echo '</ul>';
		}

		/**
		 * Show order data is status widget.
		 */
		private function status_widget_order_rows() {
			if ( ! current_user_can( 'edit_shop_orders' ) ) {
				return;
			}
			$on_hold_count    = 0;
			$processing_count = 0;

			foreach ( wc_get_order_types( 'order-count' ) as $type ) {
				$counts            = (array) wp_count_posts( $type );
				$on_hold_count    += isset( $counts['wc-on-hold'] ) ? $counts['wc-on-hold'] : 0;
				$processing_count += isset( $counts['wc-processing'] ) ? $counts['wc-processing'] : 0;
			}
			?>
			<li class="processing-orders">
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_status=wc-processing&post_type=shop_order' ) ); ?>">
				<?php
					printf(
						/* translators: %s: order count */
						_n( '<strong>%s order</strong> awaiting processing', '<strong>%s orders</strong> awaiting processing', $processing_count, 'woocommerce' ),
						$processing_count
					); // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped
				?>
				</a>
			</li>
			<li class="on-hold-orders">
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_status=wc-on-hold&post_type=shop_order' ) ); ?>">
				<?php
					printf(
						/* translators: %s: order count */
						_n( '<strong>%s order</strong> on-hold', '<strong>%s orders</strong> on-hold', $on_hold_count, 'woocommerce' ),
						$on_hold_count
					); // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped
				?>
				</a>
			</li>
			<?php
		}

		/**
		 * Show stock data is status widget.
		 */
		private function status_widget_stock_rows() {
			global $wpdb;

			// Requires lookup table added in 3.6.
			if ( version_compare( get_option( 'woocommerce_db_version', null ), '3.6', '<' ) ) {
				return;
			}

			$stock   = absint( max( get_option( 'woocommerce_notify_low_stock_amount' ), 1 ) );
			$nostock = absint( max( get_option( 'woocommerce_notify_no_stock_amount' ), 0 ) );

			$transient_name   = 'wc_low_stock_count';
			$lowinstock_count = get_transient( $transient_name );

			if ( false === $lowinstock_count ) {
				/**
				 * Status widget low in stock count pre query.
				 *
				 * @since 4.3.0
				 * @param null|string $low_in_stock_count Low in stock count, by default null.
				 * @param int         $stock              Low stock amount.
				 * @param int         $nostock            No stock amount
				 */
				$lowinstock_count = apply_filters( 'woocommerce_status_widget_low_in_stock_count_pre_query', null, $stock, $nostock );

				if ( is_null( $lowinstock_count ) ) {
					$lowinstock_count = $wpdb->get_var(
						$wpdb->prepare(
							"SELECT COUNT( product_id )
							FROM {$wpdb->wc_product_meta_lookup} AS lookup
							INNER JOIN {$wpdb->posts} as posts ON lookup.product_id = posts.ID
							WHERE stock_quantity <= %d
							AND stock_quantity > %d
							AND posts.post_status = 'publish'",
							$stock,
							$nostock
						)
					);
				}

				set_transient( $transient_name, (int) $lowinstock_count, DAY_IN_SECONDS * 30 );
			}

			$transient_name   = 'wc_outofstock_count';
			$outofstock_count = get_transient( $transient_name );

			if ( false === $outofstock_count ) {
				/**
				 * Status widget out of stock count pre query.
				 *
				 * @since 4.3.0
				 * @param null|string $outofstock_count Out of stock count, by default null.
				 * @param int         $nostock          No stock amount
				 */
				$outofstock_count = apply_filters( 'woocommerce_status_widget_out_of_stock_count_pre_query', null, $nostock );

				if ( is_null( $outofstock_count ) ) {
					$outofstock_count = (int) $wpdb->get_var(
						$wpdb->prepare(
							"SELECT COUNT( product_id )
							FROM {$wpdb->wc_product_meta_lookup} AS lookup
							INNER JOIN {$wpdb->posts} as posts ON lookup.product_id = posts.ID
							WHERE stock_quantity <= %d
							AND posts.post_status = 'publish'",
							$nostock
						)
					);
				}

				set_transient( $transient_name, (int) $outofstock_count, DAY_IN_SECONDS * 30 );
			}
			?>
			<li class="low-in-stock">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-reports&tab=stock&report=low_in_stock' ) ); ?>">
				<?php
					printf(
						/* translators: %s: order count */
						_n( '<strong>%s product</strong> low in stock', '<strong>%s products</strong> low in stock', $lowinstock_count, 'woocommerce' ),
						$lowinstock_count
					); // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped
				?>
				</a>
			</li>
			<li class="out-of-stock">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-reports&tab=stock&report=out_of_stock' ) ); ?>">
				<?php
					printf(
						/* translators: %s: order count */
						_n( '<strong>%s product</strong> out of stock', '<strong>%s products</strong> out of stock', $outofstock_count, 'woocommerce' ),
						$outofstock_count
					); // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped
				?>
				</a>
			</li>
			<?php
		}

		/**
		 * Recent reviews widget.
		 */
		public function recent_reviews() {
			global $wpdb;

			$query_from = apply_filters(
				'woocommerce_report_recent_reviews_query_from',
				"FROM {$wpdb->comments} comments
				LEFT JOIN {$wpdb->posts} posts ON (comments.comment_post_ID = posts.ID)
				WHERE comments.comment_approved = '1'
				AND comments.comment_type = 'review'
				AND posts.post_password = ''
				AND posts.post_type = 'product'
				AND comments.comment_parent = 0
				ORDER BY comments.comment_date_gmt DESC
				LIMIT 5"
			);

			$comments = $wpdb->get_results(
				"SELECT posts.ID, posts.post_title, comments.comment_author, comments.comment_ID, comments.comment_content {$query_from};" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			);

			if ( $comments ) {
				echo '<ul>';
				foreach ( $comments as $comment ) {

					echo '<li>';

					echo get_avatar( $comment->comment_author, '32' );

					$rating = intval( get_comment_meta( $comment->comment_ID, 'rating', true ) );

					/* translators: %s: rating */
					echo '<div class="star-rating"><span style="width:' . esc_attr( $rating * 20 ) . '%">' . sprintf( esc_html__( '%s out of 5', 'woocommerce' ), esc_html( $rating ) ) . '</span></div>';

					/* translators: %s: review author */
					echo '<h4 class="meta"><a href="' . esc_url( get_permalink( $comment->ID ) ) . '#comment-' . esc_attr( absint( $comment->comment_ID ) ) . '">' . esc_html( apply_filters( 'woocommerce_admin_dashboard_recent_reviews', $comment->post_title, $comment ) ) . '</a> ' . sprintf( esc_html__( 'reviewed by %s', 'woocommerce' ), esc_html( $comment->comment_author ) ) . '</h4>';
					echo '<blockquote>' . wp_kses_data( $comment->comment_content ) . '</blockquote></li>';

				}
				echo '</ul>';
			} else {
				echo '<p>' . esc_html__( 'There are no product reviews yet.', 'woocommerce' ) . '</p>';
			}
		}

		/**
		 * Network orders widget.
		 */
		public function network_orders() {
			$suffix  = Constants::is_true( 'SCRIPT_DEBUG' ) ? '' : '.min';
			$version = Constants::get_constant( 'WC_VERSION' );

			wp_enqueue_style( 'wc-network-orders', WC()->plugin_url() . '/assets/css/network-order-widget.css', array(), $version );

			wp_enqueue_script( 'wc-network-orders', WC()->plugin_url() . '/assets/js/admin/network-orders' . $suffix . '.js', array( 'jquery', 'underscore' ), $version, true );

			$user     = wp_get_current_user();
			$blogs    = get_blogs_of_user( $user->ID );
			$blog_ids = wp_list_pluck( $blogs, 'userblog_id' );

			wp_localize_script(
				'wc-network-orders',
				'woocommerce_network_orders',
				array(
					'nonce'          => wp_create_nonce( 'wp_rest' ),
					'sites'          => array_values( $blog_ids ),
					'order_endpoint' => get_rest_url( null, 'wc/v3/orders/network' ),
				)
			);
			?>
			<div class="post-type-shop_order">
			<div id="woocommerce-network-order-table-loading" class="woocommerce-network-order-table-loading is-active">
				<p>
					<span class="spinner is-active"></span> <?php esc_html_e( 'Loading network orders', 'woocommerce' ); ?>
				</p>

			</div>
			<table id="woocommerce-network-order-table" class="woocommerce-network-order-table">
				<thead>
					<tr>
						<td><?php esc_html_e( 'Order', 'woocommerce' ); ?></td>
						<td><?php esc_html_e( 'Status', 'woocommerce' ); ?></td>
						<td><?php esc_html_e( 'Total', 'woocommerce' ); ?></td>
					</tr>
				</thead>
				<tbody id="network-orders-tbody">

				</tbody>
			</table>
			<div id="woocommerce-network-orders-no-orders" class="woocommerce-network-orders-no-orders">
				<p>
					<?php esc_html_e( 'No orders found', 'woocommerce' ); ?>
				</p>
			</div>
			<?php // @codingStandardsIgnoreStart ?>
			<script type="text/template" id="network-orders-row-template">
				<tr>
					<td>
						<a href="<%- edit_url %>" class="order-view"><strong>#<%- number %> <%- customer %></strong></a>
						<br>
						<em>
							<%- blog.blogname %>
						</em>
					</td>
					<td>
						<mark class="order-status status-<%- status %>"><span><%- status_name %></span></mark>
					</td>
					<td>
						<%= formatted_total %>
					</td>
				</tr>
			</script>
			<?php // @codingStandardsIgnoreEnd ?>
		</div>
			<?php
		}
	}

endif;

return new WC_Admin_Dashboard();
