var topWindow = parent;

while (topWindow != topWindow.parent) {

    topWindow = topWindow.parent;

}

if (typeof(topWindow.DDeliveryIntegration) == 'undefined')

    topWindow.DDeliveryIntegration = (function() {

        var th = {};
        var buttons = 'a#confirm,button#confirm,#button-confirm,#confirmbtn,#checkoutFormSubmit,button[name=setshipment],button[name=processCarrier],.payment_module a,.place_order' ;
        var button = null;
        
        function getFakeButton(){
                if ($('#fakeBtn').length > 0){
                    $('#fakeBtn').remove();
                }
                if ($('#fakeBtn').length == 0){
                    var text = '';
                    var counter = 0 ;
                    $(buttons).each(function(idx){
                       counter++; 
                       if ($(this).is(':visible')){
                           if ($(this).val())
                            text = $(this).val(); 
                           else 
                            text = $(this).text();
                           button = $(this);
                            
                       }
                    });
                    if (counter > 1){
                        button = null;
                        if ($('#dd_error').length == 0){
                            $('#HOOK_PAYMENT').append('<div id="dd_error" style="color:red; font-style:bold;">Сначала укажите способ доставки DDelivery</div>');
                            //console.log(counter);
                            }
                        return $('#dd_error');
                    }
                    if (button == null) return null;
                    var clone = button.clone();
                    $(clone).attr('id','fakeBtn');
                    $(clone).attr('onclick','');
                    button.after(clone);
                    clone.click(function(){
                        //alert('Сначала выберите точку доставки DDelivery');
                        DDeliveryIntegration.openPopup();
                        return false;
                    });
                }
                return $('#fakeBtn');
                
            }
            
            th.showFakeButton = function showFakeButton(show){
                var fake_btn = getFakeButton();
                
                if (fake_btn == null || typeof fake_btn.css == 'undefined')
                    return;
                //console.log(show);
                if (show == true){
                    $(buttons).css('display','none');
                    $(fake_btn).css('display','inline-block');
                }
                else{
                    $(buttons).css('display','inline-block');
                    $(fake_btn).css('display','none'); 
                    //alert((fake_btn.text())?fake_btn.text():fake_btn.val());   
                }
            };
            
        th.openPopup = function() {

             jQuery('#ddelivery_popup').modal().open();

            var params = {
                //formData: {}
            };
            
        
            var callback = {

                close: function() {

                    jQuery.modal().close();
                    if ($('#dd_price').text() == ''){    
                        $('#onepagecheckoutps_step_three').hide();
                        $('#dd_error').show();
                        }
                    if ($('#dd_address').text()=='') th.showFakeButton(true);
                        else th.showFakeButton(false);
                },

                change: function(data) {
                    //console.log(data);
                    status = data.comment;
                    $('input.delivery_option_radio:checked').parent().removeClass('checked');
                    $('input.delivery_option_radio:checked').removeAttr('checked');
                    var input = $('input[type=radio][value="'+data.dd_carrier_id+',"]');
                    input.parent().addClass('checked');
                    input.attr('checked','checked');
                    
                    if (input.closest('tr').find('td').length == 2){
                        input.closest('tr').find('.delivery_option_price').html('');
                        $('#dd_price').html('<div id="dd_price">' + data.clientPrice +' руб</div>');
                    }
                    else{
                        $('#dd_price').parent().html('<a id="select_way" href="javascript:DDeliveryIntegration.openPopup()">Указать способ доставки</a><div id="dd_price">' + data.clientPrice +' руб</div>');
                    }
                    $('#dd_address').html(data.comment);
                    jQuery.modal().close();
                    $('#total_shipping').html(data.clientPrice + ' руб');
                    var totalProductCost = $('#total_product').text();
                    $('#total_price').html( parseFloat(data.clientPrice) + parseFloat(totalProductCost.replace('$','').replace(' ','')) + ' руб');
                    $('#onepagecheckoutps_step_three').show();
                    $('#dd_error').hide();
                    th.showFakeButton(false);
                }

            };
            function $_GET(key) {
                var s = window.location.search;
                s = s.match(new RegExp(key + '=([^&=]+)'));
                return s ? s[1] : false;
            }
            //alert( $_GET('test') );
            if($_GET('test') == 1){
                params.debug = 1;
            }
                
            DDelivery.delivery('ddelivery_popup', '/index.php?fc=module&module=ddelivery&controller=process&' + $.param(params), params, callback);

            return void(0);

        };

return th;

})();

function DDShowFakeButton(){
    if (($('.delivery_option_radio input:radio:checked').val() == dd_id_carrier+',' || $('input.delivery_option_radio:radio:checked').val() == dd_id_carrier+',') && $('#dd_address').text()=='' && 
        typeof $('#select_way') !== null && $('#select_way').is(':visible')){
            DDeliveryIntegration.showFakeButton(true);
    }
    else{
        DDeliveryIntegration.showFakeButton(false);
    }
}

$(document).ready(function(){
    $('body').append('<div class="modal" id="ddelivery_popup" style="display: none"></div>');
    var input = $('input[type=radio][value="'+dd_id_carrier+',"]');
    input.attr('onclick','DDeliveryIntegration.openPopup()');
    $('.delivery_option_radio').change(function(){
        if (typeof(Carrier) !== 'undefined'){
           Carrier.validateSelected();
            if (Carrier.id_delivery_option_selected == dd_id_carrier+','){ 
                DDeliveryIntegration.openPopup();
                 if (document.getElementById('dd_error') == null)
                        $('#onepagecheckoutps_step_three').after('<div id="dd_error" style="color:red; font-style:bold;">Сначала укажите способ доставки DDelivery</div>');
            }
            else{
                $('#dd_error').hide();
                $('#onepagecheckoutps_step_three').show();
            }
        }
    });
    var deliv_tbl_tr =  input.parents('tr');
    $('.delivery_option').css({'float':'left','width':'100%'});
    if (!$(deliv_tbl_tr).parents('table').hasClass('resume')){
        //deliv_tbl_tr = $(input).parent().parent();
        deliv_tbl_tr = input.closest('.delivery_option').find('table.resume tr');
        //alert('a');
    }
    var tds_cnt = deliv_tbl_tr.find("td").length;
    if (tds_cnt == 2){
        deliv_tbl_tr.find("td:eq("+(tds_cnt-1)+")").append('<strong><a id="select_way" href="javascript:DDeliveryIntegration.openPopup()" class="">Указать способ доставки</a></strong><div id="dd_price"><div>');
        deliv_tbl_tr.find("td:eq("+(tds_cnt-1)+")").append('<div id=\"dd_address\"></div>');
    }
    else{
        deliv_tbl_tr.find("td:eq("+(tds_cnt-1)+")").html('<a id="select_way" href="javascript:DDeliveryIntegration.openPopup()" class="">Указать способ доставки</a><div id="dd_price"><div>');
        deliv_tbl_tr.find("td:eq("+(tds_cnt-1)+")").css('text-align','right');
        deliv_tbl_tr.find("td:eq("+(tds_cnt-2)+")").append('<div id=\"dd_address\"></div>');
    }
    var DDeliveryIntegration = topWindow.DDeliveryIntegration;
    
    if (typeof Carrier !== 'undefined'){
        Carrier.validateSelected();
        if (Carrier.id_delivery_option_selected == dd_id_carrier+','){ 
            $('#onepagecheckoutps_step_three').hide();
            $('#dd_error').show();
            DDeliveryIntegration.openPopup();
             if (document.getElementById('dd_error') == null)
                    $('#onepagecheckoutps_step_three').after('<div id="dd_error" style="color:red; font-style:bold;">Сначала укажите способ доставки</div>');
        }
        
    }
    else {
        //console.log(DDeliveryIntegration);
        DDShowFakeButton();
        setInterval(DDShowFakeButton,1000);
    }
    //else alert(input.parents('span').attr('class'));    
});

