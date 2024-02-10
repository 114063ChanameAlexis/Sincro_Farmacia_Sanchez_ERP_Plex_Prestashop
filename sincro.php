<?php
ini_set("memory_limit", "-1");
/* Debug only */
if (!defined('_PS_MODE_DEV_') && isset($_GET['mododebug']) and $_GET['mododebug'] == 1){
  define('_PS_MODE_DEV_', true);
}
require ('../../config/config.inc.php');
include (dirname(__FILE__) . '/flxsincro.php');
 
$sincro = new Sincro();

if(_PS_MODE_DEV_):
// Turn off output buffering
ini_set('output_buffering', 'off');
// Turn off PHP output compression
ini_set('zlib.output_compression', false);

//Flush (send) the output buffer and turn off output buffering
while(@ob_end_flush());

// Implicitly flush the buffer(s)
ini_set('implicit_flush', true);
ob_implicit_flush(true);
set_time_limit(3000);
endif;

$ID_SHOP = (Context::getContext()->shop->id  == 0 ? (int)Configuration::get('PS_SHOP_DEFAULT') : Context::getContext()->shop->id );

//activar modo debug
$mododebug = (isset($_GET['mododebug']) and $_GET['mododebug'] == 1 ? 1 : 0);
$descount = (isset($_GET['descount']) ? false : true);
$TIPOFORM = Parametros::get('MS_TIPOFORM');

/* ----> INICIO - CONEXION MOTOR ERP */
    $engine = Parametros::get('MS_ENGINE');
    // En el parámetro MS_SERVIDOR actualmente se está almacenando la lista de Host separados por ;
    $host = explode(';',Parametros::get('MS_SERVIDOR_'.$ID_SHOP));

    // En el parámetro MS_HOST actualmente se está almacenando la ruta de la BD (Firebird) o el nombre de la BD (SQL Server)
    $database = Parametros::get('MS_HOST_'.$ID_SHOP);

    $usuario = Parametros::get('MS_USUARIO_'.$ID_SHOP);
    $password = Parametros::get('MS_PASSWORD_'.$ID_SHOP);

    try{
        $ibase = new flxibase($engine,$host[0],$database,$usuario,$password);
        if($ibase->connect() == false)
            throw new Exception('reintentar');

        if($mododebug == 1)
          echo 'Conectado a '.$host[0].'<br/><br/>';

    }catch(Exception $e){
        if($e->getMessage() === 'reintentar'){
            $ibase = new flxibase($engine,$host[1],$database,$usuario,$password);
            if($ibase->connect() == false){
                Db::getInstance()->execute('INSERT INTO '._DB_PREFIX_.'flx_log_error VALUES("","Conexion","'.$e->getMessage().' Linea error ('.$e->getLine().')'.'","Pendiente","'.$_SERVER['SERVER_ADDR'].'","'.date('Y-m-d H:m:s').'");');
                $data['mensaje']['Error'] = 'No se ha podido conectar con la base de datos del ERP. Vuelva a intentarlo mas tarde';
                //echo 'Error al Sincronziar. Intentelo mas tarde';
                    $content = '<h2> Datos de Cliente</h2><br/>';
                    $content.= 'Servidor: '._DB_SERVER_.'<br/>';
                    $content.= 'Servidor: '._DB_NAME_.'<br/>';
                    $content.= 'Error al conectarse al ERP';
                    flxfn::sendMail('Error Sincronizacion: '._DB_NAME_, $content , true);
                 $ibase->close();
                    echo Tools::jsonEncode($data);
                exit;
            }

            if($mododebug == 1)
              echo 'Conectado a '.$host[1].'<br/><br/>';
        }//end $e->getMessage()

    }
/* ----> FIN - CONEXION DBMS*/

/* ----> INICIO - Integración de PARAMETROS  */
 if($TIPOFORM == 1)
 {
   //Parametro para Usar Stock de Factura sin remitir
   $sql= "SELECT VALOR FROM PARAMETROSGENERALES WHERE CODIGOPARAMETRO = 82";
   $result_parametro = $ibase->query($sql);
   $row = $ibase->fetch_object($result_parametro);
   $FACTURASINREMITIR = ((int)$row->VALOR == 1);
   $ibase->free_result($result_parametro);

   //Parametro para usar Talle
   $sql= "SELECT VALOR FROM PARAMETROSGENERALES WHERE CODIGOPARAMETRO = 83";
   $result_parametro = $ibase->query($sql);
   $row = $ibase->fetch_object($result_parametro);
   $USATALLE = (int)$row->VALOR;
   $ibase->free_result($result_parametro);

   if($USATALLE == 1)
    $USALOTE = 0;
   else{
     //Parametro para usar Lote
     $sql= "SELECT VALOR FROM PARAMETROSGENERALES WHERE CODIGOPARAMETRO = 20";
     $result_parametro = $ibase->query($sql);
     $row = $ibase->fetch_object($result_parametro);
     $USALOTE = (int)$row->VALOR;
     $ibase->free_result($result_parametro);
   }
 }
 else {
   $USATALLE = 0;
   $USALOTE = 0;
   $FACTURASINREMITIR = false;
 }

$ID_LANG = (int)Configuration::get('PS_LANG_DEFAULT');

$MS_MAYORISTA = ((int)Parametros::get('MS_MAYORISTA_'.Context::getContext()->shop->id));
$MS_DEPOSITOS = (Parametros::get('MS_DEPOSITOS_'.Context::getContext()->shop->id));
$MS_CATEGORIASASOC = (Parametros::get('MS_CATEGORIASASOC_'.Context::getContext()->shop->id));
$listaGSR = (!empty($MS_CATEGORIASASOC) ? "'".$MS_CATEGORIASASOC."'" : 'null');
$MS_TALLES = (int)Parametros::get('MS_TALLES');
$MS_LISTAPRECIO = Parametros::get('MS_LISTAPRECIO');
$MS_STOCKREAL = (int)Parametros::get('MS_STOCKREAL');
$MS_CONDICIONIVA = Parametros::get('MS_CONDICIONIVA');
$MS_BARRIO = Parametros::get('MS_BARRIO');
$MS_CODZONA = Parametros::get('MS_CODZONA');
$MS_USERERP = Parametros::get('MS_USERERP_'.Context::getContext()->shop->id);
$MS_USERCOBRADOR = Parametros::get('MS_USERCOBRADOR_'.Context::getContext()->shop->id);
$MS_CODACTIVIDAD = Parametros::get('MS_CODACTIVIDAD_'.Context::getContext()->shop->id);
$MS_CODFORMADEPAGO = Parametros::get('MS_CODFORMADEPAGO_'.$ID_SHOP);
$MS_CODOPERACION = Parametros::get('MS_CODOPERACION_'.Context::getContext()->shop->id);
$MS_ESTADOSPEDIDOS = Parametros::get('MS_ESTADOSPEDIDOS_'.Context::getContext()->shop->id);
$MS_CODTRANSPORTISTA = Parametros::get('MS_CODTRANSPORTISTA');
$MS_CODMONEDA = Parametros::get('MS_CODMONEDA_'.$ID_SHOP);
$MS_LOCSNA = Parametros::get('MS_LOCSNA');
$MS_NOMBREARTICULO = Parametros::get('MS_NOMBREARTICULO');
$MS_NOMBRECATEGORIA = Parametros::get('MS_NOMBRECATEGORIA');
$MS_FAMILIA = (int)Parametros::get('MS_FAMILIA');
$MS_FAMILIA_NOMBRE = Parametros::get('MS_FAMILIA_NOMBRE');
$MS_SUBDEPOSITOS = (int)Parametros::get('MS_SUBDEPOSITOS');

$MS_CATEGORIASASOCIADAS = Parametros::get('MS_CATEGORIASASOCIADAS');
$MS_ACTIVACIONARTICULOS = Parametros::get('MS_ACTIVACIONARTICULOS');
$MS_ACTIVACIONIMAGEN = Parametros::get('MS_ACTIVACIONIMAGEN');

$MS_DEPXCLI = Parametros::get('MS_DEPXCLI_'.Context::getContext()->shop->id);

$TaxList = flxfn::getTaxList();
$idFlete = flxfn::getDateErp('getFlete',$MS_CODTRANSPORTISTA,false);
$MS_DEPOSITOSSTOCK = Parametros::get('MS_DEPOSITOSSTOCK');
//Traemos todos los grupos de los clientes.
$groups = flxfn::getGroups($ID_LANG);

/* ----> FIN - Integracion de PARAMETROS */

/* ----> INICIO - PARAMETRO FECHA SINCRO */
$date = new DateTime($ibase->getDBMS_DateTime());
$fechaInicioSincro = $date->format('Y-m-d H:i');
if((int)$mododebug)
{
  echo 'Inicio Sincro: '.$fechaInicioSincro.'<br/>';
  echo ('</br>'.
        '<div id="obj_sincro" style="width"></div>'.
        '<div id="progress" style="width:500px;border:1px solid #ccc;"></div>'.
        '<div id="information" style="width"></div></br>'.
        '<textarea style="width: 500px; height: 150px;" rows="10" id="textresult"></textarea>');
}
/* ----> FIN - PARAMETRO FECHA SINCRO */

/* ----> INICIO - INTEGRACION DE MONEDAS */
$RegistrosSincronizados = 0;$errMensaje = 0;
flxfn::startTransaction();
    $sql_monedas = "SELECT * FROM MTO_MONEDAS";
    $result_monedas = $ibase->query($sql_monedas);
    if($result_monedas === false){
          throw new Exception('MTO_MONEDAS');
    }

    while ($row = $ibase->fetch_object($result_monedas)):
        flxfn::updateInsert($row->CODIGOMONEDA,$row->DESCRIPCION,$row->CAMBIO,'flx_monedas');
    endwhile;
$ibase->free_result($result_monedas);
flxfn::commitTransaction(0);
/* ----> FIN - INTEGRACION DE MONEDAS */

/* ----> INICIO - INTEGRACION DE ESTADOS NP FLX */
$RegistrosSincronizados = 0;$errMensaje = 0;
$flxEstados = flxfn::getEstadosNP();
$estados = array( 1 => 'PREPARADO', 2 => 'FACTURADO', 3 => 'REMITIDO', 4 => 'DESPACHADO', 5 => 'ANULADA');

if(empty($flxEstados)):
    foreach ($estados as $key => $value):
      $data['mensaje']['estados'][$key]['Codigo'] = $value;
      $data['mensaje']['estados'][$key]['Nombre'] = $value;
    endforeach;
endif;
if(isset($data['mensaje']['estados'])):
    $ibase->close();
    echo Tools::jsonEncode($data);
    return true;
    exit;
endif;
/* ----> FIN - INTEGRACION DE ESTADOS NP FLX */

/* ----> INICIO - INTEGRACION DE PROVINCIAS */
    $RegistrosSincronizados = 0;$errMensaje = 0;
    flxfn::startTransaction();
    $sql_provincias = "SELECT * FROM MTO_PROVINCIAS ".flxfn::ultimaFechaSincro('MTO_PROVINCIAS', 0, true);

    $TOTALREG = flxfn::initProgressBar('Provincias', $sql_provincias, $position, (int)$mododebug, $ibase);

    $result_provincias = $ibase->query($sql_provincias);
    if($result_provincias === false){
          throw new Exception('MTO_PROVINCIAS');
    }

    $id_pais = flxfn::getIdCountryByName();
    $i = 0;
    while ($row = $ibase->fetch_object($result_provincias)):

        flxfn::updateProgressBar($position, $TOTALREG);

        $id_provincia = State::getIdByName($row->NOMBRE);
        $iso_code = State::getIdByIso($row->CODIGOPROVINCIA);
        $equivalencia = flxfn::getIdByName($row->CODIGOPROVINCIA);
        if((int)$id_provincia > 0 || (int)$equivalencia > 0)
        {
            if(!$equivalencia){
            Db::getInstance()->insert('flx_provincia', array(
                'ID_ERP' => $row->CODIGOPROVINCIA,
                'id_prestashop' => $id_provincia,
                'name' => $row->NOMBRE,
                'modificar' => 0,
            ));
          }else{
            Db::getInstance()->update('flx_provincia', array(
                'name' => $row->NOMBRE,
            ),"id_prestashop = ".$id_provincia);
          }
        }else{
            $data['mensaje']['Provincias'][$i]['Codigo'] = $row->CODIGOPROVINCIA;
            $data['mensaje']['Provincias'][$i]['Nombre'] = flxfn::getValidestrName($row->NOMBRE, 0, true);
            $i++;
        }
        $RegistrosSincronizados = $RegistrosSincronizados + 1;
    endwhile;

$ibase->free_result($result_provincias);
if(isset($data['mensaje']['Provincias'])) {
    $ibase->close();
    echo Tools::jsonEncode($data);
    return true;
}
flxfn::updateFechaUltimaSincro('MTO_PROVINCIAS', $fechaInicioSincro,0);
flxfn::endProgressBar('provincias', $TOTALREG, $TOTALREG-$RegistrosSincronizados);
$msj = "<div class='line-result'><span class='count-badge'>".$RegistrosSincronizados."</span> Provincias</div>";
flxfn::commitTransaction(0);
/* ----> FIN - INTEGRACION DE PROVINCIAS */

/* ----> INICIO - INTEGRACION DE MARCAS */
    flxfn::startTransaction();
    $RegistrosSincronizados = 0;$errMensaje = 0;
    Shop::setContext(Shop::CONTEXT_ALL);
    $sql_marcas = "SELECT * FROM MTO_MARCAS mo ".flxfn::ultimaFechaSincro('MTO_MARCAS', 0,true);

    // -- TODO! Resolver de otra manera esto, el deterioro en la performance es muy grande. Es preferible desactivar las marcas post sintro de producto
    /*
    * Para poder resolver la sincronizacion de marcas segun multitienda, realizamos una union
    * donde solo traemos las marcas de los productos que estan aptos para pasar segun la categoria que
    * le pasamos, tuvimos que definir el parametro fecha  1900-01-02 00:00:00 para que siempre haga la relacion
    * de productos y categorias desde el inicio, solo pasamos fechaDeUltimaSincronizacion en la condicion de marcas.
    */
    // $sql_marcas = "SELECT DISTINCT mo.*
    //                     FROM MTO_MARCAS mo
    //                 INNER JOIN MTO_PRODUCTOS ('".$MS_DEPOSITOS."','1900-01-02 00:00:00','".$MS_LISTAPRECIO."') P On P.id_marca = mo.codigomarca
    //                 INNER JOIN MTO_CATEGORIAS (".$listaGSR.", '".(int)Parametros::get('MS_IDGSR')."','1900-01-02 00:00:00') C On C.id_categoria = P.id_categoria
    //                 WHERE mo.FECHAMODIFICACION >= '".flxfn::ultimaFechaSincro('MTO_MARCAS', 0, false)."'";

    $TOTALREG = flxfn::initProgressBar('Marcas', $sql_marcas, $position, (int)$mododebug, $ibase);
    $sql_marcas = $sql_marcas." ORDER BY mo.CODIGOMARCA";
    //echo $sql_marcas;
    try{
        $result_marcas = $ibase->query($sql_marcas);

        if($result_marcas === false) {
            throw new Exception('MTO_MARCAS');
        }

        while ($row = $ibase->fetch_object($result_marcas)):

            flxfn::updateProgressBar($position, $TOTALREG);

            $id_marca = flxfn::equivalenciaID($row->CODIGOMARCA,'flx_m2m');
            $marca = new Manufacturer($id_marca);
            $marca->name = flxfn::getValidestrName($row->NOMBRE,0,true);
            $marca->active = (int)$row->ACTIVO;
            $marca->date_upd = date('Y-m-d');

            if($id_marca == 'NULL'){
                $marca->date_add = date('Y-m-d');

                try{
                $marca->add();
                }catch(Exception $e){
                    $message = $e->getMessage().' Linea error ('.$e->getLine().')';
                    flxfn::addLog('Agregar Marca', $message);
                    $errMensaje = $errMensaje + 1;
                    continue;
                }

                if($marca->id > 0)
                Db::getInstance()->insert('flx_m2m', array(
                    'ID_ERP' => $row->CODIGOMARCA,
                    'id_prestashop' => $marca->id,
                ));
            }elseif($id_marca != 'NULL'){
                try{
                    if($MS_CATEGORIASASOC == '')
                    $marca->active = (int)$row->ACTIVO;
                    $marca->update();
                }catch(Exception $e){
                    $message = $e->getMessage().' Linea error ('.$e->getLine().')';
                    flxfn::addLog('Actualiza Marca', $message);
                    $errMensaje = $errMensaje + 1;
                    continue;
                }
            }

            if(!(int)$row->ACTIVO && $id_marca != 'NULL'):
                $marca->active=0;
            endif;

            if((int)$row->ACTIVO)
            $RegistrosSincronizados = $RegistrosSincronizados + 1;
        endwhile;
        $ibase->free_result($result_marcas);

    }catch(Exception $e){
        Db::getInstance()->execute('INSERT INTO '._DB_PREFIX_.'flx_log_error VALUES("","Sincronizacion","'.$e->getMessage().' Linea error ('.$e->getLine().')'.'","Pendiente","'.$_SERVER['SERVER_ADDR'].'","'.date('Y-m-d H:m:s').'");');
        $content = '<h2> Datos de Cliente</h2><br/>';
        $content.= 'Servidor: '._DB_SERVER_.'<br/>';
        $content.= 'Servidor: '._DB_NAME_.'<br/>';
        $content.= 'Error'.$e->getMessage().' Linea error ('.$e->getLine().')';

        flxfn::sendMail('Error Sincronizacion: Marcas', $content);
    }

    $msj = "<div class = 'line-result'><span class='count-badge'>".$RegistrosSincronizados."</span> MARCAS</div>";
    if($RegistrosSincronizados == 0 || $GLOBALS['mododebug'] == 0)
      $msj=0;

    if($errMensaje == 0)
      flxfn::updateFechaUltimaSincro('MTO_MARCAS', $fechaInicioSincro,0);

flxfn::commitTransaction($msj);
flxfn::endProgressBar('marcas', $TOTALREG, $errMensaje);
$data['mensaje']['Marcas'] = $RegistrosSincronizados;
if($errMensaje > 0 && $GLOBALS['mododebug'] == 1)
$data['mensaje']['Marcas con Error'] = $errMensaje;

/* ----> FIN - INTEGRACION DE MARCAS */

/* ----> INICIO - INTEGRACION DE CATEGORIAS */
$RegistrosSincronizados = 0;$errMensaje = 0;
flxfn::startTransaction();

$sql_categorias = "SELECT * FROM MTO_CATEGORIAS (".$listaGSR.", '".(int)Parametros::get('MS_IDGSR')."','".flxfn::ultimaFechaSincro('MTO_CATEGORIAS',0 , false)."')";

$TOTALREG = flxfn::initProgressBar('Categorias', $sql_categorias, $position, (int)$mododebug, $ibase);

$sql_categorias = $sql_categorias." ORDER BY NOMBRE ASC";

try{
    $HOME_CATEGORY = Configuration::get('PS_HOME_CATEGORY', null, null, $ID_SHOP);

    $result_categorias = $ibase->query($sql_categorias);

    /*echo 'Categorias en el array'.$result_categorias;*/

    if($result_categorias === false)
        throw new Exception('MTO_CATEGORIAS');

    while ($row = $ibase->fetch_object($result_categorias)):

        /*echo 'Categoria'.$id_categoria.'<br/>';*/

        flxfn::updateProgressBar($position, $TOTALREG);

        $id_categoria = flxfn::equivalenciaID($row->ID_CATEGORIA,'flx_c2c');
        if($id_categoria == 'NULL' && (int)$row->ACTIVO == 0)
            continue;

        $id_padre = (int)flxfn::equivalenciaID($row->ID_PADRE,'flx_c2c');

        $categoria = new Category($id_categoria);

        $categoria->id_parent = ((int)$row->POSICION == 1 || (int)$row->POSICION == 1 && $id_padre == 0 ? $HOME_CATEGORY : $id_padre);

        $padreStatus = flxfn::categoryParentStatus($categoria->id_parent);
        
        $categoria->nleft=0;
        $categoria->nright=0;
        $categoria->level_depth=2;
        $categoria->id_shop_default = $ID_SHOP;
        $categoria->is_root_category=0;
       
        $categoria->active = ($row->ACTIVO && $padreStatus);

        if(($MS_NOMBRECATEGORIA && $id_categoria != 'NULL') || $id_categoria == 'NULL')
        {
            $nombre = flxfn::getValidestrName($row->NOMBRE,0,true);
            $nombre = ($nombre == '' ? 'No Disponible' : $nombre);
            $categoria->name = array($ID_LANG => $nombre );         
            $categoria->link_rewrite = array($ID_LANG => Tools::link_rewrite(flxfn::getLinkRewrite($nombre)) );
        }

        if($id_categoria == 'NULL')
        {
            $categoria->date_add = date('Y-m-d');
            //Agregamos Posicion solo en el insert
            $categoria->position = $row->POSICION;
            try{
              $categoria->add();
                if($categoria->id > 0)
                Db::getInstance()->insert('flx_c2c', array(
                    'ID_ERP' => $row->ID_CATEGORIA,
                    'id_prestashop' => $categoria->id,
                    'muestraweb' => $row->ACTIVO,
                ));
            }catch(Exception $e){
                $message = $e->getMessage().' Linea error ('.$e->getLine().')';
                flxfn::addLog('Agregar Categoria', $message);
                $errMensaje = $errMensaje + 1;
                continue;
            }
        }elseif($id_categoria != 'NULL'){
            //Agregamos fecha de actualizacion
            $categoria->date_upd = date('Y-m-d');
            try{
              $categoria->update();
              Db::getInstance()->update('flx_c2c', array(
                  'muestraweb' => $row->ACTIVO,
              ),'id_prestashop='.$id_categoria);
            }catch(Exception $e){
                $message = $e->getMessage().' Linea error ('.$e->getLine().')';
                flxfn::addLog('Actualizar Categoria: '.$id_categoria, $message);
                $errMensaje = $errMensaje + 1;
                continue;
            }
        }
        
        $categoria->updateGroup($groups);
        //Activamos o desactivamos los hijos de la categoria padre
        flxfn::updateChildrenStatus((int)$id_categoria,(int)($row->ACTIVO && $padreStatus));

        $RegistrosSincronizados = $RegistrosSincronizados + 1;
    endwhile;
    $ibase->free_result($result_categorias);

}catch(Exception $e){
    Db::getInstance()->execute('INSERT INTO '._DB_PREFIX_.'flx_log_error VALUES("","Sincronizacion","'.$e->getMessage().' Linea error ('.$e->getLine().')'.'","Pendiente","'.$_SERVER['SERVER_ADDR'].'","'.date('Y-m-d H:m:s').'");');
    $content = '<h2> Datos de Cliente</h2><br/>';
    $content.= 'Servidor: '._DB_SERVER_.'<br/>';
    $content.= 'Servidor: '._DB_NAME_.'<br/>';
    $content.= 'Error'.$e->getMessage().' Linea error ('.$e->getLine().')';

    flxfn::sendMail('Error Sincronizacion: Categorias',$content);
}


$msj = "<div class='line-result'><span class='count-badge'>".$RegistrosSincronizados."</span> CATEGORIAS</div>";

    if($RegistrosSincronizados == 0 || $GLOBALS['mododebug'] == 0)
      $msj=0;

    if($errMensaje == 0)
      flxfn::updateFechaUltimaSincro('MTO_CATEGORIAS', $fechaInicioSincro,0);

flxfn::commitTransaction($msj);
flxfn::endProgressBar('categorias', $TOTALREG, $errMensaje);
$data['mensaje']['Categorias'] = $RegistrosSincronizados;
if($errMensaje > 0 && $GLOBALS['mododebug'] == 1)
$data['mensaje']['Categorias con Error'] = $errMensaje;
if((int)$RegistrosSincronizados > 0)
Category::regenerateEntireNtree();
/* ----> FIN - INTEGRACION DE CATEGORIAS */

/* ----> INICIO - INTEGRACION DE TALLES */
if($MS_TALLES)
{
    $RegistrosSincronizados = 0;$errMensaje = 0;
    flxfn::startTransaction();

    //Insertamos el grupo Talle
    if(!flxfn::getAttributesGroupsID($ID_LANG,'TALLES'))
    $id_talle_group = 'NULL';
    else
    $id_talle_group = flxfn::getAttributesGroupsID($ID_LANG,'TALLES');

    $attributo_group = new AttributeGroup($id_talle_group);
    $attributo_group->is_color_group=0;
    $attributo_group->position=0;
    $attributo_group->group_type='select';
    $attributo_group->name = array($ID_LANG => Tools::replaceAccentedChars('TALLES'));
    $attributo_group->public_name = array($ID_LANG => Tools::replaceAccentedChars('TALLES'));

    if($id_talle_group == 'NULL')
       $attributo_group->add();

    // --> leo la vista PS_TALLES
    $sql_talles = "SELECT * FROM MTO_TALLES ";

    try{
        $result_talles = $ibase->query($sql_talles);

        if($result_talles === false){
            throw new Exception('MTO_TALLES');
        }

        while ($row = $ibase->fetch_object($result_talles)):
            //insertamos o actualizamos en ps_attribute
            $id_talle = flxfn::equivalenciaID($row->TALLE,'flx_a2a');
            $attribute = new Attribute($id_talle);
            $attribute->id_attribute_group=$attributo_group->id;
            $attribute->name = array($ID_LANG => Tools::replaceAccentedChars(utf8_decode($row->TALLE)));
            $attribute->color=0;
            $attribute->position=0;

            if($id_talle == 'NULL'){
              try{
                   $attribute->add();
              }catch(Exception $e){
                  $message = $e->getMessage().' Linea error ('.$e->getLine().')';
                  flxfn::addLog('Agregar Talle', $message);
                  $errMensaje = $errMensaje + 1;
                  continue;
              }
              if($attribute->id > 0)
              Db::getInstance()->insert('flx_a2a', array(
                'ID_ERP' => $row->TALLE,
                'id_prestashop' => $attribute->id,
              ));
            }elseif($id_talle != 'NULL'){
               try{
                    $attribute->update();
               }catch(Exception $e){
                   $message = $e->getMessage().' Linea error ('.$e->getLine().')';
                   flxfn::addLog('Actualizar Talle', $message);
                   $errMensaje = $errMensaje + 1;
                   continue;
               }
            }

            $RegistrosSincronizados = $RegistrosSincronizados + 1;
        endwhile;
        $ibase->free_result($result_talles);

    }catch(Exception $e){
        Db::getInstance()->execute('INSERT INTO '._DB_PREFIX_.'flx_log_error VALUES("","Sincronizacion","'.$e->getMessage().' Linea error ('.$e->getLine().')'.'","Pendiente","'.$_SERVER['SERVER_ADDR'].'","'.date('Y-m-d H:m:s').'");');
        $content = '<h2> Datos de Cliente</h2><br/>';
        $content.= 'Servidor: '._DB_SERVER_.'<br/>';
        $content.= 'Servidor: '._DB_NAME_.'<br/>';
        $content.= 'Error'.$e->getMessage().' Linea error ('.$e->getLine().')';

        flxfn::sendMail('Error Sincronizacion:'.$e->getMessage(),$content);
    }

    $msj = "<div class='line-result'><span class='count-badge'>".$RegistrosSincronizados."</span> TALLES</div>";
    flxfn::commitTransaction(0);
}//End
/* ----> FIN - INTEGRACION DE TALLES */

/* ----> INICIO - INTEGRACION DE CARACTERISTICAS (FAMILIA) */

if($MS_FAMILIA)
{
    $RegistrosSincronizados = 0;$errMensaje = 0;
    flxfn::startTransaction();

    //Insertamos la caracteristica autor
    if(!flxfn::getFeatureID($ID_LANG, $MS_FAMILIA_NOMBRE))
    $id_feature = 'NULL';
    else
    $id_feature = flxfn::getFeatureID($ID_LANG, $MS_FAMILIA_NOMBRE);

    //echo "<br>id-feature".$id_feature;
    $caracteristica = new Feature($id_feature);
    $caracteristica->position=0;
    $caracteristica->name = array($ID_LANG => Tools::replaceAccentedChars($MS_FAMILIA_NOMBRE));
    
    if($id_feature == 'NULL')
       $caracteristica->add();
   //echo "agrega caract";

//}/*
    // --> leo la vista mto_familias
    //$sql_familias = "SELECT * FROM MTO_FAMILIA";
    $sql_familias = "SELECT * FROM MTO_FAMILIAS('".flxfn::ultimaFechaSincro('MTO_FAMILIAS', 0, false)."')";

    $TOTALREG = flxfn::initProgressBar('Familias', $sql_familias, $position, (int)$mododebug, $ibase);

    try{
        $result_familias = $ibase->query($sql_familias);

        //echo'<pre>'.var_dump($result_familias);
        if($result_familias === false){
            throw new Exception('MTO_TALLES');
        }

        while ($row = $ibase->fetch_object($result_familias)):
            flxfn::updateProgressBar($position, $TOTALREG);
           //insertamos o actualizamos en ps_attribute
            $originales = "ÑÇ´ÜÉÍÁÚ'`";
            $modificadas ="NC-UEIAU--";
            $row->DESCRIPCION = strtr($row->DESCRIPCION, utf8_decode($originales), $modificadas);

            //$fam = str_replace("Ñ","N",$row->FAMILIA);
            //echo "<br>familia-erp: ".$row->DESCRIPCION;
            $id_caracteristica = flxfn::equivalenciaID($row->CODIGOFAMILIA,'flx_fa2fa');
            $familia = new FeatureValue($id_caracteristica);
            //$familia->id_feature_value=$caracteristica->id;
            $familia->id_feature=$caracteristica->id;
            $familia->custom=0;
            $familia->value = array($ID_LANG => Tools::replaceAccentedChars(utf8_decode($row->DESCRIPCION)));

            //echo "<br>id-familia-presta: ".$id_caracteristica;
            //echo "<br>id_feature_value: ".$familia->id_feature_value;
            //echo "<br>value".$familia->value;
           

            if($id_caracteristica == 'NULL'){
              try{
                   $familia->add();
                    //echo "<br>id-familia : ".$familia->id;
              }catch(Exception $e){
                  $message = $e->getMessage().' Linea error ('.$e->getLine().')';
                  flxfn::addLog('Agregar familia', $message);
                  $errMensaje = $errMensaje + 1;
                  continue;
              }
              if($familia->id > 0)
              Db::getInstance()->insert('flx_fa2fa', array(
                'ID_ERP' => $row->CODIGOFAMILIA,
                'id_prestashop' => $familia->id
              ));
            }elseif($id_caracteristica != 'NULL'){
               try{
                    $familia->update();
               }catch(Exception $e){
                   $message = $e->getMessage().' Linea error ('.$e->getLine().')';
                   flxfn::addLog('Actualizar familia', $message);
                   $errMensaje = $errMensaje + 1;
                   continue;
               }
            }

            $RegistrosSincronizados = $RegistrosSincronizados + 1;
        endwhile;
        $ibase->free_result($result_familias);

    }catch(Exception $e){
        Db::getInstance()->execute('INSERT INTO '._DB_PREFIX_.'flx_log_error VALUES("","Sincronizacion","'.$e->getMessage().' Linea error ('.$e->getLine().')'.'","Pendiente","'.$_SERVER['SERVER_ADDR'].'","'.date('Y-m-d H:m:s').'");');
        $content = '<h2> Datos de Cliente</h2><br/>';
        $content.= 'Servidor: '._DB_SERVER_.'<br/>';
        $content.= 'Servidor: '._DB_NAME_.'<br/>';
        $content.= 'Error'.$e->getMessage().' Linea error ('.$e->getLine().')';

        flxfn::sendMail('Error Sincronizacion:'.$e->getMessage(),$content);
    }

    if($errMensaje == 0)
      flxfn::updateFechaUltimaSincro('MTO_FAMILIAS', $fechaInicioSincro,0);

    $msj = "<div class='line-result'><span class='count-badge'>".$RegistrosSincronizados."</span> FAMILIAS</div>";
    flxfn::commitTransaction(0);
    flxfn::endProgressBar('familias', $TOTALREG, $errMensaje);
    $data['mensaje']['Familias'] = $RegistrosSincronizados;
    if($errMensaje > 0 && $GLOBALS['mododebug'] == 1)
    $data['mensaje']['Familias con Error'] = $errMensaje;
    }

//End 
/* ----> FIN - INTEGRACION DE CARACTERISTICAS (FAMILIA) */

/* ----> INICIO - INTEGRACION DE CARACTERISTICAS (SUBDEPOSITOS) */

if($MS_SUBDEPOSITOS)
{
    $RegistrosSincronizados = 0;$errMensaje = 0;
    flxfn::startTransaction();

    //Insertamos la caracteristica autor
    if(!flxfn::getFeatureID($ID_LANG,'Posicion/Ubicacion'))
    $id_feature = 'NULL';
    else
    $id_feature = flxfn::getFeatureID($ID_LANG,'Posicion/Ubicacion');

    //echo "<br>id-feature".$id_feature;
    $caracteristica = new Feature($id_feature);
    $caracteristica->position=0;
    $caracteristica->name = array($ID_LANG => Tools::replaceAccentedChars('Posicion/Ubicacion'));
    
    if($id_feature == 'NULL')
       $caracteristica->add();
   //echo "agrega caract";

//}/*
    // --> leo la vista mto_subdepositos
    //$sql_familias = "SELECT * FROM MTO_subdepositos";
    $sql_caracteristica = "SELECT * FROM MTO_SUBDEPOSITOS('".flxfn::ultimaFechaSincro('MTO_PRODUCTOS', 0, false)."')";

    $TOTALREG = flxfn::initProgressBar('Subdepositos', $sql_caracteristica, $position, (int)$mododebug, $ibase);

    try{
        $result_caracteristica = $ibase->query($sql_caracteristica);

        //echo'<pre>'.var_dump($result_familias);
        if($result_caracteristica === false){
            throw new Exception('MTO_TALLES');
        }

        while ($row = $ibase->fetch_object($result_caracteristica)):
            flxfn::updateProgressBar($position, $TOTALREG);
           //insertamos o actualizamos en ps_attribute
            
            $originales = "ÑÇ´ÜÉÍÁÚ'`";
            $modificadas ="NC-UEIAU--";
            $row->DESCRIPCION = strtr($row->DESCRIPCION, utf8_decode($originales), $modificadas);

            //$fam = str_replace("Ñ","N",$row->FAMILIA);
            //echo "<br>familia-erp: ".$row->DESCRIPCION;
            $id_caracteristica = flxfn::equivalenciaID($row->CODIGOSUBDEPOSITO,'flx_su2su');
            $subdeposito = new FeatureValue($id_caracteristica);
            //$familia->id_feature_value=$caracteristica->id;
            $subdeposito->id_feature=$caracteristica->id;
            $subdeposito->custom=0;
            $subdeposito->value = array($ID_LANG => Tools::replaceAccentedChars(utf8_decode($row->DESCRIPCION)));

            //echo "<br>id-familia-presta: ".$id_caracteristica;
            //echo "<br>id_feature_value: ".$familia->id_feature_value;
            //echo "<br>value".$familia->value;
           

            if($id_caracteristica == 'NULL'){
              try{
                   $subdeposito->add();
                    //echo "<br>id-familia : ".$familia->id;
              }catch(Exception $e){
                  $message = $e->getMessage().' Linea error ('.$e->getLine().')';
                  flxfn::addLog('Agregar subdeposito', $message);
                  $errMensaje = $errMensaje + 1;
                  continue;
              }
              if($subdeposito->id > 0)
              Db::getInstance()->insert('flx_su2su', array(
                'ID_ERP' => $row->CODIGOSUBDEPOSITO,
                'id_prestashop' => $subdeposito->id
              ));
            }elseif($id_caracteristica != 'NULL'){
               try{
                    $subdeposito->update();
               }catch(Exception $e){
                   $message = $e->getMessage().' Linea error ('.$e->getLine().')';
                   flxfn::addLog('Actualizar Subdeposito', $message);
                   $errMensaje = $errMensaje + 1;
                   continue;
               }
            }

            $RegistrosSincronizados = $RegistrosSincronizados + 1;
        endwhile;
        $ibase->free_result($result_caracteristica);

    }catch(Exception $e){
        Db::getInstance()->execute('INSERT INTO '._DB_PREFIX_.'flx_log_error VALUES("","Sincronizacion","'.$e->getMessage().' Linea error ('.$e->getLine().')'.'","Pendiente","'.$_SERVER['SERVER_ADDR'].'","'.date('Y-m-d H:m:s').'");');
        $content = '<h2> Datos de Cliente</h2><br/>';
        $content.= 'Servidor: '._DB_SERVER_.'<br/>';
        $content.= 'Servidor: '._DB_NAME_.'<br/>';
        $content.= 'Error'.$e->getMessage().' Linea error ('.$e->getLine().')';

        flxfn::sendMail('Error Sincronizacion:'.$e->getMessage(),$content);
    }

    if($errMensaje == 0)
      //flxfn::updateFechaUltimaSincro('MTO_FAMILIAS', $fechaInicioSincro,0);

    $msj = "<div class='line-result'><span class='count-badge'>".$RegistrosSincronizados."</span> SUBDEPOSITOS</div>";
    flxfn::commitTransaction(0);
    flxfn::endProgressBar('Subdepositos', $TOTALREG, $errMensaje);
    $data['mensaje']['Subdepositos'] = $RegistrosSincronizados;
    if($errMensaje > 0 && $GLOBALS['mododebug'] == 1)
    $data['mensaje']['Subdepositos con Error'] = $errMensaje;
}

//End 
/* ----> FIN - INTEGRACION DE CARACTERISTICAS (SUBDEPOSITOS) */


//Seteamo el id shop por default
Shop::setContext(Shop::CONTEXT_SHOP,$ID_SHOP);
/* ----> INICIO - CREACION DE LISTAS DE PRECIO */
if($MS_MAYORISTA)
{
    $RegistrosSincronizados = 0;$errMensaje = 0;
    flxfn::startTransaction();
    $listas = array('4' => 'Lista 01','5' => 'Lista 02','6' => 'Lista 03','7' => 'Lista 04','8' => 'Lista 05');

    foreach($listas as $idLista => $lista)
    {
        $groupList[$idLista] = $idLista;

        if(Group::searchByName($lista) != '')
        $id_group = $idLista;
          else
        $id_group = 'NULL';
        
        $listaERP = new Group($id_group);
        $listaERP->reduction = '0.00';
        $listaERP->price_display_method = (int)1;
        $listaERP->name = array($ID_LANG => Tools::replaceAccentedChars($lista));

        if($id_group == 'NULL')
           $listaERP->add();
          

        $RegistrosSincronizados = $RegistrosSincronizados + 1;
    }//foreach
    $msj = "<div class = 'line-result'>Listas Creadas <span class='count-badge'>".$RegistrosSincronizados."</span></div>";
    flxfn::commitTransaction(0);
}
/* ----> FIN - CREACION DE LISTAS DE PRECIO */

/* ----> INICIO - Integracion de PRODUCTOS Y TALLES */
    $RegistrosSincronizados = 0;$errMensaje = 0;
	Shop::setContext(Shop::CONTEXT_ALL);
    //Traigo todas los id Shop
    $id_shops = (Shop::isFeatureActive() ? Shop::getContextListShopID() : '');
    flxfn::startTransaction();
    $sql_Productos ="SELECT * FROM MTO_PRODUCTOS('".$MS_DEPOSITOS."','".flxfn::ultimaFechaSincro('MTO_PRODUCTOS', 0, false)."','".$MS_LISTAPRECIO."') ORDER BY ESPACK";
    $result_Productos = $ibase->query($sql_Productos);
    $TOTALREG = flxfn::initProgressBar('Productos', $sql_Productos, $position, (int)$mododebug, $ibase);
       
    try{
        $nombreDesc = Parametros::get('MS_DESCCOMONOMBRE_'.$ID_SHOP);
        $descLarga = (int)Parametros::get('MS_DESCRIPCIONLARGA');
        $descCorta = (int)Parametros::get('MS_DESCRIPCIONCORTA');
        $shortDescLimit = (int)Configuration::get('PS_PRODUCT_SHORT_DESC_LIMIT');

        if($result_Productos === false)
            throw new Exception('MTO_PRODUCTOS');

        while ($row = $ibase->fetch_object($result_Productos))
        {
            $tiempo_inicio = microtime(true);

            flxfn::updateProgressBar($position, $TOTALREG);

            $id_categoria = flxfn::equivalenciaID($row->ID_CATEGORIA,'flx_c2c');
            if( ($id_categoria == 'NULL') && !empty($MS_CATEGORIASASOC) )
              continue;

            $id_producto = flxfn::equivalenciaID($row->ID_ARTICULO,'flx_p2p');
            if($id_producto == 'NULL' && (int)$row->ACTIVO == 0)
              continue;
            
            $id_marca = flxfn::equivalenciaID($row->ID_MARCA,'flx_m2m');

            if($MS_FAMILIA){
                $id_feature = flxfn::getFeatureID($ID_LANG, $MS_FAMILIA_NOMBRE);
                if($row->FAMILIA)
                $id_feature_value = flxfn::equivalenciaID($row->FAMILIA,'flx_fa2fa');
            }

            $producto = new Product($id_producto);

            $producto->id_manufacturer = (int)$id_marca;
                        
            $producto->id_category_default = (int)$id_categoria;

            $producto->id_tax_rules_group = (int)flxfn::impuestoIva((float)$row->COEFICIENTE);

            $nombre = ((int)$nombreDesc && $row->DESCRIPCIONCORTA != '' ? $row->DESCRIPCIONCORTA : $row->NOMBRE);

            if(($MS_NOMBREARTICULO && $id_producto != 'NULL') || $id_producto == 'NULL')
                $producto->name = array($ID_LANG => flxfn::getValidestrName($nombre,125,true) );

            if($descLarga && $row->DESCRIPCIONLARGA != '') {
                $note = new JoshRibakoff_Note;
                $rtf = utf8_encode(trim($row->DESCRIPCIONLARGA));
                $note->setRTF($rtf);
                $html = $note->formatHTML();

                $producto->description = array($ID_LANG => $html );
            }

            if($descCorta)
                $producto->description_short = array($ID_LANG => flxfn::getValidestrDescription($row->DESCRIPCIONCORTA, $shortDescLimit) );

		    $producto->link_rewrite = array($ID_LANG => Tools::link_rewrite(flxfn::getLinkRewrite($row->NOMBRE)) );
            $producto->reference = pSQL(flxfn::getValidestrName($row->CODIGO_PRODUCTO));
            $producto->price = flxfn::truncateNumberDecimals((float)$row->PRECIO,6);
            $producto->show_price = true;

            if(isset($row->CODIGO_BARRA))
            $producto->ean13 = $row->CODIGO_BARRA;
            
            //Impuesto Interno
            $impInterno = flxfn::getPrecioII($row->PRECIO,$row->MONTOII,$row->PORCENTAJEII);
            $producto->ecotax = $impInterno;

            $producto_Modify[] = "'".$row->ID_ARTICULO."'"; 

            $producto->on_sale = ((float)$row->PRECIOPROMOCION > 0.00 ? 1 : 0);

            // Creamos un precio específico con el precio promocional. Este precio estará disponible en el home para los usuarios NO logueados
            // MAYORISTAS y minoristas. En caso de ser mayorista, al loguarse va a ver el precio promocional en SU lista de precios
            $id_lista = flxfn::getIdPricePromotion($id_producto);
            if ($id_lista != 'NULL' && $id_lista > 0 && $row->FECHADESDE == '1900-01-01 00:00:00' && $row->FECHAHASTA == '1900-01-01 00:00:00') {
                $specific_price = new SpecificPrice($id_lista);
                $specific_price->delete();
            }
            elseif((int)$row->PRECIOPROMOCION > 0) { // Si no es mayorista lo está creado en la sincro de Listas
                $specific_price = new SpecificPrice($id_lista);
                $specific_price->id_specific_price_rule = 0;
                $specific_price->id_product = (int)$producto->id;
                $specific_price->id_product_attribute = 0;
                $specific_price->id_customer = 0;
                $specific_price->id_shop = $ID_SHOP;
                $specific_price->id_country = 0;
                $specific_price->id_currency = 0;
                $specific_price->id_group = (int)0;
                $specific_price->from_quantity = 1;
                $specific_price->price = -1;
                //$specific_price->price = (float)number_format($lista->PRECIO,2,',');
                $coeficiente = 1 + ($TaxList[$producto->id_tax_rules_group] / 100);
                $specific_price->reduction = ( (float)round($row->PRECIO,4) - (float)round($row->PRECIOPROMOCION,4) ) * $coeficiente;

                $specific_price->reduction_tax = 1;
                $specific_price->reduction_type = 'amount';
                $specific_price->from = date("Y-m-d", strtotime($row->FECHADESDE));
                $specific_price->to = date("Y-m-d", strtotime($row->FECHAHASTA));

                //echo $id_lista;
                if($id_lista == 'NULL' && $specific_price->id_product != 0)
                {
                    $specific_price->add();
                    //$attribute->update();
                    //Guardamos la equivalencia con flexxus
                    if($specific_price->id > 0)
                    Db::getInstance()->insert('flx_listas', array(
                        'ID_ERP' => $row->ID_ARTICULO,
                        'id_prestashop' => $specific_price->id,
                        'id_producto' => $producto->id,
                        'id_shop' => $ID_SHOP,
                    ));
                }elseif($id_lista != 'NULL'){
                    $specific_price->update();
                    //echo 'actualizo';
                }
            }
            /* ----> FIN - Precio promocion */



            $producto->wholesale_price = flxfn::truncateNumberDecimals((float)$row->PRECIO,6);
            
            if($row->PESO)
            $producto->weight = (float)$row->PESO;
            
            //$producto->quantity = 1;

            if(isset($row->CANTIDAD_MINIMA))
            $producto->minimal_quantity = (int)$row->CANTIDAD_MINIMA;

            $stockTotal = (int)$row->CANTIDAD;

            if($id_producto == 'NULL')
            $producto->date_add = date('Y-m-d');

            $producto->date_upd = date('Y-m-d h:i:s');

            if($MS_ACTIVACIONARTICULOS){
            
                $producto->active = (int)$row->ACTIVO;
            }
            
            if($id_producto == 'NULL'){
                $producto->active = 0;
            }



            $Activo = ($row->ACTIVO == 1 || $row->ESPACK == 2 ? 1 : 0 );

            $producto->associations = array(0,0,2,3,2,0);

			/*
            * Note: Agregamos los diferentes articulos item pertenecientes a Conjunto.
            **/
            if((int)$row->ESPACK)
                $producto->cache_is_pack = 1;                

            if ($id_producto == 'NULL' && $Activo) {
                try{
                    $producto->available_for_order = 1;
                    if(!empty($id_shops))
                        $producto->associateTo($id_shops);
                        if ($producto->add()) {
                        if ($producto->id > 0)
                            Db::getInstance()->insert('flx_p2p', array(
                                'ID_ERP' => $row->ID_ARTICULO,
                                'id_prestashop' => $producto->id,
                                'reference' => $producto->reference,
                                'muestraweb' => (int)$row->ACTIVO,
                                'impuestointerno' => $row->MONTOII,
                                'porcentajeinterno' => $row->PORCENTAJEII,
                            ));

                            if($MS_FAMILIA){
                                if($id_feature != NULL && $id_feature_value != NULL){
                                //echo "<br>add producto y feature: ".$producto->id;
                                Product::addFeatureProductImport($producto->id, $id_feature, $id_feature_value);

                                //echo "<br>add producto y feature: ".$id_producto;
                                }
                            }
                        }
                }catch(Exception $e){
                    $message = $e->getMessage().' Linea error ('.$e->getLine().')';
                    flxfn::addLog('Agregar Producto - ID:'.$producto->id, $message);
                    $errMensaje = $errMensaje + 1;
                    continue;
                }
            }elseif($id_producto != 'NULL'){
                try{
                    if(!empty($id_shops))
                        $producto->associateTo($id_shops);

                        if($MS_FAMILIA){
                            if($id_feature != NULL && $id_feature_value != NULL){
                            //echo "<br>update producto y feature: ".$id_producto;
                            Product::addFeatureProductImport($id_producto, $id_feature, $id_feature_value);
                            }
                        }

                        $producto->updateMto();
                        //echo $row->CODIGO_PRODUCTO.' '.(int)$row->ACTIVO.'|';
                        if($producto->id > 0)
                            Db::getInstance()->update('flx_p2p', array(
                                'reference' => $producto->reference,
                                'muestraweb' => (int)$row->ACTIVO,
                                'impuestointerno' => $row->MONTOII,
                                'porcentajeinterno' => $row->PORCENTAJEII,
                            ),'ID_ERP = '.$row->ID_ARTICULO);
                }catch(Exception $e){
                    $message = $e->getMessage().' Linea error ('.$e->getLine().')';
                    flxfn::addLog('Actualizar Producto', $message);
                    $errMensaje = $errMensaje + 1;
                    continue;
                }
            }

            if(($MS_CATEGORIASASOCIADAS && $id_producto != 'NULL') || $id_producto == 'NULL')
            {
                $padres = flxfn::categoriaPadre($id_categoria);
                sort($padres);
                
                if (($key = array_search(1, $padres)) !== false) {
                    unset($padres[$key]);
                }

                if((int)$row->DESTACADOWEB != 1)
                    if (($key = array_search(2, $padres)) !== false) {
                        unset($padres[$key]);
                    }

                $producto->deleteCategories();

                while(list($k,$v) = each($padres)){
                    $producto->addToCategories(array($v));
                }
            }

            // if($mododebug == 1)
            //     echo "ID PRODUCTO:".(int)$id_producto." Tiempo empleado: " . (microtime(true) - $tiempo_inicio)."<br/>";

            /* ----> INICIO - INTEGRACION DE PRODUCTOS POR TALLES */       
            if($MS_TALLES)
            {
                $sql_productostalles = "SELECT * FROM MTO_PRODUCTOSTALLES('".$MS_DEPOSITOS."','".$MS_LISTAPRECIO."','".$row->ID_ARTICULO."')";
                $result_productostalles = $ibase->query($sql_productostalles);
                $count = 1;
                //Obtuvimos todas las combinaciones
                $combinationAll =  flxfn::getAllCombination($row->ID_ARTICULO);
                while ($talle = $ibase->fetch_object($result_productostalles))
                {
                    if(empty($talle->TALLE))
                        continue;

                    $id_talle = flxfn::equivalenciaID($talle->TALLE,'flx_a2a');
                  
                    $id_producto_combination = flxfn::equivalenciaID($row->ID_ARTICULO.$talle->TALLE,'flx_pa2pa');

                    if($id_producto_combination != 'NULL')
                        $combinationAll[$id_producto_combination] = true;

                    $combination = new Combination($id_producto_combination);
                    $combination->id_product = (int)$producto->id;
                    $price = ($talle->PRECIO == null ? 0 : (float)round($talle->PRECIO,2));
                    $combination->price = $price;
                    $combination->quantity = (int)$talle->STOCKACTUAL;
                    //$combination->weight = (float)0.00;
                    $combination->unit_price_impact = 1;
                    $combination->minimal_quantity = 1;
                    
                    if($count == 1) {
                        $producto->deleteDefaultAttributes();
                        $producto->setDefaultAttribute($id_producto_combination);
                    }

                    $count++;
                    if((int)$row->ACTIVO){
                        $producto->price=0;
                        $producto->updateMto();
                    }

                  if($id_producto_combination == 'NULL') {
                    try{                        
                        $combination->add();                       
                        $ids_attribute = array($id_talle);
                        $combination->setAttributes($ids_attribute);
                        Db::getInstance()->insert('flx_pa2pa', array(
                            'ID_ERP' => $talle->CODIGOARTICULO.$talle->TALLE,
                            'id_prestashop' => $combination->id,
                        ));           
                    }catch(Exception $e){
                        Db::getInstance()->execute('INSERT INTO '._DB_PREFIX_.'flx_log_error
                                                VALUES("","Agregar Talle","ID Precio:'.$combination->id.' | '.$e->getMessage().' Linea error ('.$e->getLine().')'.'",
                                                "Pendiente","'.$_SERVER['SERVER_ADDR'].'","'.date('Y-m-d H:m:s').'");');
                        //$errMensaje = $errMensaje + 1;
                        continue;
                    }
                    
                  }else{
                      try{
                        $combination->update();
                      }catch(Exception $e){
                            Db::getInstance()->execute('INSERT INTO '._DB_PREFIX_.'flx_log_error
                                                    VALUES("","Actualizar Talle","ID:'.$combination->id.' | '.$e->getMessage().' Linea error ('.$e->getLine().')'.'",
                                                    "Pendiente","'.$_SERVER['SERVER_ADDR'].'","'.date('Y-m-d H:m:s').'");');
                            //$errMensaje = $errMensaje + 1;
                            continue;
                        }
                  }
               }//end while
               $ibase->free_result($result_productostalles);

               /*
               * Borramos Talles que ya no existen
               **/
               foreach($combinationAll as $comb => $c)
               {
                   if($c == false)
                   {                       
                       $combination = new Combination($comb);
                        try{
                            $combination->delete();
                        }catch(Exception $e){
                            Db::getInstance()->execute('INSERT INTO '._DB_PREFIX_.'flx_log_error
                                                    VALUES("","Delete Talle","ID:'.$combination->id.' | '.$e->getMessage().' Linea error ('.$e->getLine().')'.'",
                                                    "Pendiente","'.$_SERVER['SERVER_ADDR'].'","'.date('Y-m-d H:m:s').'");');
                            continue;
                        }
                   }
               }//end foreach
                

            }//emd GLOBAL PARAMETRO TALLE
            /* ----> FIN - INTEGRACION DE PRODUCTOS POR TALLE */
            
            /*
            * Note: Agregamos los diferentes articulos item pertenecientes a Conjunto.
            **/
            if((int)$row->ESPACK == 1 && $id_producto > 0)
            {
                Pack::deleteItems($producto->id);
                $producto->cache_is_pack = 1;             
                $producto->setWsType('pack');
                $sql_pack ="SELECT * FROM MTO_PACKS('".$row->ID_ARTICULO."')";
                $result_Pack = $ibase->query($sql_pack);
                while ($pack = $ibase->fetch_object($result_Pack))
                {
                    $id_pack = flxfn::equivalenciaID($pack->ID_ARTICULO_HIJO,'flx_p2p');
                    if($id_pack == 'NULL')
                        continue;
                    Product::setPackStockType($id_producto, 1);
                    Pack::addItem($producto->id, $id_pack, $pack->CANTIDAD);
                    $stockEpack = 1;                
                    StockAvailable::setQuantity($id_producto, 0, $stockEpack);
                }
                
            }
           
            $RegistrosSincronizados = $RegistrosSincronizados + 1;
          
        }//EndWhile
        $ibase->free_result($result_Productos);

    }catch(Exception $e){
        Db::getInstance()->execute('INSERT INTO '._DB_PREFIX_.'flx_log_error VALUES("","Sincronizacion","'.$e->getMessage().' Linea error ('.$e->getLine().')'.'","Pendiente","'.$_SERVER['SERVER_ADDR'].'","'.date('Y-m-d H:m:s').'");');

        $content = '<h2> Datos de Cliente</h2><br/>';
        $content.= 'Servidor: '._DB_SERVER_.'<br/>';
        $content.= 'Servidor: '._DB_NAME_.'<br/>';
        $content.= 'Error'.$e->getMessage().' Linea error ('.$e->getLine().')';

        flxfn::sendMail('Error Sincronizacion: Productos',$content);
    }

    $msj = "<div class='line-result'><span class='count-badge'>".$RegistrosSincronizados."</span> PRODUCTOS</div>";
    if($RegistrosSincronizados == 0 || $GLOBALS['mododebug'] == 0)
        $msj=0;

    if($errMensaje == 0)
        flxfn::updateFechaUltimaSincro('MTO_PRODUCTOS', $fechaInicioSincro,0);

flxfn::commitTransaction($msj);
flxfn::endProgressBar('productos', $TOTALREG, $errMensaje);
flxfn::cleanCache();
$data['mensaje']['Productos'] = $RegistrosSincronizados;
if($errMensaje > 0 && $GLOBALS['mododebug'] == 1)
$data['mensaje']['Productos con Error'] = $errMensaje;




//Seteamo el id shop por default
Shop::setContext(Shop::CONTEXT_SHOP,$ID_SHOP);

/* ----> INICIO - INTEGRACION DE LISTAS & Precios Promocion */
if($MS_MAYORISTA){

    $params = array('fechamodificacion' => flxfn::ultimaFechaSincro('MTO_LISTASACTIVAS', $ID_SHOP), 'fechaInicioSincro' => $fechaInicioSincro, 'id_shop' => $ID_SHOP, 'mayorista' => (int)$MS_MAYORISTA, 'depositos' => $MS_DEPOSITOS, 'listaprecio' => $MS_LISTAPRECIO);
    //var_dump($params);
    $message = $sincro->sincroListasProducto($params);
    $data['mensaje']['Listas'] = $message['success'];
    if($message['error'] > 0 && $GLOBALS['mododebug'] == 1)
        $data['mensaje']['Listas con Error'] = $message['error'];

  
}

/* ----> FIN - Integracion de PRODUCTOS */



/* ----> INICIO - Verificamos Descuento por Producto  */
if(!empty($producto_Modify) && $MS_MAYORISTA)
{
    $sql_clientes = "SELECT * FROM MTO_CLIENTES('1900-01-01') Where (PermitePromociones = 1 and FechaModificacion < '".flxfn::ultimaFechaSincro('MTO_CLIENTES',$ID_SHOP)."') or (FechaModificacion < '".flxfn::ultimaFechaSincro('MTO_CLIENTES',$ID_SHOP)."')";
    $result = $ibase->query($sql_clientes);
    $TOTALREG = flxfn::initProgressBar('Promociones', $sql_clientes, $position, (int)$mododebug, $ibase);
    $newArray = array_chunk($producto_Modify, 1000);
    $where = '';
    $where .= " AND (";
    foreach($newArray as $index => $productos)
    {
        $where .= " CODIGOARTICULO IN (".implode(',',$productos).") or ";
    }
    $where = substr($where, 0, -3);
    $where .= ")";

    while($row = $ibase->fetch_object($result))
    {
        flxfn::updateProgressBar($position, $TOTALREG);
        $id_cliente = flxfn::equivalenciaID($row->CODIGOPARTICULAR,'flx_cliente');

        if($id_cliente == 'NULL' && (int)$row->ACTIVO == 0)
          continue;        
        if((int)$descount) {
            $sincro->DescuentoCliente($row->CODIGOPARTICULAR, $id_cliente, $where);
        }
    }
}
/* ----> FIN - Verificamos Descuento por Producto */

/* ----> INICIO - Integración de STOCK  */
$RegistrosSincronizados = 0;$errMensaje = 0;

$sql_Stock = flxfn::getSQLstock((int)$GLOBALS['USALOTE'],flxfn::ultimaFechaSincro('MTO_STOCK',$ID_SHOP),$MS_DEPOSITOS);

$TOTALREG = flxfn::initProgressBar('Stock', $sql_Stock, $position, (int)$mododebug, $ibase);

$result_stock = $ibase->query($sql_Stock);
$stock='';$id_producto = '';
    try{

        if($result_stock === false){
            throw new Exception('MTO_STOCK');
        }

        while ($row = $ibase->fetch_object($result_stock)):

            flxfn::updateProgressBar($position, $TOTALREG);

            $id_producto = flxfn::equivalenciaID($row->ID_ARTICULO,'flx_p2p');

            if($id_producto == 'NULL')
              continue;

            $id_producto_combination = ((int)$GLOBALS['USATALLE'] || $TIPOFORM == 3 ? flxfn::equivalenciaID($row->ID_ARTICULO.$row->TALLE,'flx_pa2pa') : 0 );

            $stockRemanente = ($FACTURASINREMITIR ? $row->STOCKREMANENTE - $row->STOCKFACTSINREMITIR : $row->STOCKREMANENTE );
            $stock = ($MS_STOCKREAL ? $row->STOCKREAL : $stockRemanente );
            $stock = round($stock,4);

            try{
                StockAvailable::setQuantity($id_producto, $id_producto_combination, Tools::ceilf($stock));
                $RegistrosSincronizados++;
            }catch(Exception $e){
                $message = $e->getMessage().' Linea error ('.$e->getLine().')';
                flxfn::addLog('Actualizar Stock', $message);
                $errMensaje = $errMensaje + 1;
                continue;
            }

            //Si el stock es positivo lo volvemos a reindexar al buscador.
            //Search::indexation(false,$producto->id);
            //StockAvailable::setQuantity($id_producto, $id_producto_combination,$stock);
        endwhile;

        if(Parametros::get('MS_STOCKCERO'))
        {
          //Desactivo todos los productos con stock menos cero
      		flxfn::updateActive($ID_SHOP,0);
        }
        //Activo todos los productos con stock mayor a cero
        if($MS_ACTIVACIONARTICULOS)
        flxfn::updateActive($ID_SHOP,1,$MS_ACTIVACIONIMAGEN);

        if($errMensaje == 0)
        flxfn::updateFechaUltimaSincro('MTO_STOCK', $fechaInicioSincro,$ID_SHOP);

    }catch(Exception $e){
        $message = $e->getMessage().' Linea error ('.$e->getLine().')';
        flxfn::addLog('Actualizar STOCK', $message);
    }

    $ibase->free_result($result_stock);

$msj = "<div class='line-result'><span class='count-badge'>".$RegistrosSincronizados."</span> STOCKS</div>";
if($RegistrosSincronizados == 0 || $GLOBALS['mododebug'] == 0)
    $msj=0;
flxfn::commitTransaction($msj);
flxfn::endProgressBar('stock', $TOTALREG, $errMensaje);
/* ----> FIN - Integracion de STOCK */

/* ----> INICIO - Integracion de TARJETAS */
if((int)Parametros::get('MS_SINCROTARJETAS'))
{
    $RegistrosSincronizados = 0;$errMensaje = 0;
    flxfn::startTransaction();
    $sql_tarjetas = "SELECT * FROM MTO_TARJETAS ".flxfn::ultimaFechaSincro('MTO_TARJETAS',0, true);
    $TOTALREG = flxfn::initProgressBar('Tarjetas', $sql_tarjetas, $position, (int)$mododebug, $ibase);

    $result_tarjetas = $ibase->query($sql_tarjetas);
    if($result_tarjetas === false){
        throw new Exception('MTO_TARJETAS');
    }

    while ($row = $ibase->fetch_object($result_tarjetas))
    {
        flxfn::updateProgressBar($position, $TOTALREG);

        Db::getInstance()->insert('flx_tarjetas', array(
            'codigotarjeta' => $row->CODIGOTARJETA,
            'descripcion' => $row->DESCRIPCION,
            'activo' => (int)$row->ACTIVO,
            'numerocomercio' => (int)$row->NUMEROCOMERCIO,
            'paymentCod' => 0,
            'date_add' => date('Y-m-d H:m:s'),
        ));
        /* ----> INICIO - Integración de PLANES DE TARJETAS */
        $sql_planes = "SELECT * FROM MTO_PLANESTARJETAS WHERE CODIGOTARJETA = ".$row->CODIGOTARJETA;
        $result_planes = $ibase->query($sql_planes);
//        if($result_planes === false){
//            throw new Exception('MTO_PLANESTARJETAS');
//        }
        while ($planes = $ibase->fetch_object($result_planes))
        {
            //insertamos o actualizamos en PS_PLANESTARJETAS
            Db::getInstance()->insert('flx_planes_tarjetas', array(
                'codigotarjeta' => $row->CODIGOTARJETA,
                'tarjeta' => $row->TARJETA,
                'planestarjeta' => $row->PLANTARJETA,
                'cantidadcuotas' => (int)$row->CANTIDADCUOTAS,
                'coeficiente' => (float)$row->COEFICIENTE,
                'coeficienteespecial' => (float)$row->COEFICIENTEESPECIAL,
                'coeficientecuota' => (float)$row->COEFICIENTECUOTA,
                'activo' => (int)$row->ACTIVO,
                'fechahoravigenciadesde' => $row->FECHAVIGENCIADESDE,
                'fechahoravigenciahasta' => $row->FECHAVIGENCIAHASTA,
            ));
        }//end While PS_PLANESTARJETAS
        $msj = "PLANES DE TARJETAS (".$RegistrosSincronizados.")";
        $ibase->free_result($result_planes);
        /* ----> FIN - Integracion de PLANES DE TARJETAS */
        $RegistrosSincronizados = $RegistrosSincronizados + 1;

    }//end while PS_TARJETAS
    $ibase->free_result($result_tarjetas);

    flxfn::updateFechaUltimaSincro('MTO_TARJETAS', $fechaInicioSincro,0);
    flxfn::endProgressBar('tarjetas', $TOTALREG, $TOTALREG-$RegistrosSincronizados);
    $msj = "TARJETAS (".$RegistrosSincronizados.")";
    flxfn::commitTransaction(0);
}
/* ----> FIN - Integracion de TARJETAS */

/* ----> INICIO - INTEGRACION DE REGLAS DE IMPUESTOS */
/*
* Metodo para sincronizar las condiciones de iva
* para poder usar las alicuotas segun la condicion
* comentado hasta realizar el desarrollo
*
* revisar metodo updateInsert
*
--> if($MS_MAYORISTA)
{
    flxfn::startTransaction();
    $sql_tax_rule ="SELECT * FROM MTO_TIPOSIVA";
    $result_tax_rule = $ibase->query($sql_tax_rule);
    if($result_tax_rule === false){
        throw new Exception('MTO_TIPOSIVA');
    }
    while ($row = $ibase->fetch_object($result_tax_rule)):
      flxfn::updateInsert($row->CODIGOTIPO,$row->DESCRIPCION,$row->IVA1,'flx_tiposiva');
    endwhile;
    $ibase->free_result($result_tax_rule);
    flxfn::commitTransaction(0);
}*/
/* ----> FIN - INTEGRACION DE REGLAS DE IMPUESTOS */

/* ----> INICIO - INTEGRACION DE EMPLEADOS */
if($MS_MAYORISTA)
{
    $RegistrosSincronizados = 0;$errMensaje = 0;
    flxfn::startTransaction();
        $sql_empleados ="SELECT * FROM MTO_USUARIOS";
        $result_usuarios = $ibase->query($sql_empleados);
        if($result_usuarios === false){
            throw new Exception('MTO_USUARIOS');
        }
        while ($row = $ibase->fetch_object($result_usuarios))
        {
            $id_empleado = flxfn::equivalenciaID(flxfn::getValidestrName($row->CODIGOUSUARIO),'flx_usuarios');
            //$id_profile = flxfn::equivalenciaID($row->CODIGOPERFIL,'flx_perfiles');

            $empleado = new Employee($id_empleado);

            if(Validate::isEmail($row->EMAIL))
            {

            $id_perfil = strtoupper($row->TIPOACCESOWEB);
            $empleado->id_profile = (int)flxfn::getProfile(trim($id_perfil), $ID_LANG);

            $empleado->id_lang = $ID_LANG;
            $NombreCompleto = str_word_count(trim($row->RAZONSOCIAL),1);

            $lastname = (count($NombreCompleto) > 1 ? $NombreCompleto[1] : '-');

            $empleado->lastname = pSQL(flxfn::getValidestrName($NombreCompleto[0]));
            $empleado->firstname = pSQL(flxfn::getValidestrName($lastname));

            $empleado->email = $row->EMAIL;

            if($id_empleado == 'NULL')
            $empleado->passwd = md5(_COOKIE_KEY_.'12345678');

            $empleado->active=1;
            $empleado->bo_theme='default';
            $empleado->default_tab=1;

              if($id_empleado == 'NULL'){
                  try{
                        $empleado->add();
                    }catch(Exception $e){
                        $message = $e->getMessage().' Linea error ('.$e->getLine().')';
                        flxfn::addLog('Agregar Empleado', $message);
                        $errMensaje = $errMensaje + 1;
                        continue;
                    }
                    
                    Db::getInstance()->insert('flx_usuarios', array(
                        'ID_ERP' => flxfn::getValidestrName($row->CODIGOUSUARIO),
                        'id_prestashop' => $empleado->id,
                    ));
                }elseif($id_empleado != 'NULL'){                    
                    try{
                        $empleado->update();
                    }catch(Exception $e){
                        $message = $e->getMessage().' Linea error ('.$e->getLine().')';
                        flxfn::addLog('Actualizar Empleado', $message);
                        $errMensaje = $errMensaje + 1;
                        continue;
                    }
                }

            $RegistrosSincronizados = $RegistrosSincronizados + 1;
            }//valido mail
        }//END WHILE
    $ibase->free_result($result_usuarios);
    $msj = "<div class = 'line-result'>Empleados <span class='count-badge'>".$RegistrosSincronizados."</span></div>";
    flxfn::commitTransaction(0);
}
/* ----> FIN - INTEGRACION DE EMPLEADOS */

/* ----> INICIO - INTEGRACION DE CLIENTES */
$RegistrosSincronizados = 0;$errMensaje = 0;
flxfn::startTransaction();
if($MS_MAYORISTA)
{
    $fechaSincro = flxfn::ultimaFechaSincro('MTO_CLIENTES',$ID_SHOP);
    $sql_clientes ="SELECT * FROM MTO_CLIENTES('".$fechaSincro."') WHERE EMAIL <> '' or EMAIL <> '-'";

    $TOTALREG = flxfn::initProgressBar('Clientes ->desde ERP hacia Prestashop', $sql_clientes, $position, (int)$mododebug, $ibase);
    $registrosSalteados = 0;
    $result_clientes = $ibase->query($sql_clientes);
    try{
        if($result_clientes === false)
          throw new Exception('MTO_CLIENTES');

    while($row = $ibase->fetch_object($result_clientes))
    {
        flxfn::updateProgressBar($position, $TOTALREG);
        $email = explode(",",$row->EMAIL);

        $clienteMayorista = (int)$row->PEDIDOSWEB;

        if(Validate::isEmail($email[0]))
        {
            $id_cliente = flxfn::equivalenciaID($row->CODIGOPARTICULAR,'flx_cliente');
            if($id_cliente == 'NULL' && (int)$row->ACTIVO == 0) {
                $registrosSalteados = $registrosSalteados + 1;
                continue;
            }

            if ($fechaSincro != '1900-01-01')
                $clientes_Modify[] = $row->CODIGOPARTICULAR;

            $id_clienteERP = $row->CODIGOPARTICULAR;

            $NombreCompleto = str_word_count(trim($row->RAZONSOCIAL),1);
        
            $cliente = new Customer($id_cliente);
            $cliente->id_gender = 1;

            $cliente->firstname = PSQL(flxfn::getValidestrName($NombreCompleto[0]));
            $lastname = (count($NombreCompleto) > 1 ? $NombreCompleto[1] : '-');
            $cliente->lastname = pSQL(flxfn::getValidestrName($lastname));
            $cliente->id_shop = (int)$ID_SHOP;
            $cliente->email = pSQL(flxfn::letraCapital($email[0]));
            /*
            * Cuando la condicion es mayorista el cliete usa con clave
            * de logueo su cuit dado de alta en flexxus, de lo contrario
            * recibe una clave generica
            **/
            if ($id_cliente == 'NULL') {
                $cuit = trim(str_replace('-','',$row->CUIT));
                if($cuit == '' )
                    $cliente->passwd = Tools::encrypt('12345678');
                else
                    $cliente->passwd = Tools::encrypt($cuit);
            }

            $cliente->website = pSQL('');

            //if($cliente->id_shop != 1)
               $cliente->id_default_group = flxfn::listaDefault($row->LISTA);

            $cliente->date_upd = date('Y-m-d');

           //original: $list = array('1', '2', '3', flxfn::listaDefault($row->LISTA));
            $listMay = array(flxfn::listaDefault($row->LISTA));
            $list = array('1', '2', '3', flxfn::listaDefault($row->LISTA));

            if($clienteMayorista == 1){

                if($id_cliente == 'NULL'){
                        $cliente->active = 1;
                        try{
                            $cliente->add();
                        }catch(Exception $e){
                            $message = $e->getMessage().' Linea error ('.$e->getLine().')';
                            flxfn::addLog('Agregar Cliente', $message);
                            $errMensaje = $errMensaje + 1;
                            continue;
                        }
                    
                        //original: $cliente->addGroups(array('1','2','3'));
                        $cliente->addGroups(flxfn::listaDefault($row->LISTA));
                        
                        //$cliente->cleanGroups();
                        //$cliente->addGroups(array(flxfn::listaDefault($row->LISTA)));
                } elseif($id_cliente != 'NULL') {
                        //original: quitar prox linea
                        //$cliente->cleanGroups();
                        //original: quitar linea anterior

                        $cliente->active = (int)$row->ACTIVO;
                        
                        /*if ($cliente->getGroups()==flxfn::listaDefault($row->LISTA))
                        {   
                            $list = array($cliente->getGroups(), flxfn::listaDefault($row->LISTA));
                            
                        }????*/
                        
                        $cliente->updateGroup($listMay);
                 
                        
                        try{
                            $cliente->update();
                        }catch(Exception $e){
                            $message = $e->getMessage().' Linea error ('.$e->getLine().')';
                            flxfn::addLog('Actualizar Cliente', $message);
                            $errMensaje = $errMensaje + 1;
                            continue;
                        }
                    }
            }else{

                    $cliente->updateGroup($list);
                    try{
                            $cliente->update();
                        }catch(Exception $e){
                            $message = $e->getMessage().' Linea error ('.$e->getLine().') Error al actualizar Grupos del Cliente minorista. ID del cliente '.$id_cliente;
                            flxfn::addLog('Actualizar Cliente', $message);
                            $errMensaje = $errMensaje + 1;
                            continue;
                        }

            }

        /* Agrega direcion de los clientes */
        if($cliente->id > 0) {
            $direcciones = array('codprovincia' => $row->CODIGOPROVINCIA,
                'id_cliente' => $cliente->id,
                'firstname' => trim($NombreCompleto[0]),
                'lastname' => trim($lastname),
                'cp' => trim($row->CP),
                'direccion' => trim($row->DIRECCION),
                'localidad' => trim($row->LOCALIDAD),
                'telefono' => trim($row->TELEFONO),
                'dni' => 00000000,
            );

            flxfn::addAddress($direcciones);
        }

        //Insertamos la equivalencia.
        if($id_cliente == 'NULL'){
            Db::getInstance()->insert('flx_cliente', array(
                  'ID_ERP' => $id_clienteERP,
                  'id_prestashop' => $cliente->id,
                  'CODIGOIVA' => $row->CONDICIONIVA,
                  'CODVENDEDOR' => $row->CODIGOVENDEDOR
              ));
         }elseif($id_cliente != 'NULL' && $cliente->id > 0){
             Db::getInstance()->update('flx_cliente', array(
                  'CODIGOIVA' => $row->CONDICIONIVA,
                  'CODVENDEDOR' => $row->CODIGOVENDEDOR
              ),'ID_ERP="'.$id_clienteERP.'"');
        }//INSERTAR EQUIVALENCIAS

        /* ----> INICIO - Integración de Bonificacion */
            $params = array('id_customer' => $cliente->id, 'restriction' => false);
            $id =  flxfn::getIdBonification($params);
            $cartRule = new CartRule($id);
            $cartRule->id_customer = $cliente->id;        
            $cartRule->name = array($ID_LANG => 'Descuento General '.$row->BONIFICACION.'% - '.$row->RAZONSOCIAL);
            $cartRule->description = 'Sincronizado el '.date('d-m-Y H:i:s');
            $cartRule->quantity = 1000000000;
            $cartRule->quantity_per_user = 1000000000;
            $cartRule->date_from = date('Y-m-d');
            $cartRule->date_to  = date('Y-m-d', strtotime('2020-01-01'));
            $cartRule->reduction_percent = (float)$row->BONIFICACION;
            if((float)$row->BONIFICACION > 0.00)
            {
                if($id == 0)
                {
                    $cartRule->date_add = date('Y-m-d');
                    try{
                        $cartRule->add();
                    }catch(Exception $e){
                        $message = $e->getMessage().' Linea error ('.$e->getLine().')';
                        flxfn::addLog('Agregar Bonificacion', $message);
                        $errMensaje = $errMensaje + 1;
                        continue;
                    }
                }else{
                    $cartRule->date_upd = date('Y-m-d');                   
                    try{
                        $cartRule->update();
                    }catch(Exception $e){
                        $message = $e->getMessage().' Linea error ('.$e->getLine().')';
                        flxfn::addLog('Actualizar Bonificacion', $message);
                        $errMensaje = $errMensaje + 1;
                        continue;
                    }
                }

            }elseif((int)$row->BONIFICACION == 0 && $id != 0){            
                $cartRule->delete();           
            }
        /* ----> FIN - Integración de Bonificacion */

        /* ----> INICIO - Integración de DESCUENTOS X ARTICULOS, MARCAS, CATEGORIAS */
        if($mododebug == 2)
        {
            if((int)$descount)
            {
                    $errMensaje += $sincro->DescuentoCliente($id_clienteERP, $cliente->id);
            }
        }
        /* ----> FIN - Integracion de DESCUENTOS */
        $RegistrosSincronizados = $RegistrosSincronizados + 1;
      }else{
        $registrosSalteados = $registrosSalteados + 1;
      }
    }//EndWhile
    $ibase->free_result($result_clientes);

    flxfn::updateFechaUltimaSincro('MTO_CLIENTES', $fechaInicioSincro,$ID_SHOP);

    }catch(Exception $e){
          Db::getInstance()->execute('INSERT INTO '._DB_PREFIX_.'flx_log_error VALUES("","Sincronizacion","'.$e->getMessage().' Linea error ('.$e->getLine().')'.'","Pendiente","'.$_SERVER['SERVER_ADDR'].'","'.date('Y-m-d H:m:s').'");');

            $content = '<h2> Datos de Cliente</h2><br/>';
            $content.= 'Servidor: '._DB_SERVER_.'<br/>';
            $content.= 'Servidor: '._DB_NAME_.'<br/>';
            $content.= 'Error'.$e->getMessage().' Linea error ('.$e->getLine().')';

            flxfn::sendMail('Error Sincronizacion: Clientes',$content);
    }
    flxfn::endProgressBar('clientes', $TOTALREG, $TOTALREG-$RegistrosSincronizados-$registrosSalteados);
}//END TIENDAMAYORISTA

  $clientes = flxfn::getCustomers(flxfn::ultimaFechaSincro('MTO_CUSTOMER',$ID_SHOP));
  //var_dump($clientes);
  $TOTALREG = flxfn::initProgressBar('Clientes ->desde Prestashop hacia ERP', '', $position, (int)$mododebug, $ibase, count($clientes));

  foreach($clientes as $cliente => $c)
  {
    flxfn::updateProgressBar($position, $TOTALREG);

            $id_provincia = flxfn::equivalenciaID($c['CODIGOPROVINCIA'],'flx_provincia',false);
            $condicionIva = (flxfn::equivalenciaCondicionIva($c['id_customer'])? flxfn::equivalenciaCondicionIva($c['id_customer']) : $MS_CONDICIONIVA);

            $razon_social = ($c['EMPRESA'] != '' && $c['CUIT'] != '' && $MS_MAYORISTA ? $c['EMPRESA'] : substr($c['razon_social'],0,50));
           
            $LOCSNA = ($MS_LOCSNA != '' ? explode(",",$MS_LOCSNA) : '');
            $CODPROVSNA = (is_array($LOCSNA) ? $LOCSNA[0] : null);
            $CODLOCSNA  = (is_array($LOCSNA) ? $LOCSNA[1] : null);
            $CUIT = str_replace('-', '', $c['CUIT']);
            $CUIT = substr($CUIT, 0, 2).'-'.substr($CUIT, 2, 8).'-'.substr($CUIT, -1);
            $CUIT = ($CUIT  == '--' ? '' : $CUIT);
            $params = array( 1 => array('Nombre' => 'RAZONSOCIAL','type' => 'CADENA50','value' => flxfn::escape($razon_social)),
                             2 => array('Nombre' => 'DIRECCION ','type' => 'VARCHAR15','value' => flxfn::escape($c['DIRECCION']) ),
                             3 => array('Nombre' => 'CODIGOPROVINCIA ','type' => 'VARCHAR15','value' => $id_provincia),
                             4 => array('Nombre' => 'LOCALIDAD ','type' => 'VARCHAR50','value' => flxfn::escape($c['LOCALIDAD']) ),
                             5 => array('Nombre' => 'BARRIO ','type' => 'VARCHAR200','value' => $MS_BARRIO),
                             6 => array('Nombre' => 'CODIGOZONA ','type' => 'VARCHAR15','value' => $MS_CODZONA),
                             7 => array('Nombre' => 'CODIGOUSUARIO ','type' => 'VARCHAR15','value' => $MS_USERERP),
                             8 => array('Nombre' => 'CODIGOCOBRADOR ','type' => 'VARCHAR15','value' => $MS_USERCOBRADOR),
                             9 => array('Nombre' => 'CODIGOACTIVIDAD ','type' => 'VARCHAR15','value' => $MS_CODACTIVIDAD),
                             10 => array('Nombre' => 'CODIGOLISTA ','type' => 'VARCHAR15','value' => $MS_LISTAPRECIO),
                             11 => array('Nombre' => 'CODIGOFORMADEPAGO ','type' => 'ENTERO','value' => $MS_CODFORMADEPAGO),
                             12 => array('Nombre' => 'CODPROVSNA ','type' => 'VARCHAR15','value' => $CODPROVSNA),
                             13 => array('Nombre' => 'CODLOCSNA ','type' => 'VARCHAR15','value' => $CODLOCSNA),
                             14 => array('Nombre' => 'TELEFONO ','type' => 'CADENA50','value' => $c['Telefono']),
                             15 => array('Nombre' => 'CELULAR ','type' => 'CADENA50','value' => $c['Celular']),
                             16 => array('Nombre' => 'CONDICIONIVA ','type' => 'CADENA15','value' => $condicionIva),
                             17 => array('Nombre' => 'DNI','type' => 'VARCHAR15','value' => substr($c['DNI'],0,8)),
                             18 => array('Nombre' => 'CUIT ','type' => 'varchar(13)','value' => $CUIT),
                             19 => array('Nombre' => 'COMENTARIOS ','type' => 'VARCHAR2000','value' => flxfn::escape($c['Comentarios']) ),
                             20 => array('Nombre' => 'EMAIL ','type' => 'VARCHAR100','value' => $c['Email']),
                             21 => array('Nombre' => 'CP ','type' => 'VARCHAR15','value' => $c['CP'])
                            );

            $SP_INSERTARCLIENTES = $ibase->exec_sp('MTO_INSERTARCLIENTES',$params,'varchar(50)','CODIGOPARTICULAR');
            if($GLOBALS['mododebug'] == 1)
                echo $SP_INSERTARCLIENTES;
            try{
              $result_clientes = $ibase->query($SP_INSERTARCLIENTES);
              $result_insertClientes = $ibase->fetch_object($result_clientes);
              if($result_insertClientes->CODIGOPARTICULAR != '') {
                 Db::getInstance()->insert('flx_cliente', array(
                  'ID_ERP' => $result_insertClientes->CODIGOPARTICULAR,
                  'id_prestashop' => $c['id_customer'],
                  'CODIGOIVA' => $MS_CONDICIONIVA,
                  'CODVENDEDOR' => $MS_USERERP
                 ));
                $RegistrosSincronizados = $RegistrosSincronizados + 1;
              }
            }catch(Exception $e){
               $message = $e->getMessage().' Linea error ('.$e->getLine().')';
               flxfn::addLog('Insertar Cliente', $message);
               $errMensaje = $errMensaje + 1;
               flxfn::sendMail('Error Sincronizacion: Insertar Cliente'._DB_NAME_,$content);
               continue;
            }
          
          $ibase->free_result($result_clientes);

  }//end Foreach

  flxfn::updateFechaUltimaSincro('MTO_CUSTOMER', $fechaInicioSincro,$ID_SHOP);
  flxfn::endProgressBar('Clientes -> MTO', $TOTALREG, $TOTALREG-$RegistrosSincronizados);
$msj = "<div class = 'line-result'><span class='count-badge'>".$RegistrosSincronizados."</span> CLIENTES</div>";
    if($RegistrosSincronizados == 0 || $GLOBALS['mododebug'] == 0)
    $msj=0;
flxfn::commitTransaction($msj);
$data['mensaje']['Clientes'] = $RegistrosSincronizados;
if($errMensaje > 0 && $GLOBALS['mododebug'] == 1)
$data['mensaje']['Clientes con Error'] = $errMensaje;
/* ----> FIN - INTEGRACION DE CLIENTES */

/*-----> INICIO - INTEGRACION DE FACTURAS */
if((int)Parametros::get('MS_FACTURA')) {
  $RegistrosSincronizados = 0; $errMensaje = 0;

  //MTO - Corrección: truncate sobre la tabla de cabezapedidos
        Db::getInstance()->execute('TRUNCATE TABLE mto_flx_cabezacomprobante');
        Db::getInstance()->execute('TRUNCATE TABLE mto_flx_comprobante');
    // fin corrección

    $sincro->sincroFacuras('1900.01.01', null);
    if(!empty($clientes_Modify)) {
        foreach ($clientes_Modify as $customer => $c) {
            $sincro->sincroFacuras('1900.01.01', $c);
        }
    }

  $msj = "<div class = 'line-result'><span class='count-badge'>".$RegistrosSincronizados."</span> FACTURAS</div>";
  if($RegistrosSincronizados == 0 || $GLOBAL['mododebug'] == 0)
  $msj=0;
  flxfn::commitTransaction($msj);
}//end parametro mayorista
/* ----> FIN - INTEGRACION DE FACTURAS */

/*-----> INICIO - INTEGRACION DE CIENTES Y PEDIDOS */
   $RegistrosSincronizados = 0;$errMensaje = 0;
   $lastDate = date('Y-m-d',strtotime(flxfn::ultimaFechaSincro('MTO_ORDERS',0)));
   $sql_orders = "SELECT * FROM MTO_ORDERS('".$MS_CODOPERACION."','".$lastDate."')";
   $TOTALREG = flxfn::initProgressBar('Estados de Pedido -> MTO', $sql_orders, $position, (int)$mododebug, $ibase);
   $result_orders = $ibase->query($sql_orders);
   while ($row = $ibase->fetch_object($result_orders)):
        flxfn::updateProgressBar($position, $TOTALREG);
        $id_order = flxfn::equivalenciaID($row->NUMEROPEDIDO,'flx_pedido',true,true,true);
        $id_order_state = flxfn::equivalenciaID($row->ESTADO,'flx_estados_np');
        $ListIdERP = flxfn::getListIdERP($id_order);

        if($id_order != 'NULL'):
          $order = new Order((int)$id_order);
            if((int)$id_order_state > 0 && $order->current_state != $id_order_state):
              $order->setCurrentState((int)$id_order_state);
              $order->setWsShippingNumber($row->NROCOMPROBANTE);
              if($row->ESTADO == 'ANULADA')
              {
                $where = " WHERE ID_ARTICULO in (".$ListIdERP.")";
                $sql_stock = flxfn::getSQLstock((int)$GLOBALS['USALOTE'],'02.01.1900',$MS_DEPOSITOS,$where);
                $result_stock = $ibase->query($sql_stock);
                  while ($row = $ibase->fetch_object($result_stock))
                  {
                    $id_producto = flxfn::equivalenciaID($row->ID_ARTICULO,'flx_p2p');

                    if($id_producto == 'NULL')
                      continue;

                    $id_producto_combination = ((int)$GLOBALS['USATALLE'] ? flxfn::equivalenciaID($row->ID_ARTICULO.$row->TALLE,'flx_pa2pa') : 0 );

            			  $stock = ($MS_STOCKREAL ? $row->STOCKREAL : ($row->STOCKREMANENTE - $row->STOCKFACTSINREMITIR) );

                    StockAvailable::setQuantity($id_producto, $id_producto_combination,Tools::ceilf($stock));

                  }
                $ibase->free_result($result_stock);
                if(Parametros::get('MS_STOCKCERO'))
              		  flxfn::updateActive($ID_SHOP,0);
                flxfn::updateActive($ID_SHOP,1,$MS_ACTIVACIONIMAGEN);
              }
            endif;
        endif;
   endwhile;
   $ibase->free_result($result_orders);

   flxfn::updateFechaUltimaSincro('MTO_ORDERS', $fechaInicioSincro,0);

  //traigo los pedidos de la tienda que no se sincronizaron
  $orders = flxfn::getLastOrder($MS_CODTRANSPORTISTA,$MS_LISTAPRECIO, $MS_CODOPERACION,$MS_ESTADOSPEDIDOS,$MS_MAYORISTA,$ID_SHOP);
  
  if($orders){
    
    foreach($orders as $pedidos => $pedido){

      $montoPedido =0;
        
      $tr = $ibase->trans();
      flxfn::startTransaction();

      try{
        $pedido['DESCUENTOGRAL'] = flxfn::getRuleDiscount($pedido['ordenTienda']);

        $SP_INICIALIZAR_PEDIDO = $ibase->exec_sp("MTO_INICIALIZAR_PEDIDO",'','varchar(50)','RESULTADO','Execute');
        $result_pedidos = $ibase->query($SP_INICIALIZAR_PEDIDO);
        if($result_pedidos === false){
            throw new Exception('MTO_INICIALIZAR_PEDIDO');
        }
        //nro de linea del pedido
        $linea = 1;
        //traigo el detalle de cada pedido
        $orders_details = flxfn::getOrderTalles($MS_TALLES, $pedido['ordenTienda']);
        foreach($orders_details as $order => $detail)
        {
            $TALLE = ($MS_TALLES == 1 ? $detail['Talle'] : '000');

            $listaPedido[$pedido['ordenTienda']] = flxfn::equivalenciaListaPedido($detail['CLIENTE']);

            if(flxfn::equivalenciaCondicionIva($detail['CLIENTE']))
              $CondicionIVA[$pedido['ordenTienda']] = flxfn::equivalenciaCondicionIva($detail['CLIENTE']);
            else
              $CondicionIVA[$pedido['ordenTienda']] = $MS_CONDICIONIVA;

            $montoiva1[$pedido['ordenTienda']] = $detail['MONTOIVA1'];
            
            $descuentoPedido = ($detail['Descuento']!=0 ? $detail['Descuento'] : $detail['DescuentoP']);
            
            $precioUnitario = ($detail['Descuento']!=0 || $detail['DescuentoM']!=0  ? $detail['PrecioUnitario'] : $detail['price_original']);

            $precioUnitario = ($detail['montoii']!=0 ? $precioUnitario - $detail['montoii']  : $detail['price_original']);

            $montoPedido += $precioUnitario * $detail['Cantidad'];
            
            //Ejecutamos al SP_CUERPOPEDIDOS
            $params = array( 1 => array('Nombre' => 'CODIGOPEDIDO','type' => 'CADENA50','value' => $pedido['ordenTienda']),
                             2 => array('Nombre' => 'LINEA','type' => 'CADENA50','value' => $linea),
                             3 => array('Nombre' => 'CODIGOARTICULO','type' => 'VARCHAR15','value' => $detail['CodigoArticulo']),
                             4 => array('Nombre' => 'CANTIDAD','type' => 'VARCHAR15','value' => $detail['Cantidad']),
                             5 => array('Nombre' => 'LOTE','type' => 'VARCHAR50','value' => $TALLE),
                             6 => array('Nombre' => 'DESCUENTO','type' => 'VARCHAR200','value' => $detail['Descuento']),
                             7 => array('Nombre' => 'PRECIOUNITARIO','type' => 'VARCHAR15','value' => $precioUnitario),
                             8 => array('Nombre' => 'CODIGODEPOSITO','type' => 'VARCHAR15','value' => $MS_DEPOSITOSSTOCK),
                            );
            $SP_CUERPOPEDIDOS = $ibase->exec_sp("MTO_CUERPOPEDIDOS",$params,"Varchar(50)","RESULTADO","Execute");

            if($GLOBALS['mododebug'] == 1)
                echo("<br/>linea---> ".$SP_CUERPOPEDIDOS);

            $result_sp = $ibase->query($SP_CUERPOPEDIDOS);

            if($result_sp === false){
                throw new Exception('MTO_CUERPOPEDIDOS Articulo');
            }

            $linea++;
            $ibase->free_result($result_sp);
        }//end foreach

        // Agrego la linea del costo del envio
        if($pedido['montoEnvio'] != 0)
        {
            $params = array( 1 => array('Nombre' => 'CODIGOPEDIDO','type' => 'CADENA50','value' => $pedido['ordenTienda']),
                            2 => array('Nombre' => 'LINEA','type' => 'CADENA50','value' => $linea),
                            3 => array('Nombre' => 'CODIGOARTICULO','type' => 'VARCHAR15','value' => $idFlete),
                            4 => array('Nombre' => 'CANTIDAD','type' => 'VARCHAR15','value' => '1'),
                            5 => array('Nombre' => 'LOTE','type' => 'VARCHAR50','value' => '000'),
                            6 => array('Nombre' => 'DESCUENTO','type' => 'VARCHAR200','value' => '0'),
                            7 => array('Nombre' => 'PRECIOUNITARIO','type' => 'VARCHAR15','value' => $pedido['montoEnvio']),
                            8 => array('Nombre' => 'CODIGODEPOSITO','type' => 'VARCHAR15','value' => $MS_DEPOSITOSSTOCK),
                        );
            $SP_CUERPOPEDIDOS_ENVIO = $ibase->exec_sp("MTO_CUERPOPEDIDOS",$params,"Varchar(50)","RESULTADO","Execute");

            if($GLOBALS['mododebug'] == 1)
                echo("<br/>linea envio---> ".$SP_CUERPOPEDIDOS_ENVIO);

            $result_spenvio = $ibase->query($SP_CUERPOPEDIDOS_ENVIO);

            if($result_spenvio === false){
              throw new Exception('MTO_CUERPOPEDIDOS Envio');
            }

            $ibase->free_result($result_spenvio);
            $linea++;
        }//end montoenvio

        //Lista de Precio
        $LISTAPRECIO = ($MS_MAYORISTA ? $listaPedido[$pedido['ordenTienda']] : $MS_LISTAPRECIO);
        // Codigo de Cliente ERP
        $CodigoCliente = flxfn::equivalenciaID((int)$pedido['CodigoCliente'],'flx_cliente',false);
        // Codigo Vendedor segun cliente
        $vendedor = ($MS_MAYORISTA ? flxfn::getVendedor($CodigoCliente) : $MS_USERERP);
        
        //$mensajeOrden = flxfn::getMensajeOrden($pedido['ordenTienda']);

        $observacion = flxfn::getValidestrDescription($pedido['Observaciones']);
        //$observacion .= ' | Mensaje: '.$mensajeOrden;
        $menssage = flxfn::getMenssage($pedido['ordenTienda']);
        $mensaje = ( $menssage != '' ? $observacion.= ' | Mensaje: '.$menssage : $observacion );

        if($MS_CODFORMADEPAGO == 999){
            $MS_CODFORMADEPAGO = 999;
        }  
              
        //Ejecutamos SP_CABEZAPEDIDOS

        $params = array( 1 => array('Nombre' => 'CODIGOPEDIDO ','type' => 'CADENA50','value' => $pedido['ordenTienda']),
                         2 => array('Nombre' => 'CODIGOCLIENTE ','type' => 'CADENA50','value' => $CodigoCliente),
                         3 => array('Nombre' => 'FECHACOMPROBANTE  ','type' => 'VARCHAR15','value' => $pedido['FechaComprobante']),
                         4 => array('Nombre' => 'CODIGOUSUARIO  ','type' => 'VARCHAR15','value' => $vendedor),
                         5 => array('Nombre' => 'OBSERVACIONES  ','type' => 'VARCHAR50','value' => flxfn::escape($mensaje)),
                         6 => array('Nombre' => 'LISTAPRECIO  ','type' => 'VARCHAR200','value' => $LISTAPRECIO),
                         7 => array('Nombre' => 'TIPOOPERACION  ','type' => 'VARCHAR15','value' => $pedido['TipoOperacion']),
                         8 => array('Nombre' => 'CODIGOMONEDA  ','type' => 'VARCHAR15','value' => $MS_CODMONEDA),
                         9 => array('Nombre' => 'DESCUENTOGRAL  ','type' => 'VARCHAR15','value' => $pedido['DESCUENTOGRAL']),
                         10 => array('Nombre' => 'CODIGOFORMADEPAGO ','type' => 'ENTERO','value' => $MS_CODFORMADEPAGO),
                         11 => array('Nombre' => 'DEPXCLI ','type' => 'ENTERO','value' => $MS_DEPXCLI)
                        );
        $SP_CABEZAPEDIDOS = $ibase->exec_sp("MTO_CABEZAPEDIDOS", $params, "FLOAT", 'RESULTADO');
        if($GLOBALS['mododebug'] == 1)
            echo("<br/>cabeza---> ".$SP_CABEZAPEDIDOS);
                        
        $result_spPedidos = $ibase->query($SP_CABEZAPEDIDOS);

        if($result_spPedidos === false)
          throw new Exception('MTO_CABEZAPEDIDOS');

        $result_pedido_ERP = $ibase->fetch_object($result_spPedidos);
        if($result_pedido_ERP === false)
          throw new Exception($ibase->getLastErrMsg());

          if(is_string($result_pedido_ERP->RESULTADO))
          {
            flxfn::breakTransaction($e->getMessage());
            $ibase->rollback($tr);
            throw new Exception('La condicion de IVA del cliente debe ser diferente de Consumidor Final cuando se informa CUIT.');

          }else{

                if($result_pedido_ERP->RESULTADO != '')
                {
                    //guardo el numero de pedido generado en Flexxus y su equivalencia con prestashop.
                    Db::getInstance()->insert('flx_pedido', array(
                      'ID_ERP' => $result_pedido_ERP->RESULTADO,
                      'id_prestashop' => $pedido['ordenTienda'],
                    ));

                    //create a new cURL resource
                    $ch = curl_init();

                    //setup request to send json via POST
                    
                    $jsonData = array(
                        'cliente' => Context::getContext()->shop->domain,
                        'monto' => $montoPedido
                    );

                    //Encode the array into JSON.
                    $jsonDataEncoded = json_encode($jsonData);

                    //Tell cURL that we want to send a POST request.
                    curl_setopt($ch, CURLOPT_POST, true);
                        
                    
                    curl_setopt($ch, CURLOPT_URL, "https://apitest.integrador.mitiendaonline.com:4001/pedido");

                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);

                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
                                        
                    //Attach our encoded JSON string to the POST fields.
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonDataEncoded);

                    curl_setopt($ch,CURLOPT_ENCODING, '');

                    //Set the content type to application/json
                    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));


                    //Execute the request
                    // $resultp = curl_exec($ch);

                    // //close cURL resource
                   
                    // echo $resultp;

                    if (!curl_exec($ch)) {
                        die('Error: "' . curl_error($ch) . '" - Code: ' . curl_errno($ch));
                    }
                    curl_close($ch);

                    $montoPedido = 0;

                }else{
                  throw new Exception($result_pedido_ERP);
                }

              $RegistrosSincronizados = $RegistrosSincronizados + 1;
              flxfn::commitTransaction(0);
              $ibase->commit($tr);
          }//END is_string($result_pedido_ERP->RESULTADO

          $ibase->free_result($result_spPedidos);

      }catch (Exception $e){

            $message = $e->getMessage().' Linea error ('.$e->getLine().')';
            flxfn::addLog('Insertar Pedido', $message);

            $content = '<h2> Datos de Cliente</h2><br/>';
            $content.= 'Servidor: '._DB_SERVER_.'<br/>';
            $content.= 'Servidor: '._DB_NAME_.'<br/>';
            $content.= 'Error'.$e->getMessage().' Linea error ('.$e->getLine().')';
            flxfn::sendMail('Error Sincronizacion:'.$e->getMessage(), $content);

            if((bool)strpos($e->getMessage(), 'mismo CUIT/DNI.')) {
                $ID_CUSTOMER = flxfn::equivalenciaID($CodigoCliente,'flx_cliente');
                $customer = new Customer($ID_CUSTOMER);
                $content = '<h2> Atencion !!</h2><br/>';
                $content.= 'Un cliente nuevo (codigo Flx: '.$CodigoCliente.') quiere sincronizarse al sistema, pero se esta generando
                un error donde existe mas de un cliente con el mismo Cuit/DNI, porfavor verificar si el cliente existe en el sistema agregarle los siguientes datos.
                Razon Social:'.$customer->lastname.' '.$customer->firstname.'
                EMAIL:'.$customer->email.'';
                PrestaShopLogger::addLog($content, 1, null, 'Customer', (int)$customer->id, true, (int)$this->context->employee->id);
                flxfn::sendMail('Error de Cliente: ('.$CodigoCliente.') '._DB_NAME_, $content, true);
            }

            if((bool)strpos($e->getMessage(), 'condicion de IVA')) {
                $content = '<h2> Atencion !!</h2><br/>';
                $content.= 'Un cliente nuevo (codigo Flx: '.$CodigoCliente.') en la web acaba de realizar una compra 
                y se registró como Empresa para recibir Factura A o según corresponda por su condición de IVA.</br>
                El pedido en la web quedó pendiente para ser sincronizado al software de gestión 
                hasta que el Cliente sea editado en Flexxus y se le cambien la condición de IVA correspondiente.</br>
                Una vez que el cliente sea editado y deje de aparecer como Consumidor Final, el pedido se sincronizará y se generará la Nota de Pedido correspondiente en Flexxus.';
                PrestaShopLogger::addLog($content, 1, null, 'Order', 0, true, (int)$this->context->employee->id);
                flxfn::sendMail('Error de Pedido: '._DB_NAME_, $content, true);
            }

            flxfn::breakTransaction( $e->getMessage());
            $ibase->rollback($tr);            

      }//END TRYCATCH

    }//end Foreach

  }//End if orders 

  $msj="<div class='line-result'><span class='count-badge'>".$RegistrosSincronizados."</span> PEDIDOS</div>";
  if($RegistrosSincronizados == 0 || $GLOBALS['mododebug'] == 0)
  $msj = '';
  $data['mensaje']['Pedidos'] = $RegistrosSincronizados;
echo Tools::jsonEncode($data);
/*-----> FIN - INTEGRACION DE CIENTES Y PEDIDOS */
