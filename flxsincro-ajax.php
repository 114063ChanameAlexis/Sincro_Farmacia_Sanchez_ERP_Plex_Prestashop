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
* @author    Flexxus S.A <soporte@flexxus.com>
* @copyright 2007-2017 Flexxus SA
* @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
* International Registered Trademark & Property of PrestaShop SA
*/
include(dirname(__FILE__).'../../../config/config.inc.php');
include(dirname(__FILE__).'../../../init.php');
include (dirname(__FILE__) . '/flxsincro.php');
//include (dirname(__FILE__) . '/class/flxException.php');
//include (dirname(__FILE__) . '/class/flxEquivalencia.php');
    $sincroV = flxfn::getModuleVersion();
    $method = Tools::getValue('method');
    switch ($method) {
    case 'tipoForm':
        Parametros::updateValues('MS_TIPOFORM',Tools::getValue('tipoForm'));
        Parametros::updateValues('MS_DISPLAYFORM1','block');
    break;
    case 'DBMSConnect':
        $host = explode(';',Tools::getValue('serv'));
        $database = Tools::getValue('host');			
        echo flxfn::DBMSConnect(Tools::getValue('idErp'),$host[0], $database, Tools::getValue('usuario'), Tools::getValue('pass'));
    break;
    case 'instalar':
        flxfn::installarScript($mensaje);
        echo $mensaje;
    break;
    case 'getFecha':
        echo '<i class="icon-calendar"></i> '.flxfn::getUltimaSincroGeneral();
    break;
    case 'getVersion':
        $MS_VERSIONMD =  'v'.$sincroV;
        echo $MS_VERSIONMD;
    break;
    case 'btnSincro':
        echo formulario::displayBtntoolbar();
    break;
    case 'getOptios':
        if (Tools::getValue('action') == 'getFlete') {
            echo flxfn::getDateErp(Tools::getValue('action'),Tools::getValue('codtransportista'));
		} elseif(Tools::getValue('action') == 'getLocalidades') {
							echo flxfn::getDateErp(Tools::getValue('action'),Tools::getValue('parametro'));
		} else {
          echo flxfn::getDateErp(Tools::getValue('action'));
		}
    break;
    case 'getLastVersion':
        $result = flxfn::getLastVersion();
        echo str_replace("v","",$result['version'][0]);
        //echo $module->version;
    break;
    case 'getERP':
        //if(isset($_GET['action']) == 'estados')
        $result = '';
        if (isset($_GET['action']) && $_GET['action'] == 'monedas') {
                $result = flxfn::getMonedas('','erp');
                header('Content-type: application/json; charset=utf-8');
                echo json_encode($result);
        }

        if (isset($_GET['action']) && $_GET['action'] == 'estados') {
                $result = flxfn::getEstados('','erp');
                header('Content-type: application/json; charset=utf-8');
                echo json_encode($result);
        }

        if (isset($_GET['action']) && $_GET['action'] == 'provincia') {
                $result = flxfn::getProvincias(44, '', 'erp');
                header('Content-type: application/json; charset=utf-8');
                echo json_encode($result);
        }

    break;
    case 'getMonedas':
        $result = flxfn::getMonedas();
        $id_ps = (isset($_GET['idMoneda']) ? (int)flxfn::getMonedas($_GET['idMoneda']) : '');

        $html   = '<option value="0">Seleccione su Moneda</option>';
        foreach ($result as $value => $key):
            $html.='<option value="'.$key['id_currency'].'" '.((int)$key['id_currency'] == $id_ps ? 'selected' : '').'>'.$key['name'].'</option>';
        endforeach;
        echo $html;
    break;
    case 'addMonedas':
        $data = array_combine(explode(",",Tools::getValue('monedas')),explode(",",Tools::getValue('equivalencias')));
        echo json_encode($data);
        //Db::getInstance()->execute('TRUNCATE '._DB_PREFIX_.'flx_monedas');
        foreach ($data as $mon => $equiv) {
            Db::getInstance()->update('flx_monedas', array(
                //'ID_ERP' => $mon,
                'id_prestashop' => $equiv,
            ),'ID_ERP = "'.$mon.'"');
        }
    break;
    case 'getEstados':
        $result = flxfn::getEstados('');
        $id_ps = (isset($_GET['idEstado']) ? flxfn::getEstados(Tools::getValue('idEstado')) : '');

        $html   = '<option value="0">Seleccione su estado</option>';
        foreach ($result as $value => $key) {
            $html.='<option value="'.$key['id_order_state'].'" '.($key['id_order_state'] == (int)$id_ps['id_order_state'] ? 'selected' : '').'>'.$key['name'].'</option>';
        }
        echo $html;
    break;
    case 'addEstados':
        $data = array_combine(explode(",",Tools::getValue('estados')),explode(",",Tools::getValue('equivalencias')));
        echo json_encode($data);
        Db::getInstance()->execute('TRUNCATE '._DB_PREFIX_.'flx_estados_np');
        foreach ($data as $prov => $equiv) {
            Db::getInstance()->insert('flx_estados_np', array(
                'ID_ERP' => $prov,
                'id_prestashop' => $equiv,
            ));
        }
    break;
    case 'getProvincias':
        $Countrys = flxfn::getIdCountryByName();
        $html = '<option value="0">Seleccione su equivalencia</option>';
        foreach($Countrys as $c => $cv)
        {
            $html .= '<optgroup label="'.$cv['name'].'">';
            
            $result = flxfn::getProvincias($cv['id_country']);

            $id_ps = (isset($_GET['idProvincia'])  ? flxfn::getProvincias($cv['id_country'], $_GET['idProvincia']) : '');

            foreach ($result as $value => $key) {
                $html.='<option value="'.$key['id_state'].'" '.($key['id_state'] == (int)$id_ps['id_state'] ? 'selected': '' ).'>'.$key['name'].'</option>';
            }
            $html .= '</optgroup>';

        }
        echo $html;
    break;
    case 'addProvincias':
        $nombres = array_combine(explode(",",Tools::getValue('provincias')),explode(",",Tools::getValue('nombres')));
        $data = array_combine(explode(",",Tools::getValue('provincias')),explode(",",Tools::getValue('equivalencias')));
        echo json_encode($data);
        //Db::getInstance()->execute('TRUNCATE '._DB_PREFIX_.'flx_provincia');

        foreach ($data as $prov => $equiv) {
            $id_ps = flxfn::equivalenciaID($prov,'flx_provincia');
            if ($id_ps == 'NULL') {
                Db::getInstance()->insert('flx_provincia', array(
                  'ID_ERP' => $prov,
                  'id_prestashop' => $equiv,
                  'name' => $nombres[$prov],
                  'modificar' => 1,
                ));
            } else {
                Db::getInstance()->update('flx_provincia', array(
                  'id_prestashop' => $equiv
                ),'ID_ERP="'.$prov.'"');
            }
        }
    break;
    case 'addLocalidades':
        Parametros::updateValues('MS_LOCSNA',Tools::getValue('option'));
    break;
    case 'addConfiguration':
        $params = explode(",", Tools::getValue('params'));
        foreach ($params as $objetosincro) {
            Db::getInstance()->update('flx_sincro', array(
                  'fechaultimasincro' => '1900.01.01 00:00:00'
                ),'objetosincro = "'.strtoupper($objetosincro).'"');
        }
    break;
    case 'CleanLocalidades':
        Parametros::updateValues('MS_LOCSNA','');
    break;
    case 'existDni':
        $count = Db::getInstance()->getValue('SELECT count(*)
            FROM '._DB_PREFIX_.'address
        WHERE dni = '.Tools::getValue('dni'));
        $result = ($count > 0 ? 'Este Dni ya existe' : '');
        echo $result;
    break;
    case 'existCuit':
        $count = Db::getInstance()->getValue('SELECT count(*)
            FROM '._DB_PREFIX_.'address
        WHERE vat_number = '.Tools::getValue('cuit'));
        $result = ($count > 0 ? 'Este Cuit ya existe' : '');
        echo $result;
    break;
    case 'InstallOverride':
        $module = new Flxsincro();

        foreach (Tools::scandir($module->getLocalPath().'override', 'php', '', true) as $file)
        {
            $class = basename($file, '.php');
            //echo $module->getLocalPath().'override/'.$file.'</br>';
            if($class != 'index') {
                $override_path = _PS_ROOT_DIR_.'/override/'.$file;
                //echo $override_path.'</br>';
                unlink($override_path);
                if (copy($module->getLocalPath().'override/'.$file, $override_path)) {
                    echo 'Se instalaron correctamente las vistas.';
                }
                // Re-generate the class index
                Tools::generateIndex();
            }
        }    
    break;
    case 'getLog':
        $result = flxfn::getLog();
        $html = '';
        foreach ($result as $value => $key) {
            $html .= '<tr>';
                $html.='<td>'.$key['idLog'].'</td>';
                $html.='<td>'.$key['evento'].'</td>';
                $html.='<td>'.str_replace('/\/', '', $key['error']).'</td>';
                $html.='<td>'.$key['estado'].'</td>';
                $html.='<td>'.$key['fechahora'].'</td>';
            $html .= '</tr>';
        }
        echo $html;
    break;
    }
?>