<?php



class DD_Autoloader

{

    private static $__loader;



    // ----- The awesome constructor ----- //

    private function __construct ()

    {

        spl_autoload_register ( array ( $this, 'autoLoad' ));



    }



    // ----- The fire up call for an autoloader ----- //

    public static function init ()

    {

        if ( self::$__loader == NULL )

        {

            self::$__loader = new self();

        }



        return self::$__loader;

    }



    // ----- The autoloder registered function ----- //

    public function autoLoad( $class )

    {
        if (!class_exists($class)){
        $file = dirname(__FILE__).DS.str_replace("\\","/",$class).'.php';

        require_once $file;
        }

    }





}