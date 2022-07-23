<?php
namespace WeDevs\WePOS\REST\WoocommercePoints;

use WC_Order;
use WC_Points_Rewards_Manager;
use WC_Points_Rewards_Product;

/**
 * Tax API Controller
 */
class PointsController extends \WP_REST_Controller {

	/**
	 * Endpoint namespace
	 *
	 * @var string
	 */
	protected $namespace = 'wepos/v1';

	/**
	 * Route name
	 *
	 * @var string
	 */
	protected $base = 'points';

    /**
     * Register all routes related with settings
     *
     * @return void
     */
    public function register_routes() {
        register_rest_route( $this->namespace, '/' . $this->base, array(
            array(
                'methods'              => \WP_REST_Server::READABLE,
                'callback'             => array( $this, 'get_points' ),
                'args'                 => $this->get_collection_params(),
                'permission_callback'  => [ $this, 'get_points_permission_check' ]
            ),
        ) );
    }

	/**
	 * Setting permission check
	 *
     * @return bool|\WP_Error
     *
     */
	public function get_points_permission_check() {
		if ( ! ( current_user_can( 'manage_woocommerce' ) || apply_filters( 'wepos_rest_manager_permissions', false ) ) ) {
			return new \WP_Error( 'wepos_rest_cannot_batch', __( 'Sorry, you are not allowed view this resource.', 'wepos' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}

	/**
	 * Get settings
	 *
     * @since 1.0.0
     *
     * @return \WP_Error|\WP_HTTP_Response|\WP_REST_Response
     *
     */
	public function get_points( $request ) {
		$order = wc_get_order( $request['id'] );

        if ( ! $order instanceof WC_Order ) {
            return new \WP_Error( 'no-order-found', __( 'No order found for processing this request', 'wepos' ), [ 'status' => 404 ] );
        }

        $points_earned = 0;

		foreach ( $order->get_items() as $item_key => $item ) {

			if ( version_compare( WC_VERSION, '4.4.0', '>=' ) ) {
				$product = $item->get_product();
			} else {
				$product = $order->get_product_from_item( $item );
			}

			if ( ! is_object( $product ) ) {
				continue;
			}

			// If prices include tax, we include the tax in the points calculation
			if ( 'no' === get_option( 'woocommerce_prices_include_tax' ) ) {
				// Get the un-discounted price paid and adjust our product price
				$item_price = $order->get_item_subtotal( $item, false, true );
			} else {
				// Get the un-discounted price paid and adjust our product price
				$item_price = $order->get_item_subtotal( $item, true, true );
			}

			$product->set_price( $item_price );

			// Calc points earned
			$points_earned += WC_Points_Rewards_Product::get_points_earned_for_product_purchase( $product, $order, 'edit' ) * $item['qty'];
		}

		// Reduce by any discounts.  One minor drawback: if the discount includes a discount on tax and/or shipping
		// It will cost the customer points, but this is a better solution than granting full points for discounted orders.
		if ( version_compare( WC_VERSION, '2.3', '<' ) ) {
			$discount = $order->get_total_discount( false );
		} else {
			$discount = $order->get_total_discount( ! wc_prices_include_tax() );
		}

		$points_earned -= min( WC_Points_Rewards_Manager::calculate_points( $discount ), $points_earned );

		// Check if applied coupons have a points modifier and use it to adjust the points earned.
		$coupons = version_compare( WC_VERSION, '3.7', 'ge' ) ? $order->get_coupon_codes() : $order->get_used_coupons();

		$points_earned = WC_Points_Rewards_Manager::calculate_points_modification_from_coupons( $points_earned, $coupons );

		$points_earned = WC_Points_Rewards_Manager::round_the_points( $points_earned );



		return rest_ensure_response( [
            'earned'         => $points_earned,
            'points_balance' => WC_Points_Rewards_Manager::get_users_points( $order->user_id )
        ] );
	}

}
