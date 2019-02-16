$(document).ready(function() {
    var url = document.getElementsByTagName('base'),id = document.getElementsByName('products_id'),token = document.getElementsByName('securityToken');	
    if(typeof url !== 'undefined' && typeof url[0] !== 'undefined' && url[0].href != '' && typeof token !== 'undefined' && typeof token[0] !== 'undefined' && token[0].value != '' && typeof id !== 'undefined' && typeof id[0] !== 'undefined' && id[0].value != '' ) {
        addHTML(id[0].value,token[0].value,url[0].href);
    }
})
function addHTML(id,token,url)
{
	$.ajax({
        type     : 'post',
        url      : url+'finance_main_handler.php',
        data     : {
        	action:'getCalculatorWidget',
        	products_id: id,
        	securityToken: token,
        },
        dataType : 'json',
        cache    : false,
        success: function(data) {
        	if(typeof data.js !== 'undefined') {
        		if(typeof data.widget !== 'undefined') {
        			$('#productDetailsList').prepend(data.widget);
        		}
        		if(typeof data.calculator !== 'undefined') {
        			$('form[name="cart_quantity"]').append(data.calculator);
        		}
        		setTimeout(function(){
        			dividoKey = data.js;
        			var script = document.createElement('script');
					script.src = data.jsSrc;
					document.head.appendChild(script);
        		},100);
        	}
        }
    });
}