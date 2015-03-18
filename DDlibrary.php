<?php
/**
 * Created by PhpStorm.
 * User: okwinza
 * Date: 5/21/14
 * Time: 5:22 AM
 */

class DDlibrary extends ObjectModel {

    public static function getCompaniesToHTML($type = 'pp') {

        if ($type == 'pp')
            $companies_array = array(
                array('id' => '25]', 'name' => 'СДЭК склад-склад', 'val' => '1', 'sId'=>25, 'attrs' => array('v'=>150000, 'w' => 30)),
                array('id' => '4]', 'name' => 'Boxberry', 'val' => '1', 'sId'=>4, 'attrs' => array('x' => 50, 'y' => 80, 'z' => 100, 'w' => 15)),
                array('id' => '21]', 'name' => 'Boxberry Express', 'val' => '1', 'sId'=>21, 'attrs' => array('x' => 50, 'y' => 80, 'z' => 100, 'w' => 15)),
                array('id' => '11]', 'name' => 'Hermes', 'val' => '1', 'sId'=>11, 'attrs' => array('s' => 150, 'w' => 5)),
                array('id' => '22]', 'name' => 'IM Logistics Экспресс', 'val' => '1', 'sId'=>22, 'attrs' => array('s' => 150, 'w' => 20)),
                array('id' => '17]', 'name' => 'IM Logistics', 'val' => '1', 'sId'=>17, 'attrs' => array('s' => 150, 'w' => 20)),
                array('id' => '16]', 'name' => 'IM Logistics Пушкинская', 'val' => '1', 'sId'=>16, 'attrs' => array('s' => 150, 'w' => 25)),
                array('id' => '3]', 'name' => 'Logibox', 'val' => '1', 'sId'=>3, 'attrs' => array('x' => 33, 'y' => 35, 'z' => 58, 'w' => 15)),
                //    array('id' => 'maxima', 'name' => 'Maxima Express', 'val' => '1', 'sId'=>14, 'attrs' => array('s' => 80, 'w' => 5)),
                array('id' => '1]', 'name' => 'PickPoint', 'val' => '1', 'sId'=>1, 'attrs' => array('x' => 36, 'y' => 36, 'z' => 60, 'w' => 10)),
                array('id' => '7]', 'name' => 'QIWI', 'val' => '1', 'sId'=>7, 'attrs' => array('x' => 38, 'y' => 41, 'z' => 64, 'w' => 30))
            );
        else
            $companies_array = array(
                array('id' => '26]', 'name' => 'СДЭК склад-дверь', 'val' => '1', 'sId'=>26, 'attrs' => array('v'=>150000, 'w' => 30)),
                array('id' => '29]', 'name' => 'DPD Classic', 'val' => '1', 'sId'=>29, 'attrs' => array('x' => 80, 'y' => 80, 'z' => 120, 'w' => 400)),
                array('id' => '27]', 'name' => 'DPD Economy', 'val' => '1', 'sId'=>27, 'attrs' => array('x' => 120, 'y' => 150, 'z' => 170, 'w' => 1000)),
                array('id' => '23]', 'name' => 'DPD Consumer', 'val' => '1', 'sId'=>23, 'attrs' => array('x' => 80, 'y' => 80, 'z' => 120, 'w' => 400)),
                array('id' => '28]', 'name' => 'DPD Express', 'val' => '1', 'sId'=>28, 'attrs' => array('x' => 80, 'y' => 80, 'z' => 120, 'w' => 150)),
                array('id' => '20]', 'name' => 'DPD Parcel', 'val' => '1', 'sId'=>20, 'attrs' => array('v' => 120000, 'w' => 20)),
                array('id' => '17]', 'name' => 'IM Logistics', 'val' => '1', 'sId'=>17, 'attrs' => array('s' => 150, 'w' => 20)),
                //   array('id' => 'logibox', 'name' => 'Logibox', 'val' => '1', 'sId'=>3, 'attrs' => array('x' => 33, 'y' => 35, 'z' => 58, 'w' => 15)),
                //    array('id' => 'maxima', 'name' => 'Maxima Express', 'val' => '1', 'sId'=>14, 'attrs' => array('s' => 80, 'w' => 5)),
                //    array('id' => 'pickpoint', 'name' => 'PickPoint', 'val' => '1', 'sId'=>1, 'attrs' => array('x' => 36, 'y' => 36, 'z' => 60, 'w' => 10)),
                //    array('id' => 'qiwi', 'name' => 'QIWI', 'val' => '1', 'sId'=>7, 'attrs' => array('x' => 38, 'y' => 41, 'z' => 64, 'w' => 30))
            );
        return $companies_array;
    }

    public static function getDDPaymentModules() {
        $payment_methods = array();
        foreach (PaymentModule::getInstalledPaymentModules() as $payment){
            $module = Module::getInstanceByName($payment['name']);
            if (Validate::isLoadedObject($module) && $module->active)
                $payment_methods[] = array(
                    'key' => $payment['name'],
                    'name' => $module->displayName
                );
        }
        return $payment_methods;
    }

    public static function getOrderStatuses() {
        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('
		SELECT os.id_order_state as id, osl.name
		FROM `'._DB_PREFIX_.'order_state` os
		LEFT JOIN `'._DB_PREFIX_.'order_state_lang` osl ON (os.`id_order_state` = osl.`id_order_state` AND osl.`id_lang` = 1)
		WHERE deleted = 0
		ORDER BY `name` ASC');
    }



} 