(function($){
    $(function() {

        var city = $('#city');
        var street = $('#address1');

        // Автодополнение населённых пунктов
        city.kladr({
            token: '51dfe5d42fb2b43e3300006e',
            key: '86a2c2a06f1b2451a87d05512cc2c3edfdf41969',
            type: $.kladr.type.city,
            select: function( obj ) {
                // Изменения родительского объекта для автодополнения улиц
                street.kladr('parentId', obj.id);
            }
        });

        // Автодополнение улиц
        street.kladr({
            token: '538c4281fca916df1f4d411c',
            key: '2e6583195ebc7172146815d1376b84ea3bc36e3a',
            type: $.kladr.type.street,
            parentType: $.kladr.type.city
        });
    });
})(jQuery);