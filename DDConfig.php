<?php
/**
 * Created by PhpStorm.
 * User: okwinza
 * Date: 5/14/14
 * Time: 12:19 AM
 */
class DDConfig {
    protected static $config = array(
        'api_key'               => '',

        'courier_id_self'       => '',
        'courier_id_delivery'   => '',

        'modifier_pickup'       => null,
        'modifier_courier'      => null,

        'insurance_percent'     => 0,
        'around_price_type'     => 'round',
        'around_price_step'     => 0.5,

        'payment_mode_self'     => '3',
        'payment_mode_delivery' => '3',

        'is_pay_pickup'         => 1,


        'courier_title_self'     => 'DDelivery(самовывоз)',
        'courier_title_delivery' => 'DDelivery(курьерская доставка)',

        'courier_desc_self'     => 'Плагин доставки Digital Delivery(самовывоз)',
        'courier_desc_delivery' => 'Плагин доставки Digital Delivery(курьерская доставка)',

        'ddelivery_test_mode' => '1',

        'default_width'     => '10',
        'default_length'    => '10',
        'default_height'    => '10',
        'default_weight'    => '10',
        'width_assoc'        => 'width',
        'height_assoc'       => 'height',
        'lenght_assoc'       => 'lenght',
        'weight_assoc'       => 'weight',
    );

    public static function loadConfig(){
        $module_config = json_decode(Configuration::get('DD_MODULE_SETTINGS'), 1);


        if(!is_array($module_config)) return static::getConfig();

        static::$config = $module_config;

/*        foreach($module_config as $key => $value){
            if(!array_key_exists($key, static::$config)) continue;
            static::$config[$key] = $value;
        }*/

        return static::getConfig();
    }
    public static function saveConfig(array $new_config){
/*        foreach(static::$config as $key => $value){
            static::$config[$key] = $new_config[$key];
        }*/

        static::$config = $new_config;

        return Configuration::updateValue('DD_MODULE_SETTINGS', json_encode($new_config), 1);
    }

    public static function getConfig(){
        return static::$config;
    }
}