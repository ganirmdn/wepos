<template>
    <div class="wepos-checkout-print-wrapper" v-if="settings.wepos_receipts">
        <div class="custom-logo">
            <img :src="wepos.custom_logo_url" alt="" v-if="wepos.custom_logo_url">
        </div>
        <div class="header" v-html="settings.wepos_receipts.receipt_header"></div>
        <div class="order-info">
            <p >{{ __( 'Nomor Order', 'wepos' ) }}: #{{ printdata.order_id }}</p>
            <p>{{ __( 'Tanggal', 'wepos' ) }}: {{ formatDate( printdata.order_date ) }}</p>
            <template v-if="printdata.customer_id">
                <p>{{ __( 'Nama Customer', 'wepos') }}: {{ `${printdata.billing.first_name} ${printdata.billing.last_name}` }}</p>
                <p>{{ __( 'ID Customer', 'wepos') }}: {{ printdata.customer_id }}</p>
            </template>
            <div class="wepos-clearfix"></div>
        </div>
        <div class="content">
            <table class="sale-summary">
                <thead>
                    <tr class="divider">
                        <th scope="col" colspan="4"></th>
                    </tr>
                    <tr>
                        <th scope="col">Nama Produk</th>
                        <th scope="col">Harga Satuan</th>
                        <th scope="col">Jumlah</th>
                        <th scope="col" class="total">Total</th>
                    </tr>
                    <tr class="divider">
                        <th scope="col" colspan="4"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item, index in printdata.line_items" :key="index">
                        <td class="name">
                            {{ item.name }}
                            <div class="attribute" v-if="item.measurement_needed_total">
                                <ul>
                                    <li v-for="measurement, indexMeta in getMeasurementData( item )" :key="indexMeta">
                                        <span class="attr_name">{{ measurement.label }} ({{ measurement.unit }})</span>: <span class="attr_value">{{ measurement.value }}</span>,
                                    </li>
                                    <li><span class="attr_name">Total({{ item.measurement_needed_unit }})</span>: <span class="attr_value">{{ item.measurement_needed_total }}</span></li>
                                </ul>
                            </div>
                            <div class="attribute" v-if="item.attribute.length > 0">
                                <ul>
                                    <li v-for="attribute_item in item.attribute"><span class="attr_name">{{ attribute_item.name }}</span>: <span class="attr_value">{{ attribute_item.option }}</span></li>
                                </ul>
                            </div>
                        </td>
                        <td>
                            <template v-if="item.on_sale">
                                <span class="sale-price">{{ formatPrice( item.sale_price ) }}</span>
                            </template>
                            <template v-else>
                                <span class="sale-price">{{ formatPrice( item.regular_price ) }}</span>
                            </template>
                        </td>
                        <td class="quantity">{{ item.quantity }}</td>
                        <td class="price">
                            <template v-if="item.on_sale">
                                <span class="sale-price">{{ formatPrice( item.quantity*item.sale_price ) }}</span>
                            </template>
                            <template v-else>
                                <span class="sale-price">{{ formatPrice( item.quantity*item.regular_price ) }}</span>
                            </template>
                        </td>
                    </tr>
                    <tr>
                        <td></td>
                        <td colspan="3" class="item-sold-title"><strong>Jumlah Item Terjual</strong></td>
                    </tr>
                    <tr class="cart-meta-data">
                        <td colspan="3" class="name">
                            {{ __( 'Subtotal', 'wepos' ) }}
                            <span class="metadata" v-if="settings.woo_tax.wc_tax_display_cart == 'incl'">
                                {{ __( 'Includes Tax', 'wepos' ) }} {{ formatPrice( $store.getters['Cart/getTotalLineTax'] ) }}
                            </span>
                        </td>
                        <td class="price">{{ formatPrice( printdata.subtotal ) }}</td>
                    </tr>
                    <tr v-for="(fee,key) in printdata.fee_lines" class="cart-meta-data">
                        <template v-if="fee.type=='discount'">
                            <td colspan="3" class="name">{{ __( 'Discount', 'wepos' ) }} <span class="metadata">{{ fee.discount_type == 'percent' ? fee.value + '%' : formatPrice( fee.value ) }}</span></td>
                            <td class="price">-{{ formatPrice( Math.abs( fee.total ) ) }}</td>
                        </template>
                        <template v-else>
                            <td colspan="3" class="name">{{ __( 'Fee', 'wepos' ) }} <span class="metadata">{{ fee.name }} {{ fee.fee_type == 'percent' ? fee.value + '%' : formatPrice( fee.value ) }}</span></td>
                            <td class="price">-{{ formatPrice( Math.abs( fee.total ) ) }}</td>
                        </template>
                    </tr>
                    <tr v-if="printdata.taxtotal">
                        <td colspan="3" class="name">{{ __( 'Tax', 'wepos' ) }}</td>
                        <td class="price">{{ formatPrice(printdata.taxtotal) }}</td>
                    </tr>
                    <tr>
                        <td colspan="3" class="name">{{ __( 'Total', 'wepos' ) }}</td>
                        <td class="price">{{ formatPrice(printdata.ordertotal) }}</td>
                    </tr>
                    <tr class="divider">
                        <td scope="col" colspan="4"></td>
                    </tr>
                    <tr>
                        <td colspan="3">{{ __( 'Pembayaran', 'wepos' ) }}</td>
                        <td class="price">{{ printdata.gateway.title || '' }}</td>
                    </tr>
                    <template v-if="printdata.gateway.id='wepos_cash'">
                        <tr>
                            <td colspan="3">{{ __( 'Total dibayar', 'wepos' ) }}</td>
                            <td class="price">{{ formatPrice( printdata.cashamount ) }}</td>
                        </tr>
                        <tr>
                            <td colspan="3">{{ __( 'Kembalian', 'wepos' ) }}</td>
                            <td class="price">{{ formatPrice( printdata.changeamount ) }}</td>
                        </tr>
                    </template>
                    <template v-if="printdata.points">
                        <tr>
                            <td colspan="3">Point Diperoleh</td>
                            <td class="points">{{ printdata.points }}</td>
                        </tr>
                        <tr>
                            <td colspan="3">Total Point Anda</td>
                            <td class="points">{{ printdata.points_balance }}</td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        <div class="footer">
            <p>Terima Kasih</p>
            <p>{{ wepos.current_user_display_name }}</p>
            <h2>{{ wepos.site_name }}</h2>
        </div>
        <div class="footer" v-html="settings.wepos_receipts.receipt_footer"></div>
    </div>
</template>
<script>

export default {
    name: 'ReceiptPrintHtml',

    props: {
        printdata: {
            type: Object,
            default() {
                return {};
            }
        },
        settings: {
            type: Object,
            default() {
                return {};
            }
        }
    },

    methods: {
        formatDate( date ) {
            var date = new Date( date );
            return date.toLocaleString();
        },

        getMeasurementData( cartItem ) {
            let result = {};

            weLo_.forIn( cartItem, ( item, key ) => {
                if (
                    key.includes('measurement_needed_') &&
                    ! ['measurement_needed_total', 'measurement_needed_unit'].includes(key)
                ) {
                    Object.assign( result, { [key]: item } );
                }
            } );

            return result;
        }
    }
};

</script>
<style lang="less">

[v-cloak] {display: none}

@media print {
    body * {
        visibility: hidden;
    }

    .wepos-modal-content {
        // display: none;
        visibility: hidden;
    }

    .wepos-checkout-print-wrapper {
        color: #181818;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
        display: inline-block !important;
    }

    .wepos-checkout-print-wrapper * {
        visibility: visible;
    }

    .wepos-checkout-print-wrapper {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        max-width: 100%;

        .custom-logo {
            width: 80px;
            height: auto;
            margin: 0 auto;
            text-align: center;
        }

        .header, .footer{
            padding: 5px;
            text-align: center;
        }

        .footer {
            font-weight: bold;
        }

        .order-info {
            margin: 0;
            padding: 8px 0;
            p {
                line-height: 0.8em;
            }
        }

        .content {
            table.sale-summary {
                width: 100%;
                table-layout: auto;
                border-collapse: collapse;
                thead {
                    tr {
                        th {
                            text-align: left;
                            font-size: 14px;
                            padding: 4px 0;
                            &.total {
                                text-align: right;
                            }
                        }
                        &.divider {
                            border-bottom: 1px dashed #b7b7b7;
                            color: #b5b5b5;
                        }
                    }
                }
                tbody {
                    tr {
                        td {
                            font-size: 14px;
                            padding: 8px 0;
                            &.points {
                                text-align: right;
                                font-weight: bold;
                            }
                            &.name {
                                width: 45%;
                                font-weight: bold;
                                .attribute {
                                    margin-top: 2px;
                                    ul {
                                        margin: 0;
                                        padding: 0;
                                        list-style: none;
                                        li {
                                            display: inline-block;
                                            margin-right: 5px;
                                            font-size: 12px;
                                            font-weight: normal;
                                            .attr_name {
                                                color: #758598;
                                            }
                                        }
                                    }
                                }
                            }
                            &.quantity {
                                color: #758598;
                            }
                            &.item-sold-title {
                                text-align: right;
                            }
                            &.price {
                                text-align: right;
                                font-weight: bold;
                                span {
                                    &.regular-price {
                                        font-size: 12px;
                                        text-decoration: line-through;
                                    }
                                }
                            }
                        }

                        &.cart-meta-data {
                            td {
                                .metadata {
                                    margin-left: 5px;
                                    color: #758598;
                                    font-size: 13px;
                                    font-weight: normal;
                                }
                            }
                        }

                        &.divider {
                            border-bottom: 1px dashed #b7b7b7;
                            color: #b5b5b5;
                        }
                    }
                }
            }
        }
    }
}
</style>
