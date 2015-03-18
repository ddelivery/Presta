<?php
/**
 * Created by PhpStorm.
 * User: okwinza
 * Date: 6/4/14
 * Time: 1:09 AM
 */
use DDelivery\Order\DDeliveryOrder;
use DDelivery\Order\DDeliveryProduct;
use DDelivery\Order\DDStatusProvider;


    
class prestashopIntegrator extends \DDelivery\Adapter\PluginFilters
{

    public $context;
    public $ddeliveryUI;
    public $module_config;


    /**
     * Синхронизация локальных статусов
     * @var array
     */
    protected $cmsOrderStatus = array(
        DDStatusProvider::ORDER_IN_PROGRESS => 3,
        DDStatusProvider::ORDER_CONFIRMED => 15,
        DDStatusProvider::ORDER_IN_STOCK => 14,
        DDStatusProvider::ORDER_IN_WAY => 15,
        DDStatusProvider::ORDER_DELIVERED => 5,
        DDStatusProvider::ORDER_RECEIVED => 17,
        DDStatusProvider::ORDER_RETURN => 20,
        DDStatusProvider::ORDER_CUSTOMER_RETURNED => 21,
        DDStatusProvider::ORDER_PARTIAL_REFUND => 22,
        DDStatusProvider::ORDER_RETURNED_MI => 2,
        DDStatusProvider::ORDER_WAITING => 25,
        DDStatusProvider::ORDER_CANCEL => 6);
    
    function __construct()
    {
        $this->context = Context::getContext();
        if (isset($this->context->cart) && isset($this->context->cart->id_address_delivery))
            $this->customer_data = new Address($this->context->cart->id_address_delivery);
        $this->module_config = DDConfig::loadConfig();
        $this->comparStatuses();
    }

    /**
     * Верните true если нужно использовать тестовый(stage) сервер
     * @return bool
     */
    public function isTestMode()
    {
        return ($this->module_config['ddelivery_test_mode'] == 1) ? TRUE : FALSE;
    }


    /**
     * Возвращает товары находящиеся в корзине пользователя, будет вызван один раз, затем закеширован
     * @return DDeliveryProduct[]
     */
    protected function _getProductsFromCart()
    {
        $products = array();
        $cartProducts = $this->context->cart->getProducts();
        //if (isset($_GET['debug']))
          // echo '<pre>'.print_r($cart_rules[0]['value_tax_exc'],1).'</pre>';
        foreach ($cartProducts as $cartProduct) {

            if($cartProduct['width']    == 0)   $cartProduct['width']   = $this->module_config['default_width'];
            if($cartProduct['height']   == 0)   $cartProduct['height']  = $this->module_config['default_height'];
            if($cartProduct['weight']   == 0)   $cartProduct['weight']  = $this->module_config['default_weight'];
            if($cartProduct['depth']    == 0)   $cartProduct['depth']   = $this->module_config['default_length'];
            $products[] = new DDeliveryProduct(
                $cartProduct['id_product'], //	int $id id товара в системе и-нет магазина
                $cartProduct['width'], //	float $width длинна
                $cartProduct['height'], //	float $height высота
                $cartProduct['depth'], //	float $length ширина
                $cartProduct['weight'], //	float $weight вес кг
                $cartProduct['price'], //	float $price стоимостьв рублях
                $cartProduct['quantity'], //	int $quantity количество товара
                $cartProduct['name'], //	string $name Название вещи
                $cartProduct['reference']
            );

        }
        return $products;
    }
    
    public function getDiscount(){
        $discount = 0.;
        $cart_rules = $this->context->cart->getCartRules();
        if (is_array($cart_rules) && count($cart_rules)){
            $field = 'value_tax_exc';
            foreach($cart_rules as $rule){
                if (isset($rule[$field]) && (double)$rule[$field]>0)
                    $discount += (double)$rule[$field];
            }
        }
        //if (isset($_GET['debug']))
          //  print_r($discount);
        return $discount;
    }
    
    public function getAmount()
    {
        $amount = 0.;
        foreach($this->getProductsFromCart() as $product) {
            $amount += $product->getPrice() * $product->getQuantity();
        }
        if ($amount > 0){
            $discount = $this->getDiscount();
            if ($discount <= $amount){
                $amount -= $discount;
            }
        }
        //if (isset($_GET['debug']))
          //  print_r($amount);
        return $amount;
    }
    
    public function comparStatuses(){
        $this->cmsOrderStatus = array(
            DDStatusProvider::ORDER_IN_PROGRESS         => $this->module_config['status_in_progress'],
            DDStatusProvider::ORDER_CONFIRMED           => $this->module_config['status_confirmed'],
            DDStatusProvider::ORDER_IN_STOCK            => $this->module_config['status_in_stock'],
            DDStatusProvider::ORDER_IN_WAY              => $this->module_config['status_in_way'],
            DDStatusProvider::ORDER_DELIVERED           => $this->module_config['status_delivered'],
            DDStatusProvider::ORDER_RECEIVED            => $this->module_config['status_received'],
            DDStatusProvider::ORDER_RETURN              => $this->module_config['status_return'],
            DDStatusProvider::ORDER_CUSTOMER_RETURNED   => $this->module_config['status_customer_returned'],
            DDStatusProvider::ORDER_PARTIAL_REFUND      => $this->module_config['status_partial_refund'],
            DDStatusProvider::ORDER_RETURNED_MI         => $this->module_config['status_returned_mi'],
            DDStatusProvider::ORDER_WAITING             => $this->module_config['status_waiting'],
            DDStatusProvider::ORDER_CANCEL              => $this->module_config['status_cancel']
        );
    }

    public function getDbConfig(){
        
        if (!defined('_DB_PREFIX_')){
            require(__DIR__.'/../../config/settings.inc.php');
        }
        $db = Db::getInstance();
        $user = _DB_USER_;
        $host =  _DB_SERVER_;
        $db =  _DB_NAME_; 
        $pref = 'ps_dd_';
        $pass = _DB_PASSWD_;
        return array(
            'type' => self::DB_MYSQL,
            'dsn' => "mysql:host=$host;dbname=$db",
            'user' => $user,
            'pass' => $pass,
            'prefix' => $pref,
        );
        $return = array(
            'pdo' => new \PDO("mysql:host=$host;dbname=$db", $user, $pass, array(\PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8")),
            'prefix' => $pref,
        );
        return $return;
    }
    
    /**
     * Меняет статус внутреннего заказа cms
     *
     * @param $cmsOrderID - id заказа
     * @param $status - статус заказа для обновления
     *
     * @return bool
     */
    public function setCmsOrderStatus($cmsOrderID, $status)
    {
        $order = new Order($cmsOrderID);
        if ((int)$status !== (int)$order->getCurrentState()) 
            $order->setCurrentState($status,1);
        /*
        $db = DB::getInstance();
        $q = "select current_state from "._DB_PREFIX_."orders where id_order='$cmsOrderID'";
        $old_status = $db->getValue($q);
        if ((int)$old_status !== (int)$status){
            $q = "UPDATE "._DB_PREFIX_."orders set current_state='$status' where id_order='$cmsOrderID'";
            $db->Execute($q);
            $q = "insert into "._DB_PREFIX_."order_history set id_employee=0, id_order='$cmsOrderID', id_order_state='$status', date_add=NOW()";
            $db->Execute($q);
        }*/
    }

    /**
     * Возвращает API ключ, вы можете получить его для Вашего приложения в личном кабинете
     * @return string
     */
    public function getApiKey()
    {
        return $this->module_config['api_key'];
    }

    /**
     * Должен вернуть url до каталога с статикой
     * @return string
     */
    public function getStaticPath()
    {
        return '/modules/ddelivery/assets/';
    }

    /**
     * URL до скрипта где вызывается DDelivery::render
     * @return string
     */
    public function getPhpScriptURL()
    {
        // Тоесть до этого файла
        return '/index.php?fc=module&module=ddelivery&controller=process';
    }

    /**
     * Возвращает путь до файла базы данных, положите его в место не доступное по прямой ссылке
     * @return string
     */
    public function getPathByDB()
    {
        return dirname(__FILE__) . '/db/db.sqlite';
    }

    /**
     * Возможность что - нибудь добавить к информации
     * при окончании оформления заказа
     *
     * @param $order DDeliveryOrder
     * @param $resultArray
     */
    public function onFinishResultReturn( $order, $resultArray ){
        $resultArray['dd_carrier_id'] = Configuration::get('DD_CARRIER_ID');
        return $resultArray;
    }
    
    /**
     * Метод будет вызван когда пользователь закончит выбор способа доставки
     *
     * @param int $orderId
     * @param \DDelivery\Order\DDeliveryOrder $order
     * @param bool $customPoint Если true, то заказ обрабатывается магазином
     * @return void
     */
    public function onFinishChange($order)
    {
        $point = $order->getPoint();
        $orderId = $order->localId;
        $cost = $this->ddeliveryUI->getOrderClientDeliveryPrice($order);
        if($this->getCart($this->context->cart->id)){
            $this->updateCartData($this->context->cart->id,$orderId,$cost);
        }else{
            $this->saveCartData($this->context->cart->id,$orderId,$cost);
        }
        $cart_id = (int)$this->context->cart->id;
        $sql = "UPDATE `ps_ddelivery_orders` SET dd_order_id = '{$orderId}' WHERE ps_cart_id = '{$cart_id}' LIMIT 1";
        Db::getInstance()->Execute($sql);
    }

    public function getCart($cart_id){

        $sql = "SELECT * FROM `ps_ddelivery_orders` WHERE ps_cart_id ='{$cart_id}' LIMIT 1";

        return Db::getInstance()->ExecuteS($sql);
    }

    public function updateCartData($cart_id,$dd_order_id,$point_data){
        $cart_products = json_encode($this->context->cart->getProducts());
        $cart_sig = crc32($cart_products);
        $sql = "UPDATE ps_ddelivery_orders SET dd_order_id='{$dd_order_id}', point_data = '{$point_data}', cart_sig = '{$cart_sig}' WHERE ps_cart_id='{$cart_id}' LIMIT 1";

        return Db::getInstance()->Execute($sql);

    }

    public function saveCartData($cart_id,$dd_order_id,$point_data){

        $cart_products = json_encode($this->context->cart->getProducts());
        $cart_sig = crc32($cart_products);
        $sql = "INSERT INTO `ps_ddelivery_orders`(`ps_cart_id`,`dd_order_id`,`point_data`,`cart_sig`) VALUES ('{$cart_id}','{$dd_order_id}','{$point_data}', '{$cart_sig}')";


        return Db::getInstance()->Execute($sql);
    }

    /**
     * Какой процент от стоимости страхуется
     * @return float
     */
    public function getDeclaredPercent()
    {
       // return 100;
        return $this->module_config['insurance_percent']; // Ну это же пример, пускай будет случайный процент
    }

    /**
     * Должен вернуть те компании которые показываются в курьерке
     * см. список компаний в DDeliveryUI::getCompanySubInfo()
     * @return int[]
     */
    public function filterCompanyPointCourier()
    {
        $enabled = array();
        //return array	(4,21,29,23,27,28,20,30,31,11,16,22,17,3,14,1,13,18,6,
        //                 26,25,24,7,35,36,37,39,40,42,43,44,45,46,47,48,49);
        
        foreach (static::getCompanySubInfo() as $item){

            if(isset($this->module_config['courier_companies_'.(int)$item['id']])){
                if($this->module_config['courier_companies_'.(int)$item['id']] == 'on'){
                    $enabled[] = (int) $item['id'];
                }
            }
        }
        
        return $enabled;
        // TODO: Implement filterCompanyPointCourier() method.
    }

    /**
     * Должен вернуть те компании которые показываются в самовывозе
     * см. список компаний в DDeliveryUI::getCompanySubInfo()
     * @return int[]
     */
    public function filterCompanyPointSelf()
    {
        //return array	(4,21,29,23,27,28,20,30,31,11,16,22,17,3,14,1,13,18,6,
         //                26,25,24,7,35,36,37,39,40,42,43,44,45,46,47,48,49);
        
        $enabled = array();
        foreach (static::getCompanySubInfo() as $item){
                
            if(isset($this->module_config['self_companies_'.(int)$item['id']])){
                if($this->module_config['self_companies_'.(int)$item['id']] == 'on'){
                    $enabled[] = (int)$item['id'];
                }
            }
        }

        return $enabled;
        // TODO: Implement filterCompanyPointSelf() method.
    }

    /**
     * Возвращаем способ оплаты константой PluginFilters::PAYMENT_, предоплата или оплата на месте. Курьер
     * @return int
     */
    public function filterPointByPaymentTypeCourier($order)
    {
        if ($order->paymentVariant == $this->module_config['courier_payment_module'])
            return \DDelivery\Adapter\PluginFilters::PAYMENT_POST_PAYMENT;
        else return \DDelivery\Adapter\PluginFilters::PAYMENT_PREPAYMENT;

        return self::PAYMENT_POST_PAYMENT;
    }

    /**
     * Возвращаем способ оплаты константой PluginFilters::PAYMENT_, предоплата или оплата на месте. Самовывоз
     * @return int
     */
    public function filterPointByPaymentTypeSelf($order)
    {
        if ($order->paymentVariant == $this->module_config['pvz_payment_module'])
            return \DDelivery\Adapter\PluginFilters::PAYMENT_POST_PAYMENT;
        else return \DDelivery\Adapter\PluginFilters::PAYMENT_PREPAYMENT;
        
        /*switch($this->module_config['pvz_payment_module']){
            case 1:
                return self::PAYMENT_POST_PAYMENT;
            case 2:
                return self::PAYMENT_PREPAYMENT;
            case 3:
                return self::PAYMENT_NOT_CARE;
        }*/

/*        return self::PAYMENT_POST_PAYMENT;
        // выбираем один из 3 вариантов(см документацию или комменты к констатам)
        return self::PAYMENT_POST_PAYMENT;
        return self::PAYMENT_PREPAYMENT;
        return self::PAYMENT_NOT_CARE; */

        return self::PAYMENT_POST_PAYMENT;
    }

    /**
     * Если true, то не учитывает цену забора
     * @return bool
     */
    public function isPayPickup()
    {
        //return true;
        return $this->module_config['is_pay_pickup'] == 'Y';
    }

    /**
     * Метод возвращает настройки оплаты фильтра которые должны быть собраны из админки
     *
     * @return array
     */
    public function getIntervalsByPoint()
    {
        $intervals = array();
        $types = array( 'allshop'               => self::INTERVAL_RULES_MARKET_ALL,
                        'percent'               => self::INTERVAL_RULES_MARKET_PERCENT,
                        'fixed'                 => self::INTERVAL_RULES_MARKET_AMOUNT,
                        'allcustomer'           => self::INTERVAL_RULES_CLIENT_ALL
        );

       // return $intervals;

        for($i=1; $i<=3; $i++){
            $intervals[] = array(
                'min'       =>     $this->module_config['modifier_min_'.$i],
                'max'       =>     $this->module_config['modifier_max_'.$i],
                'type'      =>     $types[$this->module_config['modifier_type_'.$i]],
                'amount'    =>     $this->module_config['modifier_percent_'.$i]
            );

        }
        //print_r($intervals);
        return $intervals;

/*        return array(
            array('min' => $this->module_config['modifier_min_1'], 'max' => $this->module_config['modifier_max_1'], 'type' => $types[$this->module_config['modifier_type_1']], 'amount' => $this->module_config['modifier_percent_1']),
            array('min' => 100, 'max' => 200, 'type' => self::INTERVAL_RULES_CLIENT_ALL, 'amount' => 60),
            array('min' => 300, 'max' => 400, 'type' => self::INTERVAL_RULES_MARKET_PERCENT, 'amount' => 3),
            array('min' => 1000, 'max' => null, 'type' => self::INTERVAL_RULES_MARKET_ALL),
        );*/
    }

    /**
     * Тип округления
     * @return int
     */
    public function aroundPriceType()
    {

        switch($this->module_config['around_price_type']){
            case 'floor':
                return self::AROUND_FLOOR;
            case 'round':
                return self::AROUND_ROUND;
            case 'ceil':
                return self::AROUND_CEIL;

        }


        return self::AROUND_ROUND; // self::AROUND_FLOOR, self::AROUND_CEIL
    }

    /**
     * Шаг округления
     * @return float
     */
    public function aroundPriceStep()
    {
        return empty($this->module_config['around_price_step']) ? 0.5 : $this->module_config['around_price_step']; // До 50 копеек
    }

    /**
     * описание собственных служб доставки
     * @return string
     */
    public function getCustomPointsString()
    {
        return '';
    }

    /**
     * Если вы знаете имя покупателя, сделайте чтобы оно вернулось в этом методе
     * @return string|null
     */
    public function getClientFirstName()
    {
        return $this->customer_data->lastname.' '.$this->customer_data->firstname;
    }
    
     public function getClientLastName()
    {
        return '';//$this->customer_data->lastname;
    }

    /**
     * Если вы знаете фамилию покупателя, сделайте чтобы оно вернулось в этом методе
     * @return string|null
     */
    public function getClientEmail()
    {
        //echo '<pre>'.print_r($this->context).'</pre>';
        return $this->context->customer->email;
    }

    /**
     * Если вы знаете телефон покупателя, сделайте чтобы оно вернулось в этом методе. 11 символов, например 79211234567
     * @return string|null
     */
    public function getClientPhone()
    {
        if ($this->customer_data->phone)
            $phone = $this->customer_data->phone;
        elseif ($phone = $this->customer_data->phone_mobile){
            $phone = $this->customer_data->phone_mobile;
        }    
        $phone = str_replace(array('+','-','(',')',' '),'',$phone);
        $phone = '+7'.substr($phone,-10);
        //throw new Exception("phone: $phone");
        return $phone;
    }

    /**
     * Верни массив Адрес, Дом, Корпус, Квартира. Если не можешь можно вернуть все в одном поле и настроить через get*RequiredFields
     * @return string[]
     */
    public function getClientAddress()
    {
        $addr = trim($this->customer_data->address1 . ' ' . $this->customer_data->address2);
        if ($addr) $ar = explode(',',$addr);
        else $ar = array();
        $return = array();
        $street  = '';
        $house  = '';
        $corp  = '';
        $flat  = '';
        if (count($ar)){
            foreach($ar as $k => $v){
                $ar[$k] = trim($v);
                if ($k >0 && strpos($v,'корп'))
                    $corp = trim($v);
                if ($k >0 && strpos($v,'кв'))
                    $flat = trim($v);
                }
        }else return $addr;
        $pat_street = "/^(.+)\s+(\d+)$/is";
        if (preg_match($pat_street,$ar[0], $matches)){
            if (isset($matches[1]))
                $street = $matches[1];
            if (isset($matches[2]))
                $house = $matches[2];
        }
        else {
            if (isset($ar[0]))
                $street = $ar[0];
            if (isset($ar[1]))
                $house = $ar[1];
            }
        //$street = trim(str_replace(array('ул.','ул','улица'),'',$street));
        $street = trim($street);
        $house = trim(str_replace(array('дом','д.','д',),'',$house));
        if ($corp)
            $corp = trim(str_replace(array('корпус','корп.','корп',),'',$corp));
        if ($flat)
            $flat = trim(str_replace(array('квартира','кв.','кв',),'',$flat));
        $return[] = $street;
        $return[] = $house;
        $return[] = $corp;
        $return[] = $flat;
        
        //print_r($matches);
        
        return $return;
    }

    /**
     * Верните id города в системе DDelivery
     * @return int
     */
    public function getClientCityId()
    {

        //$cityRes = $this->ddeliveryUI->sdk->getAutoCompleteCity($this->customer_data->city);
        //var_dump($cityRes->response);

        //return $cityRes->response[0]['_id'];
        // Если нет информации о городе, оставьте вызов родительского метода.
        return parent::getClientCityId();
    }

    /**
     * Возвращает поддерживаемые магазином способы доставки
     * @return array
     */
     public function getSupportedType()
        {
        switch($this->module_config['available_services']){
            case 'pickup':
                return array(
                    \DDelivery\Sdk\DDeliverySDK::TYPE_SELF
                );
            case 'delivery':
                return array(
                    \DDelivery\Sdk\DDeliverySDK::TYPE_COURIER,
                );
            case 'all':
            default:
                return array(
                    \DDelivery\Sdk\DDeliverySDK::TYPE_COURIER,
                    \DDelivery\Sdk\DDeliverySDK::TYPE_SELF
                );
        }
    }


    public function setDDeliveryUI($ddeliveryUI){
        $this->ddeliveryUI = $ddeliveryUI;
    }
    
    public function isStatusToSendOrder( $cmsStatus ){
        return ((int)$this->module_config['status_confirmed'] == (int)$cmsStatus ||
                (int)$this->module_config['status_in_progress'] == (int)$cmsStatus);
    }
    
    /**
     *
     * Используется при отправке заявки на сервер DD для указания стартового статуса
     *
     * Если true то заявка в сервисе DDelivery будет выставлена в статус "Подтверждена",
     * если false то то заявка в сервисе DDelivery будет выставлена в статус "В обработке"
     *
     * @param mixed $localStatus
     *
     * @return bool
     */
    public function isConfirmedStatus( $localStatus ){
        return ((int)$this->module_config['status_confirmed'] == (int)$localStatus);
    }
    

    
    static public function getCompanySubInfoHTML()
    {
        // pack забита для тех у кого нет иконки
        $companies = DDelivery\DDeliveryUI::getCompanySubInfo();
        if (is_array($companies) && count($companies)){
            foreach ($companies as $id => $item){
                $return[$id] = array('id'=> $id.']','name' => $item['name'], 'ico' => $item['ico']);
            }
        }
        return $return;
        return array(
            39 => array('id'=> '39]','name' => 'Aplix Qiwi', 'ico' => 'aplix'),
            40 => array('id'=> '40]','name' => 'Aplix СДЭК',  'ico' => 'aplix'),
            45 => array('id'=> '45]','name' => 'Aplix курьерская доставка',  'ico' => 'aplix'),
            48 => array('id'=> '48]','name' => 'Aplix IML курьерская доставка', 'ico' => 'aplix'), 
            35 => array('id'=> '35]','name' => 'Aplix DPD Consumer', 'ico' => 'aplix'),
            36 => array('id'=> '36]','name' => 'Aplix DPD parcel', 'ico' => 'aplix'),
            37 => array('id'=> '37]','name' => 'Aplix Post', 'ico' => 'aplix'),
            38 => array('id'=> '38]','name' => 'Aplix PickPoint', 'ico' => 'aplix'),
            4  => array('id'=> '4]','name' => 'Boxberry', 'ico' => 'boxberry'),
            21 => array('id'=> '21]','name' => 'Boxberry Express', 'ico' => 'boxberry'),
            29 => array('id'=> '29]','name' => 'DPD Classic', 'ico' => 'dpd'),
            23 => array('id'=> '23]','name' => 'DPD Consumer', 'ico' => 'dpd'),
            27 => array('id'=> '27]','name' => 'DPD ECONOMY', 'ico' => 'dpd'),
            28 => array('id'=> '28]','name' => 'DPD Express', 'ico' => 'dpd'),
            20 => array('id'=> '20]','name' => 'DPD Parcel', 'ico' => 'dpd'),
            30 => array('id'=> '30]','name' => 'EMS', 'ico' => 'ems'),
            31 => array('id'=> '31]','name' => 'Grastin', 'ico' => 'pack'),
            11 => array('id'=> '11]','name' => 'Hermes', 'ico' => 'hermes'),
            16 => array('id'=> '16]','name' => 'IMLogistics Пушкинская', 'ico' => 'imlogistics'),
            22 => array('id'=> '22]','name' => 'IMLogistics Экспресс', 'ico' => 'imlogistics'),
            17 => array('id'=> '17]','name' => 'IMLogistics', 'ico' => 'imlogistics'),
            49 => array('id'=> '49]','name' => 'IML Забор', 'ico' => 'imlogistics'),
            42 => array('id'=> '42]','name' => 'IML самовывоз', 'ico' => 'imlogistics'),
            43 => array('id'=> '43]','name' => 'IML курьерская доставка', 'ico' => 'imlogistics'),
            46 => array('id'=> '46]','name' => 'LENOD курьерская служба', 'ico' => 'lenod'),
            3 => array('id'=> '3]','name' => 'Logibox', 'ico' => 'logibox'),
            14 => array('id'=> '14]','name' => 'Maxima Express', 'ico' => 'pack'),
            1 => array('id'=> '1]', 'name' => 'PickPoint', 'ico' => 'pickpoint'),
            13 => array('id'=> '13]','name' => 'КТС', 'ico' => 'pack'),
            18 => array('id'=> '18]','name' => 'Сам Заберу', 'ico' => 'pack'),
            6 => array('id'=> '6]','name' => 'СДЭК забор', 'ico' => 'cdek'),
            26 => array('id'=> '26]','name' => 'СДЭК Посылка до двери', 'ico' => 'cdek'),
            25 => array('id'=> '25]','name' => 'СДЭК Посылка Самовывоз', 'ico' => 'cdek'),
            24 => array('id'=> '24]','name' => 'Сити Курьер', 'ico' => 'pack'),
            7 => array('id'=> '7]','name' => 'QIWI Post', 'ico' => 'qiwi'),
            44 => array('id'=> '44]','name' => 'Почта России', 'ico' => 'pack'),
            47 => array('id'=> '47]','name' => 'TelePost', 'ico' => 'pack'),
            
        );
    }
    
    static public function getCompanySubInfo()
    {
        //4,21,29,23,27,28,20,30,31,11,16,22,17,3,14,1,13,18,6,
        //26,25,24,7,35,36,37,39,40,42,43,44,45,46,47,48,49
        // pack забита для тех у кого нет иконки
        $companies = DDelivery\DDeliveryUI::getCompanySubInfo();
        if (is_array($companies) && count($companies)){
            foreach ($companies as $id => $item){
                $return[$id] = array('id'=> $id.']','name' => $item['name'], 'ico' => $item['ico']);
            }
        }
        return $return;
        return array(
            39 => array('id'=> '39]','name' => 'Aplix Qiwi', 'ico' => 'aplix'),
            40 => array('id'=> '40]','name' => 'Aplix СДЭК',  'ico' => 'aplix'),
            45 => array('id'=> '45]','name' => 'Aplix курьерская доставка',  'ico' => 'aplix'),
            48 => array('id'=> '48]','name' => 'Aplix IML курьерская доставка', 'ico' => 'aplix'), 
            35 => array('id'=> '35]','name' => 'Aplix DPD Consumer', 'ico' => 'aplix'),
            36 => array('id'=> '36]','name' => 'Aplix DPD parcel', 'ico' => 'aplix'),
            37 => array('id'=> '37]','name' => 'Aplix Post', 'ico' => 'aplix'),
            38 => array('id'=> '38]','name' => 'Aplix PickPoint', 'ico' => 'aplix'),
            4  => array('id'=> '4]','name' => 'Boxberry', 'ico' => 'boxberry'),
            21 => array('id'=> '21]','name' => 'Boxberry Express', 'ico' => 'boxberry'),
            29 => array('id'=> '29]','name' => 'DPD Classic', 'ico' => 'dpd'),
            23 => array('id'=> '23]','name' => 'DPD Consumer', 'ico' => 'dpd'),
            27 => array('id'=> '27]','name' => 'DPD ECONOMY', 'ico' => 'dpd'),
            28 => array('id'=> '28]','name' => 'DPD Express', 'ico' => 'dpd'),
            20 => array('id'=> '20]','name' => 'DPD Parcel', 'ico' => 'dpd'),
            30 => array('id'=> '30]','name' => 'EMS', 'ico' => 'ems'),
            31 => array('id'=> '31]','name' => 'Grastin', 'ico' => 'pack'),
            11 => array('id'=> '11]','name' => 'Hermes', 'ico' => 'hermes'),
            16 => array('id'=> '16]','name' => 'IMLogistics Пушкинская', 'ico' => 'imlogistics'),
            22 => array('id'=> '22]','name' => 'IMLogistics Экспресс', 'ico' => 'imlogistics'),
            17 => array('id'=> '17]','name' => 'IMLogistics', 'ico' => 'imlogistics'),
            49 => array('id'=> '49]','name' => 'IML Забор', 'ico' => 'imlogistics'),
            42 => array('id'=> '42]','name' => 'IML самовывоз', 'ico' => 'imlogistics'),
            43 => array('id'=> '43]','name' => 'IML курьерская доставка', 'ico' => 'imlogistics'),
            46 => array('id'=> '46]','name' => 'LENOD курьерская служба', 'ico' => 'lenod'),
            3 => array('id'=> '3]','name' => 'Logibox', 'ico' => 'logibox'),
            14 => array('id'=> '14]','name' => 'Maxima Express', 'ico' => 'pack'),
            1 => array('id'=> '1]', 'name' => 'PickPoint', 'ico' => 'pickpoint'),
            13 => array('id'=> '13]','name' => 'КТС', 'ico' => 'pack'),
            18 => array('id'=> '18]','name' => 'Сам Заберу', 'ico' => 'pack'),
            6 => array('id'=> '6]','name' => 'СДЭК забор', 'ico' => 'cdek'),
            26 => array('id'=> '26]','name' => 'СДЭК Посылка до двери', 'ico' => 'cdek'),
            25 => array('id'=> '25]','name' => 'СДЭК Посылка Самовывоз', 'ico' => 'cdek'),
            24 => array('id'=> '24]','name' => 'Сити Курьер', 'ico' => 'pack'),
            7 => array('id'=> '7]','name' => 'QIWI Post', 'ico' => 'qiwi'),
            44 => array('id'=> '44]','name' => 'Почта России', 'ico' => 'pack'),
            47 => array('id'=> '47]','name' => 'TelePost', 'ico' => 'pack'),
        );
    }

} 