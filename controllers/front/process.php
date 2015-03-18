<?php



ini_set("display_errors", "On");





if (!defined('_PS_VERSION_'))

    exit;


$project_dir = getcwd();

require_once($project_dir.'/modules/ddelivery/DDConfig.php');
require_once($project_dir.'/modules/ddelivery/application/bootstrap.php');
require_once($project_dir.'/modules/ddelivery/prestashopIntegrator.php');

use DDelivery\DDeliveryUI;

class DdeliveryProcessModuleFrontController extends ModuleFrontController
{
    //class name consists of {ModuleName}{filename}ModuleFrontController

    public function init()
    {
        parent::init();
        try{
            $prestashopIntegrator = new prestashopIntegrator();

            if (isset($_GET['print_order'])){
                $id = (int)$_GET['print_order'];
                $ddeliveryUI = new DDeliveryUI($prestashopIntegrator,true);
                $prestashopIntegrator->setDDeliveryUI($ddeliveryUI);
                $order = $ddeliveryUI->getOrderByCmsID($id);
                /*if (in_array((int)$id,array(4211,4212)) ){
                    $order->addField1 = (double)284;
                    $ddeliveryUI->saveFullOrder($order);
                }*/
                $order = $ddeliveryUI->getOrderByCmsID($id);    
                echo '<pre>'.print_r($order,1).'</pre>';
                
                return ;
            }

            if (isset($_GET['compareStatuses'])){

                $context = Context::getContext();

                $context->employee = new Employee(1);

                //echo '<pre>'.print_r(Context::getContext(),1).'</pre>'; 

                $ddeliveryUI = new DDeliveryUI($prestashopIntegrator,true);

                $ddOrders = $ddeliveryUI->getNotFinishedOrders();

                if (is_array($ddOrders) && count($ddOrders))

                    foreach($ddOrders as $order){

                        $ddStatus = $ddeliveryUI->getDDOrderStatus($order->ddeliveryID);

                        //echo '<pre>'.print_r($order,1).'</pre>';

                        $localStatus = $ddeliveryUI->getLocalStatusByDD($ddStatus);

                        $oldDescr = $ddeliveryUI->getDDStatusDescription($order->ddStatus);

                        $newDescr = $ddeliveryUI->getDDStatusDescription($ddStatus);

                        echo "id: $order->localId ddId: $order->ddeliveryID cmsId: $order->shopRefnum oldDDstatus: $order->ddStatus $oldDescr newDDstatus: $ddStatus $newDescr oldLocalStatus: $order->localStatus newLocalStatus: $localStatus<br />";

                        $order->ddStatus = $ddStatus;

                        $order->localStatus = $localStatus;

                        $ddeliveryUI->saveFullOrder($order);

                        $prestashopIntegrator->setCmsOrderStatus($order->shopRefnum,$localStatus);

                    }

                    

                

                //$ddeliveryUI->getPullOrdersStatus();

            }

            else{

                $ddeliveryUI = new DDeliveryUI($prestashopIntegrator);

                $prestashopIntegrator->setDDeliveryUI($ddeliveryUI);
                //echo '<pre>'.print_r($ddeliveryUI->getOrder(),1).'</pre>';
                //print_r($ddeliveryUI->getOrder());

                $ddeliveryUI->render(isset($_REQUEST) ? $_REQUEST : array());

            }

            

            







        }catch( \DDelivery\DDeliveryException $e  )

        {

        	$ddeliveryUI->logMessage($e);

        }

















/*



        $db = DB::getInstance();



        $city = Tools::getValue('city', false);



        $id_city = $this->module->idCityByName($city);



        $street = Tools::getValue('street', false);



        $house = Tools::getValue('house', false);



        $building = Tools::getValue('building', false);



        $room = Tools::getValue('room', false);



        $metro = Tools::getValue('metro', false);*/







        die;



    }







    public function initContent()



    {



        //function is not used if using ajax



        parent::initContent();



        //$this->setTemplate('sometpl.tpl');







    }



}