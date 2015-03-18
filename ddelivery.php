<?php

if (!defined('_PS_VERSION_')) exit;

class ddelivery extends CarrierModule
{

    public $_html;
    public $id_carrier; //set from out side. Will need it when counting cost of delivery by one of carriers
    public $hooks = array(
        'displayCarrierList',
        'AdminOrder',
        'updateCarrier',
        'newOrder',
        'paymentTop',
        'backOfficeHeader',
        'displayHeader',
        'displayFooter',
        'displayTop',
        'beforeCarrier',
        'displayOrderConfirmation',
        'actionCustomerAccountAdd',
        'displayCustomerAccountForm',
        'displayCity',
        'ActionBeforeSubmitAccount',
        'displayBeforeCarrier',
        'displayBeforePayment',
        'actionOrderStatusPostUpdate',
        'actionCartSave',
        'actionValidateOrder',
        'displayAdminOrder'
    );

    function __construct()
    {
        $this->name = 'ddelivery';
        $this->tab = 'shipping_logistics';
        $this->version = '1';
        $this->author = 'Сервис доставки DDelivery';
        $this->limited_countries = array('ru');
        parent::__construct();
        $this->page = basename(__FILE__, '.php');
        $this->displayName = $this->l('DDelivery');
        $this->description = $this->l('Offer your customer delivery methods available at DDelivery.ru');

        require_once(dirname(__FILE__).DS.'application'.DS.'bootstrap.php');
        require_once(dirname(__FILE__).DS.'DDConfig.php');
        require_once(dirname(__FILE__).DS.'DDlibrary.php');
        require_once(dirname(__FILE__).DS.'prestashopIntegrator.php');
        DDConfig::loadConfig();
    }

    function install()
    {
        if (!parent::install()) {
            $this->_errors[] = "Failed on module install";
            return false;
        }
        require_once(dirname(__FILE__).DS.'application'.DS.'bootstrap.php');
        require_once(dirname(__FILE__).DS.'application'.DS.'classes'.DS.'DDelivery'.DS.'DDeliveryUI.php');
        require_once(dirname(__FILE__).DS.'DDConfig.php');
        require_once(dirname(__FILE__).DS.'DDlibrary.php');
        require_once(dirname(__FILE__).DS.'prestashopIntegrator.php');
        DDConfig::loadConfig();
        try{
    		$IntegratorShop = new prestashopIntegrator();
    		$ddeliveryUI = new \Ddelivery\DDeliveryUI($IntegratorShop, true);
    		$ddeliveryUI->createTables();
    	}
    	catch( \DDelivery\DDeliveryException $e  )
    	{
    		$ddeliveryUI->logMessage($e);
            echo $e->getMessage();
    	}
        $config = array(
            'name' => 'Сервис доставки товаров DDelivery.ru',
            'id_tax_rules_group' => 1,
            'url' => '',
            'delay' => "Доступно несколько способов доставки",
            'active' => 1,
            'deleted' => 0,
            'shipping_handling' => false,
            'range_behavior' => 0,
            'id_zone' => 1,
            'is_module' => true,
            'shipping_external' => true,
            'external_module_name' => $this->name,
            'need_range' => true
        );

        if (!$this->installCarrier($config)) {
            $this->_errors[] = "Failed on install Carriers";
            return false;
        }
        if (!$this->registerHooks()) {
            $this->_errors[] = "Failed on registerHooks";
            return false;
        }
        $this->sql_install();
        Configuration::updateValue('DD_MODULE_SETTINGS', serialize(DDConfig::getConfig()));
        return true;
    }

    function uninstall()
    {
        $this->deleteCarrier();
        Configuration::deleteByName('DD_MODULE_SETTINGS');
        $this->sql_uninstall();
        return parent::uninstall();
    }

    public function registerHooks()
    {
        foreach ($this->hooks as $hook) {
            if (!$this->registerHook($hook)) {
                $this->_errors[] = "Failed to install hook '$hook'<br />\n";
                return false;
            }
        }
        return true;
    }

    public function unregisterHooks()
    {
        foreach ($this->hooks as $hook) {
            if (!$this->unregisterHook($hook)) {
                $this->_errors[] = "Failed to uninstall hook '$hook'<br />\n";
                return false;
            }
        }
        return true;
    }

    public function sql_install(){
        $sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'ddelivery_orders`;';
        $sql[] = 'CREATE TABLE IF NOT EXISTS `ps_ddelivery_orders` (
                  `id` int(10) NOT NULL AUTO_INCREMENT,
                  `ps_cart_id` int(11) NOT NULL,
                  `dd_order_id` int(11) NOT NULL,
                  `cart_sig` text NOT NULL,
                  `dd_order_id_external` int(11) DEFAULT NULL,
                  `point_data` varchar(255) DEFAULT NULL,  
                  PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;';

        foreach ($sql as $s) // исполняем все запросы в очереди
            if (!Db::getInstance()->Execute($s)) {
                $this->_errors[] = "Failed on SQL Query";
                return false;
            }
        return true;
    }

    public function sql_uninstall(){
        $sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'ddelivery_orders`;';
        foreach ($sql as $s) // исполняем все запросы в очереди
            if (!Db::getInstance()->Execute($s)) {
                $this->_errors[] = "Failed on SQL Query";
                return false;
            }
        return true;
    }

    public function installCarrier($config)
    {
        $carrier = new Carrier();
        $carrier->name = $config['name'];
        $carrier->id_tax_rules_group = $config['id_tax_rules_group'];
        $carrier->id_zone = $config['id_zone'];
        $carrier->url = $config['url'];
        $carrier->active = $config['active'];
        $carrier->deleted = $config['deleted'];
        $carrier->delay = array();
        $carrier->shipping_handling = $config['shipping_handling'];
        $carrier->range_behavior = $config['range_behavior'];
        $carrier->is_module = $config['is_module'];
        $carrier->shipping_external = $config['shipping_external'];
        $carrier->external_module_name = $config['external_module_name'];
        $carrier->need_range = $config['need_range'];
        $languages = Language::getLanguages(true);
        foreach ($languages as $language) {
            if(empty($carrier->delay[$language['id_lang']])){
                $carrier->delay[$language['id_lang']] = rand(0,10);
            }
        }
        if ($carrier->add()) {
            $carrier->addZone(7);
            $groups = Group::getgroups(true);
            foreach ($groups as $group)
                Db::getInstance()->execute('INSERT INTO ' . _DB_PREFIX_ . 'carrier_group VALUE (\'' . (int)($carrier->id) . '\',\'' . (int)($group['id_group']) . '\')');
            $rangePrice = new RangePrice();
            $rangePrice->id_carrier = $carrier->id;
            $rangePrice->delimiter1 = '0';
            $rangePrice->delimiter2 = '10000';
            $rangePrice->add();
            $rangeWeight = new RangeWeight();
            $rangeWeight->id_carrier = $carrier->id;
            $rangeWeight->delimiter1 = '0';
            $rangeWeight->delimiter2 = '10000';
            $rangeWeight->add();
            //copy logo
            if (!copy(dirname(__FILE__) . '/entry2.jpg', _PS_SHIP_IMG_DIR_ . '/' . $carrier->id . '.jpg'))
                return false;
            Configuration::updateValue('DD_CARRIER_ID', (int)($carrier->id));
            return (int)($carrier->id);
        }
        return false;
    }

    public function deleteCarrier()
    {
        if (Configuration::get('DD_CARRIER_ID')) {
            $carrier = new Carrier(Configuration::get('DD_CARRIER_ID'));
            if ($carrier->id) {
                $carrier->delete();
            }
            Configuration::deleteByName('DD_CARRIER_ID');
        }
    }

    public function getOrderShippingCost($presta_cart, $shipping_cost)
    {
        $dd_cart = $this->getCart($presta_cart->id);
        //$shipping_cost -= 200;
        $cost = empty($dd_cart) ? $shipping_cost : $dd_cart[0]['point_data'];
        //$cost -= 200;
        return $cost;
    }

    public function getOrderShippingCostExternal($params)
    {
        //this function MUST be defined
    }

    public function checkApiKey($key)
    {
        try {
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($curl, CURLOPT_HEADER, 0);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
            $url = 'http://cabinet.ddelivery.ru/api/v1/' . $key . '/shop_info.json';
            curl_setopt($curl, CURLOPT_URL, $url);
            $result = array();
            $result = json_decode( curl_exec($curl) , true);
            return (bool)$result['success'];
        } catch (\DDelivery\DDeliveryException $e) {
        }
    }

    private function displayConfigForm()
    {
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');
        $fields_form[]['form'] = array(
            'legend' => array(
                'title' => $this->l('Основные настройки'),
            ),
            'input' => array(
                array(
                    'type' => 'text',
                    'label' => $this->l('API-Ключ'),
                    'name' => "DD_MODULE_SETTINGS[api_key]",
                    'desc' => "Ключ можно получить в личном кабинете DDelivery.ru,<br />зарегистрировавшись на сайте ( для новых клиентов )",
                    'size' => 60,
                    'required' => true
                ),
                array(
                    'type' => 'select',
                    'label' => 'Активировать тестовый режим?',
                    'name' => 'DD_MODULE_SETTINGS[ddelivery_test_mode]',
                    'desc' => "Для отладки модуля используйте, пожалуйста,<br />режим тестирования.",
                    'required' => true,
                    'options' => array(
                        'query' => array(
                            array('key' => '1',     'name'      => 'Да'),
                            array('key' => '0',     'name'      => 'Нет'),
                        ),
                        'name' => 'name',
                        'id' => 'key'
                    ),
                ),
                array(
                    'type' => 'text',
                    'label' => 'Какой % от стоимости товара страхуется',
                    'name' => 'DD_MODULE_SETTINGS[insurance_percent]',
                    'desc' => 'Вы можете снизить оценочную стоимость<br />для уменьшения стоимости доставки<br />за счет снижения размеров страховки',
                    'size' => 80,
                    'required' => true
                ),
                array(
                    'type' => 'select',
                    'label' => 'Доступные способы',
                    'name' => 'DD_MODULE_SETTINGS[available_services]',
                    'desc' => 'Настройка влияет на то, какие методы будут отображатся.',
                    'required' => true,
                    'options' => array(
                        'query' => array(
                            array('key' => 'all', 'name' => 'Самовывоз + Курьерская доставка'),
                            array('key' => 'pickup', 'name' => 'Самовывоз'),
                            array('key' => 'delivery', 'name' => 'Курьерская доставка'),
                        ),
                        'name' => 'name',
                        'id' => 'key'
                    ),
                ),
                array(
                    'type' => 'text',
                    'label' => 'Шаг округления',
                    'name' => 'DD_MODULE_SETTINGS[around_price_step]',
                    'size' => 80,
                    'required' => true
                ),
                array(
                    'type' => 'select',
                    'label' => 'Тип округления',
                    'name' => 'DD_MODULE_SETTINGS[around_price_type]',
                    'required' => true,
                    'options' => array(
                        'query' => array(
                            array('key' => 'floor', 'name' => 'Floor'),
                            array('key' => 'ceil', 'name' => 'Ceil'),
                            array('key' => 'round', 'name' => 'Round'),
                        ),
                        'name' => 'name',
                        'id' => 'key'
                    ),
                ),
                array(
                    'type' => 'select',
                    'label' => 'Учитывать цену забора?',
                    'name' => 'DD_MODULE_SETTINGS[is_pay_pickup]',
                    'required' => true,
                    'options' => array(
                        'query' => array(
                            array('key' => 'Y', 'name' => 'Да'),
                            array('key' => 'N', 'name' => 'Нет'),
                        ),
                        'name' => 'name',
                        'id' => 'key'
                    ),
                ),
            ),
        );

        $fields_form[]['form'] = array(
            'legend' => array(
                'title' => $this->l('Статусы заказов'),
            ),
            'input' => array(
                array(
                    'type' => 'select',
                    'label' => 'Статус для отправки на сервер DD со стартовым статусом "Подтверждена"',
                    'name' => 'DD_MODULE_SETTINGS[status_confirmed]',
                    'desc' => 'Выберите статус, при котором заявки из вашей системы<br />
                               будут уходить в DDelivery со статусом "Подтверждена".<br />
                               Помните, что отправка означает готовность отгрузить заказ<br />
                               на следующий рабочий день.',
                    'required' => true,
                    'options' => array(
                        'query' => DDlibrary::getOrderStatuses(),
                        'name' => 'name',
                        'id' => 'id'
                    ),
                ),
            array(
                    'type' => 'select',
                    'label' => 'Статус для отправки на сервер DD со стартовым статусом "В обработке"',
                    'name' => 'DD_MODULE_SETTINGS[status_in_progress]',
                    'desc' => 'Выберите статус, при котором заявки из вашей системы<br />
                               будут уходить в DDelivery со статусом "В обработке".',
                    'required' => true,
                    'options' => array(
                        'query' => DDlibrary::getOrderStatuses(),
                        'name' => 'name',
                        'id' => 'id'
                    ),
                ),
            array(
                    'type' => 'select',
                    'label' => 'Статус DD "На&nbsp;складе&nbsp;ИМ"',
                    'name' => 'DD_MODULE_SETTINGS[status_in_stock]',
                    'desc' => 'Выберите статус заказа в интернет-магазине, соответствующий<br />
                               статусу DD "На складе Интернет Магазина".',
                    'required' => true,
                    'options' => array(
                        'query' => DDlibrary::getOrderStatuses(),
                        'name' => 'name',
                        'id' => 'id'
                    ),
                ), 
            array(
                    'type' => 'select',
                    'label' => 'Статус DD "Заказ в пути"',
                    'name' => 'DD_MODULE_SETTINGS[status_in_way]',
                    'desc' => 'Выберите статус заказа в интернет-магазине, соответствующий<br />
                               статусу DD "Заказ в пути".',
                    'required' => true,
                    'options' => array(
                        'query' => DDlibrary::getOrderStatuses(),
                        'name' => 'name',
                        'id' => 'id'
                    ),
                ),
             array(
                    'type' => 'select',
                    'label' => 'Статус DD "Заказ доставлен"',
                    'name' => 'DD_MODULE_SETTINGS[status_delivered]',
                    'desc' => 'Выберите статус заказа в интернет-магазине, соответствующий<br />
                               статусу DD "Заказ доставлен".',
                    'required' => true,
                    'options' => array(
                        'query' => DDlibrary::getOrderStatuses(),
                        'name' => 'name',
                        'id' => 'id'
                    ),
                ),
              array(
                    'type' => 'select',
                    'label' => 'Статус DD "Заказ получен"',
                    'name' => 'DD_MODULE_SETTINGS[status_received]',
                    'desc' => 'Выберите статус заказа в интернет-магазине, соответствующий<br />
                               статусу DD "Заказ получен".',
                    'required' => true,
                    'options' => array(
                        'query' => DDlibrary::getOrderStatuses(),
                        'name' => 'name',
                        'id' => 'id'
                    ),
                ),
                array(
                    'type' => 'select',
                    'label' => 'Статус DD "Возврат заказа"',
                    'name' => 'DD_MODULE_SETTINGS[status_return]',
                    'desc' => 'Выберите статус заказа в интернет-магазине, соответствующий<br />
                               статусу DD "Возврат заказа".',
                    'required' => true,
                    'options' => array(
                        'query' => DDlibrary::getOrderStatuses(),
                        'name' => 'name',
                        'id' => 'id'
                    ),
                ),
                array(
                    'type' => 'select',
                    'label' => 'Статус DD "Клиент вернул заказ"',
                    'name' => 'DD_MODULE_SETTINGS[status_customer_returned]',
                    'desc' => 'Выберите статус заказа в интернет-магазине, соответствующий<br />
                               статусу DD "Клиент вернул заказ".',
                    'required' => true,
                    'options' => array(
                        'query' => DDlibrary::getOrderStatuses(),
                        'name' => 'name',
                        'id' => 'id'
                    ),
                ), 
                array(
                    'type' => 'select',
                    'label' => 'Статус DD "Частичный возврат заказа"',
                    'name' => 'DD_MODULE_SETTINGS[status_partial_refund]',
                    'desc' => 'Выберите статус заказа в интернет-магазине, соответствующий<br />
                               статусу DD "Частичный возврат заказа".',
                    'required' => true,
                    'options' => array(
                        'query' => DDlibrary::getOrderStatuses(),
                        'name' => 'name',
                        'id' => 'id'
                    ),
                ), 
                array(
                    'type' => 'select',
                    'label' => 'Статус DD "Отмена"',
                    'name' => 'DD_MODULE_SETTINGS[status_cancel]',
                    'desc' => 'Выберите статус заказа в интернет-магазине, соответствующий<br />
                               статусу DD "Отмена".',
                    'required' => true,
                    'options' => array(
                        'query' => DDlibrary::getOrderStatuses(),
                        'name' => 'name',
                        'id' => 'id'
                    ),
                ),
                array(
                    'type' => 'select',
                    'label' => 'Статус DD "Возвращен в ИМ"',
                    'name' => 'DD_MODULE_SETTINGS[status_returned_mi]',
                    'desc' => 'Выберите статус заказа в интернет-магазине, соответствующий<br />
                               статусу DD "Возвращен в ИМ".',
                    'required' => true,
                    'options' => array(
                        'query' => DDlibrary::getOrderStatuses(),
                        'name' => 'name',
                        'id' => 'id'
                    ),
                ), 
                array(
                    'type' => 'select',
                    'label' => 'Статус DD "Ожидание"',
                    'name' => 'DD_MODULE_SETTINGS[status_waiting]',
                    'desc' => 'Выберите статус заказа в интернет-магазине, соответствующий<br />
                               статусу DD "Ожидание".',
                    'required' => true,
                    'options' => array(
                        'query' => DDlibrary::getOrderStatuses(),
                        'name' => 'name',
                        'id' => 'id'
                    ),
                ), 
            ),
        );
        $fields_form[]['form'] = array(
            'legend' => array(
                'title' => $this->l('Габариты по умолчанию'),
                'desc'  => 'Данные габариты используются для определения цены доставки в случае, если у товара не прописаны размеры. Просим Вас внимательней отнестись к вводу данных',
            ),
            'input' => array(
                array(
                    'type' => 'text',
                    'label' => $this->l('Ширина по умолчанию (см.)'),
                    'name' => "DD_MODULE_SETTINGS[default_width]",
                    'size' => 60,
                    'required' => true
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Длина по умолчанию (см.)'),
                    'name' => "DD_MODULE_SETTINGS[default_length]",
                    'size' => 60,
                    'required' => true
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Высота по умолчанию (см.)'),
                    'name' => "DD_MODULE_SETTINGS[default_height]",
                    'size' => 60,
                    'required' => true
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Вес по умолчанию (кг.)'),
                    'name' => "DD_MODULE_SETTINGS[default_weight]",
                    'size' => 60,
                    'required' => true
                ),
            ),
        );

        $fields_form[]['form'] = array(
            'legend' => array(
                'title' => $this->l('Модификаторы суммы оплаты'),
            ),
            'input' => array(
                array(
                    'type' => 'text',
                    'label' => 'Сумма заказа от:',
                    'name' => 'DD_MODULE_SETTINGS[modifier_min_1]',
                    'size' => 80,
                    'required' => true,
                ),
                array(
                    'type' => 'text',
                    'label' => 'Сумма заказа до:',
                    'name' => 'DD_MODULE_SETTINGS[modifier_max_1]',
                    'size' => 80,
                    'required' => true,
                ),
                array(
                    'type' => 'select',
                    'label' => 'Режим оплаты:',
                    'name' => 'DD_MODULE_SETTINGS[modifier_type_1]',
                    'required' => true,
                    'options' => array(
                        'query' => $this->getDDRepaymentRate(),
                        'name' => 'name',
                        'id' => 'key'
                    ),
                ),
                array(
                    'type' => 'text',
                    'label' => 'Модификатор стоимости(%)',
                    'name' => 'DD_MODULE_SETTINGS[modifier_percent_1]',
                    'size' => 80,
                    'required' => true
                ),
                //###########################1#############################
                array(
                    'type' => 'text',
                    'label' => 'Сумма заказа от:',
                    'name' => 'DD_MODULE_SETTINGS[modifier_min_2]',
                    'size' => 80,
                    'required' => true,
                ),
                array(
                    'type' => 'text',
                    'label' => 'Сумма заказа до:',
                    'name' => 'DD_MODULE_SETTINGS[modifier_max_2]',
                    'size' => 80,
                    'required' => true,
                ),
                array(
                    'type' => 'select',
                    'label' => 'Режим оплаты:',
                    'name' => 'DD_MODULE_SETTINGS[modifier_type_2]',
                    'required' => true,
                    'options' => array(
                        'query' => $this->getDDRepaymentRate(),
                        'name' => 'name',
                        'id' => 'key'
                    ),
                ),
                array(
                    'type' => 'text',
                    'label' => 'Модификатор стоимости(%)',
                    'name' => 'DD_MODULE_SETTINGS[modifier_percent_2]',
                    'size' => 80,
                    'required' => true
                ), //###########################2#############################
                array(
                    'type' => 'text',
                    'label' => 'Сумма заказа от:',
                    'name' => 'DD_MODULE_SETTINGS[modifier_min_3]',
                    'size' => 80,
                    'required' => true,
                ),
                array(
                    'type' => 'text',
                    'label' => 'Сумма заказа до:',
                    'name' => 'DD_MODULE_SETTINGS[modifier_max_3]',
                    'size' => 80,
                    'required' => true,
                ),
                array(
                    'type' => 'select',
                    'label' => 'Режим оплаты:',
                    'name' => 'DD_MODULE_SETTINGS[modifier_type_3]',
                    'required' => true,
                    'options' => array(
                        'query' => $this->getDDRepaymentRate(),
                        'name' => 'name',
                        'id' => 'key'
                    ),
                ),
                array(
                    'type' => 'text',
                    'label' => 'Модификатор стоимости(%)',
                    'name' => 'DD_MODULE_SETTINGS[modifier_percent_3]',
                    'size' => 80,
                    'required' => true
                ),
            ),
        );

        $fields_form[]['form'] = array(
            'legend' => array(
                'title' => $this->l('Доступные компании для курьерской доставки'),
            ),
            'input' => array(
                array(
                    'type' => 'checkbox',
                    'name' => 'DD_MODULE_SETTINGS[courier_companies',
                    'values' => array(
                        'query' => prestashopIntegrator::getCompanySubInfoHTML(),
                        'id' => 'id',
                        'name' => 'name'
                    ),
                ),
                
            ),
        );
        
        $fields_form[]['form'] = array(
            'legend' => array(
                'title' => $this->l('Доступные компании ПВЗ'),
            ),
            'input' => array(
                array(
                    'type' => 'checkbox',
                    'name' => 'DD_MODULE_SETTINGS[self_companies',
                    'values' => array(
                        'query' => prestashopIntegrator::getCompanySubInfoHTML(),
                        'id' => 'id',
                        'name' => 'name'
                    ),
                ),
                
            ),
        );

/*        $fields_form[3]['form'] = array(
            'legend' => array(
                'title' => $this->l('Отключенные компании(Самовывоз)'),
            ),
            'input' => array(
                array(
                    'type' => 'checkbox',
                    'name' => 'DD_MODULE_SETTINGS[pickup_companies',
                    'values' => array(
                       'query' => DDlibrary::getCompaniesToHTML('pp'),

                       'id' => 'id',
                        'name' => 'name'
                    ),
                ),
            ),
        );*/

$fields_form[]['form'] = array(
            'legend' => array(
                'title' => $this->l('Платежная информация'),
            ),
            'input' => array(
                array(
                    'type' => 'select',
                    'label' => 'Оплата на месте при курьерской доставке',
                    'name' => 'DD_MODULE_SETTINGS[courier_payment_module]',
                    'required' => true,
                    'options' => array(
                        'query' => DDlibrary::getDDPaymentModules(),
                        'name' => 'name',
                        'id' => 'key'
                        ),
                    'desc' => 'Выберите значение, соответствующее способу оплаты "Оплата на месте".<br />
                               У вас в системе может быть только один, такой способ',
                ),
                array(
                    'type' => 'select',
                    'label' => 'Оплата на месте при самовывозе',
                    'name' => 'DD_MODULE_SETTINGS[pvz_payment_module]',
                    'required' => true,
                    'options' => array(
                        'query' => DDlibrary::getDDPaymentModules(),
                        'name' => 'name',
                        'id' => 'key'
                        ),
                    'desc' => 'Выберите значение, соответствующее способу оплаты "Оплата на месте".<br />
                               У вас в системе может быть только один, такой способ',
                ),
            ),
            'submit' => array(
                'title' => $this->l('Сохранить'),
                'class' => 'button'
            ),
        );
        /*                'submit' => array(
                                        'title' => $this->l('Сохранить'),
                                        'class' => 'button'
                                    )
                        );*/
        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;
        $helper->title = $this->displayName;
        $helper->show_toolbar = true; // false -> remove toolbar
        $helper->toolbar_scroll = true; // yes - > Toolbar is always visible on the top of the screen.
        $helper->submit_action = 'submit' . $this->name;

        $helper->toolbar_btn = array(
            'save' => array(
                'desc' => $this->l('Сохранить'),
                'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&save' . $this->name .
                    '&token=' . Tools::getAdminTokenLite('AdminModules'),
            ),
            'back' => array(
                'href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Назад к модулям')
            ),
        );

        foreach (DDConfig::getConfig() as $key => $item) {
            $helper->fields_value["DD_MODULE_SETTINGS[$key]"] = trim($item);
        }

        return @$helper->generateForm($fields_form);
    }

    private function validateFormItem($key, $value)
    {
        return true;

        switch ($key) { //form validation
            case 'api_key':
                if (!$this->checkApiKey($value)) {
                    $this->_html .= $this->displayError($this->l('Введенный API-Ключ не действителен!'));
                    return false;
                }
                break;

            case 'courier_id_self':
                if(!is_numeric($value)){
                    $this->_html .= $this->displayError( $this->l('В поле "ID Курьера(самовывоз)" в разделе "Основные настройки" можно ввести только целое число!') );
                    return false;
                }
                break;

            case 'courier_id_delivery':
                if(!is_numeric($value)){
                    $this->_html .= $this->displayError( $this->l('В поле "ID Курьера(курьерская доставка)" в разделе "Основные настройки" можно ввести только целое число!') );
                    return false;
                }
                break;

            case 'default_width':
                if(!is_numeric($value) && !empty($value)){
                    $this->_html .= $this->displayError( $this->l('В поле "Ширина по умолчанию (см.)" в разделе "Основные настройки" можно ввести только целое число!') );
                    return false;
                }
                break;

            case 'default_lenght':
                if(!is_numeric($value) && !empty($value)){
                    $this->_html .= $this->displayError( $this->l('В поле "Длина по умолчанию (см.)" в разделе "Основные настройки" можно ввести только целое число!') );
                    return false;
                }
                break;

            case 'default_height':
                if(!is_numeric($value) && !empty($value)){
                    $this->_html .= $this->displayError( $this->l('В поле "Высота по умолчанию (см.)" в разделе "Основные настройки" можно ввести только целое число!') );
                    return false;
                }
                break;

            case 'default_weight':
                if(!is_numeric($value) && !empty($value)){
                    $this->_html .= $this->displayError( $this->l('В поле "Вес по умолчанию (см.)" в разделе "Основные настройки" можно ввести только целое число!') );
                    return false;
                }
                break;

            case 'appox_value_percent':
                if(!is_numeric($value) && !empty($value)){
                    $this->_html .= $this->displayError( $this->l('В поле "Оценочная стоимость" в разделе "Основные настройки" можно ввести только целое число!') );
                    return false;
                }
                break;

            case 'delivery_modifier_min_value_1':
            case 'delivery_modifier_min_value_2':
            case 'delivery_modifier_min_value_3':
                if(!is_numeric($value) && !empty($value)){
                    $this->_html .= $this->displayError( $this->l('Поле "Сумма заказа от" в разделе "Оплата курьерской доставки" содержит недопустимые символы!') );
                    return false;
                }
                break;

            case 'pickup_modifier_min_value_1':
            case 'pickup_modifier_min_value_2':
            case 'pickup_modifier_min_value_3':
                if(!is_numeric($value) && !empty($value)){
                    $this->_html .= $this->displayError( $this->l('Поле "Сумма заказа от" в разделе "Оплата самовывоза" содержит недопустимые символы!') );
                    return false;
                }
                break;

            case 'delivery_modifier_max_value_1':
            case 'delivery_modifier_max_value_2':
            case 'delivery_modifier_max_value_3':
                if(!is_numeric($value) && !empty($value)){
                    $this->_html .= $this->displayError( $this->l('Поле "Сумма заказа до" в разделе "Оплата курьерской доставки" содержит недопустимые символы!') );
                    return false;
                }
                break;

            case 'pickup_modifier_max_value_1':
            case 'pickup_modifier_max_value_2':
            case 'pickup_modifier_max_value_3':
                if(!is_numeric($value) && !empty($value)){
                    $this->_html .= $this->displayError( $this->l('Поле "Сумма заказа до" в разделе "Оплата самовывоза" содержит недопустимые символы!') );
                    return false;
                }
                break;

            case 'delivery_modifier_discount_value_1':
            case 'delivery_modifier_discount_value_2':
            case 'delivery_modifier_discount_value_3':
                if(!is_numeric($value) && !empty($value)){
                    $this->_html .= $this->displayError( $this->l('Поле "Сумма/процент компенсации" в разделе "Оплата самовывоза" содержит недопустимые символы!') );
                    return false;
                }
                break;

            case 'pickup_modifier_discount_value_1':
            case 'pickup_modifier_discount_value_2':
            case 'pickup_modifier_discount_value_3':
                if(!is_numeric($value) && !empty($value)){
                    $this->_html .= $this->displayError( $this->l('Поле "Сумма/процент компенсации" в разделе "Оплата самовывоза" содержит недопустимые символы!') );
                    return false;
                }
                break;
        }

        /*        if(!array_key_exists($key,DDConfig::getConfig())){
                    $this->_html .= $this->displayError( $this->l('Ошибка! Неизвестное поле: '.$key) );
                    return false;
                }*/

        return true;
    }

    public function getContent()
    {
        if (Tools::isSubmit('submit' . $this->name) && is_array(Tools::getValue('DD_MODULE_SETTINGS'))) {
            $form_data = Tools::getValue('DD_MODULE_SETTINGS'); //all the data in one array
            $new_config = array();
            $form_has_errors = false;

            foreach ($form_data as $key => $item) {
                if ($this->validateFormItem($key, $item)) {
                    $new_config[$key] = $item;
                } else {
                    $form_has_errors = true; // i hate the fckin flagvars
                }
            }

            if (!$form_has_errors) {
                DDConfig::saveConfig($new_config);
                //var_dump(DDConfig::loadConfig());
                $this->_html .= $this->displayConfirmation($this->l('Настройки сохранены!'));

                if ($this->checkApiKey($new_config['api_key'])) {
                    $this->_html .= $this->displayConfirmation($this->l('API-Ключ рабочий!'));
               } else {

                   $this->_html .= $this->displayError($this->l('API-Ключ не действителен!'));
                }
            } else {
                $this->_html .= $this->displayError($this->l('Изменения отменены. Исправьте ошибки и попробуйте снова.'));
            }

        } else { //idle check, w/o form submitting. In case something happened on the API-server side.
            $config = DDConfig::loadConfig();

            if ($this->checkApiKey($config['api_key'])) {
                $this->_html .= $this->displayConfirmation($this->l('API-Ключ рабочий!'));
            } else {
                $this->_html .= $this->displayError($this->l('API-Ключ не действителен!'));
            }
        }

        $this->_html .= $this->displayConfigForm();
        return $this->_html;
    }

    private function getDDRepaymentRate()
    {
        return array(
            'allcustomer' => array('key' => 'allcustomer', 'name' => 'Все оплачивает клиент'),
            'allshop' => array('key' => 'allshop', 'name' => 'Все оплачивает магазин'),
            'percent' => array('key' => 'percent', 'name' => 'Магазин оплачивает процент'),
            'fixed' => array('key' => 'fixed', 'name' => 'Магазин оплачивает фиксированную цену'),
        );
    }

    private function getDimensions()
    {
        return array(
            array('key' => 'width',  'name' => 'Ширина'),
            array('key' => 'height', 'name' => 'Высота'),
            array('key' => 'lenght', 'name' => 'Длина'),
            array('key' => 'weight', 'name' => 'Вес')
        );
    }

    public function getCart($cart_id){
        $sql = "SELECT * FROM `ps_ddelivery_orders` WHERE ps_cart_id ='{$cart_id}' LIMIT 1";
        return Db::getInstance()->ExecuteS($sql);
    }

    public function setDDExternalOrderStatus($id , $cart_id){
        $sql = "UPDATE `ps_ddelivery_orders` SET dd_order_id_external = '{$id}' WHERE ps_cart_id = '{$cart_id}' LIMIT 1";
        return Db::getInstance()->Execute($sql);
    }

    public function removeDDcart($cart_id){
        $sql = "DELETE FROM `ps_ddelivery_orders` WHERE ps_cart_id ='{$cart_id}' LIMIT 1";
        return Db::getInstance()->Execute($sql);
    }

    public function hookUpdateCarrier($params) {
         $id_carrier_old = (int)($params['id_carrier']);
          $id_carrier_new = (int)($params['carrier']->id);
          if ($id_carrier_old == (int)(Configuration::get('DD_CARRIER_ID')))
            Configuration::updateValue('DD_CARRIER_ID', $id_carrier_new);
    }

    public function hookDisplayBeforeCarrier(){
        $id_carrier = Configuration::get('DD_CARRIER_ID');
       $output =  "<script> var dd_id_carrier = $id_carrier ;</script>";
        $output .= '<script src="/modules/ddelivery/assets/ddelivery.js"></script>';

       $output .= '<script src="/modules/ddelivery/assets/jquery.kladr.js"></script>';
        $output .= '<script src="/modules/ddelivery/assets/kladr.js"></script>';
        $output .= '<script src="/modules/ddelivery/assets/jquery.modal.js"></script>';

        $output .= '<script src="/modules/ddelivery/assets/ddelivery_include.js"></script>';

        //$output .= "<script>$(function(){ $('#HOOK_PAYMENT').hide(); });</script>";



        if($this->getCart(Context::getContext()->cart->id) != FALSE) {

        	$output .= "<script>$(function(){ $('#HOOK_PAYMENT').show(); });</script>";

        }

        return $output;

    }



    public function hookdisplayCarrierList() {



    }



    public function hookHeader(){

        $this->context->controller->addCSS('/modules/ddelivery/assets/jquery.kladr.css');

        $this->context->controller->addCSS('/modules/ddelivery/assets/dd_module.css');



        if($this->getCart(Context::getContext()->cart->id) == FALSE) {

            if (Configuration::get('PS_ORDER_PROCESS_TYPE') == 1) {

                $out .= "

                <script> 

                    $(function(){



                     if($('input[type=radio][value=\"".$id_carrier.",\"]').is(':checked')){

                      	 $('#HOOK_PAYMENT').hide();

                     }else{

                    	 $('#HOOK_PAYMENT').show();

                     }

                    });            

                </script>";

            }

        }

        return $out;

    }



    public function hookDisplayBeforePayment($params){

        

    }



    public function hookActionOrderStatusPostUpdate($params){

        //ini_set('display_errors',1);

        //error_reporting(E_ALL);

        $status = $params['newOrderStatus']->id;

        $cmsOrderID = $params['id_order'];

        $dd_cart = Order::getCartIdStatic($cmsOrderID);

        //$dd_cart = $this->getCart(Context::getContext()->cart->id);

        try{

            $prestashopIntegrator = new prestashopIntegrator();

            $ddeliveryUI = new \DDelivery\DDeliveryUI($prestashopIntegrator,true);

            $order = $ddeliveryUI->getOrderbyCmsId($cmsOrderID);

            if (is_object($order)){

                if ((int)$order->ddeliveryID == 0){

                    $dd_order_id = $ddeliveryUI->onCmsChangeStatus($cmsOrderID, $status);

                    $order->ddeliveryID = $dd_order_id;

                    $ddeliveryUI->saveFullOrder($order);

                    $this->setDDExternalOrderStatus($dd_order_id,$dd_cart[0]['ps_cart_id']);

                }

            }

            //echo '<pre>'. print_r($order,1).'</pre>';

           }

         catch(Exception $e){

            

            $ddeliveryUI->logMessage($e);

         }

         

    }



    public function hookActionCartSave($params){

        $dd_cart = $this->getCart(Context::getContext()->cart->id);

        if(empty($dd_cart)) return;

        $cartProducts = json_encode(Context::getContext()->cart->getProducts());

        

        if($dd_cart[0]['cart_sig'] != crc32($cartProducts)){

           $this->removeDDcart(Context::getContext()->cart->id);

        }

    }



    public function hookActionValidateOrder($params){

        //ini_set('display_errors',1);

        //error_reporting(E_ALL);

        //echo  '<pre>'.print_r($params['order'],1).'</pre>';

        $dd_data = $this->getCart($params['cart']->id);

        

        try{

            $id             = $dd_data[0]['dd_order_id'];

            if ($id >0){

                $prestashopIntegrator = new prestashopIntegrator();

                $ddeliveryUI = new \DDelivery\DDeliveryUI($prestashopIntegrator,true);

                $shopOrderID    = $params['order']->id;

                $status         = $params['orderStatus']->id;

                $payment        = $params['order']->module;
                $dd_order = $ddeliveryUI->getOrderByCmsID($shopOrderID);
                if (is_object($dd_order)){
                    $dd_order->shopRefnum = '';
                    $ddeliveryUI->saveFullOrder($dd_order);
                }
                    

                $ddeliveryUI->onCmsOrderFinish( $id, $shopOrderID, $status, $payment);

                } 

            }

        catch(\DDelivery\DDeliveryException $e)

            {

                $ddeliveryUI->logMessage($e);

            }

        return $params;

    }



    public function hookDisplayAdminOrder($params){

        $dd_data = $this->getCart($params['cart']->id);

        

        if(empty($dd_data)) return;

        

        try{

            $prestashopIntegrator = new prestashopIntegrator();

            $ddeliveryUI = new \DDelivery\DDeliveryUI($prestashopIntegrator,true);

            $dd_order = $ddeliveryUI->getOrderbyCmsId((int)$params['id_order']);

            /*if (is_object($order)){

                $dd_order_id = (int)$order->ddeliveryID;

                $this->setDDExternalOrderStatus($dd_order_id,$dd_cart[0]['ps_cart_id']);

            }*/

            //$dd_order = $ddeliveryUI->initOrder($dd_data[0]['dd_order_id']);

            if(empty($dd_order)) return;

            $point = $dd_order->getPoint();



            if($dd_order->toStreet == NULL && $dd_order->toHouse == NULL){

            	$address = 'Адрес доставки: Самовывоз';

            }else{

             	$address = 'Адрес доставки: '.$dd_order->toStreet . ' ' . $dd_order->toHouse . ' ' . $dd_order->toFlat;

            }

            

            $dd_order_id = (is_null($dd_data[0]['dd_order_id_external'])) ? '' : "Номер заказа Digital Delivery: ".$dd_data[0]['dd_order_id_external'];

            $return = array(

                'ID заявки на сервере DD:' => $dd_order->ddeliveryID,

                'Способ доставки:' => ((int)$dd_order->type == 1)?'Самовывоз':'Курьерская доставка',

                'Клиент:' => "{$dd_order->secondName} {$dd_order->firstName} {$dd_order->toEmail} {$dd_order->toPhone}",

                'Компания доставки:' => $point['delivery_company_name'],

                'Стоимость доставки для клиента:' => $ddeliveryUI->getOrderClientDeliveryPrice($dd_order) .' руб.',

                'Реальная стоимость доставки:' => $ddeliveryUI->getOrderRealDeliveryPrice($dd_order) .' руб.',

                'Выбранный модуль оплаты в магазине:' => $dd_order->paymentVariant,

                'Сторона1:' => $dd_order->dimensionSide1 .' см',

                'Сторона2:' => $dd_order->dimensionSide2 .' см',

                'Сторона3:' => $dd_order->dimensionSide3 .' см',

                'Общий вес:' => $dd_order->weight .' кг',

                'Сумма заказа:' => $dd_order->amount .' руб.',

                'Оценочная стоимость:' => $dd_order->declaredPrice .' руб.',

                'Комментарий:' => $dd_order->comment,

            );

            if ((int)$dd_order->type == 1){

                $return['Регион:'] = $point['region'];

                $return['Город:'] = $point['city_type'] . ' '.  $point['city'];

                $return['Индекс:'] = $point['postal_code'];

                $return['Пункт самовывоза:'] = $point['name'];

                $return['Тип пункта самовывоза:'] = ($point['type'] == 2)?'Живой пункт':'Ячейка';

                $return['Описание пункта самовывоза:'] = $point['description_out'];

                $return['Адрес пункта самовывоза:'] = $point['address'];

                $return['Режим работы:'] = $point['schedule'];

                if (strlen($point['metro']))

                    $return['Метро:'] = $point['metro'];

                if ((int)$point['is_cash'] == 1 && (int)$point['is_card'] !== 1)

                    $return['Доступные способы оплаты:'] = 'Оплата наличными';

                if ((int)$point['is_cash'] !== 1 && (int)$point['is_card'] == 1)

                    $return['Доступные способы оплаты:'] = 'Оплата картой';

                if ((int)$point['is_cash'] == 1 && (int)$point['is_card'] == 1)

                    $return['Доступные способы оплаты:'] = 'Оплата наличными или картой';

            }elseif((int)$dd_order->type == 2){

                $return['Город:'] = $dd_order->cityName;

                $return['Улица:'] = $dd_order->toStreet;

                if ($dd_order->toHouse)

                    $return['Дом:'] = $dd_order->toHouse;

                if ($dd_order->toHousing)

                    $return['Корпус:'] = $dd_order->toHousing;

                if ($dd_order->toFlat)

                    $return['Квартира:'] = $dd_order->toFlat;

                

                $return['Время доставки (в днях):'] = "от $point[delivery_time_min] до $point[delivery_time_max] (в среднем: $point[delivery_time_avg])";

            }

            $html = '<pre><legend><img src="../img/admin/tab-customers.gif">Digital Delivery</legend>';

			if (is_array($return) && count($return))

                foreach ($return as $k => $v)

                    $html .= "<strong>$k</strong> $v<br />";	    

            $html .= '</pre>';
            //$html .= '<pre>'.print_r($dd_order,1).'</pre>';
        }
        catch(Exception $e){
            $this->_html .= $this->displayError($this->l($e->getMessage()));
            echo $e->getMessage();
        }

        return $html;
    }
}