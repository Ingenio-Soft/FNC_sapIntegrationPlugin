<?php

/**
 * Plugin Name: Pedidos SAP
 * Description: Manejo de estado de pedidos con integracion por SAP
 * Version: 1.0
 * Author: IngenioSoft
 */



function ActivateSAPIntegration(){

  global $wpdb;


$ordersTableName = "{$wpdb->prefix}sapwc_orders";
$ordersTransportGuideTableName = "{$wpdb->prefix}sapwc_orders_transportguides";
$orderProductsTableName = "{$wpdb->prefix}sapwc_order_products";
$orderMessagesTableName = "{$wpdb->prefix}sapwc_order_sapmessages";

  //CREAMOS TABLAS PARA MANEJO INTERNO DE PEDIDOS Y SUS PRODUCTOS
  //Tabla interna de pedidos
  $createOrdersTableQuery = "CREATE TABLE IF NOT EXISTS {$ordersTableName} (
    id INT NOT NULL AUTO_INCREMENT,
    transportGuide varchar(100) NULL,
    mpOrder INT NOT NULL,
    orderAddress varchar(100) NULL,
    city varchar(100) NULL,
    department varchar(100) NULL,
    docNumber varchar(100) NULL,
    customerFullName varchar(200) NULL,
    phoneNumber varchar(12) NULL,
    email varchar(100) NULL,
    totalPrice varchar(100) NULL,
    orderDate varchar(100) NULL,
    sapOrderId varchar(100) NULL,
    sapStatus varchar(100) NULL, 
    sapOrderDateShipped varchar(100) NULL, 
    exxeStatus varchar(100) NULL,
    exxeStatusUpdatedAt TIMESTAMP NULL,
    colorNumber INT NULL,
    exxeNovedadFifteenDays TINYINT(1) NULL,
    novedadExxeNotified TINYINT(1) NULL,
    novedadDelayExxeNotified TINYINT(1) NULL,
    sapErrorNotified TINYINT(1) NULL,
    exxeError TINYINT(1) NULL,
    CONSTRAINT sapwc_orders_PK PRIMARY KEY (id) 
  )
  ENGINE=MyISAM
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_520_ci;
  "; 

$wpdb->query($createOrdersTableQuery);

//tabla de productos del pedido
  $createOrderProductsTableQuery = "CREATE TABLE IF NOT EXISTS {$orderProductsTableName} (
    order_product_id INT NOT NULL AUTO_INCREMENT,
    mpOrder INT NOT NULL,
    product_id INT NOT NULL,
    product_qty INT NOT NULL,
    CONSTRAINT sapwc_order_products_PK PRIMARY KEY (order_product_id)
  )
  ENGINE=MyISAM
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_520_ci;
  ";

  $wpdb->query($createOrderProductsTableQuery);

  //tabla para manejo interno de numeros de guia de los pedidos
  $createOrderTransportGuideTableQuery = "CREATE TABLE IF NOT EXISTS {$ordersTransportGuideTableName} (
    id INT NOT NULL AUTO_INCREMENT,
    mpOrder INT NOT NULL,
    transportGuide varchar(100) NOT NULL,
    docNumber varchar(20) NOT NULL,
	createdOn TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, 
    CONSTRAINT sapwc_orders_transportguides PRIMARY KEY (id)
  )
  ENGINE=MyISAM
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_520_ci;
  ";

  $wpdb->query($createOrderTransportGuideTableQuery);

  $createOrderTransportGuideTableQuery = "CREATE TABLE IF NOT EXISTS {$orderMessagesTableName} (
    id INT NOT NULL AUTO_INCREMENT,
    mpOrder INT NOT NULL,
    message varchar(500) NOT NULL,
    CONSTRAINT sapwc_order_sapmessages PRIMARY KEY (id)
  )
  ENGINE=MyISAM
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_520_ci;
  ";

  $wpdb->query($createOrderTransportGuideTableQuery);


}

function DesactivateSAPIntegration(){

  //DESACTIVAMOS CRON DE EXXE

  $timestamp = wp_next_scheduled( 'sap_exxe_integration_cron' );
  wp_unschedule_event( $timestamp, 'sap_exxe_integration_cron' );
  

}

//funciones para la estructuracion de la data extraida de la orden

//funcion para estructurar orderItems
function estructureOrderItems($orderItem){

  return array(
    "mpSKU" => $orderItem["mpSKU"],
    "description" => $orderItem["description"],
    "quantity" => $orderItem["product_qty"],
    "brand" => $orderItem["brand"]
  );

};

//funcion principal de estructuracion
function estructureAndInsertOrderInfo($id){

  global $wpdb;
  $ordersTableName = "{$wpdb->prefix}sapwc_orders";
  $ordersTransportGuideTableName = "{$wpdb->prefix}sapwc_orders_transportguides";
  $orderProductsTableName = "{$wpdb->prefix}sapwc_order_products";
  $orderProductsMetaTableName = "{$wpdb->prefix}woocommerce_order_itemmeta";

  $order = wc_get_order( $id );
  $order_data = $order->get_data(); // The Order data

  //QUERY PARA TRAER INFO DE ORDERHEADERS Y CUSTOMER:
  //SE DEBE ACTUALIZAR PARA OBTENER TRANSPORTGUIDE
  $orderHeadersAndCustomerQuery = "SELECT 
  orderS.order_id as mpOrder,
  orderS.date_created as orderDate,
  orderS.total_sales as totalPrice,
  orderGuide.transportGuide,
  orderGuide.docNumber,
  orderS.customer_id
  FROM
  {$wpdb->prefix}wc_order_stats as orderS
  INNER JOIN {$ordersTransportGuideTableName} as orderGuide
    ON orderGuide.mpOrder = orderS.order_id
  
  WHERE
  orderS.order_id = {$id}";

  //QUERY PARA TRAER INFO DE LOS PRODUCTOS DE LA ORDEN/PEDIDO:

  $orderItemsQuery = "SELECT 
  or_prod.product_qty,
  or_prod.product_id, 
  or_prod.order_id as mpOrder, 
  prod_info.sku as mpSKU,
  prod_extra_info.order_item_name as description,
  orderPL.meta_value as brand
  FROM
  {$wpdb->prefix}wc_order_product_lookup as or_prod
  INNER JOIN {$wpdb->prefix}wc_product_meta_lookup as prod_info
  ON or_prod.product_id = prod_info.product_id
  INNER JOIN {$wpdb->prefix}woocommerce_order_items as prod_extra_info
  ON or_prod.order_item_id = prod_extra_info.order_item_id
  INNER JOIN {$orderProductsMetaTableName} as orderPL
  ON or_prod.order_item_id = orderPL.order_item_id
  AND orderPL.meta_key = 'Vendido por'
  WHERE 
  or_prod.order_id = {$id} AND
  prod_extra_info.order_id = {$id}
  ";
  

  $orderHeadersAndCustomerResults = $wpdb->get_results($orderHeadersAndCustomerQuery, ARRAY_A);
  $orderItemsResult = $wpdb->get_results($orderItemsQuery, ARRAY_A);


  $shipments = json_decode(file_get_contents(plugin_dir_path( __FILE__ ). '/daneColombia.json'), true);
  $codigoDepartment = 0;
	foreach ($shipments as $key => $value) {
		if($value['DEPARTAMENTO'] == $order_data['shipping']['state'] && $value['MUNICIPIO'] == strtoupper($order_data['shipping']['city']))
		{
			$codigoDepartment = $value['CODDEPARTAMENTO'];
		}
	};

  $orderForRequestBody = array(
    "customer" => array(
      "name" => $order_data['billing']['first_name'] . " " . $order_data['billing']['last_name'],
      "docNumber" => $orderHeadersAndCustomerResults[0]["docNumber"], //falta docNumber
      "address" => $order_data['shipping']['address_1'],
      "city" => strtoupper($order_data['shipping']['city']),
      "department" => $codigoDepartment,
      "phoneNumber" => $order_data['billing']['phone'],
      "email" => $order_data['billing']['email'],
    ),
    "orderHeader" => array(
      "transportGuide" => $orderHeadersAndCustomerResults[0]["transportGuide"], //falta anadirlo desde el plugin mentor shipping
      "mpOrder" => $orderHeadersAndCustomerResults[0]["mpOrder"], 
    ),
    "orderItems" => array_map("estructureOrderItems", $orderItemsResult)

  );

  //funciones para estructurar datos a insertar en DB - TEMPORAL---------

  $estructureOrderProducts = function($orderProduct){
    return array(
      "product_qty" => $orderProduct["product_qty"],
      "product_id" => $orderProduct["product_id"],
      "mpOrder" => $orderProduct["mpOrder"]
    );
  };



  $estructureOrderInfo = function($order, $orderForRequestBody){

      $states = json_decode(file_get_contents(plugin_dir_path( __FILE__ ). '/departmentsColombia.json'), true);
      $stateKey = array_search($orderForRequestBody["department"], array_column($states, 'c_digo_dane_del_departamento'));
      $nameDepartment = $states[$stateKey]["departamento"];

    return array(
      "transportGuide"      => $order["transportGuide"],
      "mpOrder"             => $order["mpOrder"],
      "orderAddress"        => $orderForRequestBody["address"],
      "city"                => $orderForRequestBody["city"],
      "department"          => $nameDepartment,
      "docNumber"           => $orderForRequestBody["docNumber"],  //FALTA DOCNUMBER $orderForRequestBody["docNumber"],
      "customerFullName"    => $orderForRequestBody["name"],
      "phoneNumber"         => $orderForRequestBody["phoneNumber"],
      "email"               => $orderForRequestBody["email"],
      "totalPrice"          => $order["totalPrice"],
      "orderDate"           => $order["orderDate"],
    );
  };

  $estructuredOrderProducts = array_map($estructureOrderProducts, $orderItemsResult);
  $estructuredOrderInfoToDB = array_map($estructureOrderInfo, $orderHeadersAndCustomerResults, $orderForRequestBody);


  //Insertamos info de pedido en tablas internas para manejo interno

  $doesOrderExistsQuery = "SELECT
  mpOrder
  FROM
  $ordersTableName
  WHERE
  mpOrder = {$id}
  ";
  $doesOrderExists = $wpdb->get_results($doesOrderExistsQuery, ARRAY_A);

  //SOLO INSERTAMOS SI NO EXISTE REFERENCIA AL PEDIDO EN NUESTRA TABLA INTERNA
  if (sizeof($doesOrderExists) == 0 ) {
    $wpdb->insert($ordersTableName, $estructuredOrderInfoToDB[0]);
    foreach ($estructuredOrderProducts as $key) {  
      $wpdb->insert($orderProductsTableName, $key);
    }
  }

  //CREDENCIALES PARA LOGIN SAP:
  $sapCredentialsLogin = array(
    "user" => "mkpfncuat",
    "password" => "3TuC3Lh7FT9vtuD5",
  );

  $sapCredentialsLoginJSON = json_encode($sapCredentialsLogin);


  //HACEMOS PETICION AL LOGIN
  $curl = curl_init();
  curl_setopt_array($curl, array(
    CURLOPT_URL => 'https://serviciosrestqa.federaciondecafeteros.org/rest/mktosap/login',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => $sapCredentialsLoginJSON,
    CURLOPT_HTTPHEADER => array(
      'Content-Type: application/json'
    ),
  ));
  $response = curl_exec($curl);
  curl_close($curl);
  $responseJSON = json_decode($response, true);


  $tokenJSON = 'token: ' . $responseJSON["token"];

  //HACEMOS PETICION PARA ENVIAR PEDIDO

  date_default_timezone_set("America/Bogota");
  $currentDate = date('YmdHis');

  $requestHeaderInfo = array(
      "client" => "marketplace",
      "ipAdress" => $_SERVER["REMOTE_ADDR"],
      "userName" => "mpfncuat",
      "sessionID" => $currentDate,
      "requestID" => $currentDate,
      "activeRecord" => 1,
    );

  $requestHeaderAndBodyData = array(
    "requestHeader" => $requestHeaderInfo,
    "requestBody" => $orderForRequestBody
  );

  $requestHeaderAndBodyDataJSON = json_encode($requestHeaderAndBodyData); 

  $curl = curl_init();
  curl_setopt_array($curl, array(
    CURLOPT_URL => 'https://serviciosrestqa.federaciondecafeteros.org/rest/mktosap/receiveOrder',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => $requestHeaderAndBodyDataJSON,
    CURLOPT_HTTPHEADER => array(
      $tokenJSON,
      'Content-Type: application/json'
    ),
  ));

  $response2 = curl_exec($curl);

  curl_close($curl);
  
  $response2JSON = json_decode($response2, true);

  if ($response2JSON["responseBody"]["code"] == 1) {
    $wpdb->update(
      $ordersTableName, 
      array(
        'sapStatus'=> "Enviado",
        'colorNumber'=> 4,
      ),
      array('mpOrder'=>$id)
    );
  } 


  return  $orderForRequestBody;

}

//CODIGO A COLOCAR EN PLUGIN EPAYCO WOOCOMMERCE, PARA INSERTAR EN NUESTRA TABLA EL TRANSPORTGUIDE
function insertTransportGuideInInternTable($order_id, $transport_guide, $order, $docNumber){

  global $wpdb;
  $ordersTransportGuideTableName = "{$wpdb->prefix}sapwc_orders_transportguides";

  try {
	  //se busca si existe
	  $results = $wpdb->get_row("SELECT COUNT(1) as count FROM {$ordersTransportGuideTableName} WHERE mpOrder = {$order_id};" , OBJECT );

	  if($results->count > 0){
		  $wpdb->update(
			  $ordersTransportGuideTableName, 
			  array('transportGuide'=>$transport_guide),
			  array('mpOrder'=>$order_id)
		  );
	  }
	  else{
		  $wpdb->insert(
			  $ordersTransportGuideTableName, 
			  array(
				  "mpOrder" => $order_id,
				  "transportGuide" => $transport_guide,
				  "docNumber" => $docNumber,
          )
		  );
	  }

  } catch (\Throwable $th) {
    $error = "Hubo un error al intentar insertar en la tabla de la orden. {$th}";
	$order->add_order_note('Ocurrio un Error insertando en la tabla de la orden');
  }

}

//FUNCION CENTRALIZADA PARA LOGICA DE MANEJO DE ESTADOS EN AMBOS ENDPOINTS
function handlerOrderStatusByEndpoint($id, $isProcessed, $sapId, $status, $messages){

  global $wpdb;

  $whereOrderQuery = "";

  if ($isProcessed) {
    $whereOrderQuery = "orderW.mpOrder = {$id}";
  }else{
    $whereOrderQuery = "orderW.sapOrderId = {$id}";
  }

  //Tabla de pedidos del woocommerce, clientes y tabla interna
  $ordersTable = "{$wpdb->prefix}wc_order_stats";
  $ordersTransportGuideTableName = "{$wpdb->prefix}sapwc_orders_transportguides";
  $ordersInternTable = "{$wpdb->prefix}sapwc_orders";
  $customersTable = "{$wpdb->prefix}wc_customer_lookup";
  $orderMessagesTableName = "{$wpdb->prefix}sapwc_order_sapmessages";

  //Validamos que existan tablas
  $ordersTableInternExists = $wpdb->query("SHOW TABLES like {$ordersInternTable}");
  $ordersTransportGuideTableExists = $wpdb->query("SHOW TABLES like {$ordersTransportGuideTableName}");
  $ordersTableExists = $wpdb->query("SHOW TABLES like {$ordersTable}");
  $customersTableExists = $wpdb->query("SHOW TABLES like {$customersTable}");

  if (
  sizeof($ordersTableInternExists) > 0 && 
  sizeof($customersTableExists) > 0 && 
  sizeof($ordersTableExists) > 0 && 
  sizeof($ordersTransportGuideTableExists) > 0  
  ) {


  //buscamos pedido por id extraido de los params de la request.
  //query del pedido
  $query = "SELECT 
  orderW.mpOrder, orderW.transportGuide,
  orderW.sapStatus as orderStatus, 
  orderW.customerFullName,
  CONCAT('$', orderW.totalPrice) as totalPrice,
  orderW.phoneNumber,
  orderW.docNumber,
  orderW.orderAddress,
  orderW.orderDate,
  orderW.email, orderW.city,
  orderW.department
  FROM 
  {$ordersInternTable} as orderW
  WHERE {$whereOrderQuery}";



  //ejecutamos query del pedido
  $orderById = $wpdb->get_results($query, ARRAY_A);

  //hacemos map para retornar info sin status
  $mapOrderFunc = function($order){
    return array(
      "mpOrder" => $order["mpOrder"],
      "transportGuide" => $order["transportGuide"],
      "customer_fullname" => $order["customerFullName"],
      "customer_email" => $order["email"],
      "customer_city" => $order["city"],
      "customer_department" => $order["department"],
    );
  };

  $mapOrder = array_map($mapOrderFunc, $orderById);

  //inicializamos variables de data y statuscode para devolverlas en la response de la peticion
  $data;
  $statusCode;


  try {
    if (sizeof($orderById) == 0) {
      $data = array(
        "status" => "404",
        "message" => "No se ha encontrado pedido por el ID especificado en la peticion."
      );
      $statusCode = 404;
    }else{

      $update;

      //si se encuentra pedido por id, se valida si es por procesado o despachado, y actualizamos registro en ambos casos
      //en caso de procesado (FASE 2)
      if ($isProcessed) {
        //evaluamos que no este despachado
        if ($orderById[0]["orderStatus"] == "Despachado"){
          //estado para cuando esta despachado y no puede volver al estado anterior
          $update = 2;

        }else{
          $sapStatuses = array(
            "No relevante" => array(
              "status" => "No relevante",
              "color" => 1,
            ),
            "A" => array(
              "status" => "No tratado",
              "color" => 4,
            ),
            "B" => array(
              "status" => "Tratado parcialmente",
              "color" => 4
            ),
            "C" => array(
              "status" => "Tratado completamente",
              "color" => 4
            ),
          );
          $newSapStatus = $sapStatuses[$status];
          $update = $wpdb->update( 
            $ordersInternTable, 
            array(
              "sapStatus" => $newSapStatus["status"], 
              "sapOrderId" => $sapId,
              "colorNumber" => $newSapStatus["color"]
            ), 
            array("mpOrder" => $id));
            if ($messages != null) {
              $queryMessages = "SELECT
              mpOrder
              FROM {$orderMessagesTableName}
              WHERE
              mpOrder = {$id}
              ";
              $resultsMessages = $wpdb->get_results($queryMessages, ARRAY_A);
              if (sizeof($resultsMessages) == 0) {
                foreach ($messages as $key) {
                  $wpdb->insert( $orderMessagesTableName, 
                  array(
                    "mpOrder" => $id,
                    "message" => $key,
                  ));
                }
              }
            }
            if ($newSapStatus["color"] == 1) {
              // $to = "comprocafedecolombia@cafedecolombia.com";
              $to = "yeisong12ayeisondavidsuarezg12@gmail.com";
              $subject = "Pedido #{$orderById[0]["mpOrder"]} contiene errores por parte de SAP";
              $message = "El pedido #{$orderById[0]["mpOrder"]}, guía {$orderById[0]["transportGuide"]} ha sido evaluado por SAP y se ha determinado que contiene errores. Por favor, realice las respectivas correcciones para reenviar el pedido.";

              wp_mail( $to, $subject, $message);
            }
        }
        

      }
      //en caso de despachado (FASE 3)
      else{
        $newSapStatus = "Despachado";
        date_default_timezone_set("America/Bogota");
        $currentDate = date('Y-m-d h:i:s');       
        $update = $wpdb->update( 
          $ordersInternTable, 
          array(
            "sapStatus" => $newSapStatus, 
            "sapOrderDateShipped" => $currentDate, 
          ), 
          array("sapOrderId" => $id));
        	
        //ENVIAMOS CORREO DE NOTIFICACION PARA PEDIDO DESPACHADO
        
        // $to = "comprocafedecolombia@cafedecolombia.com";
        $to = "yeisong12ayeisondavidsuarezg12@gmail.com";
        $subject = "Pedido #{$orderById[0]["mpOrder"]} despachado";
        $message = "El pedido #{$orderById[0]["mpOrder"]}, guía {$orderById[0]["transportGuide"]} ha sido despachado.";

        wp_mail( $to, $subject, $message);
        //NOTIFICACION DE DESPACHADO A CLIENTE

        $order = wc_get_order( $orderById[0]["mpOrder"] ); 
        $order_data = $order->get_data();  

        $orderProductsMetaTableName = "{$wpdb->prefix}woocommerce_order_itemmeta";
        $orderItemsQuery = "SELECT 
          prod_extra_info.order_item_name as NombreProducto, 
          or_prod.product_qty as CantidadDelProducto, 
          or_prod.product_net_revenue as PrecioDelProducto
          FROM
          {$wpdb->prefix}wc_order_product_lookup as or_prod
          INNER JOIN {$wpdb->prefix}wc_product_meta_lookup as prod_info
          ON or_prod.product_id = prod_info.product_id
          INNER JOIN {$wpdb->prefix}woocommerce_order_items as prod_extra_info
          ON or_prod.order_item_id = prod_extra_info.order_item_id
          INNER JOIN {$orderProductsMetaTableName} as orderPL
          ON or_prod.order_item_id = orderPL.order_item_id
          AND orderPL.meta_key = 'Vendido por'
          WHERE 
          or_prod.order_id = {$orderById[0]["mpOrder"]} AND
          prod_extra_info.order_id = {$orderById[0]["mpOrder"]}
          ";
          $orderItemsResult = $wpdb->get_results($orderItemsQuery, ARRAY_A);

          $orderDateExploded = explode(" ", $orderById[0]["orderDate"]);
          $orderDateFormatted = str_replace("-", "/", $orderDateExploded[0]); 

          $orderItemsHTML = "";
          foreach ($orderItemsResult as $key => $value) {
            $orderItemsHTML .= "
          <tr>
          <td style='color:#636363;border:1px solid #e5e5e5;padding:12px;text-align:left;vertical-align:middle;font-family:\"Helvetica Neue\",Helvetica,Roboto,Arial,sans-serif;word-wrap:break-word'>
              {$value['NombreProducto']}
          </td>
          <td style='color:#636363;border:1px solid #e5e5e5;padding:12px;text-align:left;vertical-align:middle;font-family:\"Helvetica Neue\",Helvetica,Roboto,Arial,sans-serif'>
              {$value['CantidadDelProducto']}</td>
          <td style='color:#636363;border:1px solid #e5e5e5;padding:12px;text-align:left;vertical-align:middle;font-family:\"Helvetica Neue\",Helvetica,Roboto,Arial,sans-serif'>
              <span><span>$</span>{$value['PrecioDelProducto']}</span>
          </td>
          </tr>
          ";
          }
          $orderShippingCustomerName = $order_data['shipping']['first_name'] . ' ' . $order_data['shipping']['last_name'];

         $toClient = $orderById[0]["email"];
        $subjectClient = "Tu pedido #{$orderById[0]['mpOrder']} ha sido enviado";
        $headers = array( 'Content-Type: text/html; charset=UTF-8' );
        $messageClient = "
        <div marginwidth='0' marginheight='0' style='padding:0'>
    <div id='m_5128378839382569643m_5682122329319615743wrapper' dir='ltr'
        style='background-color:#f7f7f7;margin:0;padding:70px 0;width:100%'>
        <table width='100%' height='100%' cellspacing='0' cellpadding='0' border='0'>
            <tbody>
                <tr>
                    <td valign='top' align='center'>
                        <div id='m_5128378839382569643m_5682122329319615743template_header_image'>
                            <p style='margin-top:0'><img
                                    src='https://ci3.googleusercontent.com/proxy/JaaiwpvatzrbEZKl7Mg8jBQiJEnoeinrmzIVEg6ctxVXtRlaYVgqW6U4AGM8NNwSO5esVREmVvQU1FunElPD05QJnEhd4-mwClVor2Sgd65uIQuGo7wMjBtXN3jj5AdN=s0-d-e1-ft#https://fncsap.ingeniosoft.co/wp-content/uploads/2019/06/logo-fondo-claro.svg'
                                    alt='Compro Café de Colombia'
                                    style='border:none;display:inline-block;font-size:14px;font-weight:bold;height:auto;outline:none;text-decoration:none;text-transform:capitalize;vertical-align:middle;max-width:100%;margin-left:0;margin-right:0'
                                    class='CToWUd' jslog='138226; u014N:xr6bB; 53:W2ZhbHNlXQ..'></p>
                        </div>
                        <table id='m_5128378839382569643m_5682122329319615743template_container'
                            style='background-color:#ffffff;border:1px solid #dedede;border-radius:3px' width='600'
                            cellspacing='0' cellpadding='0' border='0'>
                            <tbody>
                                <tr>
                                    <td valign='top' align='center'>

                                        <table id='m_5128378839382569643m_5682122329319615743template_header'
                                            style='background-color:#a66e66;color:#ffffff;border-bottom:0;font-weight:bold;line-height:100%;vertical-align:middle;font-family:&quot;Helvetica Neue&quot;,Helvetica,Roboto,Arial,sans-serif;border-radius:3px 3px 0 0'
                                            width='100%' cellspacing='0' cellpadding='0' border='0'>
                                            <tbody>
                                                <tr>
                                                    <td id='m_5128378839382569643m_5682122329319615743header_wrapper'
                                                        style='padding:36px 48px;display:block'>
                                                        <h1
                                                            style='font-family:&quot;Helvetica Neue&quot;,Helvetica,Roboto,Arial,sans-serif;font-size:30px;font-weight:300;line-height:150%;margin:0;text-align:left;color:#ffffff;background-color:inherit'>
                                                            Tu pedido ya ha sido enviado.</h1>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>

                                    </td>
                                </tr>
                                <tr>
                                    <td valign='top' align='center'>

                                        <table id='m_5128378839382569643m_5682122329319615743template_body'
                                            width='600' cellspacing='0' cellpadding='0' border='0'>
                                            <tbody>
                                                <tr>
                                                    <td id='m_5128378839382569643m_5682122329319615743body_content'
                                                        style='background-color:#ffffff' valign='top'>

                                                        <table width='100%' cellspacing='0' cellpadding='20'
                                                            border='0'>
                                                            <tbody>
                                                                <tr>
                                                                    <td style='padding:48px 48px 32px' valign='top'>
                                                                        <div id='m_5128378839382569643m_5682122329319615743body_content_inner'
                                                                            style='color:#636363;font-family:&quot;Helvetica Neue&quot;,Helvetica,Roboto,Arial,sans-serif;font-size:14px;line-height:150%;text-align:left'>

                                                                            <p style='margin:0 0 16px'>Hola, {$orderById[0]["customerFullName"]}. Solo para que lo sepas, hemos recibido tu pedido #{$orderById[0]["mpOrder"]}, y ya ha sido enviado a la dirección suministrada: </p>
                                                                            <h2
                                                                                style='color:#a66e66;display:block;font-family:&quot;Helvetica Neue&quot;,Helvetica,Roboto,Arial,sans-serif;font-size:18px;font-weight:bold;line-height:130%;margin:0 0 18px;text-align:left'>
                                                                                <a href='https://{$_SERVER['SERVER_NAME']}/wp-admin/post.php?post={$orderById[0]["mpOrder"]}&amp;action=edit'
                                                                                    style='font-weight:normal;text-decoration:underline;color:#a66e66'
                                                                                    target='_blank'
                                                                                    data-saferedirecturl='https://www.google.com/url?q=https://{$_SERVER['SERVER_NAME']}/wp-admin/post.php?post%3D{$orderById[0]["mpOrder"]}%26action%3Dedit&amp;source=gmail&amp;ust=1646176019157000&amp;usg=AOvVaw3QQ6LeYb-msNh6Rcl6t0ES'>[Pedido
                                                                                    #{$orderById[0]["mpOrder"]}]</a> ({$orderDateFormatted})</h2>

                                                                            <div style='margin-bottom:40px'>
                                                                                <table
                                                                                    style='color:#636363;border:1px solid #e5e5e5;vertical-align:middle;width:100%;font-family:'Helvetica Neue',Helvetica,Roboto,Arial,sans-serif'
                                                                                    cellspacing='0' cellpadding='6'
                                                                                    border='1'>
                                                                                    <thead>
                                                                                        <tr>
                                                                                            <th scope='col'
                                                                                                style='color:#636363;border:1px solid #e5e5e5;vertical-align:middle;padding:12px;text-align:left'>
                                                                                                Producto</th>
                                                                                            <th scope='col'
                                                                                                style='color:#636363;border:1px solid #e5e5e5;vertical-align:middle;padding:12px;text-align:left'>
                                                                                                Cantidad</th>
                                                                                            <th scope='col'
                                                                                                style='color:#636363;border:1px solid #e5e5e5;vertical-align:middle;padding:12px;text-align:left'>
                                                                                                Precio</th>
                                                                                        </tr>
                                                                                    </thead>
                                                                                    <tbody>
                                                                                        {$orderItemsHTML}
                                                                                    </tbody>
                                                                                    <tfoot>
                                                                                        <tr>
                                                                                            <th scope='row'
                                                                                                colspan='2'
                                                                                                style='color:#636363;border:1px solid #e5e5e5;vertical-align:middle;padding:12px;text-align:left;border-top-width:4px'>
                                                                                                Subtotal:</th>
                                                                                            <td
                                                                                                style='color:#636363;border:1px solid #e5e5e5;vertical-align:middle;padding:12px;text-align:left;border-top-width:4px'>
                                                                                                <span><span>$</span>{$order->get_subtotal()}</span>
                                                                                            </td>
                                                                                        </tr>
                                                                                        <tr>
                                                                                            <th scope='row'
                                                                                                colspan='2'
                                                                                                style='color:#636363;border:1px solid #e5e5e5;vertical-align:middle;padding:12px;text-align:left'>
                                                                                                Envío:</th>
                                                                                            <td
                                                                                                style='color:#636363;border:1px solid #e5e5e5;vertical-align:middle;padding:12px;text-align:left'>
                                                                                                <span><span>$</span>{$order_data['shipping_total']}</span>&nbsp;<small>vía
                                                                                                {$orderById[0]["transportGuide"]}</small>
                                                                                            </td>
                                                                                        </tr>
                                                                                        <tr>
                                                                                            <th scope='row'
                                                                                                colspan='2'
                                                                                                style='color:#636363;border:1px solid #e5e5e5;vertical-align:middle;padding:12px;text-align:left'>
                                                                                                Método de pago:</th>
                                                                                            <td
                                                                                                style='color:#636363;border:1px solid #e5e5e5;vertical-align:middle;padding:12px;text-align:left'>
                                                                                                {$order_data['payment_method_title']}
                                                                                            </td>
                                                                                        </tr>
                                                                                        <tr>
                                                                                            <th scope='row'
                                                                                                colspan='2'
                                                                                                style='color:#636363;border:1px solid #e5e5e5;vertical-align:middle;padding:12px;text-align:left'>
                                                                                                Total:</th>
                                                                                            <td
                                                                                                style='color:#636363;border:1px solid #e5e5e5;vertical-align:middle;padding:12px;text-align:left'>
                                                                                                <span><span>$</span>{$order->get_total()}</span>
                                                                                            </td>
                                                                                        </tr>
                                                                                    </tfoot>
                                                                                </table>
                                                                            </di>
                                                                            <p style='margin:16px 0 16px'>
                                                                                <strong>Número de
                                                                                    documento:</strong>{$orderById[0]["docNumber"]}
                                                                            </p>
                                                                        
                                                                            <table
                                                                                id='m_5128378839382569643m_5682122329319615743addresses'
                                                                                style='width:100%;vertical-align:top;margin-bottom:40px;padding:0'
                                                                                cellspacing='0' cellpadding='0'
                                                                                border='0'>
                                                                                <tbody>
                                                                                    <tr>
                                                                                        <td style='text-align:left;font-family:'Helvetica Neue',Helvetica,Roboto,Arial,sans-serif;border:0;padding:0'
                                                                                            width='50%'
                                                                                            valign='top'>
                                                                                            <h2
                                                                                                style='color:#a66e66;display:block;font-family:&quot;Helvetica Neue&quot;,Helvetica,Roboto,Arial,sans-serif;font-size:18px;font-weight:bold;line-height:130%;margin:0 0 18px;text-align:left'>
                                                                                                Dirección de
                                                                                                facturación</h2>

                                                                                            <address
                                                                                                style='padding:12px;color:#636363;border:1px solid #e5e5e5'>
                                                                                                {$orderById[0]["customerFullName"]}<br>{$order_data['billing']['address_1']}<br>{$order_data['billing']['city']}<br>{$order_data['billing']['state']}<br>{$order_data['billing']['postcode']}
                                                                                                <br><a
                                                                                                    href='tel:{$order_data['billing']['phone']}'
                                                                                                    style='color:#a66e66;font-weight:normal;text-decoration:underline'
                                                                                                    target='_blank'>{$order_data['billing']['phone']}</a>
                                                                                                <br><a
                                                                                                    href='mailto:{$order_data['billing']['email']}'
                                                                                                    target='_blank'>{$order_data['billing']['email']}</a>
                                                                                            </address>
                                                                                        </td>
                                                                                        <td style='text-align:left;font-family:'Helvetica Neue',Helvetica,Roboto,Arial,sans-serif;padding:0'
                                                                                            width='50%'
                                                                                            valign='top'>
                                                                                            <h2
                                                                                                style='color:#a66e66;display:block;font-family:&quot;Helvetica Neue&quot;,Helvetica,Roboto,Arial,sans-serif;font-size:18px;font-weight:bold;line-height:130%;margin:0 0 18px;text-align:left'>
                                                                                                Dirección de envío
                                                                                            </h2>

                                                                                            <address
                                                                                                style='padding:12px;color:#636363;border:1px solid #e5e5e5'>
                                                                                                {$orderShippingCustomerName}<br> {$order_data['shipping']['address_1']}<br>{$order_data['shipping']['city']}<br>{$order_data['shipping']['state']}<br>{$order_data['shipping']['postcode']}
                                                                                            </address>
                                                                                        </td>
                                                                                    </tr>
                                                                                </tbody>
                                                                            </table>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            </tbody>
                                                        </table>

                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>

                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td valign='top' align='center'>

                        <table id='m_5128378839382569643m_5682122329319615743template_footer' width='600'
                            cellspacing='0' cellpadding='10' border='0'>
                            <tbody>
                                <tr>
                                    <td style='padding:0;border-radius:6px' valign='top'>
                                        <table width='100%' cellspacing='0' cellpadding='10' border='0'>
                                            <tbody>
                                                <tr>
                                                    <td colspan='2'
                                                        id='m_5128378839382569643m_5682122329319615743credit'
                                                        style='border-radius:6px;border:0;color:#8a8a8a;font-family:&quot;Helvetica Neue&quot;,Helvetica,Roboto,Arial,sans-serif;font-size:12px;line-height:150%;text-align:center;padding:24px 0'
                                                        valign='middle'>
                                                        <p style='margin:0 0 16px'>Federación Nacional de Cafeteros
                                                            2021 ©</p>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                    </td>
                </tr>
            </tbody>
        </table>
        <div class='yj6qo'></div>
        <div class='adL'>
        </div>
    </div>
    <div class='adL'>
    </div>
</div>";
        wp_mail( $toClient, $subjectClient, $messageClient, $headers);
      }

      //validamos retorno del update y devolvemos feedback en cada caso
      //si hay error interno de db
      if ($update === false) {
        $data = array(
          "status" => "500",
          "message" => "Ocurrio un error al intentar actualizar el estado del pedido. Contactese con el administrador del sitio"
        );
        $statusCode = 500;
      }
      //mostramos a user que pedido no puede volver a procesado si ya fue despachado
      elseif($update === 2){

        $data = array(
          "status" => "400",
          "message" => "El pedido ya ha sido despachado anteriormente y no puede volver al estado anterior."
        );
        $statusCode = 400;
        
      }
      //si se ejecuta correctamente el update
      else{

        $data = array(
          "status" => "201",
          "pedido" => $mapOrder[0],
          "message" => "Se actualizo correctamente el pedido."
        );
        $statusCode = 201;

      }

    }
  } catch (\Throwable $th) {
    $data = array(
      "status" => "500",
      "message" => "Ocurrio un error al intentar actualizar el estado del pedido. Contactese con el administrador del sitio. Info del error: {$th}", 
    );
    $statusCode = 500;
  }
  //retornamos pedido o error en caso de no encontrar pedido
  }else{
    $data = array(
      "status" => "500",
      "message" => "No hay tabla de pedidos registrada en este sitio",
    );
    $statusCode = 500;
  }

  return array( "data" => $data, "statusCode" => $statusCode );

}

//registramos nuevos endpoints en la API REST del WP

//ENDPOINT PARA PROCESADO (FASE 2)
add_action( 'rest_api_init', function () {
    register_rest_route( 'sapintegration/v1', '/orders/processed/(?P<id>\d+)', array(
      'methods' => 'POST',
      'callback' => 'changeOrderStatusProcessed',
      'args' => array(
        'id' => array(
          //validacion del id
          'validate_callback' => function($param, $request, $key) {
            //validar que sea numerico
            return is_numeric( $param );
          }
        ),
      ),
      //valida que el usuario tenga la capacidad
      'permission_callback' => function () {
        return current_user_can( 'sap_change_status' );
      }
    ) );
  } );

//ENDPOINT PARA DESPACHADO (FASE 3)
add_action( 'rest_api_init', function () {
    register_rest_route( 'sapintegration/v1', '/orders/shipped/(?P<id>\d+)', array(
      'methods' => 'POST',
      'callback' => 'changeOrderStatusShipped',
      'args' => array(
        'id' => array(
          //validacion del id
          'validate_callback' => function($param, $request, $key) {
            //validar que sea numerico
            return is_numeric( $param );
          }
        ),
      ),
      //valida que el usuario tenga la capacidad
      'permission_callback' => function () {
        return current_user_can( 'sap_change_status' );
      }
    ) );
  } );

add_action( 'rest_api_init', function () {
    register_rest_route( 'sapintegration/v1', '/orders/test/(?P<id>\d+)', array(
      'methods' => 'POST',
      'callback' => 'changeOrderStatusTest',
      'args' => array(
        'id' => array(
          //validacion del id
          'validate_callback' => function($param, $request, $key) {
            //validar que sea numerico
            return is_numeric( $param );
          }
        ),
      ),
      //valida que el usuario tenga la capacidad
      'permission_callback' => function () {
        return current_user_can( 'sap_change_status' );
      }
    ) );
  } );
 
//ENDPOINT PARA CONSULTAR PRODUCTOS DE UN PEDIDO  
add_action( 'rest_api_init', function () {
    register_rest_route( 'sapintegration/v1', '/orders/products/(?P<id>\d+)', array(
      'methods' => 'GET',
      'callback' => 'getOrderProducts',
      'args' => array(
        'id' => array(
          //validacion del id
          'validate_callback' => function($param, $request, $key) {
            //validar que sea numerico
            return is_numeric( $param );
          }
        ),
      )
    ) );
  } );

//ENDPOINT PARA ELIMINAR PEDIDOS EN ESTADO 2  
add_action( 'rest_api_init', function () {
    register_rest_route( 'sapintegration/v1', '/orders/delete/(?P<id>\d+)', array(
      'methods' => 'POST',
      'callback' => 'deleteOrder',
      'args' => array(
        'id' => array(
          //validacion del id
          'validate_callback' => function($param, $request, $key) {
            //validar que sea numerico
            return is_numeric( $param );
          }
        ),
      )
    ) );
  } );

add_action( 'rest_api_init', function () {
    register_rest_route( 'sapintegration/v1', '/orders/messages/(?P<id>\d+)', array(
      'methods' => 'GET',
      'callback' => 'getOrderMessages',
      'args' => array(
        'id' => array(
          //validacion del id
          'validate_callback' => function($param, $request, $key) {
            //validar que sea numerico
            return is_numeric( $param );
          }
        ),
      )
    ) );
  } );

add_action( 'rest_api_init', function () {
    register_rest_route( 'sapintegration/v1', '/orders/resend/(?P<id>\d+)', array(
      'methods' => 'POST',
      'callback' => 'reSendOrderToSAP',
      'args' => array(
        'id' => array(
          //validacion del id
          'validate_callback' => function($param, $request, $key) {
            //validar que sea numerico
            return is_numeric( $param );
          }
        ),
      )
    ) );
  } );

  
  //FUNCIONES DE CALLBACK PARA CADA ENDPOINT
  //funcion para reenviar a sap
  function reSendOrderToSAP($request){
  global $wpdb;
  $id = $request["id"];
  $ordersTableName = "{$wpdb->prefix}sapwc_orders";
  $ordersTransportGuideTableName = "{$wpdb->prefix}sapwc_orders_transportguides";
  $orderProductsMetaTableName = "{$wpdb->prefix}woocommerce_order_itemmeta";
  $order = wc_get_order( $id );
  $order_data = $order->get_data(); // The Order data

  //obtenemos data

  //QUERY PARA TRAER INFO DE ORDERHEADERS Y CUSTOMER:
  //SE DEBE ACTUALIZAR PARA OBTENER TRANSPORTGUIDE
  $orderHeadersAndCustomerQuery = "SELECT 
  orderS.order_id as mpOrder,
  orderS.date_created as orderDate,
  orderS.total_sales as totalPrice,
  orderGuide.transportGuide,
  orderGuide.docNumber,
  orderS.customer_id
  FROM
  {$wpdb->prefix}wc_order_stats as orderS
  INNER JOIN {$ordersTransportGuideTableName} as orderGuide
    ON orderGuide.mpOrder = orderS.order_id
  
  WHERE
  orderS.order_id = {$id}";

  //QUERY PARA TRAER INFO DE LOS PRODUCTOS DE LA ORDEN/PEDIDO:

  $orderItemsQuery = "SELECT 
  or_prod.product_qty,
  or_prod.product_id, 
  or_prod.order_id as mpOrder, 
  prod_info.sku as mpSKU,
  prod_extra_info.order_item_name as description,
  orderPL.meta_value as brand
  FROM
  {$wpdb->prefix}wc_order_product_lookup as or_prod
  INNER JOIN {$wpdb->prefix}wc_product_meta_lookup as prod_info
  ON or_prod.product_id = prod_info.product_id
  INNER JOIN {$wpdb->prefix}woocommerce_order_items as prod_extra_info
  ON or_prod.order_item_id = prod_extra_info.order_item_id
  INNER JOIN {$orderProductsMetaTableName} as orderPL
  ON or_prod.order_item_id = orderPL.order_item_id
  AND orderPL.meta_key = 'Vendido por'
  WHERE 
  or_prod.order_id = {$id} AND
  prod_extra_info.order_id = {$id}
  ";
  $orderHeadersAndCustomerResults = $wpdb->get_results($orderHeadersAndCustomerQuery, ARRAY_A);
  $orderItemsResult = $wpdb->get_results($orderItemsQuery, ARRAY_A);
  $shipments = json_decode(file_get_contents(plugin_dir_path( __FILE__ ). '/daneColombia.json'), true);
  $codigoDepartment = 0;
	foreach ($shipments as $key => $value) {
		if($value['DEPARTAMENTO'] == $order_data['shipping']['state'] && $value['MUNICIPIO'] == strtoupper($order_data['shipping']['city']))
		{
			$codigoDepartment = $value['CODDEPARTAMENTO'];
		}
	};

  $orderForRequestBody = array(
    "customer" => array(
      "name" => $order_data['billing']['first_name'] . " " . $order_data['billing']['last_name'],
      "docNumber" => $orderHeadersAndCustomerResults[0]["docNumber"], //falta docNumber
      "address" => $order_data['shipping']['address_1'],
      "city" => strtoupper($order_data['shipping']['city']),
      "department" => $codigoDepartment,
      "phoneNumber" => $order_data['billing']['phone'],
      "email" => $order_data['billing']['email'],
    ),
    "orderHeader" => array(
      "transportGuide" => $orderHeadersAndCustomerResults[0]["transportGuide"], //falta anadirlo desde el plugin mentor shipping
      "mpOrder" => $orderHeadersAndCustomerResults[0]["mpOrder"], 
    ),
    "orderItems" => array_map("estructureOrderItems", $orderItemsResult)

  );

  //REENVIAMOS DATA A SAP
  //CREDENCIALES PARA LOGIN SAP:
  $sapCredentialsLogin = array(
    "user" => "mkpfncuat",
    "password" => "3TuC3Lh7FT9vtuD5",
  );

  $sapCredentialsLoginJSON = json_encode($sapCredentialsLogin);


  //HACEMOS PETICION AL LOGIN
  $curl = curl_init();
  curl_setopt_array($curl, array(
    CURLOPT_URL => 'https://serviciosrestqa.federaciondecafeteros.org/rest/mktosap/login',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => $sapCredentialsLoginJSON,
    CURLOPT_HTTPHEADER => array(
      'Content-Type: application/json'
    ),
  ));
  $response = curl_exec($curl);
  curl_close($curl);
  $responseJSON = json_decode($response, true);


  $tokenJSON = 'token: ' . $responseJSON["token"];

  //HACEMOS PETICION PARA ENVIAR PEDIDO

  date_default_timezone_set("America/Bogota");
  $currentDate = date('YmdHis');

  $requestHeaderInfo = array(
      "client" => "marketplace",
      "ipAdress" => $_SERVER["REMOTE_ADDR"],
      "userName" => "mpfncuat",
      "sessionID" => $currentDate,
      "requestID" => $currentDate,
      "activeRecord" => 1,
    );

  $requestHeaderAndBodyData = array(
    "requestHeader" => $requestHeaderInfo,
    "requestBody" => $orderForRequestBody
  );

  $requestHeaderAndBodyDataJSON = json_encode($requestHeaderAndBodyData); 

  $curl = curl_init();
  curl_setopt_array($curl, array(
    CURLOPT_URL => 'https://serviciosrestqa.federaciondecafeteros.org/rest/mktosap/receiveOrder',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => $requestHeaderAndBodyDataJSON,
    CURLOPT_HTTPHEADER => array(
      $tokenJSON,
      'Content-Type: application/json'
    ),
  ));

  $response2 = curl_exec($curl);

  curl_close($curl);
  
  $response2JSON = json_decode($response2, true);

  $data = "";
  if ($response2JSON["responseBody"]["code"] == 1) {
    $data = array(
      "result" => true
    );
    //ACTUALIZAMOS PEDIDO A ESTADO ENVIADO
    $wpdb->update($ordersTableName, array(
      "sapStatus" => "Enviado",
      "colorNumber" => 4
    ), array("mpOrder" => $id) );
    //RESETEAMOS MENSAJES DE ERROR
    $orderMessagesTableName = "{$wpdb->prefix}sapwc_order_sapmessages";
    $deleteMessagesQuery = "DELETE
    FROM {$orderMessagesTableName}
    WHERE
    mpOrder = {$id}
    ";

    $wpdb->query($deleteMessagesQuery);

  }else{
    $data = array(
      "result" => false,
      "responseBody" => $response2JSON["responseBody"]
    );
  }

    $responseAPI = new WP_REST_Response( $data );
    return $responseAPI;

  }
  //funcion para obtener mensajes de error del pedido
  function getOrderMessages($request){
    global $wpdb;
    $id = $request["id"];

    $orderMessagesTableName = "{$wpdb->prefix}sapwc_order_sapmessages";

    $orderMessagesQuery = "SELECT 
    om.message
    FROM {$orderMessagesTableName} as om
    WHERE mpOrder = {$id}
    ";
    $orderMessagesResult = $wpdb->get_results($orderMessagesQuery, ARRAY_A);


    $responseAPI = new WP_REST_Response( array("messages" => $orderMessagesResult) );
    return $responseAPI;

  }

  //funcion para borrar pedido y reembolsar en woocommerce
  function deleteOrder($request){

    global $wpdb;
    $id = $request["id"];

    $ordersWoocommerceTableName = "{$wpdb->prefix}wc_order_stats";
    $ordersInternTable = "{$wpdb->prefix}sapwc_orders";
    $ordersProductsTable = "{$wpdb->prefix}sapwc_order_products";

    $orderDeletedInfo = "SELECT
    mpOrder as orderId,
    transportGuide as guide,
    sapOrderId as idSap
    from {$ordersInternTable}
    WHERE
    mpOrder = {$id}
    ";
    $orderData = $wpdb->get_results($orderDeletedInfo, ARRAY_A);

    $deleteOrderProducts = "DELETE
    FROM {$ordersProductsTable}
    WHERE
    mpOrder = {$id}
    ";

    $deleteOrder = "DELETE
    FROM {$ordersInternTable}
    WHERE
    mpOrder = {$id} AND
    exxeNovedadFifteenDays = 1
    ";


    $deleteResults = $wpdb->query($deleteOrder);
    $wpdb->query($deleteOrderProducts);

    $data = "";
    if ($deleteResults > 0) {
      $data = array("result" => true); 
      //CAMBIAMOS ESTADO DE PEDIDO WC A REEMBOLSADO
    $wpdb->update($ordersWoocommerceTableName, array("status" => "wc-refunded"), array("order_id" => $id));
    //AL REEMBOLSAR EN WC, HACEMOS TRIGGER DEL CORREO
    $wcEmail = WC()->mailer();
    $emailer = $wcEmail->emails['WC_Email_Customer_Refunded_Order']; //Enviar una nota al usuario
    $emailer->subject = "Tu pedido No. {$id} ha sido reembolsado"; //Sujeto del correo
    $emailer->heading = "Pedido reembolsado No. {$id}"; //Título del contenido del correo
    $emailer->trigger($id);
    //ENVIAMOS CORREO A SAP NOTIFICANDO EL PEDIDO REEMBOLSADO
    $to = ["yeisong12ayeisondavidsuarezg12@gmail.com"];
    // $to = ["cristian.beltran@almacafe.com.co", "luz.toro@almacafe.com.co"];
    $subject = "Pedido #{$id} ha sido reembolsado desde el Marketplace";
    $message = "Se ha reembolsado un pedido desde el marketplace, el cual tiene la siguiente información: \n Número de pedido en MarketPlace - {$orderData[0]['orderId']}\n Número de pedido en SAP - {$orderData[0]['idSap']} \n Número de guía de transporte - {$orderData[0]['guide']} \n\n Por favor, valide esta información para realizar el respectivo proceso con el pedido.";
    wp_mail( $to, $subject, $message); 
    }else{
      $data = array("result" => false);
    }

    $responseAPI = new WP_REST_Response( $data );
    return $responseAPI;
  }

  function getOrderProducts($request){

    global $wpdb;
    $id = $request["id"];
  $orderProductsMetaTableName = "{$wpdb->prefix}woocommerce_order_itemmeta";

    $orderItemsQuery = "SELECT 
    or_prod.product_net_revenue as price, 
    or_prod.product_qty as quantity,
    prod_info.sku as sku,
    prod_extra_info.order_item_name as productName
    FROM
    {$wpdb->prefix}wc_order_product_lookup as or_prod
    INNER JOIN {$wpdb->prefix}wc_product_meta_lookup as prod_info
    ON or_prod.product_id = prod_info.product_id
    INNER JOIN {$wpdb->prefix}woocommerce_order_items as prod_extra_info
    ON or_prod.order_item_id = prod_extra_info.order_item_id
    INNER JOIN {$orderProductsMetaTableName} as orderPL
    ON or_prod.order_item_id = orderPL.order_item_id
    AND orderPL.meta_key = 'Vendido por'
    WHERE 
    or_prod.order_id = {$id} AND
    prod_extra_info.order_id = {$id}
    ";

    $results = $wpdb->get_results($orderItemsQuery, ARRAY_A);

    $data = array(
      "products" => $results,
    );

    $responseAPI = new WP_REST_Response( $data );
    return $responseAPI;

  }

    function changeOrderStatusTest($request){ 



      $id = $request["id"];

      $order = wc_get_order( $id ); 
      $order_data = $order->get_data();  


      $data = array(
        "Direccion de facturacion:" => array(
          "name" => $order_data['billing']['first_name'] . ' ' . $order_data['billing']['last_name'],
          "address" => $order_data['billing']['address_1'],
          "city" => $order_data['billing']['city'],
          "state" => $order_data['billing']['state'],
          "postcode" => $order_data['billing']['postcode'],
          "email" => $order_data['billing']['email'],
          "phone" => $order_data['billing']['phone'],
        ),
        "Direccion de envio:" => array(
          "name" => $order_data['shipping']['first_name'] . ' ' . $order_data['shipping']['last_name'],
          "address" => $order_data['shipping']['address_1'],
          "city" => $order_data['shipping']['city'],
          "state" => $order_data['shipping']['state'],
          "postcode" => $order_data['shipping']['postcode'],
        ),
        "Metodo de pago" => $order_data['payment_method_title'],
        "Envio" => $order_data['shipping_total'],
        "Subtotal" => $order->get_subtotal(),
        "Total" => $order->get_total(),
      );

      $responseAPI = new WP_REST_Response( $data );

      return $responseAPI;

    }

  //CALLBACK ENDPOINT PROCESADO / FASE 2
  function changeOrderStatusProcessed($request){

    $id = $request["id"];

    //validamos que venga el sapOrderId por el body:
    $data;
    $statusCode;
    $sapOrderId = $request["sapDeliveryId"];
    $status = $request["status"];
    $messages = $request["messages"];
    $statusValues = ["No relevante", "A", "B", "C"];
    $statusValueJoin = implode(", ", $statusValues);
    if ($sapOrderId == null || $sapOrderId == "" || $sapOrderId == undefined) {
      $data = array(
        "status" => "400",
        "message" => "El ID de entrega del pedido de SAP debe ser enviado obligatoriamente.",
      );
      $statusCode = 400;
    }
    elseif ($status == null || $status == "" || $status == undefined) {
      $data = array(
        "status" => "400",
        "message" => "El status del pedido de SAP debe ser enviado obligatoriamente.",
      );
      $statusCode = 400;
    }
    elseif (!in_array($status, $statusValues)) {
      $data = array(
        "status" => "400",
        "message" => "El Status de pedido de SAP no es valido, debe ser uno de los siguientes: {$statusValueJoin}",
      );
      $statusCode = 400;
    }
    elseif ($status == "No relevante" && $messages == null) {
      $data = array(
        "status" => "400",
        "message" => "Se deben enviar los mensajes de error correspondientes al pedido.",
      );
      $statusCode = 400;
    }
    elseif ($messages !== null && !is_array($messages)) {
        $data = array(
          "status" => "400",
          "message" => "Los mensajes de error de SAP correspondientes al pedido deben ser enviados como un array.",
        );
        $statusCode = 400;
    }
    else{
      $dataAndStatus = handlerOrderStatusByEndpoint($id, true, $sapOrderId, $status, $messages);
      $data = $dataAndStatus["data"];
      $statusCode = $dataAndStatus["statusCode"];
    }

    $response = new WP_REST_Response( $data );
    $response->set_status( $statusCode );

    return $response;

  }


  //CALLBACK ENDPOINT DESPACHADO / FASE 3
  function changeOrderStatusShipped($request){


    $id = $request["id"];

    $dataAndStatus = handlerOrderStatusByEndpoint($id, false, null, null, null);
    $data = $dataAndStatus["data"];
    $statusCode = $dataAndStatus["statusCode"];
    

    $response = new WP_REST_Response( $data );
    $response->set_status( $statusCode );

    return $response;

  }



/* //codigo a ejecutar al momento de ejecutarse correctamente el pago de un pedido
function getOrderInfoAfterCheckoutProcessed($order_id) {

  global $wpdb;

  //OBTENEMOS PEDIDO, GUARDAMOS INTERNAMENTE Y RETORNAMOS DATA LISTA PARA ENVIARSE A API
  $dataToJson = estructureAndInsertOrderInfo( $order_id );

}
add_action( 'woocommerce_thankyou', 'getOrderInfoAfterCheckoutProcessed' ); */


//CODIGO PARA ACTIVAR CRON

//filtro para anadir intervalo de 5 segundos para el cron - SOLO PARA FINES DE DESARROLLO Y PRUEBAS

add_filter( 'cron_schedules', 'example_add_cron_interval' );
function example_add_cron_interval( $schedules ) { 
    $schedules['five_seconds'] = array(
        'interval' => 5,
        'display'  => esc_html__( 'Every Five Seconds' ), );
    return $schedules;
}

//funcion que accede a todos los pedidos despachados y consulta su estado en exxe
function exxeCron(){

  global $wpdb;

  $ordersInternTable = "{$wpdb->prefix}sapwc_orders";

  //hacemos query de todos los pedidos que ya hayan sido despachados por SAP:
  $ordersSent = "SELECT
  orderS.id, orderS.mpOrder, orderS.transportGuide, orderS.exxeStatus, orderS.colorNumber 
  FROM
  {$ordersInternTable} as orderS
  WHERE
  orderS.sapStatus = 'Despachado' AND
  (
    ISNULL(orderS.colorNumber) OR 
    orderS.colorNumber = 4 OR 
    orderS.colorNumber = 3 OR 
    orderS.colorNumber = 1 )
  ";

  $ordersSentResults = $wpdb->get_results($ordersSent, ARRAY_A);

  //en caso de haber pedidos despachados, por cada uno extraemos su estado de exxe y actualizamos
  if (sizeof($ordersSentResults) > 0) {
    foreach ($ordersSentResults as $key) {
      
      //PETICION A API SOAP DE EXXE PARA EXTRAER ULTIMO ESTADO Y FECHA DE ACTUALIZACION
      [$exxeStatus, $statusExxeDate] =  getExxeStatusByTransportGuide($key["transportGuide"]);
      //extraemos id de pedido y status exxe anterior
      $order_id = $key["id"];
      $currentExxeStatus = $key["exxeStatus"];
      $currentColor = $key["colorNumber"];
      //ejecutamos actualizacion si el status exxe es diferente al anterior
      if ($currentExxeStatus != $exxeStatus) {
        //AQUI OBTENEMOS EL COLOR SEGUN EL ESTADO PARA ACTUALIZARLO
        $colorNumber = getColorNumberFromExxeStatus($exxeStatus);
        $wpdb->update(
          $ordersInternTable, 
          array(
            "exxeStatus" => $exxeStatus,
          ), 
          array("id" => $order_id));
          if ($currentColor != $colorNumber) {
            $wpdb->update(
              $ordersInternTable, 
              array(
                "colorNumber" => $colorNumber,
                "exxeStatusUpdatedAt" => $statusExxeDate,
              ), 
              array("id" => $order_id));
              if ($colorNumber == 1) {
                $wpdb->update($ordersInternTable, array("exxeError" => 1), array("id" => $order_id));
              }else{
                $wpdb->update($ordersInternTable, array("exxeError" => 0), array("id" => $order_id));
              }
          }

      }
    }
  }

  //NOTIFICAMOS PEDIDOS QUE HAYAN PASADO A ROJO
  sendEmailByOrderStatus(1, "novedadExxeNotified");
  //NOTIFICAMOS PEDIDOS QUE PASARON A NOVEDAD RETRASO
  sendEmailByOrderStatus(1, "novedadDelayExxeNotified", true);
  //NOTIFICAMOS A CLIENTE PEDIDOS QUE HAYAN SIDO ENTREGADOS
  sendEmailByOrderStatus(5, "sapErrorNotified");

  //SE ACTUALIZAN TODOS LOS REGISTROS QUE TENGAN MAS DE 8 DIAS Y ESTEN EN COLOR VERDE
  // updateColorNumberIfTimePassed(4, 3, "colorNumber", 30, "SECOND");
  updateColorNumberIfTimePassed(4, 3, "colorNumber", 7, "DAY");

  //SE ACTUALIZAN TODOS LOS REGISTROS QUE TENGAN MAS DE 25 DIAS Y ESTEN EN COLOR ROJO
  // updateColorNumberIfTimePassed(1, 1, "exxeNovedadFifteenDays", 30, "SECOND", true);
  updateColorNumberIfTimePassed(1, 1, "exxeNovedadFifteenDays", 25, "DAY");
};

function updateColorNumberIfTimePassed($currentColor, $newColor, $valueField,$timeValue, $timeParamDiff, $isExxeNotify = false){
  global $wpdb;
  $ordersInternTable = "{$wpdb->prefix}sapwc_orders";

  //extraemos fecha actual para hacer comparacion
  date_default_timezone_set("America/Bogota");
  $currentDate = date('YmdHis');

  //Si el cambio es para novedad y mas de 25 dias, colocamos condicional de campo exxe error:
  $exxeErrorWhere = "";
  if ($isExxeNotify) {
    $exxeErrorWhere = "AND exxeError = 1";
  }

  //actualizamos cada pedido del color especificado, que haya pasado mas del tiempo especificado en ese estado, a su respectivo estado de retraso
  $updateOrders = "UPDATE
    {$ordersInternTable}
    SET
    {$valueField} = {$newColor}
    WHERE
    colorNumber = {$currentColor} AND 
    TIMESTAMPDIFF({$timeParamDiff}, exxeStatusUpdatedAt, {$currentDate}) > {$timeValue} 
    {$exxeErrorWhere}
    ";

    $wpdb->query($updateOrders);
}

function sendEmailByOrderStatus($colorNumber, $statusField, $isFifteenDays = false){
  global $wpdb;
  $ordersInternTable = "{$wpdb->prefix}sapwc_orders";

  if ($colorNumber == 5) {
    
    $statusFieldConcat = 'orderW.' . $statusField;
  $queryEntregados = "SELECT 
  orderW.mpOrder as orderId
  FROM {$ordersInternTable} as orderW
    WHERE
    orderW.colorNumber = {$colorNumber} AND 
    (ISNULL({$statusFieldConcat}) OR {$statusFieldConcat} = 0) 
  ";

  $resultsEntregados = $wpdb->get_results($queryEntregados, ARRAY_A);
  if (sizeof($resultsEntregados) > 0) {
    foreach ($resultsEntregados as $key => $value) {

      $ordersWoocommerceTableName = "{$wpdb->prefix}wc_order_stats";
      $updateOrderStatus = $wpdb->update($ordersWoocommerceTableName, array("status" => "wc-completed"), array("order_id" => $value["orderId"]));

      $wcEmail = WC()->mailer();
      $emailer = $wcEmail->emails['WC_Email_Customer_Completed_Order']; //Enviar una nota al usuario
      $emailer->subject = "Tu pedido en Compro Café de Colombia ya ha sido completado"; //Sujeto del correo
      $emailer->heading = "Gracias por tu compra"; //Título del contenido del correo
      $emailer->trigger($value["orderId"]);
       
    }
    //ACTUALIZAMOS EXXESTATUS DE ESTOS PEDIDOS PARA NO RECIBIR MAS CORREOS DE ELLOS
    $ordersDeliveredNotified = "UPDATE
    {$ordersInternTable}
    SET 
    {$statusField} = 1
    WHERE
    colorNumber = {$colorNumber}
    ";
    $wpdb->query($ordersDeliveredNotified);    
  }
  }
  else{
    $isFifteenDaysWhere = "AND (ISNULL(exxeNovedadFifteenDays) OR exxeNovedadFifteenDays = 0)";
    if ($isFifteenDays) {
      $isFifteenDaysWhere = "AND exxeNovedadFifteenDays = 1";
    }
    //BUSCAMOS INFO BASICA DE PEDIDO POR EL ESTADO PASADO POR ARGS
    $ordersDelayed = "SELECT
    CONCAT('Pedido #', mpOrder, ' - guía: ', transportGuide, ' - ', customerFullName) as orderInfo
    FROM {$ordersInternTable}
    WHERE
    colorNumber = {$colorNumber} AND
    exxeError = 1 AND 
    (ISNULL({$statusField}) OR {$statusField} = 0)
    {$isFifteenDaysWhere}
    ";
    $resultsOrders = $wpdb->get_results($ordersDelayed, ARRAY_A);

    //HACEMOS FOR EACH Y AGREGAMOS A ARRAY LA INFO DE CADA PEDIDO
    if (sizeof($resultsOrders)) {
      $ordersDelayedArrayInfo = [];
      foreach ($resultsOrders as $key => $value) {
        array_push($ordersDelayedArrayInfo, $value["orderInfo"]);
      }
      //HACEMOS JOIN AL ARREGLO:
      $ordersImploded = implode("\n", $ordersDelayedArrayInfo);
      //ENVIAMOS CORREO CON MENSAJE INFORMATIVO DE PEDIDOS CON MAS DE 15 DIAS EN ROJO

      // $to = "comprocafedecolombia@cafedecolombia.com";
      $to = "yeisong12ayeisondavidsuarezg12@gmail.com";
      if (!$isFifteenDays) {
        $subject = "Pedidos con novedad";
        $messageInfo = "";
        $predicateInfo = "Por favor, recuerde validar con Exxe Logística el estado del pedido.";
      }else{
        $subject = "Pedidos con novedad que llevan más de 25 días";
        $messageInfo = " y llevan más de 25 días sin entregar";
        $predicateInfo = "Por favor, recuerde ingresar a la página de administración y eliminar los pedidos si es necesario.";
      }
      $message = "Estos son los pedidos que tuvieron alguna novedad por parte de Exxe Logística{$messageInfo}. {$predicateInfo} \n {$ordersImploded} \n";

      $wasEmailSent = wp_mail( $to, $subject, $message);

      if ($wasEmailSent) {
        //ACTUALIZAMOS EXXESTATUS DE ESTOS PEDIDOS PARA NO RECIBIR MAS CORREOS DE ELLOS
        $ordersDelayedNotified = "UPDATE
        {$ordersInternTable}
        SET 
        {$statusField} = 1
        WHERE
        colorNumber = {$colorNumber} AND
        exxeError = 1
        ";
        $wpdb->query($ordersDelayedNotified);
      }
    }
  }

  
}

function getColorNumberFromExxeStatus($exxeStatus){

  //SWITCH CASE POR CADA ESTADO Y RETORNAR UN NUMERO DE COLOR
  $colorNumber = 0;
  switch ($exxeStatus) {

    //ESTADOS DE PROCESANDO / EN ENTREGA

    case 'EN PREDESPACHO':
      $colorNumber = 4;
      break;

    case 'GUIA CREADA':
      $colorNumber = 4;
      break;

    case 'EN BODEGA ORIGEN':
      $colorNumber = 4;
      break;

    case 'GUIA ASIGNADA A PLANILLA NACIONAL':
      $colorNumber = 4;
      break;

    case 'GUIA EN VEHICULO NACIONAL':
      $colorNumber = 4;
      break;

    case 'GUIA EN VIAJE TRONCAL':
      $colorNumber = 4;
      break;

    case 'EN BODEGA ENLACE':
      $colorNumber = 4;
      break;

    case 'EN BODEGA DESTINO':
      $colorNumber = 4;
      break;

    case 'GUIA ASIGNADA A PLANILLA URBANA':
      $colorNumber = 4;
      break;

    case 'GUIA DESASIGNADA DE LA PLANILLA':
      $colorNumber = 4;
      break;

    case 'GUIA EN VEHICULO URBANO':
      $colorNumber = 4;
      break;

    case 'GUIA EN REPARTO':
      $colorNumber = 4;
      break;

    //ESTADOS DE ENTREGADO

    case 'LLEGO AL PUNTO DE ENTREGA':
      $colorNumber = 5;
      break;

    case 'ENTREGA A REMITENTE':
      $colorNumber = 5;
      break;

    case 'ENTREGA EXXE':
      $colorNumber = 5;
      break;

    //ESTADOS DE NOVEDAD
    case 'ENTREGA PARCIAL':
      $colorNumber = 1;
      break;

    case 'NO ENTREGADO':
      $colorNumber = 1;
      break;

    case 'GUIA DEVUELTA A BODEGA':
      $colorNumber = 1;
      break;
      
    case 'REDIRECCION':
      $colorNumber = 1;
      break;

    case 'REDIRECCIONADA':
      $colorNumber = 1;
      break;

    case 'CERRAR GUIA':
      $colorNumber = 1;
      break;

    case 'ANULACION AUTOMATICA':
      $colorNumber = 1;
      break;

    case 'ANULACION DE CITA':
      $colorNumber = 1;
      break;

    case 'ANULADA':
      $colorNumber = 1;
      break;

    case 'INGRESO DE CITA':
      $colorNumber = 1;
      break;

    case 'MODIFICACION DE CITA':
      $colorNumber = 1;
      break;

    case 'GUIA CON CITA REPROGRAMADA':
      $colorNumber = 1;
      break;
    
    default:
    $colorNumber = 0;
      break;
  }

  return $colorNumber;
}

function getExxeStatusByTransportGuide($transportGuide){

  //inicializamos soap client
  $client = new SoapClient('http://solex.blulogistics.net/solexrc/services/webservicesolex.asmx?wsdl');
  //creamos params para el body de la peticion
  $params->user = "wsfedbog";
  $params->password = "wsfedbog";
  $params->numero = $transportGuide;

  //ejecutamos metodo de exxe para obtener estado de guia dentro de un trycatch
  try {
    //echo 'fv' . print_r($params);
    $result = $client->ConsultaGuia($params);
    //var_dump($result);
  } 
  catch (SOAPFault $f) {
    echo $f->getMessage();
  }

  //Extraemos ultimo estado del array o objeto estados, incluyendo su fecha:
  if(is_array( $result->ConsultaGuiaResult->Estados->EEstadoGuia)){
    $statusArray = $result->ConsultaGuiaResult->Estados->EEstadoGuia;
    $lastStatusInfo = $statusArray[sizeof($statusArray) - 1]; 
  }
	else
  $lastStatusInfo = $result->ConsultaGuiaResult->Estados->EEstadoGuia;
  
  $guideStatus = $lastStatusInfo->Estado;
  $guideStatusDate = $lastStatusInfo->FechaEstado;

  return [$guideStatus, $guideStatusDate];
}

//anadimos custom hook con funcion de cron y lo programamos
add_action( 'sap_exxe_integration_cron', 'exxeCron');
if ( ! wp_next_scheduled( 'sap_exxe_integration_cron' ) ) {
  //scheduleamos a 5 segundos - DESARROLLO
  // wp_schedule_event( time(), 'five_seconds', 'sap_exxe_integration_cron' );
  //scheduleamos a 1hora
  wp_schedule_event( time(), 'hourly', 'sap_exxe_integration_cron' );
}

/*----------------------------------------------------------------------*/
//CONFIGURACION DE CORREO ENTREGADO WOOCOMMERCE
/* add_action( 'woocommerce_email_header', 'function_woocommerce_email_header', 20, 4 );
function function_woocommerce_email_header( $order, $sent_to_admin, $plain_text, $email ) {
    if ( $email->id == 'customer_completed_order' ) {
        echo '<div class="woocommerce-info">woocommerce_email_header</div>';
    }
} */

/*----------------------------------------------------------------------*/

//CONFIGURACION PARTE VISUAL

/*AGREGAR PLUGIN A BARRA LATERAL*/

add_action('admin_menu', 'CrearMenu');

function CrearMenu()
{
    add_menu_page(
        'Pedidos', //Titulo de la pagina
        'Pedidos', //Titulo del menu
        'manage_options', //Capability
        plugin_dir_path(__FILE__) . 'admin/lista_formularios.php', //Slug
        null, //Funcion del contenido
        plugin_dir_url(__FILE__) . 'admin/img/icon.png', //Icono
        '2'
    );
	
	add_submenu_page(
	 plugin_dir_path(__FILE__) . 'admin/lista_formularios.php', //Slug
	'Dashboard',
	'Dashboard',
    'manage_options',
	 plugin_dir_path(__FILE__) . 'admin/lista_formularios.php' //Slug

	
	);

	
			add_submenu_page(
	 plugin_dir_path(__FILE__) . 'admin/lista_formularios.php', //Slug
	'Entregados',
	'Entregados',
	'manage_options',
	 plugin_dir_path(__FILE__) . 'admin/Entregados.php', //Slug

	
	);
	

}



//encolar bootstrap

function EncolarBootstrapJS($hook){
    //echo "<script>console.log('$hook')</script>";
    if($hook != "sap-integration/admin/lista_formularios.php" and $hook != "sap-integration/admin/Entregados.php"){
        return ;
    }
    wp_enqueue_script('bootstrapJs',plugins_url('admin/bootstrap/js/bootstrap.min.js',__FILE__),array('jquery'));
}
add_action('admin_enqueue_scripts','EncolarBootstrapJS');


function EncolarBootstrapCSS($hook){
    if($hook != "sap-integration/admin/lista_formularios.php" and $hook != "sap-integration/admin/Entregados.php" ){
        return ;
    }
    wp_enqueue_style('bootstrapCSS',plugins_url('admin/bootstrap/css/bootstrap.min.css',__FILE__));
}


add_action('admin_enqueue_scripts','EncolarBootstrapCSS');

function EncolarCSS($hook){

    wp_enqueue_style('CSS',plugins_url('admin/css/custom.css',__FILE__));
}
add_action('admin_enqueue_scripts','EncolarCSS');


register_activation_hook(__FILE__, 'ActivateSAPIntegration');
register_deactivation_hook(__FILE__, 'DesactivateSAPIntegration');

//hola