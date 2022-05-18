<?php
namespace WeDevs\WePOS\REST;

/**
 * REST Manager Handler
 */
class Manager {

    /**
     * Class dir and class name mapping
     *
     * @var array
     */
    protected $class_map;

    /**
     * Load autometically
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function __construct() {
        $this->class_map = apply_filters( 'wepos_rest_api_class_map', array(
            WEPOS_INCLUDES . '/REST/PaymentController.php'  => '\WeDevs\WePOS\REST\PaymentController',
            WEPOS_INCLUDES . '/REST/SettingController.php'  => '\WeDevs\WePOS\REST\SettingController',
            WEPOS_INCLUDES . '/REST/TaxController.php'      => '\WeDevs\WePOS\REST\TaxController',
            WEPOS_INCLUDES . '/REST/CustomerController.php' => '\WeDevs\WePOS\REST\CustomerController',
            WEPOS_INCLUDES . '/REST/ProductController.php'  => '\WeDevs\WePOS\REST\ProductController'
        ) );

        // Init REST API routes.
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ), 10 );
        add_filter( 'woocommerce_rest_prepare_product_object', [ $this, 'product_response' ], 10, 3 );
        add_filter( 'woocommerce_rest_prepare_product_variation_object', [ $this, 'product_response' ], 10, 3 );
        add_filter( 'woocommerce_rest_prepare_product_cat', [ $this, 'category_response' ], 10, 3 );
        add_filter( 'woocommerce_rest_prepare_tax', [ $this, 'tax_response' ], 10, 3 );
        add_filter( 'woocommerce_rest_pre_insert_shop_order_object', [ $this, 'validate_item_stock_before_order' ], 10, 3 );
    }

    /**
     * Register REST API routes.
     *
     * @since 1.0.0
     */
    public function register_rest_routes() {
        foreach ( $this->class_map as $file_name => $controller ) {
            require_once $file_name;
            $controller = new $controller();
            $controller->register_routes();
        }
    }

    /**
     * Modify product response for variations
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function product_response( $response, $product, $request ) {
        global $_wp_additional_image_sizes;
        $data           = $response->get_data();
        $type           = isset( $data['type'] ) ? $data['type'] : '';
        $variation_data = [];
        $tax_display_on_shop = get_option( 'woocommerce_tax_display_shop', 'excl' );
        $tax_display_on_cart = get_option( 'woocommerce_tax_display_cart', 'excl' );
        $tax_calculations    = get_option( 'woocommerce_prices_include_tax', 'no' );

        if ( 'variable' == $type ) {
            foreach( $data['variations'] as $variation ) {
                $variation_api_class = new \WC_REST_Product_Variations_Controller();
                $response = $variation_api_class->get_item(
                    [
                        'id'         => $variation,
                        'product_id' => $variation,
                        'context'    => 'view'
                    ]
                );
                $variation_data[] = $response->get_data();
            }
        }

        $price_excl_tax                = wc_get_price_excluding_tax( $product );
        $price_incl_tax                = wc_get_price_including_tax( $product );
        $tax_amount                    = (float)$price_incl_tax - (float)$price_excl_tax;

        $data['variations']            = [];
        $data['variations']            = $variation_data;
        $data['tax_amount']            = wc_format_decimal( $tax_amount, wc_get_price_decimals() );

        if ( 'no' == $tax_calculations ) {
            if ( 'incl' == $tax_display_on_cart ) {
                $data['regular_display_price'] = wc_format_decimal( (float)wc_get_price_excluding_tax( $product, [ 'price' => $product->get_regular_price() ] ) + $tax_amount, wc_get_price_decimals() );
                $data['sales_display_price']   = wc_format_decimal( (float)wc_get_price_excluding_tax( $product, [ 'price' => $product->get_sale_price() ] ) + $tax_amount, wc_get_price_decimals() );
            } else {
                $data['regular_display_price'] = wc_format_decimal( (float)wc_get_price_excluding_tax( $product, [ 'price' => $product->get_regular_price() ] ), wc_get_price_decimals() );
                $data['sales_display_price']   = wc_format_decimal( (float)wc_get_price_excluding_tax( $product, ['price' => $product->get_sale_price() ] ), wc_get_price_decimals() );
            }
        } else {
            if ( 'incl' == $tax_display_on_cart ) {
                $data['regular_display_price'] = wc_format_decimal( (float)wc_get_price_excluding_tax( $product, [ 'price' => $product->get_regular_price() ] ) + $tax_amount, wc_get_price_decimals() );
                $data['sales_display_price']   = wc_format_decimal( (float)wc_get_price_excluding_tax( $product, [ 'price' => $product->get_sale_price() ] ) + $tax_amount, wc_get_price_decimals() );
            } else {
                $data['regular_display_price'] = wc_format_decimal( (float)wc_get_price_excluding_tax( $product, [ 'price' => $product->get_regular_price() ] ), wc_get_price_decimals() );
                $data['sales_display_price']   = wc_format_decimal( (float)wc_get_price_excluding_tax( $product, ['price' => $product->get_sale_price() ] ), wc_get_price_decimals() );
            }
        }

        $data['barcode']               = $product->get_meta( '_wepos_barcode' );

        if ( ! empty( $data['images'] ) ) {
            foreach ( $data['images'] as $key => $image) {
                $image_urls = [];
                foreach ( $_wp_additional_image_sizes as $size => $value ) {
                    $image_info = wp_get_attachment_image_src( $image['id'], $size );
                    $data['images'][$key][$size] = $image_info[0];
                }
            }
        }

        if (
            class_exists( 'WC_Measurement_Price_Calculator' ) &&
            \WC_Price_Calculator_Product::calculator_enabled( $product )
        ) {
            $wepos_measurement_price_calculator = $this->measurment_price_calculator_support( $data, $product );
            if ( is_array( $wepos_measurement_price_calculator ) ) {
                $data['wepos_measurement_price_calculator'] = $wepos_measurement_price_calculator;
            }
        }

        $response->set_data( $data );

        return $response;
    }

    /**
     * Added some param in tax return
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function tax_response( $response, $tax, $request ) {
        $data = $response->get_data();
        $data['percentage_rate'] = \WC_Tax::get_rate_percent( $tax->tax_rate_id );
        $response->set_data( $data );
        return $response;
    }

    /**
     * Added some param in category return
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function category_response( $response, $category, $request ) {
        $data = $response->get_data();
        $data['parent_id'] = $category->parent ? $category->parent : null;
        $response->set_data( $data );
        return $response;
    }

    /**
     * Validate Stock in order item
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function validate_item_stock_before_order( $order, $request, $creating ) {
        if ( ! $creating ) {
            return $order;
        }

        $items = $order->get_items();

        foreach ( $items as $item ) {
            $product              = $item->get_product();
            $is_manage_stock      = $product->get_manage_stock();
            $is_backorder_allowed = $product->get_backorders();

            if ( ! $is_manage_stock || 'no' !== $is_backorder_allowed ) {
                return $order;
            }

            $stock_quantity = $product->get_stock_quantity();
            $order_quantity = $item->get_quantity();

            if ( $order_quantity > $stock_quantity ) {
                throw new \WC_REST_Exception( 'woocommerce_rest_invalid_product_quantity', sprintf( __( 'The item %s already out of stock. Please remove this from cart', 'wepos' ), $product->get_name() ), 400 );
            }
        }

        return $order;
    }

    /**
     * measurment_price_calculator_support
     *
     * @param  array $data
     * @param  WC_Product $product
     * @return array|bool
     */
    public function measurment_price_calculator_support( $data, $product ) {
        $settings            = new \WC_Price_Calculator_Settings( $product );
        $product_measurement = \WC_Price_Calculator_Product::get_product_measurement( $product, $settings );

        if ( ! $product_measurement ) {
            return false;
        }

        $measurement_precision      = apply_filters( 'wepos_measurement_price_calculator_measurement_precision', 3 );
        $wc_price_calculator_params = [
            'unit_normalize_table'  => \WC_Price_Calculator_Measurement::get_normalize_table(),
            'unit_conversion_table' => \WC_Price_Calculator_Measurement::get_conversion_table(),
            'measurement_precision' => $measurement_precision,
            'measurement_type'      => $settings->get_calculator_type(),
        ];

        $min_price = $product->get_meta( '_wc_measurement_price_calculator_min_price' );

        $wc_price_calculator_params['minimum_price'] = is_numeric( $min_price ) ? wc_get_price_to_display( $product, [ 'price' => $min_price ] ) : '';

        // information required for either pricing or quantity calculator to function
        $wc_price_calculator_params['product_price'] = $product->is_type( 'variable' ) ? '' : wc_get_price_to_display( $product );

        list( $measurement ) = $settings->get_calculator_measurements();

        $product_measurement->set_common_unit( $measurement->get_unit_common() );

        // this is the unit that the product total measurement will be in, ie it's how we know what unit we get for the Volume (AxH) calculator after multiplying A * H
        $wc_price_calculator_params['product_total_measurement_common_unit'] = $product_measurement->get_unit_common();

        if ( \WC_Price_Calculator_Product::pricing_calculator_enabled( $product ) ) {

            // product information required for the pricing calculator javascript to function
            $wc_price_calculator_params['calculator_type']    = 'pricing';
            $wc_price_calculator_params['product_price_unit'] = $settings->get_pricing_unit();
            $wc_price_calculator_params['pricing_overage']    = $settings->get_pricing_overage();

            foreach ($data['meta_data'] as $key => $meta_data) {
                if ( $meta_data->key === '_wc_price_calculator' ) {
                    $wc_price_calculator_params['measurement_data'] = $meta_data->value[$settings->get_calculator_type()];
                }
            }

            // if there are pricing rules, include them on the page source
            if ( $settings->pricing_rules_enabled() ) {
                $wc_price_calculator_params['pricing_rules'] = $settings->get_pricing_rules();
            }
        }

        return apply_filters( 'wepos_measurement_price_calculator_product', $wc_price_calculator_params );
    }
}
