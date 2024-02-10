<?php
require('../../config/config.inc.php');
include('../../init.php');
include('class/flxfn.php');
include('class/Parametros.php');

// Configuraciones de memoria
header("X-Accel-Buffering: no");
ini_set("memory_limit", "-1");
ini_set('output_buffering', 'off');
ini_set('zlib.output_compression', false);
while (@ob_end_flush());
ini_set('implicit_flush', true);
ob_implicit_flush(true);
set_time_limit(30000);

$ID_SHOP = (Context::getContext()->shop->id  == 0 ? (int)Configuration::get('PS_SHOP_DEFAULT') : Context::getContext()->shop->id);

// Configuraciones  Curl
$ch = curl_init();
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_ENCODING, '');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// Seteo parametros basicos
$date     = new DateTime();
$username = Parametros::get('user');
$password = Parametros::get('password');
$url      = Parametros::get('url');
$estados  = Parametros::get('MS_ESTADOSPEDIDOS_' . Context::getContext()->shop->id);

// Obtengo Fecha de Inicio
$date 			  = new DateTime();
$fechaInicioSincro = $date->format('Y-m-d H:i');

echo 'Inicio Sincro: ' . $fechaInicioSincro . '<br/>';
echo ('</br>' .
	'<div id="obj_sincro" style="width"></div>' .
	'<div id="progress" style="width:500px;border:1px solid #ccc;"></div>' .
	'<div id="information" style="width"></div></br>' .
	'<textarea style="width: 500px; height: 150px;" rows="10" id="textresult"></textarea>');

// Inicializo contador de errores y de registros sincronizados
$errMensaje			    = 0;
$registrosSincronizados = 0;

//INTEGRACIÖN DE PRODUCTOS 
//$fechaProductos = flxfn::ultimaFechaSincro('MTO_PRODUCTOS', 0, false);

$fechaProductos = '2000-01-02 09:28:00';

dump($fechaProductos);

$fechaProductos = str_replace("-", "", substr($fechaProductos, 0, 10));
curl_setopt($ch, CURLOPT_URL, $url . "/ec_getproductos?fechacambio=" . $fechaProductos);
echo curl_setopt($ch, CURLOPT_URL, $url . "/ec_getproductos?fechacambio=" . $fechaProductos);
curl_setopt($ch, CURLOPT_USERPWD, $username . ":" . $password);
$response_productos = json_decode(curl_exec($ch));
try {
	if (!empty($response_productos->response->content->productos)) {

		$TOTALREG = flxfn::initProgressBar('Productos', $response_productos->response->content->totregistros, $position);

		foreach ($response_productos->response->content->productos as $key_producto => $producto) {


			flxfn::updateProgressBar($position, $TOTALREG);
			$id_producto = flxfn::equivalenciaID($producto->codproducto, 'flx_p2p');
			
			$MS_NOMBREARTICULO = Parametros::get('MS_NOMBREARTICULO');
			$product 			  = new Product($id_producto);


			$product->reference   = $producto->codebar;
			/*
			if ($product->reference != '7794626013300') {
				continue;
			}
			*/
			dump($producto);
			
			if (($MS_NOMBREARTICULO && $id_producto != 'NULL') || $id_producto == 'NULL') {
				$product->name        = $producto->producto;
			}

			$product->ean13 	  = Validate::isEan13($producto->codebar) ? $producto->codebar : "";
			$product->price 	  = (float)$producto->precio;
			if ($id_producto == "NULL") {
				$product->active      = 1;
			}


			if ($id_producto == "NULL") {
				try {
					if ($product->add()) {
						Db::getInstance()->insert('flx_p2p', array(
							'ID_ERP' => $producto->codproducto,
							'id_prestashop' => $product->id,
							'reference' => $producto->codebar,
							'muestraweb' => 1,
							'impuestointerno' => 0,
							'porcentajeinterno' => 0,
						));
					}
				} catch (Exception $e) {
					dump($e);
					$message = $e->getMessage() . ' Linea error (' . $e->getLine() . ')';
					flxfn::addLog('Agregar Producto - IDERP:' . $producto->codproducto, $message);
					$errMensaje = $errMensaje + 1;
					continue;
				}
			} else {
				try {
					$product->update();
					if ($product->id > 0) {
						Db::getInstance()->update('flx_p2p', array(
							'reference' => $producto->codebar,
							'muestraweb' => 1,
						), 'ID_ERP = ' . $product->reference);
					}
				} catch (Exception $e) {
					dump($e);
					$message = $e->getMessage() . ' Linea error (' . $e->getLine() . ')';
					flxfn::addLog('Actualizar Producto ID:' . $product->id, $message);
					$errMensaje++;
					continue;
				}
			}
			$registrosSincronizados++;
		}
	}
} catch (Exception $e) {
	dump($e);
	Db::getInstance()->execute('INSERT INTO ' . _DB_PREFIX_ . 'flx_log_error VALUES("","Sincronizacion","' . $e->getMessage() . ' Linea error (' . $e->getLine() . ')' . '","Pendiente","' . $_SERVER['SERVER_ADDR'] . '","' . date('Y-m-d H:m:s') . '");');
}

flxfn::endProgressBar('productos', $TOTALREG, $errMensaje);

if ($errMensaje == 0) {
	flxfn::updateFechaUltimaSincro('MTO_PRODUCTOS', $fechaInicioSincro, 0);
}
echo "<br> Productos Sincronizados: " . $registrosSincronizados . "";


// INTEGRACIÖN DE STOCK

$errMensaje 			= 0;
$registrosSincronizados = 0;

$fechaStock = flxfn::ultimaFechaSincro('MTO_STOCK', 0, false);
$fechaStock = str_replace("-", "", substr($fechaStock, 0, 10));
curl_setopt($ch, CURLOPT_URL, $url . "/ec_getstock?&idsucursal{3,21,29,24,5,11,14,16,18,19,22,23,25}&stockquantio=S");
$response_stock = json_decode(curl_exec($ch));

if (!empty($response_stock->response->content->productos)) {

	$TOTALREG = flxfn::initProgressBar('Stock', count($response_stock->response->content->productos), $position);

	foreach ($response_stock->response->content->productos as $key_stock => $stock) {
		flxfn::updateProgressBar($position, $TOTALREG);

		$id_producto = flxfn::equivalenciaID($stock->codproducto, 'flx_p2p');

		if ($id_producto == 'NULL') {
			continue;
		}
		$totalStock = $stock->stock_sucursales + $stock->stock_quantio;

		try {
			StockAvailable::setQuantity($id_producto, 0, Tools::ceilf($totalStock));
			$registrosSincronizados++;
		} catch (Exception $e) {
			dump($e);
			$message = $e->getMessage() . ' Linea error (' . $e->getLine() . ')';
			flxfn::addLog('Actualizar Stock', $message);
			$errMensaje++;
			continue;
		}
	}
}
flxfn::updateActive($ID_SHOP, 0);
flxfn::updateActive($ID_SHOP, 1);
flxfn::endProgressBar('Stock', $TOTALREG, $errMensaje);
echo "<br> Stock Sincronizados: " . $registrosSincronizados . "";
