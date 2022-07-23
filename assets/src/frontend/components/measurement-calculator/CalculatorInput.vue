<template>
    <div class="measurement-calculator-inner">
        <div class="calculator-input">
            <div v-for="item, index in measurementElements" :key="index">
                <label :for="`calculator-input-${ index }`">{{ getLabel( index ) }} </label>
                <input
                    type="number"
                    :id="`calculator-input-${ index }`"
                    min="0"
                    @input="measurementInputHandler( $event.target.value )"
                    v-model="cartItem[index].value"
                >
            </div>
        </div>
        <p><strong>{{ `Total (${ cartItem.measurement_needed_unit }): ` }}</strong>{{ cartItem.measurement_needed_total }}</p>
    </div>
</template>
<style lang="less">
.measurement-calculator-inner {
    .calculator-input {
        display: flex;
        align-items: center;
        gap: 15px;
        & input {
            -webkit-appearance: none;
            border-radius: 3px;
            border: 1px solid #eceef0;
            display: block;
            font-size: 13px;
            margin-right: 5px;
            margin-top: 8px;
            outline: none;
            padding: 5px;
            width: 60px;
        }
    }
}
</style>

<script>
export default {
    name: 'CalculatorInput',
    props: {
        products: {
            type: Array,
            required: true,
        },
        cartItemKey: Number,
    },

    data() {
        return {
            product: {},
        }
    },

    computed: {
        cartItem() {
            return this.$store.state.Cart.cartdata.line_items[this.cartItemKey];
        },

        measurementPriceCalculator() {
            return this.product.wepos_measurement_price_calculator;
        },

        measurementElements() {
            const data = this.measurementPriceCalculator.measurement_data;
            let result = {};

            weLo_.forIn( this.cartItem, ( item, key ) => {
                if (
                    key.includes('measurement_needed_') &&
                    ! ['measurement_needed_total', 'measurement_needed_unit'].includes(key)
                ) {
                    Object.assign( result, { [key]: item } );
                }
            } );

            return result;
        },
    },

    methods: {
        getLabel( key ) {
            return `${ this.cartItem[key].label } (${ this.cartItem[key].unit })`;
        },

        initData() {
            const indexProduct = weLo_.findIndex( this.products, { id: this.cartItem.product_id } );
            this.product       = this.products[indexProduct];

            this.updateCartPrice();
        },

        measurementInputHandler( key ) {
            this.resetMeasurementTotal();
            weLo_.forIn( this.measurementElements, ( item, index ) => {
                this.setTotalMeasurmentNeeded( item.value );
            } );

            this.updateCartPrice();
        },

        setTotalMeasurmentNeeded( value ) {
            this.$store.dispatch( 'Cart/setTotalMeasurmentNeededAction', {
                product: this.product,
                value: value,
                key: this.cartItemKey
            } );
        },

        resetMeasurementTotal() {
            this.$store.dispatch( 'Cart/setCartItemAction', {
                itemKey: this.cartItemKey,
                key: 'measurement_needed_total',
                value: 0
            } );
        },

        updateCartPrice() {
            const pricingRules = this.measurementPriceCalculator.pricing_rules;
            const needed       = this.cartItem.measurement_needed_total;

            const price = pricingRules.filter( el => {
                return needed >= parseFloat(el.range_start) && (el.range_end === '' || needed <= el.range_end);
            });

            let resultprice = this.product.sales_display_price;

            if ( price.hasOwnProperty( 0 ) && needed ) {
                resultprice = price[0].price * needed
            }

            weLo_.forEach( ['total', 'regular_price', 'sale_price'], item => {
                let price = resultprice
                if ( item === 'total' ) {
                    price = this.$store.getters['Cart/getSubtotal'];
                }

                this.$store.dispatch( 'Cart/setCartItemAction', {
                    itemKey: this.cartItemKey,
                    key: item,
                    value: price
                } );
            } );
        },

        updateMeasurementData() {
            this.updateCartPrice();

            if ( ! this.cartItem.hasOwnProperty( 'meta_data' ) ) {
                this.cartItem.meta_data = [];
            }

            let metaData = [
                {
                    key: `Total (${this.cartItem.measurement_needed_unit})`,
                    value: this.cartItem.measurement_needed_total
                }
           ];

            let measurementMetaData = {
                key: '_measurement_data',
                value: {
                    '_measurement_needed': this.cartItem.measurement_needed_total,
                    '_measurement_needed_unit': this.cartItem.measurement_needed_unit,
                },
            };

            weLo_.forIn( this.measurementElements, ( item, index ) => {
                Object.assign( measurementMetaData.value, {
                    [index]: item
                });
                metaData.push({
                    key: item.label,
                    value: item.value
                });
            } );

            metaData.push( measurementMetaData );

            this.$store.dispatch( 'Cart/setCartItemAction', {
                itemKey: this.cartItemKey,
                key: 'meta_data',
                value: metaData
            } );
        }
    },

    mounted() {
        wepos.hooks.addAction( 'wepos_before_create_order', 'CalculatorInput', () => {
            this.updateMeasurementData();
        } );
    },

    created() {
        this.initData();
    }
}
</script>

