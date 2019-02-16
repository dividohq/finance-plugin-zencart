$(document).ready(function() {
    var base_url = window.location.origin,href = window.location.href, regex = new RegExp(/(modules.php)(?=.*module=(financepayment))(?=.*action=(edit))(?=.*set=(payment))/g),mat = regex.exec(href);
    console.log(mat);
    if(mat !== null && mat.length > 0 && mat[0] !== undefined) {
        if(mat[1] !== undefined && mat[2] !== undefined && mat[3] !== undefined && mat[4] !== undefined) { //welcome to backend order edit page
            var e = $("td.infoBoxContent:contains(Product price minimum)"), h = e.html(), CusV = $('input[name="configuration[MODULE_PAYMENT_FINANCEPAYMENT_MIN_PRODUCT]"]')[0].value, ns = h.replace('<b>Product price minimum</b><br>Product price minimum<br><input type="text" name="configuration[MODULE_PAYMENT_FINANCEPAYMENT_MIN_PRODUCT]" value="'+CusV+'"><br>','<div class="product_min"><br><b>Product price minimum</b><br>Product price minimum<br><input type="text" name="configuration[MODULE_PAYMENT_FINANCEPAYMENT_MIN_PRODUCT]" value="'+CusV+'"></div>');
            e.html(ns);
            var ec = $('input[name="configuration[MODULE_PAYMENT_FINANCEPAYMENT_PRODUCT_SELECTION]"]');
            function selectionChanged()
            {
                var pm = $('.product_min');
                ($('input[name="configuration[MODULE_PAYMENT_FINANCEPAYMENT_PRODUCT_SELECTION]"]:checked').val() == "Products above minimum value") ? pm.show("slow") : pm.hide("slow");
            }
            ec.on("change",selectionChanged);
            selectionChanged();
        }
    }
});