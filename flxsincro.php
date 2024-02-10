<?php
/**
* 2007-2017 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Open Software License (OSL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/osl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author Flexxus S.A <soporte@flexxus.com>
*  @copyright  2007-2017 Flexxus SA
*  @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/
set_time_limit(30);

if (!defined('_PS_VERSION_'))
  exit;
	
  include (dirname(__FILE__) . '/class/install.php');
  include (dirname(__FILE__) . '/class/flxbase.php');
  include (dirname(__FILE__) . '/class/Parametros.php');
  include (dirname(__FILE__) . '/class/flxfn.php');
  include (dirname(__FILE__) . '/class/Note.php');
  include (dirname(__FILE__) . '/class/formulario.php');
  include (dirname(__FILE__) . '/class/sincro.php');
  //include (dirname(__FILE__) . '/class/flxException.php');
  //include (dirname(__FILE__) . '/class/flxEquivalencia.php');


class flxsincro extends Module
{
    public function __construct()
    {
        $this->name = 'flxsincro';
        $this->tab = 'administration';

        $this->version = '1.15.13';
        $this->author = 'Flexxus S.A';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.5', 'max' => _PS_VERSION_);
        $this->bootstrap = true;

        parent::__construct();

        $this->page = basename(__FILE__, '.php');
        $this->displayName = $this->l('Wualá sincronizador con Flexxus');
        $this->description = $this->l('Sincronización con Sistema de Gestión Flexxus');
        $this->confirmUninstall = $this->l('Estas seguro que deseas desintalar este modulo?');

    }

    public function install()
	  {
        if (Shop::isFeatureActive())
   	        Shop::setContext(Shop::CONTEXT_ALL);

        if ( !parent::install()
        || !$this->registerHook('DisplayHeader')
        || !$this->registerHook('footer')
        || !$this->registerHook('displayBackOfficeHeader')
        || !$this->registerHook('actionProductUpdate')
        || !$this->registerHook('actionUpdateQuantity')
        || !flxinstall::installTablas()
        || !formulario::initParametros()
        || !formulario::insertDatos()
        || !flxinstall::upgradeTablas()
        || unlink(_PS_ROOT_DIR_.'/cache/class_index.php'))
            return false;
        return true;
    }

    public function uninstall()
    {
        if ( !parent::uninstall()
        || !formulario::deletParametros()
        || !flxinstall::uninstallTablas()
        )
            return false;
        return true;
    }

    public function reset()
    {
        if (!$this->uninstall(false))
            return false;

        if (!$this->install(false))
            return false;

        return true;
    }

    /**
    * recupero y guardo los valores ingresados en el formulario
    **/
    protected function _postProcess()
    {
        $id_lang = (int)Configuration::get('PS_LANG_DEFAULT');

        if (Tools::isSubmit('submit'.$this->name)):
                formulario::setParametros($_POST);
            endif;
        return $output;
    }

    /**
    * Cargo el contenido del modulo
    **/
    public function getContent()
    {
        return $this->_postProcess().$this->renderForm();
    }

    /**
    * Dibujo los formulario
    */
    public function renderForm()
    {
        return formulario::renderForm();
    }

    public function renderList($filter)
    {

    }

    /**
    * Opcional: para archivos CSS y JavaScript
    * que se usaran en el BackOffice de este modulo.
    *
    */
    public function hookDisplayBackOfficeHeader()
    {
        $this->context->controller->addJquery();
        $this->context->controller->addJS(($this->_path).'views/js/'.$this->name.'.js');
        //$this->context->controller->addJS(($this->_path).'views/js/asistSincro-upgrade.js');
        $this->context->controller->addCSS(($this->_path).'views/css/'.$this->name.'.css', 'all');
        $this->context->controller->addJqueryPlugin('fancybox');
    }

    /**
    * Opcional: para archivos CSS y JavaScript que se usaran en el FrontOffice de la tienda.
    */
    public function hookHeader($params)
    {
    }

    /**
    * Opcional: para archivos CSS y JavaScript que se usaran en el FrontOffice de la tienda.
    */
    public function hookFooter($params)
    {
        //$this->context->controller->addJS(($this->_path).'views/js/'.$this->name.'Override.js');
        return $this->display(($this->_path), 'views/templates/hook/'.$this->name.'.tpl');
    }

    public function hookActionProductUpdate($params)
    {
        return true;
    }

    public function hookActionUpdateQuantity($params)
    {
        return true;
    }
}