<?php
/**
 * Admin screens: WooCommerce > Trade Accounts, the users list column and the user profile panel.
 *
 * @package DemfilzTradeAccounts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Store owner screens and their handlers.
 */
class DTAW_Admin {

	/**
	 * Admin page slug.
	 */
	const PAGE = 'dtaw';

	/**
	 * Capability for every screen and action.
	 */
	const CAP = 'manage_woocommerce';

	/**
	 * Hook suffix of the screen.
	 *
	 * @var string
	 */
	private $hook = '';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'menu' ), 60 );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'plugin_action_links_' . DTAW_BASENAME, array( $this, 'action_links' ) );

		foreach ( array( 'dtaw_account_status', 'dtaw_account_save', 'dtaw_account_add', 'dtaw_record_payment', 'dtaw_reverse_payment', 'dtaw_save_settings', 'dtaw_export' ) as $action ) {
			add_action( 'admin_post_' . $action, array( $this, 'handle_' . substr( $action, 5 ) ) );
		}

		add_filter( 'manage_users_columns', array( $this, 'users_column' ) );
		add_filter( 'manage_users_custom_column', array( $this, 'users_column_value' ), 10, 3 );
		add_action( 'show_user_profile', array( $this, 'user_panel' ), 30 );
		add_action( 'edit_user_profile', array( $this, 'user_panel' ), 30 );
		add_action( 'edit_user_profile_update', array( $this, 'user_panel_save' ) );
	}

	/*
	|--------------------------------------------------------------------------
	| URLs and helpers
	|--------------------------------------------------------------------------
	*/

	/**
	 * URL of the screen.
	 *
	 * @param array $args Query args.
	 * @return string
	 */
	public static function url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::PAGE ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * URL of an account's detail view.
	 *
	 * @param int $user_id Customer id.
	 * @return string
	 */
	public static function account_url( $user_id ) {
		return self::url(
			array(
				'tab'  => 'accounts',
				'user' => absint( $user_id ),
			)
		);
	}

	/**
	 * Nonced admin-post URL for a status change.
	 *
	 * @param int    $user_id Customer id.
	 * @param string $status  New status.
	 * @return string
	 */
	public static function status_url( $user_id, $status ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'dtaw_account_status',
					'user'   => absint( $user_id ),
					'status' => $status,
				),
				admin_url( 'admin-post.php' )
			),
			'dtaw_account_status_' . absint( $user_id )
		);
	}

	/**
	 * Redirect back with a result message.
	 *
	 * @param string $url   Target.
	 * @param string $done  Message key.
	 * @param string $error Error text (stored for one display).
	 * @return void
	 */
	private function back( $url, $done = '', $error = '' ) {
		if ( '' !== $error ) {
			set_transient( 'dtaw_admin_error_' . get_current_user_id(), $error, 120 );
			$done = 'error';
		}
		wp_safe_redirect( '' !== $done ? add_query_arg( 'dtaw_done', $done, $url ) : $url );
		exit;
	}

	/**
	 * Stop unless the user may manage the store and the nonce is right.
	 *
	 * @param string $action Nonce action.
	 * @param string $field  Nonce field.
	 * @return void
	 */
	private function guard( $action, $field = '_wpnonce' ) {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to manage trade accounts.', 'demfilz-trade-accounts-for-woocommerce' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( $action, $field );
	}

	/**
	 * Read a posted text field (after guard()).
	 *
	 * @param string $key      Field.
	 * @param bool   $textarea Keep line breaks.
	 * @return string
	 */
	private function posted( $key, $textarea = false ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Called only after guard() verified the nonce.
		if ( ! isset( $_POST[ $key ] ) ) {
			return '';
		}
		return $textarea ? sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) ) : sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
		// phpcs:enable
	}

	/**
	 * Tabs on the screen.
	 *
	 * @return array Slug => label.
	 */
	public static function tabs() {
		$tabs = array(
			'accounts' => __( 'Accounts', 'demfilz-trade-accounts-for-woocommerce' ),
			'invoices' => __( 'Invoices', 'demfilz-trade-accounts-for-woocommerce' ),
			'settings' => __( 'Settings', 'demfilz-trade-accounts-for-woocommerce' ),
		);

		/**
		 * Tabs on WooCommerce > Trade Accounts. Render a custom tab on the dtaw_admin_tab_{slug} action.
		 *
		 * @param array $tabs Slug => label.
		 */
		return (array) apply_filters( 'dtaw_admin_tabs', $tabs );
	}

	/*
	|--------------------------------------------------------------------------
	| Menu, assets, notices
	|--------------------------------------------------------------------------
	*/

	/**
	 * Add the screen under WooCommerce.
	 *
	 * @return void
	 */
	public function menu() {
		$pending = DTAW_Accounts::counts();
		$label   = __( 'Trade Accounts', 'demfilz-trade-accounts-for-woocommerce' );
		if ( ! empty( $pending['pending'] ) ) {
			$label .= ' <span class="awaiting-mod">' . (int) $pending['pending'] . '</span>';
		}
		$this->hook = (string) add_submenu_page(
			'woocommerce',
			__( 'Trade Accounts', 'demfilz-trade-accounts-for-woocommerce' ),
			$label,
			self::CAP,
			self::PAGE,
			array( $this, 'render' )
		);
	}

	/**
	 * Assets on the screen, the order editor and the users screens.
	 *
	 * @param string $hook Hook suffix.
	 * @return void
	 */
	public function assets( $hook ) {
		$screen   = get_current_screen();
		$order_id = function_exists( 'wc_get_page_screen_id' ) ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';
		$on_page  = '' !== $this->hook && $hook === $this->hook;
		$on_order = $screen && in_array( $screen->id, array( $order_id, 'shop_order', 'edit-shop_order', 'woocommerce_page_wc-orders' ), true );
		$on_users = $screen && in_array( $screen->id, array( 'users', 'profile', 'user-edit' ), true );
		if ( ! $on_page && ! $on_order && ! $on_users ) {
			return;
		}
		wp_enqueue_style( 'dtaw-admin', DTAW_URL . 'admin/css/admin.css', array(), DTAW_VERSION );
		wp_enqueue_script( 'dtaw-admin', DTAW_URL . 'admin/js/admin.js', array( 'jquery' ), DTAW_VERSION, true );
		if ( $on_page ) {
			wp_enqueue_style( 'woocommerce_admin_styles' );
			wp_enqueue_script( 'wc-enhanced-select' );
			wp_enqueue_script( 'selectWoo' );
			wp_enqueue_style( 'select2' );
		}
	}

	/**
	 * Links on the Plugins screen.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public function action_links( $links ) {
		array_unshift(
			$links,
			'<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Accounts', 'demfilz-trade-accounts-for-woocommerce' ) . '</a>',
			'<a href="' . esc_url( self::url( array( 'tab' => 'settings' ) ) ) . '">' . esc_html__( 'Settings', 'demfilz-trade-accounts-for-woocommerce' ) . '</a>'
		);
		return $links;
	}

	/**
	 * Result of the last action, printed inline where the action was taken: at the top of
	 * this screen and inside the Trade account box on an order. Nothing is printed outside these two places.
	 *
	 * @return void
	 */
	public static function result_notice() {
		// Display only: selects one of the fixed messages.
		$done = isset( $_GET['dtaw_done'] ) ? sanitize_key( wp_unslash( $_GET['dtaw_done'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' === $done ) {
			return;
		}
		$messages = array(
			'approved'  => __( 'Account approved and the customer emailed.', 'demfilz-trade-accounts-for-woocommerce' ),
			'declined'  => __( 'Application declined and the customer emailed.', 'demfilz-trade-accounts-for-woocommerce' ),
			'suspended' => __( 'Account suspended and the customer emailed.', 'demfilz-trade-accounts-for-woocommerce' ),
			'pending'   => __( 'Account set back to pending.', 'demfilz-trade-accounts-for-woocommerce' ),
			'none'      => __( 'Trade account record removed.', 'demfilz-trade-accounts-for-woocommerce' ),
			'saved'     => __( 'Account details saved.', 'demfilz-trade-accounts-for-woocommerce' ),
			'added'     => __( 'Customer added and approved.', 'demfilz-trade-accounts-for-woocommerce' ),
			'paid'      => __( 'Payment recorded.', 'demfilz-trade-accounts-for-woocommerce' ),
			'unpaid'    => __( 'The invoice is unpaid again.', 'demfilz-trade-accounts-for-woocommerce' ),
			'settings'  => __( 'Settings saved.', 'demfilz-trade-accounts-for-woocommerce' ),
		);
		if ( isset( $messages[ $done ] ) ) {
			echo '<div class="notice notice-success inline is-dismissible"><p>' . esc_html( $messages[ $done ] ) . '</p></div>';
		}
		if ( 'error' === $done ) {
			$key   = 'dtaw_admin_error_' . get_current_user_id();
			$error = get_transient( $key );
			delete_transient( $key );
			if ( $error ) {
				echo '<div class="notice notice-error inline is-dismissible"><p>' . esc_html( $error ) . '</p></div>';
			}
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Screen
	|--------------------------------------------------------------------------
	*/

	/**
	 * Route the screen.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		// Display only: routing between the screen's views.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$tab     = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'accounts';
		$user_id = isset( $_GET['user'] ) ? absint( $_GET['user'] ) : 0;
		// phpcs:enable

		$tabs = self::tabs();
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'accounts';
		}

		echo '<div class="wrap dtaw-wrap">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Trade Accounts', 'demfilz-trade-accounts-for-woocommerce' ) . '</h1>';
		echo '<hr class="wp-header-end" />';
		self::result_notice();

		echo '<nav class="nav-tab-wrapper woo-nav-tab-wrapper" aria-label="' . esc_attr__( 'Trade account sections', 'demfilz-trade-accounts-for-woocommerce' ) . '">';
		foreach ( $tabs as $slug => $label ) {
			printf(
				'<a href="%1$s" class="nav-tab%2$s"%3$s>%4$s</a>',
				esc_url( self::url( array( 'tab' => $slug ) ) ),
				$slug === $tab ? ' nav-tab-active' : '',
				$slug === $tab ? ' aria-current="page"' : '',
				esc_html( $label )
			);
		}
		echo '</nav>';

		if ( 'accounts' === $tab && $user_id ) {
			$this->render_account( $user_id );
		} elseif ( 'accounts' === $tab ) {
			$this->render_accounts();
		} elseif ( 'invoices' === $tab ) {
			$this->render_invoices();
		} elseif ( 'settings' === $tab ) {
			$this->render_settings();
		} else {
			/**
			 * Render a custom tab added through dtaw_admin_tabs.
			 */
			do_action( 'dtaw_admin_tab_' . $tab );
		}
		echo '</div>';
	}

	/**
	 * Summary tiles.
	 *
	 * @param array $summary From DTAW_Ledger::summary().
	 * @return void
	 */
	public static function tiles( $summary ) {
		$tiles = array(
			array( __( 'Balance owing', 'demfilz-trade-accounts-for-woocommerce' ), wc_price( $summary['owing'] ), '' ),
			array( __( 'Overdue', 'demfilz-trade-accounts-for-woocommerce' ), wc_price( $summary['overdue'] ), $summary['overdue'] > 0 ? 'dtaw-tile-overdue' : '' ),
			array( __( 'Due in the next 7 days', 'demfilz-trade-accounts-for-woocommerce' ), wc_price( $summary['due_soon'] ), '' ),
			array( __( 'Open invoices', 'demfilz-trade-accounts-for-woocommerce' ), number_format_i18n( $summary['open_count'] ), '' ),
		);

		/**
		 * Tiles at the top of the Accounts tab and the account view.
		 *
		 * @param array $tiles   Each: label, value (HTML from wc_price or escaped text), extra class.
		 * @param array $summary Ledger summary.
		 */
		$tiles = (array) apply_filters( 'dtaw_admin_tiles', $tiles, $summary );

		echo '<div class="dtaw-tiles">';
		foreach ( $tiles as $tile ) {
			echo '<div class="dtaw-tile ' . esc_attr( isset( $tile[2] ) ? $tile[2] : '' ) . '"><span class="dtaw-tile-label">' . esc_html( $tile[0] ) . '</span><span class="dtaw-tile-value">' . wp_kses_post( $tile[1] ) . '</span></div>';
		}
		echo '</div>';
	}

	/**
	 * Status badge.
	 *
	 * @param string $status Status.
	 * @return string HTML.
	 */
	public static function badge( $status ) {
		return '<span class="dtaw-badge dtaw-badge-' . esc_attr( $status ) . '">' . esc_html( DTAW_Accounts::label( $status ) ) . '</span>';
	}

	/**
	 * Action buttons for an account.
	 *
	 * @param int    $user_id Customer id.
	 * @param string $status  Stored status.
	 * @return void
	 */
	private function account_actions( $user_id, $status ) {
		$buttons = array();
		if ( in_array( $status, array( 'none', 'pending', 'declined', 'suspended' ), true ) ) {
			$buttons[] = array( 'approved', 'pending' === $status ? __( 'Approve', 'demfilz-trade-accounts-for-woocommerce' ) : ( 'suspended' === $status ? __( 'Reinstate', 'demfilz-trade-accounts-for-woocommerce' ) : __( 'Approve', 'demfilz-trade-accounts-for-woocommerce' ) ), 'button-primary' );
		}
		if ( 'pending' === $status ) {
			$buttons[] = array( 'declined', __( 'Decline', 'demfilz-trade-accounts-for-woocommerce' ), '' );
		}
		if ( 'approved' === $status ) {
			$buttons[] = array( 'suspended', __( 'Suspend', 'demfilz-trade-accounts-for-woocommerce' ), '' );
		}
		if ( 'none' !== $status ) {
			$buttons[] = array( 'none', __( 'Remove record', 'demfilz-trade-accounts-for-woocommerce' ), 'button-link-delete dtaw-confirm' );
		}
		echo '<span class="dtaw-actions">';
		foreach ( $buttons as $b ) {
			$confirm = 'none' === $b[0] ? ' data-confirm="' . esc_attr__( 'Remove this trade account record? Orders already placed on account are kept.', 'demfilz-trade-accounts-for-woocommerce' ) . '"' : '';
			echo '<a class="button button-small ' . esc_attr( $b[2] ) . '" href="' . esc_url( self::status_url( $user_id, $b[0] ) ) . '"' . $confirm . '>' . esc_html( $b[1] ) . '</a> '; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $confirm is built with esc_attr__ above.
		}
		echo '</span>';
	}

	/**
	 * Accounts tab.
	 *
	 * @return void
	 */
	private function render_accounts() {
		// Display only: list filters.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$paged  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		// phpcs:enable

		self::tiles( DTAW_Ledger::summary() );

		$counts = DTAW_Accounts::counts();
		$total  = array_sum( $counts );
		echo '<div class="dtaw-filters">';
		echo '<ul class="subsubsub">';
		$links = array( '' => __( 'All', 'demfilz-trade-accounts-for-woocommerce' ) ) + array_diff_key( DTAW_Accounts::statuses(), array( 'none' => 1 ) );
		$i     = 0;
		foreach ( $links as $slug => $label ) {
			$count = '' === $slug ? $total : ( isset( $counts[ $slug ] ) ? $counts[ $slug ] : 0 );
			echo '<li>' . ( $i++ ? ' | ' : '' ) . '<a href="' . esc_url( self::url( array_filter( array( 'status' => $slug ) ) ) ) . '"' . ( $slug === $status ? ' class="current" aria-current="page"' : '' ) . '>' . esc_html( $label ) . ' <span class="count">(' . (int) $count . ')</span></a></li>';
		}
		echo '</ul>';
		echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '"><input type="hidden" name="page" value="' . esc_attr( self::PAGE ) . '" />';
		if ( '' !== $status ) {
			echo '<input type="hidden" name="status" value="' . esc_attr( $status ) . '" />';
		}
		echo '<label class="screen-reader-text" for="dtaw-search">' . esc_html__( 'Search accounts', 'demfilz-trade-accounts-for-woocommerce' ) . '</label>';
		echo '<input type="search" id="dtaw-search" name="s" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Name or email', 'demfilz-trade-accounts-for-woocommerce' ) . '" />';
		submit_button( __( 'Search', 'demfilz-trade-accounts-for-woocommerce' ), 'secondary', 'dtaw_search_submit', false );
		echo '</form>';
		echo '</div>';

		$per    = 20;
		$result = DTAW_Accounts::query(
			array(
				'status' => $status,
				'search' => $search,
				'page'   => $paged,
				'per'    => $per,
			)
		);
		?>
		<table class="widefat striped dtaw-accounts">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Customer', 'demfilz-trade-accounts-for-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Status', 'demfilz-trade-accounts-for-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Terms', 'demfilz-trade-accounts-for-woocommerce' ); ?></th>
					<th class="dtaw-num"><?php esc_html_e( 'Owing', 'demfilz-trade-accounts-for-woocommerce' ); ?></th>
					<th class="dtaw-num"><?php esc_html_e( 'Overdue', 'demfilz-trade-accounts-for-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Since', 'demfilz-trade-accounts-for-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'demfilz-trade-accounts-for-woocommerce' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! $result['users'] ) : ?>
					<tr><td colspan="7"><?php esc_html_e( 'No trade accounts yet. Customers can apply from My Account, or add one below.', 'demfilz-trade-accounts-for-woocommerce' ); ?></td></tr>
				<?php endif; ?>
				<?php
				foreach ( $result['users'] as $user ) :
					$st  = DTAW_Accounts::status( $user->ID );
					$sum = DTAW_Ledger::summary( $user->ID );
					?>
					<tr>
						<td>
							<a href="<?php echo esc_url( self::account_url( $user->ID ) ); ?>"><strong><?php echo esc_html( DTAW_Accounts::display_name( $user->ID ) ); ?></strong></a><br />
							<span class="dtaw-muted"><?php echo esc_html( $user->user_email ); ?></span>
						</td>
						<td><?php echo wp_kses_post( self::badge( $st ) ); ?></td>
						<td>
							<?php
							/* translators: %d: days. */
							echo esc_html( sprintf( __( '%d days', 'demfilz-trade-accounts-for-woocommerce' ), DTAW_Accounts::terms_days( $user->ID ) ) );
							?>
						</td>
						<td class="dtaw-num"><?php echo wp_kses_post( wc_price( $sum['owing'] ) ); ?></td>
						<td class="dtaw-num"><?php echo $sum['overdue'] > 0 ? '<span class="dtaw-overdue">' . wp_kses_post( wc_price( $sum['overdue'] ) ) . '</span>' : '<span class="dtaw-muted">&ndash;</span>'; ?></td>
						<td><?php echo esc_html( DTAW_Accounts::format_date( DTAW_Accounts::status_at( $user->ID ) ) ); ?></td>
						<td><?php $this->account_actions( $user->ID, $st ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		$this->paginate(
			$result['total'],
			$per,
			$paged,
			array_filter(
				array(
					'status' => $status,
					's'      => $search,
				)
			)
		);
		?>
		<div class="dtaw-panel">
			<h2><?php esc_html_e( 'Add a customer', 'demfilz-trade-accounts-for-woocommerce' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Approve an existing customer without an application. They are emailed that their account is open.', 'demfilz-trade-accounts-for-woocommerce' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="dtaw_account_add" />
				<?php wp_nonce_field( 'dtaw_account_add' ); ?>
				<p>
					<label for="dtaw_add_user" class="screen-reader-text"><?php esc_html_e( 'Customer', 'demfilz-trade-accounts-for-woocommerce' ); ?></label>
					<select class="wc-customer-search" id="dtaw_add_user" name="user" data-placeholder="<?php esc_attr_e( 'Search for a customer', 'demfilz-trade-accounts-for-woocommerce' ); ?>" data-allow_clear="true" style="min-width:320px;"></select>
					<label for="dtaw_add_terms"><?php esc_html_e( 'Terms', 'demfilz-trade-accounts-for-woocommerce' ); ?></label>
					<input type="number" id="dtaw_add_terms" name="terms" min="0" max="365" class="small-text" placeholder="<?php echo esc_attr( DTAW_Settings::get( 'terms_days' ) ); ?>" /> <?php esc_html_e( 'days', 'demfilz-trade-accounts-for-woocommerce' ); ?>
					<?php submit_button( __( 'Add and approve', 'demfilz-trade-accounts-for-woocommerce' ), 'primary', 'dtaw_add_submit', false ); ?>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * One account.
	 *
	 * @param int $user_id Customer id.
	 * @return void
	 */
	private function render_account( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			echo '<p>' . esc_html__( 'Customer not found.', 'demfilz-trade-accounts-for-woocommerce' ) . '</p>';
			return;
		}
		$status = DTAW_Accounts::status( $user_id );
		$app    = DTAW_Accounts::application( $user_id );
		$sum    = DTAW_Ledger::summary( $user_id );
		echo '<p><a href="' . esc_url( self::url() ) . '">&larr; ' . esc_html__( 'All accounts', 'demfilz-trade-accounts-for-woocommerce' ) . '</a></p>';
		?>
		<div class="dtaw-panel">
			<div class="dtaw-account-head">
				<div>
					<h2><?php echo esc_html( DTAW_Accounts::display_name( $user_id ) ); ?> <?php echo wp_kses_post( self::badge( $status ) ); ?></h2>
					<?php if ( 'none' === $status && DTAW_Accounts::role_approved( $user_id ) ) : ?>
						<p class="dtaw-muted"><?php esc_html_e( 'Approved through their role (see Settings).', 'demfilz-trade-accounts-for-woocommerce' ); ?></p>
					<?php endif; ?>
					<dl>
						<dt><?php esc_html_e( 'Customer', 'demfilz-trade-accounts-for-woocommerce' ); ?></dt>
						<dd><a href="<?php echo esc_url( get_edit_user_link( $user_id ) ); ?>"><?php echo esc_html( trim( $user->first_name . ' ' . $user->last_name ) ? trim( $user->first_name . ' ' . $user->last_name ) : $user->display_name ); ?></a> &middot; <?php echo esc_html( $user->user_email ); ?></dd>
						<?php if ( '' !== $app['business'] ) : ?>
							<dt><?php esc_html_e( 'Business', 'demfilz-trade-accounts-for-woocommerce' ); ?></dt>
							<dd><?php echo esc_html( $app['business'] ); ?></dd>
						<?php endif; ?>
						<?php if ( '' !== $app['tax_id'] ) : ?>
							<dt><?php esc_html_e( 'ABN / tax id', 'demfilz-trade-accounts-for-woocommerce' ); ?></dt>
							<dd><?php echo esc_html( $app['tax_id'] ); ?></dd>
						<?php endif; ?>
						<?php if ( '' !== $app['phone'] ) : ?>
							<dt><?php esc_html_e( 'Phone', 'demfilz-trade-accounts-for-woocommerce' ); ?></dt>
							<dd><?php echo esc_html( $app['phone'] ); ?></dd>
						<?php endif; ?>
						<?php if ( '' !== $app['message'] ) : ?>
							<dt><?php esc_html_e( 'Message', 'demfilz-trade-accounts-for-woocommerce' ); ?></dt>
							<dd><?php echo wp_kses( nl2br( esc_html( $app['message'] ) ), array( 'br' => array() ) ); ?></dd>
						<?php endif; ?>
						<?php if ( '' !== $app['applied_at'] ) : ?>
							<dt><?php esc_html_e( 'Applied', 'demfilz-trade-accounts-for-woocommerce' ); ?></dt>
							<dd><?php echo esc_html( DTAW_Accounts::format_date( $app['applied_at'] ) ); ?></dd>
						<?php endif; ?>
						<dt><?php esc_html_e( 'Status since', 'demfilz-trade-accounts-for-woocommerce' ); ?></dt>
						<dd><?php echo esc_html( DTAW_Accounts::format_date( DTAW_Accounts::status_at( $user_id ) ) ); ?></dd>
					</dl>
					<p><?php $this->account_actions( $user_id, $status ); ?></p>
				</div>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="dtaw-account-form">
					<input type="hidden" name="action" value="dtaw_account_save" />
					<input type="hidden" name="user" value="<?php echo esc_attr( $user_id ); ?>" />
					<?php wp_nonce_field( 'dtaw_account_save_' . $user_id ); ?>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="dtaw_terms"><?php esc_html_e( 'Payment terms', 'demfilz-trade-accounts-for-woocommerce' ); ?></label></th>
							<td>
								<input type="number" id="dtaw_terms" name="terms" min="0" max="365" class="small-text" value="<?php echo esc_attr( DTAW_Accounts::terms_raw( $user_id ) ? DTAW_Accounts::terms_raw( $user_id ) : '' ); ?>" placeholder="<?php echo esc_attr( DTAW_Settings::get( 'terms_days' ) ); ?>" /> <?php esc_html_e( 'days', 'demfilz-trade-accounts-for-woocommerce' ); ?>
								<p class="description">
									<?php
									/* translators: %d: days. */
									echo esc_html( sprintf( __( 'Leave empty for the store default (%d days).', 'demfilz-trade-accounts-for-woocommerce' ), (int) DTAW_Settings::get( 'terms_days' ) ) );
									?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="dtaw_ref"><?php esc_html_e( 'Account reference', 'demfilz-trade-accounts-for-woocommerce' ); ?></label></th>
							<td><input type="text" id="dtaw_ref" name="ref" class="regular-text" maxlength="40" value="<?php echo esc_attr( DTAW_Accounts::ref( $user_id ) ); ?>" placeholder="<?php esc_attr_e( 'For example ACME01', 'demfilz-trade-accounts-for-woocommerce' ); ?>" /><p class="description"><?php esc_html_e( 'The account number in your books. Shown to the customer.', 'demfilz-trade-accounts-for-woocommerce' ); ?></p></td>
						</tr>
						<?php
						/**
						 * Extra rows in the account edit form (an add-on can add a credit limit).
						 *
						 * @param int $user_id Customer id.
						 */
						do_action( 'dtaw_account_form_rows', $user_id );
						?>
						<tr>
							<th scope="row"><label for="dtaw_notes"><?php esc_html_e( 'Internal notes', 'demfilz-trade-accounts-for-woocommerce' ); ?></label></th>
							<td><textarea id="dtaw_notes" name="notes" rows="4" class="large-text"><?php echo esc_textarea( DTAW_Accounts::notes( $user_id ) ); ?></textarea><p class="description"><?php esc_html_e( 'Never shown to the customer.', 'demfilz-trade-accounts-for-woocommerce' ); ?></p></td>
						</tr>
					</table>
					<?php submit_button( __( 'Save account', 'demfilz-trade-accounts-for-woocommerce' ), 'primary', 'dtaw_account_submit', false ); ?>
				</form>
			</div>
		</div>
		<?php
		self::tiles( $sum );

		/**
		 * Print more on the account view, under the tiles (an add-on can add statement tools).
		 *
		 * @param int   $user_id Customer id.
		 * @param array $summary Ledger summary.
		 */
		do_action( 'dtaw_admin_account_after', $user_id, $sum );

		$result = DTAW_Ledger::query(
			array(
				'user_id' => $user_id,
				'per'     => 50,
			)
		);
		echo '<h2>' . esc_html__( 'Invoices', 'demfilz-trade-accounts-for-woocommerce' ) . '</h2>';
		$this->invoice_table( $result['orders'], false );
	}

	/**
	 * Invoices tab.
	 *
	 * @return void
	 */
	private function render_invoices() {
		// Display only: list filters.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$view   = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'open';
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$paged  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		// phpcs:enable
		$views = array(
			'open'    => __( 'Unpaid', 'demfilz-trade-accounts-for-woocommerce' ),
			'overdue' => __( 'Overdue', 'demfilz-trade-accounts-for-woocommerce' ),
			'paid'    => __( 'Paid', 'demfilz-trade-accounts-for-woocommerce' ),
			'void'    => __( 'Void', 'demfilz-trade-accounts-for-woocommerce' ),
			'all'     => __( 'All', 'demfilz-trade-accounts-for-woocommerce' ),
		);
		if ( ! isset( $views[ $view ] ) ) {
			$view = 'open';
		}
		$args   = array(
			'state'   => in_array( $view, array( 'open', 'paid', 'void' ), true ) ? $view : ( 'overdue' === $view ? 'open' : '' ),
			'overdue' => 'overdue' === $view,
			'search'  => $search,
			'page'    => $paged,
			'per'     => 25,
		);
		$result = DTAW_Ledger::query( $args );

		echo '<div class="dtaw-filters">';
		echo '<ul class="subsubsub">';
		$i = 0;
		foreach ( $views as $slug => $label ) {
			echo '<li>' . ( $i++ ? ' | ' : '' ) . '<a href="' . esc_url(
				self::url(
					array(
						'tab'  => 'invoices',
						'view' => $slug,
					)
				)
			) . '"' . ( $slug === $view ? ' class="current" aria-current="page"' : '' ) . '>' . esc_html( $label ) . '</a></li>';
		}
		echo '</ul>';
		echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '"><input type="hidden" name="page" value="' . esc_attr( self::PAGE ) . '" /><input type="hidden" name="tab" value="invoices" /><input type="hidden" name="view" value="' . esc_attr( $view ) . '" />';
		echo '<label class="screen-reader-text" for="dtaw-isearch">' . esc_html__( 'Search invoices', 'demfilz-trade-accounts-for-woocommerce' ) . '</label>';
		echo '<input type="search" id="dtaw-isearch" name="s" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Order number, PO or email', 'demfilz-trade-accounts-for-woocommerce' ) . '" />';
		submit_button( __( 'Search', 'demfilz-trade-accounts-for-woocommerce' ), 'secondary', 'dtaw_isearch_submit', false );
		echo ' <a class="button" href="' . esc_url(
			wp_nonce_url(
				add_query_arg(
					array(
						'action' => 'dtaw_export',
						'view'   => $view,
					),
					admin_url( 'admin-post.php' )
				),
				'dtaw_export'
			)
		) . '">' . esc_html__( 'Export CSV', 'demfilz-trade-accounts-for-woocommerce' ) . '</a>';
		echo '</form>';
		echo '</div>';

		$this->invoice_table( $result['orders'], true );
		$this->paginate(
			$result['total'],
			25,
			$paged,
			array_filter(
				array(
					'tab'  => 'invoices',
					'view' => $view,
					's'    => $search,
				)
			)
		);
	}

	/**
	 * Invoice table.
	 *
	 * @param WC_Order[] $orders        Orders.
	 * @param bool       $show_customer Show the customer column.
	 * @return void
	 */
	private function invoice_table( $orders, $show_customer ) {
		?>
		<table class="widefat striped dtaw-invoice-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Order', 'demfilz-trade-accounts-for-woocommerce' ); ?></th>
					<?php if ( $show_customer ) : ?>
						<th><?php esc_html_e( 'Customer', 'demfilz-trade-accounts-for-woocommerce' ); ?></th>
					<?php endif; ?>
					<th><?php esc_html_e( 'Date', 'demfilz-trade-accounts-for-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Status', 'demfilz-trade-accounts-for-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'PO', 'demfilz-trade-accounts-for-woocommerce' ); ?></th>
					<th class="dtaw-num"><?php esc_html_e( 'Amount', 'demfilz-trade-accounts-for-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'demfilz-trade-accounts-for-woocommerce' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! $orders ) : ?>
					<tr><td colspan="7"><?php esc_html_e( 'No invoices match.', 'demfilz-trade-accounts-for-woocommerce' ); ?></td></tr>
				<?php endif; ?>
				<?php
				foreach ( $orders as $order ) :
					$state = DTAW_Ledger::state( $order );
					$class = 'open' === $state ? ( DTAW_Ledger::is_overdue( $order ) ? 'dtaw-badge-overdue' : 'dtaw-badge-open' ) : 'dtaw-badge-' . $state;
					?>
					<tr>
						<td><a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>"><strong>#<?php echo esc_html( $order->get_order_number() ); ?></strong></a> <span class="dtaw-muted"><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></span></td>
						<?php if ( $show_customer ) : ?>
							<td><a href="<?php echo esc_url( self::account_url( $order->get_customer_id() ) ); ?>"><?php echo esc_html( DTAW_Accounts::display_name( $order->get_customer_id() ) ); ?></a></td>
						<?php endif; ?>
						<td><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></td>
						<td><span class="dtaw-badge <?php echo esc_attr( $class ); ?>"><?php echo esc_html( DTAW_Ledger::status_text( $order ) ); ?></span></td>
						<td><?php echo esc_html( DTAW_Ledger::po( $order ) ); ?></td>
						<td class="dtaw-num"><?php echo wp_kses_post( wc_price( DTAW_Ledger::invoice_amount( $order ), array( 'currency' => $order->get_currency() ) ) ); ?></td>
						<td class="dtaw-actions">
							<?php if ( 'open' === $state ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="dtaw-inline-form">
									<input type="hidden" name="action" value="dtaw_record_payment" />
									<input type="hidden" name="order_id" value="<?php echo esc_attr( $order->get_id() ); ?>" />
									<input type="hidden" name="date" value="<?php echo esc_attr( DTAW_Ledger::today() ); ?>" />
									<?php wp_nonce_field( 'dtaw_record_payment_' . $order->get_id() ); ?>
									<button type="submit" class="button button-small dtaw-confirm" data-confirm="<?php esc_attr_e( 'Record this invoice as paid in full today? Open the order to enter a date or reference.', 'demfilz-trade-accounts-for-woocommerce' ); ?>"><?php esc_html_e( 'Paid today', 'demfilz-trade-accounts-for-woocommerce' ); ?></button>
								</form>
							<?php endif; ?>
							<a class="button button-small" href="<?php echo esc_url( $order->get_edit_order_url() ); ?>"><?php esc_html_e( 'Open order', 'demfilz-trade-accounts-for-woocommerce' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Pagination links.
	 *
	 * @param int   $total Total rows.
	 * @param int   $per   Per page.
	 * @param int   $paged Current page.
	 * @param array $args  Query args to keep.
	 * @return void
	 */
	private function paginate( $total, $per, $paged, $args ) {
		$pages = (int) ceil( $total / max( 1, $per ) );
		if ( $pages <= 1 ) {
			return;
		}
		echo '<div class="tablenav"><div class="tablenav-pages">';
		echo wp_kses_post(
			paginate_links(
				array(
					'base'      => add_query_arg( array_merge( $args, array( 'paged' => '%#%' ) ), self::url() ),
					'format'    => '',
					'current'   => $paged,
					'total'     => $pages,
					'prev_text' => '&lsaquo;',
					'next_text' => '&rsaquo;',
				)
			)
		);
		echo '</div></div>';
	}

	/**
	 * Settings tab.
	 *
	 * @return void
	 */
	private function render_settings() {
		$s     = DTAW_Settings::all();
		$roles = wp_roles()->get_names();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="dtaw-panel">
			<input type="hidden" name="action" value="dtaw_save_settings" />
			<?php wp_nonce_field( 'dtaw_save_settings' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="dtaw_s_terms"><?php esc_html_e( 'Default payment terms', 'demfilz-trade-accounts-for-woocommerce' ); ?></label></th>
					<td><input type="number" id="dtaw_s_terms" name="terms_days" min="0" max="365" class="small-text" value="<?php echo esc_attr( $s['terms_days'] ); ?>" /> <?php esc_html_e( 'days from the order date', 'demfilz-trade-accounts-for-woocommerce' ); ?><p class="description"><?php esc_html_e( 'Used for every account without its own terms.', 'demfilz-trade-accounts-for-woocommerce' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label for="dtaw_s_status"><?php esc_html_e( 'Status for orders on account', 'demfilz-trade-accounts-for-woocommerce' ); ?></label></th>
					<td>
						<select id="dtaw_s_status" name="order_status">
							<?php foreach ( DTAW_Settings::status_choices() as $slug => $label ) : ?>
								<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $slug, $s['order_status'] ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Processing lets the order ship straight away, the usual trade account arrangement. The order is not marked paid until you record the payment.', 'demfilz-trade-accounts-for-woocommerce' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="dtaw_s_po"><?php esc_html_e( 'Purchase order number', 'demfilz-trade-accounts-for-woocommerce' ); ?></label></th>
					<td>
						<select id="dtaw_s_po" name="po_field">
							<option value="optional" <?php selected( 'optional', $s['po_field'] ); ?>><?php esc_html_e( 'Ask for it (optional)', 'demfilz-trade-accounts-for-woocommerce' ); ?></option>
							<option value="required" <?php selected( 'required', $s['po_field'] ); ?>><?php esc_html_e( 'Require it', 'demfilz-trade-accounts-for-woocommerce' ); ?></option>
							<option value="off" <?php selected( 'off', $s['po_field'] ); ?>><?php esc_html_e( 'Do not ask', 'demfilz-trade-accounts-for-woocommerce' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'Shown in the Pay on account box at checkout and stored on the order.', 'demfilz-trade-accounts-for-woocommerce' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Auto approved roles', 'demfilz-trade-accounts-for-woocommerce' ); ?></th>
					<td class="dtaw-roles">
						<?php foreach ( $roles as $slug => $name ) : ?>
							<?php
							if ( 'administrator' === $slug ) {
								continue;
							}
							?>
							<label><input type="checkbox" name="auto_roles[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $s['auto_roles'], true ) ); ?> /> <?php echo esc_html( translate_user_role( $name ) ); ?></label>
						<?php endforeach; ?>
						<p class="description"><?php esc_html_e( 'Customers with one of these roles can pay on account without applying. A suspended or declined status still wins.', 'demfilz-trade-accounts-for-woocommerce' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="dtaw_s_who"><?php esc_html_e( 'Applications', 'demfilz-trade-accounts-for-woocommerce' ); ?></label></th>
					<td>
						<select id="dtaw_s_who" name="who_can_apply">
							<option value="customers" <?php selected( 'customers', $s['who_can_apply'] ); ?>><?php esc_html_e( 'Any logged in customer can apply from My Account', 'demfilz-trade-accounts-for-woocommerce' ); ?></option>
							<option value="none" <?php selected( 'none', $s['who_can_apply'] ); ?>><?php esc_html_e( 'No applications: I add accounts myself', 'demfilz-trade-accounts-for-woocommerce' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'The [dtaw_apply] shortcode shows the same application form on any page.', 'demfilz-trade-accounts-for-woocommerce' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="dtaw_s_instructions"><?php esc_html_e( 'Payment instructions', 'demfilz-trade-accounts-for-woocommerce' ); ?></label></th>
					<td><textarea id="dtaw_s_instructions" name="instructions" rows="5"><?php echo esc_textarea( $s['instructions'] ); ?></textarea><p class="description"><?php esc_html_e( 'Bank details and how to reference the payment. Shown on the thank you page, in the order emails and in My Account for unpaid invoices.', 'demfilz-trade-accounts-for-woocommerce' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'When a payment is recorded', 'demfilz-trade-accounts-for-woocommerce' ); ?></th>
					<td><label><input type="checkbox" name="complete_when_paid" value="yes" <?php checked( 'yes', $s['complete_when_paid'] ); ?> /> <?php esc_html_e( 'Mark Processing or On hold orders as Completed', 'demfilz-trade-accounts-for-woocommerce' ); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Uninstall', 'demfilz-trade-accounts-for-woocommerce' ); ?></th>
					<td><label><input type="checkbox" name="delete_data" value="yes" <?php checked( 'yes', $s['delete_data'] ); ?> /> <?php esc_html_e( 'Delete account statuses, applications and settings when the plugin is deleted (invoice details stay on the orders)', 'demfilz-trade-accounts-for-woocommerce' ); ?></label></td>
				</tr>
			</table>
			<p class="description">
				<?php
				printf(
					/* translators: %s: link to the gateway settings. */
					esc_html__( 'The title and description shown at checkout are on the %s.', 'demfilz-trade-accounts-for-woocommerce' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . DTAW_Gateway::ID ) ) . '">' . esc_html__( 'Pay on account gateway settings', 'demfilz-trade-accounts-for-woocommerce' ) . '</a>'
				);
				?>
			</p>
			<?php submit_button( __( 'Save settings', 'demfilz-trade-accounts-for-woocommerce' ), 'primary', 'dtaw_settings_submit' ); ?>
		</form>
		<?php
	}

	/*
	|--------------------------------------------------------------------------
	| Handlers
	|--------------------------------------------------------------------------
	*/

	/**
	 * Approve, decline, suspend, reinstate or remove.
	 *
	 * @return void
	 */
	public function handle_account_status() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified in guard() below, which needs the id first.
		$user_id = isset( $_GET['user'] ) ? absint( $_GET['user'] ) : 0;
		$this->guard( 'dtaw_account_status_' . $user_id );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified in guard().
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		if ( ! get_userdata( $user_id ) || ! DTAW_Accounts::set_status( $user_id, $status ) ) {
			$this->back( self::url(), '', __( 'That status change could not be made.', 'demfilz-trade-accounts-for-woocommerce' ) );
		}
		$this->back( 'none' === $status ? self::url() : self::account_url( $user_id ), $status );
	}

	/**
	 * Save terms, reference and notes.
	 *
	 * @return void
	 */
	public function handle_account_save() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in guard() below, which needs the id first.
		$user_id = isset( $_POST['user'] ) ? absint( $_POST['user'] ) : 0;
		$this->guard( 'dtaw_account_save_' . $user_id );
		if ( ! get_userdata( $user_id ) ) {
			$this->back( self::url(), '', __( 'Customer not found.', 'demfilz-trade-accounts-for-woocommerce' ) );
		}
		DTAW_Accounts::set_terms( $user_id, (int) $this->posted( 'terms' ) );
		DTAW_Accounts::set_ref( $user_id, $this->posted( 'ref' ) );
		DTAW_Accounts::set_notes( $user_id, $this->posted( 'notes', true ) );

		/**
		 * Save extra account fields posted from the account form.
		 *
		 * @param int $user_id Customer id.
		 */
		do_action( 'dtaw_account_form_save', $user_id );

		$this->back( self::account_url( $user_id ), 'saved' );
	}

	/**
	 * Add and approve a customer.
	 *
	 * @return void
	 */
	public function handle_account_add() {
		$this->guard( 'dtaw_account_add' );
		$user_id = (int) $this->posted( 'user' );
		if ( ! $user_id || ! get_userdata( $user_id ) ) {
			$this->back( self::url(), '', __( 'Choose a customer first.', 'demfilz-trade-accounts-for-woocommerce' ) );
		}
		DTAW_Accounts::set_terms( $user_id, (int) $this->posted( 'terms' ) );
		DTAW_Accounts::set_status( $user_id, 'approved' );
		$this->back( self::account_url( $user_id ), 'added' );
	}

	/**
	 * Record a payment.
	 *
	 * @return void
	 */
	public function handle_record_payment() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in guard() below, which needs the id first.
		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$this->guard( 'dtaw_record_payment_' . $order_id );
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			$this->back( self::url( array( 'tab' => 'invoices' ) ), '', __( 'Order not found.', 'demfilz-trade-accounts-for-woocommerce' ) );
		}
		$result = DTAW_Ledger::record_payment(
			$order,
			array(
				'date' => $this->posted( 'date' ),
				'ref'  => $this->posted( 'ref' ),
				'note' => $this->posted( 'note' ),
			)
		);
		$back   = wp_get_referer() ? wp_get_referer() : $order->get_edit_order_url();
		if ( is_wp_error( $result ) ) {
			$this->back( $back, '', $result->get_error_message() );
		}
		$this->back( $back, 'paid' );
	}

	/**
	 * Reverse a payment.
	 *
	 * @return void
	 */
	public function handle_reverse_payment() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in guard() below, which needs the id first.
		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$this->guard( 'dtaw_reverse_payment_' . $order_id );
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			$this->back( self::url( array( 'tab' => 'invoices' ) ), '', __( 'Order not found.', 'demfilz-trade-accounts-for-woocommerce' ) );
		}
		$result = DTAW_Ledger::reverse_payment( $order );
		$back   = wp_get_referer() ? wp_get_referer() : $order->get_edit_order_url();
		if ( is_wp_error( $result ) ) {
			$this->back( $back, '', $result->get_error_message() );
		}
		$this->back( $back, 'unpaid' );
	}

	/**
	 * Save settings.
	 *
	 * @return void
	 */
	public function handle_save_settings() {
		$this->guard( 'dtaw_save_settings' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified in guard().
		$input = array(
			'terms_days'         => $this->posted( 'terms_days' ),
			'order_status'       => $this->posted( 'order_status' ),
			'po_field'           => $this->posted( 'po_field' ),
			'auto_roles'         => isset( $_POST['auto_roles'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['auto_roles'] ) ) : array(),
			'instructions'       => $this->posted( 'instructions', true ),
			'complete_when_paid' => isset( $_POST['complete_when_paid'] ) ? 'yes' : 'no',
			'who_can_apply'      => $this->posted( 'who_can_apply' ),
			'delete_data'        => isset( $_POST['delete_data'] ) ? 'yes' : 'no',
		);
		// phpcs:enable
		DTAW_Settings::save( $input );
		$this->back( self::url( array( 'tab' => 'settings' ) ), 'settings' );
	}

	/**
	 * CSV export of the invoices list.
	 *
	 * @return void
	 */
	public function handle_export() {
		$this->guard( 'dtaw_export' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified in guard().
		$view   = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'open';
		$result = DTAW_Ledger::query(
			array(
				'state'   => in_array( $view, array( 'open', 'paid', 'void' ), true ) ? $view : ( 'overdue' === $view ? 'open' : '' ),
				'overdue' => 'overdue' === $view,
				'per'     => -1,
			)
		);
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="trade-account-invoices-' . $view . '-' . DTAW_Ledger::today() . '.csv"' );
		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Streaming CSV to the browser.
		fputcsv( $out, array( 'order', 'customer', 'business', 'email', 'account_ref', 'date', 'due', 'state', 'days_overdue', 'paid_on', 'payment_ref', 'po', 'amount', 'currency' ) );
		foreach ( $result['orders'] as $order ) {
			$user_id = $order->get_customer_id();
			$paid    = (string) $order->get_meta( DTAW_Ledger::META_PAID_AT, true );
			fputcsv(
				$out,
				array(
					$order->get_order_number(),
					trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
					DTAW_Accounts::application( $user_id )['business'],
					$order->get_billing_email(),
					DTAW_Accounts::ref( $user_id ),
					$order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d' ) : '',
					DTAW_Ledger::due( $order ),
					DTAW_Ledger::state( $order ),
					DTAW_Ledger::days_overdue( $order ),
					'' !== $paid ? substr( $paid, 0, 10 ) : '',
					(string) $order->get_meta( DTAW_Ledger::META_PAID_REF, true ),
					DTAW_Ledger::po( $order ),
					wc_format_decimal( DTAW_Ledger::invoice_amount( $order ), wc_get_price_decimals() ),
					$order->get_currency(),
				)
			);
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Streaming CSV to the browser.
		exit;
	}

	/*
	|--------------------------------------------------------------------------
	| Users screens
	|--------------------------------------------------------------------------
	*/

	/**
	 * Column on the users list.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function users_column( $columns ) {
		if ( current_user_can( self::CAP ) ) {
			$columns['dtaw'] = __( 'Trade account', 'demfilz-trade-accounts-for-woocommerce' );
		}
		return $columns;
	}

	/**
	 * Column value.
	 *
	 * @param string $output  Output so far.
	 * @param string $column  Column.
	 * @param int    $user_id User id.
	 * @return string
	 */
	public function users_column_value( $output, $column, $user_id ) {
		if ( 'dtaw' !== $column ) {
			return $output;
		}
		$status = DTAW_Accounts::effective_status( $user_id );
		if ( 'none' === $status ) {
			return '<span class="dtaw-muted">&ndash;</span>';
		}
		return '<a href="' . esc_url( self::account_url( $user_id ) ) . '">' . self::badge( $status ) . '</a>';
	}

	/**
	 * Panel on the user profile.
	 *
	 * @param WP_User $user User.
	 * @return void
	 */
	public function user_panel( $user ) {
		if ( ! current_user_can( self::CAP ) || ! $user instanceof WP_User ) {
			return;
		}
		$status = DTAW_Accounts::status( $user->ID );
		?>
		<h2 id="dtaw"><?php esc_html_e( 'Trade account', 'demfilz-trade-accounts-for-woocommerce' ); ?></h2>
		<table class="form-table dtaw-user-panel" role="presentation">
			<tr>
				<th><label for="dtaw_u_status"><?php esc_html_e( 'Status', 'demfilz-trade-accounts-for-woocommerce' ); ?></label></th>
				<td>
					<select id="dtaw_u_status" name="dtaw_u_status">
						<?php foreach ( DTAW_Accounts::statuses() as $slug => $label ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $slug, $status ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php if ( 'none' === $status && DTAW_Accounts::role_approved( $user->ID ) ) : ?>
						<span class="description"><?php esc_html_e( 'Approved through their role.', 'demfilz-trade-accounts-for-woocommerce' ); ?></span>
					<?php endif; ?>
					<p class="description">
						<a href="<?php echo esc_url( self::account_url( $user->ID ) ); ?>"><?php esc_html_e( 'Open the trade account (application, invoices, balance)', 'demfilz-trade-accounts-for-woocommerce' ); ?></a>
						&middot; <?php esc_html_e( 'Changing the status here emails the customer, as approving from the Trade Accounts screen does.', 'demfilz-trade-accounts-for-woocommerce' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th><label for="dtaw_u_terms"><?php esc_html_e( 'Payment terms', 'demfilz-trade-accounts-for-woocommerce' ); ?></label></th>
				<td><input type="number" id="dtaw_u_terms" name="dtaw_u_terms" min="0" max="365" class="small-text" value="<?php echo esc_attr( DTAW_Accounts::terms_raw( $user->ID ) ? DTAW_Accounts::terms_raw( $user->ID ) : '' ); ?>" placeholder="<?php echo esc_attr( DTAW_Settings::get( 'terms_days' ) ); ?>" /> <?php esc_html_e( 'days (empty = store default)', 'demfilz-trade-accounts-for-woocommerce' ); ?></td>
			</tr>
			<tr>
				<th><label for="dtaw_u_ref"><?php esc_html_e( 'Account reference', 'demfilz-trade-accounts-for-woocommerce' ); ?></label></th>
				<td><input type="text" id="dtaw_u_ref" name="dtaw_u_ref" class="regular-text" maxlength="40" value="<?php echo esc_attr( DTAW_Accounts::ref( $user->ID ) ); ?>" /></td>
			</tr>
			<?php
			/**
			 * Extra rows on the user profile panel (an add-on can add a credit limit).
			 *
			 * @param WP_User $user User.
			 */
			do_action( 'dtaw_user_panel_rows', $user );
			?>
		</table>
		<?php
	}

	/**
	 * Save the profile panel (WordPress verified the profile nonce before firing this hook).
	 *
	 * @param int $user_id User id.
	 * @return void
	 */
	public function user_panel_save( $user_id ) {
		if ( ! current_user_can( self::CAP ) || ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Core verified update-user_{id} before edit_user_profile_update.
		if ( ! isset( $_POST['dtaw_u_status'] ) ) {
			return;
		}
		$status = sanitize_key( wp_unslash( $_POST['dtaw_u_status'] ) );
		DTAW_Accounts::set_terms( $user_id, isset( $_POST['dtaw_u_terms'] ) ? (int) $_POST['dtaw_u_terms'] : 0 );
		DTAW_Accounts::set_ref( $user_id, isset( $_POST['dtaw_u_ref'] ) ? sanitize_text_field( wp_unslash( $_POST['dtaw_u_ref'] ) ) : '' );
		// phpcs:enable
		if ( DTAW_Accounts::status( $user_id ) !== $status ) {
			DTAW_Accounts::set_status( $user_id, $status );
		}

		/**
		 * Save extra profile panel fields.
		 *
		 * @param int $user_id User id.
		 */
		do_action( 'dtaw_user_panel_save', $user_id );
	}
}
