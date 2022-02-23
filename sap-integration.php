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

  $orderForRequestBody = array(
    "customer" => array(
      "name" => $order_data['billing']['first_name'] . " " . $order_data['billing']['last_name'],
      "docNumber" => $orderHeadersAndCustomerResults[0]["docNumber"], //falta docNumber
      "address" => $order_data['billing']['address_1'],
      "city" => $order_data['billing']['city'],
      "department" => $order_data['billing']['state'],
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

    return array(
      "transportGuide"      => $order["transportGuide"],
      "mpOrder"             => $order["mpOrder"],
      "orderAddress"        => $orderForRequestBody["address"],
      "city"                => $orderForRequestBody["city"],
      "department"          => $orderForRequestBody["department"],
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
      array('sapStatus'=> "enviado"),
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
function handlerOrderStatusByEndpoint($id, $isProcessed, $sapId){

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
        "message" => "No se ha encontrado pedido por el ID especificado en la petición."
      );
      $statusCode = 404;
    }else{

      $update;

      //si se encuentra pedido por id, se valida si es por procesado o despachado, y actualizamos registro en ambos casos
      //en caso de procesado (FASE 2)
      if ($isProcessed) {
        //evaluamos que no este despachado
        if ($orderById[0]["orderStatus"] == "despachado"){
          //estado para cuando esta despachado y no puede volver al estado anterior
          $update = 2;

        }else{
          $newSapStatus = "procesado";      
          $update = $wpdb->update( 
            $ordersInternTable, 
            array("sapStatus" => $newSapStatus, "sapOrderId" => $sapId), 
            array("mpOrder" => $id));
        }
        

      }
      //en caso de despachado (FASE 3)
      else{
        $newSapStatus = "despachado";
        date_default_timezone_set("America/Bogota");
        $currentDate = date('m-d-Y h:i:s');       
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
        $subject = "Pedido #{$orderById["mpOrder"]} despachado";
        $message = "El pedido #{$id}, guía {$orderById["transportGuide"]} ha sido despachado.";

        wp_mail( $to, $subject, $message);
      }

      //validamos retorno del update y devolvemos feedback en cada caso
      //si hay error interno de db
      if ($update === false) {
        $data = array(
          "status" => "500",
          "message" => "Ocurrio un error al intentar actualizar el estado del pedido. Contáctese con el administrador del sitio"
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
          "message" => "Se actualizó correctamente el pedido."
        );
        $statusCode = 201;

      }

    }
  } catch (\Throwable $th) {
    $data = array(
      "status" => "500",
      "message" => "Ocurrio un error al intentar actualizar el estado del pedido. Contáctese con el administrador del sitio. Info del error: {$th}", 
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

  //FUNCIONES DE CALLBACK PARA CADA ENDPOINT

  function deleteOrder($request){

    global $wpdb;
    $id = $request["id"];
    $ordersInternTable = "{$wpdb->prefix}sapwc_orders";
    $ordersProductsTable = "{$wpdb->prefix}sapwc_order_products";

    $deleteOrderProducts = "DELETE
    FROM {$ordersProductsTable}
    WHERE
    mpOrder = {$id}
    ";

    $deleteOrder = "DELETE
    FROM {$ordersInternTable}
    WHERE
    mpOrder = {$id} AND
    colorNumber = 2
    ";

    $deleteResults = $wpdb->query($deleteOrder);
    $wpdb->query($deleteOrderProducts);

    if ($deleteResults > 0) {
      $responseAPI = new WP_REST_Response( array("result" => true) );
      return $responseAPI;
    }
  }

  function getOrderProducts($request){

    global $wpdb;
    $id = $request["id"];
  $orderProductsMetaTableName = "{$wpdb->prefix}woocommerce_order_itemmeta";

    $orderItemsQuery = "SELECT 
    or_prod.product_net_revenue as price, 
    or_prod.product_qty as quantity,
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
      
      //DESARROLLO - PROBAR CREACION DE PEDIDO POR API

      //queries:

      //QUERY PARA LA TABLA DEL DASHBOARD

      /* $mainQuery = "SELECT 
      CONCAT('#', orderW.mpOrder, ' ', orderW.customerFullName) as orderNumberName,
      orderW.phoneNumber,
      orderW.orderDate,
      orderW.sapOrderDateShipped,
      orderW.totalPrice,
      orderW.colorNumber
      FROM 
      {$ordersInternTable} as orderW
      ORDER BY 
          orderW.colorNumber ASC,
          orderW.exxeStatusUpdatedAt DESC  
      ";
      //array para el foreach
      $mainResults = $wpdb->get_results($mainQuery, ARRAY_A);


      //QUERY PARA CARD DASHBOARD - FUNCION
      function getCardNumber($status){
        global $wpdb;
        $ordersInternTable = "{$wpdb->prefix}sapwc_orders";

        $mainQuery = "SELECT 
        CONCAT_WS(' ', orderW.mpOrder, orderW.customerFullName) as orderNumberName,
        orderW.phoneNumber,
        orderW.orderDate,
        orderW.sapOrderDateShipped,
        orderW.totalPrice,
        orderW.colorNumber
        FROM 
        {$ordersInternTable} as orderW
        WHERE
        orderW.colorNumber = {$status}
        ORDER BY
        orderW.exxeStatusUpdatedAt DESC    
        ";
        $results = $wpdb->get_results($mainQuery, ARRAY_A);
        return $results;
      }

      //EN PROCESO:
      $inProcessOrders = sizeof(getCardNumber(4));
      //EN RETRASO  :
      $delayedOrders = sizeof(getCardNumber(3));
      //Con novedades  :
      $novedadOrders = sizeof(getCardNumber(1));
      //Mas de 15 dias con novedad  :
      $novedadDelayedOrders = sizeof(getCardNumber(2));
      //Entregados  :
      $deliveredOrders = sizeof(getCardNumber(5));

      $novedadOrdersTable = getCardNumber(1);
      $deliveredOrdersTable = getCardNumber(5);

      $data = array(
        "dataToJson" => $estructuredData,
        "mainQuery" => $mainResults,
        "inProcess" => $inProcessOrders,
        "delayed" => $delayedOrders,
        "novedad" => $novedadOrders,
        "novedadDelayed" => $novedadDelayedOrders,
        "delivered" => $deliveredOrders,
      ); */

      // $estructuredData = estructureAndInsertOrderInfo($id);

      // global $wpdb;
      // $ordersInternTable = "{$wpdb->prefix}sapwc_orders";

      [$statusExxe, $statusExxeDate] =  getExxeStatusByTransportGuide($id);

      $colorNumber = getColorNumberFromExxeStatus($statusExxe);
      
      $data = array(
        "status" => $statusExxe, 
        "date" => $statusExxeDate, 
        "color" => $colorNumber, 
      );

      /* global $wpdb;

      $ordersInternTable = "{$wpdb->prefix}sapwc_orders";

      $ordersSent = "SELECT
      orderS.id, orderS.mpOrder, orderS.transportGuide, orderS.exxeStatus, orderS.colorNumber 
      FROM
      {$ordersInternTable} as orderS
      WHERE
      orderS.sapStatus = 'despachado' AND
      (ISNULL(orderS.colorNumber) OR orderS.colorNumber = 4 OR orderS.colorNumber = 3 )
      ";

      $ordersSentResults = $wpdb->get_results($ordersSent, ARRAY_A);

      $data = array(
        "results" => $ordersSentResults
      ); */

      $responseAPI = new WP_REST_Response( $data );

      return $responseAPI;

    }

  //CALLBACK ENDPOINT PROCESADO / FASE 2
  function changeOrderStatusProcessed($request){

    $id = $request["id"];

    //validamos que venga el sapOrderId por el body:
    $data;
    $statusCode;
    $sapOrderId = $request["sapOrderId"];
    if ($sapOrderId == null || $sapOrderId == "" || $sapOrderId == undefined) {
      $data = array(
        "status" => "404",
        "error" => "El ID del pedido de SAP debe ser enviado obligatoriamente.",
      );
      $statusCode = 404;
    }
    //VALIDACION EN CASO DE REQUERIRSE QUE SEA NUMERICO
    /* elseif( !is_numeric($sapOrderId) ){
      $data = array(
        "status" => "404",
        "error" => "El ID del pedido de SAP debe ser de tipo numérico.",
      );
      $statusCode = 404;
    } */
    else{
      $dataAndStatus = handlerOrderStatusByEndpoint($id, true, $sapOrderId);
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

    $dataAndStatus = handlerOrderStatusByEndpoint($id, false, null);
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
  orderS.sapStatus = 'despachado' AND
  (ISNULL(orderS.colorNumber) OR orderS.colorNumber = 4 OR orderS.colorNumber = 3 )
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
          }

      }
    }
  }

  //NOTIFICAMOS PEDIDOS QUE HAYAN PASADO A ROJO
  sendEmailByOrderStatus(1, "NOVEDAD NOTIFICADO");

  //SE ACTUALIZAN TODOS LOS REGISTROS QUE TENGAN MAS DE 8 DIAS Y ESTEN EN COLOR VERDE
  // updateColorNumberIfTimePassed(4, 3, 30, "SECOND");
  updateColorNumberIfTimePassed(4, 3, 7, "DAY");

  //SE ACTUALIZAN TODOS LOS REGISTROS QUE TENGAN MAS DE 15 DIAS Y ESTEN EN COLOR ROJO
  // updateColorNumberIfTimePassed(1, 2, 30, "SECOND");
  updateColorNumberIfTimePassed(1, 2, 15, "DAY");
};

function updateColorNumberIfTimePassed($currentColor, $newColor, $timeValue, $timeParamDiff){
  global $wpdb;
  $ordersInternTable = "{$wpdb->prefix}sapwc_orders";

  //extraemos fecha actual para hacer comparacion
  date_default_timezone_set("America/Bogota");
  $currentDate = date('YmdHis');

  //actualizamos cada pedido del color especificado, que haya pasado mas del tiempo especificado en ese estado, a su respectivo estado de retraso
  $updateOrders = "UPDATE
    {$ordersInternTable}
    SET
    colorNumber = {$newColor}
    WHERE
    colorNumber = {$currentColor} AND 
    TIMESTAMPDIFF({$timeParamDiff}, exxeStatusUpdatedAt, {$currentDate}) > {$timeValue} 
    ";

    $wpdb->query($updateOrders);

    //ESTABLECEMOS MENSAJES DE CORREO CUANDO CAMBIA A ESTADO NARANJA, MOSTRANDO INFO BASICA DE CADA PEDIDO ACTUALIZADO
    if ($newColor == 2) {
      sendEmailByOrderStatus($newColor, "NOTIFICADO"); 
    }
}

function sendEmailByOrderStatus($colorNumber, $newStatus){
  global $wpdb;
  $ordersInternTable = "{$wpdb->prefix}sapwc_orders";

  //BUSCAMOS INFO BASICA DE PEDIDO POR EL ESTADO PASADO POR ARGS
  $ordersDelayed = "SELECT
      CONCAT('Pedido #', mpOrder, ' - ', customerFullName) as orderInfo
      FROM {$ordersInternTable}
      WHERE
      colorNumber = {$colorNumber} AND 
      sapStatus != '{$newStatus}'
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
        if ($colorNumber == 1) {
          $subject = "Notificación de Pedidos con novedad";
          $messageInfo = "";
        }else{
          $subject = "Notificación de Pedidos con novedad que llevan más de 15 días";
          $messageInfo = " y llevan más de 15 días en ese estado";
        }
        $message = "Estos son los pedidos que tuvieron alguna novedad por parte de exxe{$messageInfo}. Por favor, notificar el procedimiento a realizar con estos pedidos: \n {$ordersImploded} \n";
  
        $wasEmailSent = wp_mail( $to, $subject, $message);
  
        if ($wasEmailSent) {
          //ACTUALIZAMOS EXXESTATUS DE ESTOS PEDIDOS PARA NO RECIBIR MAS CORREOS DE ELLOS
          $ordersDelayedNotified = "UPDATE
          {$ordersInternTable}
          SET 
          sapStatus = '{$newStatus}'
          WHERE
          colorNumber = {$colorNumber}
          ";
          $wpdb->query($ordersDelayedNotified);
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
    if($hook != "sap-integration/admin/lista_formularios.php"){
        return ;
    }
    wp_enqueue_script('bootstrapJs',plugins_url('admin/bootstrap/js/bootstrap.min.js',__FILE__),array('jquery'));
}
add_action('admin_enqueue_scripts','EncolarBootstrapJS');


function EncolarBootstrapCSS($hook){
    if($hook != "sap-integration/admin/lista_formularios.php"){
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