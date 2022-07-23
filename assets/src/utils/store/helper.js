import BigNumber from "bignumber.js";

export default {
    hasStock( product, productCartQty = 0 ) {
        if ( ! product.manage_stock ) {
            return ( 'outofstock' == product.stock_status ) ? false : true;
        } else {
            if ( product.backorders_allowed ) {
                return true;
            } else {
                return product.stock_quantity > productCartQty;
            }
        }
    },

    /**
	 * Convert value from the current unit to a new unit
	 *
	 * @param {numeric} value the value in fromUnit units
	 * @param {string} fromUnit fromUnit the unit that value is in
	 * @param {string} toUnit the unit to convert to
     * @param {object} product
	 * @return {numeric} value in toUnit untis
	 */
     convertUnits( value, fromUnit, toUnit, product ) {

		value = new BigNumber(value);

		// fromUnit to its corresponding standard unit
		if ( 'undefined' !== typeof( product.wepos_measurement_price_calculator.unit_normalize_table[ fromUnit ] ) ) {

			if ( 'undefined' !== typeof( product.wepos_measurement_price_calculator.unit_normalize_table[ fromUnit ].inverse ) && product.wepos_measurement_price_calculator.unit_normalize_table[ fromUnit ].inverse ) {
				value = value.div(product.wepos_measurement_price_calculator.unit_normalize_table[ fromUnit ].factor);
			} else {
				value = value.times(product.wepos_measurement_price_calculator.unit_normalize_table[ fromUnit ].factor);
			}

			fromUnit = product.wepos_measurement_price_calculator.unit_normalize_table[ fromUnit ].unit;
		}

		// standard unit to toUnit
		if ( 'undefined' !== typeof( product.wepos_measurement_price_calculator.unit_conversion_table[ fromUnit ] ) && 'undefined' !== typeof( product.wepos_measurement_price_calculator.unit_conversion_table[ fromUnit ][ toUnit ] ) ) {

			if ( 'undefined' !== typeof( product.wepos_measurement_price_calculator.unit_conversion_table[ fromUnit ][ toUnit ].inverse ) && product.wepos_measurement_price_calculator.unit_conversion_table[ fromUnit ][ toUnit ].inverse ) {
				value = value.div(product.wepos_measurement_price_calculator.unit_conversion_table[ fromUnit ][ toUnit ].factor);
			} else {
				value = value.times(product.wepos_measurement_price_calculator.unit_conversion_table[ fromUnit ][ toUnit ].factor);
			}
		}

		return value.toNumber();
	}
};
