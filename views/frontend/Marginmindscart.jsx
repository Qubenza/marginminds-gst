const { registerPlugin } = wp.plugins;
const showOnCart     = gstmarginmindsSettings?.showOnCart     ?? false;
const isCart = gstmarginmindsSettings?.isCart ?? false;
const showOnCheckout = gstmarginmindsSettings?.showOnCheckout ?? false;
const isCheckout = gstmarginmindsSettings?.isCheckout ?? false;

const CartGSTTotals = () => {
    const { ExperimentalDiscountsMeta } = wc.blocksCheckout;
    const { TotalsItem } = wc.blocksComponents;
    const { getCurrencyFromPriceResponse } = wc.priceFormat;
    const { useSelect } = wp.data;
    const { gst, currency } = useSelect((select) => {
        const cart = select('wc/store/cart').getCartData();
        return {
            gst: cart?.extensions?.marginminds ?? null,
            currency: cart?.totals
                ? getCurrencyFromPriceResponse(cart.totals)
                : null,
        };
    });
    if (!gst || gst.total <= 0 || !currency) {
        return null;
    }

    const half = gst.rate / 2;
    const isCgstSgst = gst.tax_type === 'cgst_sgst';

    const children = [];

    if (isCgstSgst) {
        children.push(
            wp.element.createElement(TotalsItem, {
                key: 'cgst',
                label: 'CGST (' + half + '%)',
                value: gst.cgst,
                currency: currency,
            }),
            wp.element.createElement(TotalsItem, {
                key: 'sgst',
                label: 'SGST (' + half + '%)',
                value: gst.sgst,
                currency: currency,
            })
        );
    } else {
        children.push(
            wp.element.createElement(TotalsItem, {
                key: 'igst',
                label: 'IGST (' + gst.rate + '%)',
                value: gst.igst,
                currency: currency,
            })
        );
    }

    return wp.element.createElement(
        ExperimentalDiscountsMeta,
        null,
        wp.element.createElement(
            'div',
            { className: 'marginminds-gst-rows' },
            ...children
        )
    );
};

if ( showOnCart && isCart ) {
    registerPlugin('marginminds-cart-gst', {
        render: CartGSTTotals,
        scope: 'woocommerce-checkout',
    });
}
if ( showOnCheckout && isCheckout ) {
    registerPlugin('marginminds-cart-gst', {
        render: CartGSTTotals,
        scope: 'woocommerce-checkout',
    });
}
