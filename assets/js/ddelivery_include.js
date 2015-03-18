var topWindow = parent;

var price = "label[for^=ddelivery]:eq(1)";

while(topWindow != topWindow.parent) {
    topWindow = topWindow.parent;
}

if(typeof(topWindow.DDeliveryIntegration) == 'undefined')
    topWindow.DDeliveryIntegration = (function(){
        var th = {};
        var status = 'Выберите условия доставки';
        var buttons = '#button-shipping-method,#simplecheckout_button_confirm,a#confirm,button#confirm,#button-confirm,#simplecheckout_next,#button-checkout' ;
        var button = null;
        
        th.getStatus = function(){
            return status;
        };
        
        
        function hideCover() {
            document.body.removeChild(document.getElementById('ddelivery_cover'));
        }

        function showPrompt() {
            var cover = document.createElement('div');
            cover.id = 'ddelivery_cover';
            document.body.appendChild(cover);
            document.getElementById('ddelivery_container').style.display = 'block';
        }
        
        function getFakeButton(){
            if ($('#fakeBtn').length > 0){
                $('#fakeBtn').remove();
            }
            if ($('#fakeBtn').length == 0){
                var text = '';
                $(buttons).each(function(idx){
                   if ($(this).is(':visible')){
                       if ($(this).val())
                        text = $(this).val(); 
                       else 
                        text = $(this).text();
                       button = $(this); 
                   }
                });
                var clone = button.clone();
                $(clone).attr('id','fakeBtn');
                $(clone).attr('onclick','');
                button.after(clone);
                clone.click(function(){
                    //alert('Сначала выберите точку доставки DDelivery');
                    DDeliveryIntegration.openPopup();
                });
            }
            return $('#fakeBtn');
            
        }
        
        th.showFakeButton = function showFakeButton(show){
            var fake_btn = getFakeButton();
            
            if (fake_btn == null && typeof fake_btn.css == 'undefined')
                return;
            
            if (show == true){
                $(button).css('display','none');
                $(fake_btn).css('display','inline-block');
                
            }
            else{
                $(button).css('display','inline-block');
                $(fake_btn).css('display','none'); 
                
                //alert((fake_btn.text())?fake_btn.text():fake_btn.val());   
            }
        }
        

        th.openPopup = function(){
            showPrompt();
            document.getElementById('ddelivery_popup').innerHTML = '';
            //jQuery('#ddelivery_popup').html('').modal().open();
            var params = {
                formData: {}
            };
            /*
            $($('#ORDER_FORM').serializeArray()).each(function(){
                params.formData[this.name] = this.value;
            });
            */

            var callback = {
                close: function(){
                    hideCover();
                    document.getElementById('ddelivery_container').style.display = 'none';
                    this.updatePage();
                    if ($('label#dd_info').text()=='') th.showFakeButton(true);
                    else th.showFakeButton(false);
                },
                change: function(data) {
                    //alert('Не забываем фильтровать способы оплаты ');
                    status = data.comment;
                    console.log(data);
                    
                    hideCover();
                    $('#ddelivery_container').css('display','none');
                    $('label#dd_info').text(data.comment);
                    jQuery('label[for^=ddelivery]:eq(1)').text(data.clientPrice.toFixed(2) + ' руб.');
                    jQuery('#button-shipping-method').css('display','inline-block');
                    this.updatePage();
                    th.showFakeButton(false);
                    $(price).css('visibility','visible');
                    //$('#ID_DELIVERY_ddelivery_all').click();
                },
                updatePage: function(){
                    if (typeof simplecheckout_reload !== 'undefined'){
                        simplecheckout_reload('shipping_changed');
                        }
                    else if (typeof reloadAll !== 'undefined'){
                        reloadAll();
                        }
                    else if (typeof window.simplecheckout !== 'undefined' && typeof window.simplecheckout.reloadAll !== 'undefined'){
                        window.simplecheckout.reloadAll();
                        }
                    console.log(window.Simplecheckout);
                }
            };
            callback.updatePage();
            //console.log(location);
            DDelivery.delivery('ddelivery_popup', 'ddelivery/ajax.php', {orderId: 4,dd_plugin:1}, callback);
            return void(0);
        };
        var body = document.getElementsByTagName('div')[0];
        if ((body !== null) && (typeof body !== "undefined") ){
            
            var style = document.createElement('STYLE');
            style.innerHTML = // Скрываем ненужную кнопку
                " #delivery_info_ddelivery_all a{display: none;} " +
                " #ddelivery_popup { display: inline-block; vertical-align: middle; margin: 10px auto; width: 1000px; height: 650px;} " +
                " #ddelivery_container { position: fixed; top: 0; left: 0; z-index: 9999;display: none; width: 100%; height: 100%; text-align: center;  } " +
                " #ddelivery_container:before { display: inline-block; height: 100%; content: ''; vertical-align: middle;} " +
                " #ddelivery_cover {  position: fixed; top: 0; left: 0; z-index: 2; width: 100%; height: 100%; background-color: #000; background: rgba(0, 0, 0, 0.5); filter: progid:DXImageTransform.Microsoft.gradient(startColorstr = #7F000000, endColorstr = #7F000000); } ";
            body.appendChild(style);
            
            var div = document.createElement('div');
            div.innerHTML = '<div id="ddelivery_popup"></div>';
            div.id = 'ddelivery_container';
            body.appendChild(div);
            
            
        }

        return th;
    })();

var DDeliveryIntegration = topWindow.DDeliveryIntegration;

if (document.getElementById('select_way') == null){
    $('label[for^=ddelivery]:eq(0)').after('<a href="javascript:void(null)" onclick="DDeliveryIntegration.openPopup();" id="select_way" class="trigger">Выбрать точку доставки</a>');
}
    
$('input:radio[name=shipping_method]').click(function (){
    if ($(this).val() == 'ddelivery.ddelivery'){
        DDeliveryIntegration.openPopup();
        if ($('label#dd_info').text()==''){
            DDeliveryIntegration.showFakeButton(true);
            }
    }
    else{
        DDeliveryIntegration.showFakeButton(false);
    }
    
});

$(document).ready(function(){
    $(price).css('visibility','hidden');
    setInterval(function(){
        if ($('label#dd_info').text()=='')
            $(price).css('visibility','hidden');
        if ($('input:radio[name=shipping_method]:checked').val() == 'ddelivery.ddelivery' && $('label#dd_info').text()=='' && 
            typeof $('#select_way') !== null && $('#select_way').is(':visible')){
            DDeliveryIntegration.showFakeButton(true);
        }
        else{
            //alert('hide');
            DDeliveryIntegration.showFakeButton(false);
            
        }    
    },1000);
});

/* Хуки на выбор компании или точки
mapPointChange: function(data) {},
courierChange: function(data) {}
*/