<?php

/**
 * Plugin Name: Pedidos SAP
 * Description: Manejo de estado de pedidos con integracion por SAP
 * Version: 1.0
 * Author: IngenioSoft
 */


//CODIGO A COLOCAR EN PLUGIN EPAYCO WOOCOMMERCE, PARA INSERTAR EN NUESTRA TABLA EL TRANSPORTGUIDE
function insertTransportGuideInInternTable($order_id, $transport_guide){

  global $wpdb;
  $ordersTransportGuideTableName = "{$wpdb->prefix}sapwc_orders_transportguides";

  try {

    $wpdb->insert(
      $ordersTransportGuideTableName, 
      array(
        "mpOrder" => $order_id,
        "transportGuide" => $transport_guide,
      ));

  } catch (\Throwable $th) {
    $error = "Hubo un error al intentar insertar el numero de guia. {$th}";
  }

}


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
    customer_id INT NOT NULL,
    sapOrderId varchar(100) NULL,
    sapStatus varchar(100) NULL, 
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
    transportGuide varchar(100) NULL,
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

  /* $timestamp = wp_next_scheduled( 'sap_exxe_integration_cron' );
  wp_unschedule_event( $timestamp, 'sap_exxe_integration_cron' ); */

  /* global $wpdb;

  $ordersTableName = "{$wpdb->prefix}sapwc_orders";
  $orderProductsTableName = "{$wpdb->prefix}sapwc_order_products";
  //BORRAMOS TABLAS PARA FINES DE DESARROLLO - PRUEBAS

  $wpdb->query("DROP TABLE {$ordersTableName}");
  $wpdb->query("DROP TABLE {$orderProductsTableName}"); */
  

}

//funciones para la estructuracion de la data extraida de la orden

//funcion para estructurar orderItems
function estructureOrderItems($orderItem){

  return array(
    "mpSKU" => $orderItem["mpSKU"],
    "description" => $orderItem["description"],
    "quantity" => $orderItem["product_qty"],
    "brand" => "" //falta extraer marca de producto,
  );

};

//funcion principal de estructuracion
function estructureAndInsertOrderInfo($id){

  global $wpdb;
  $ordersTableName = "{$wpdb->prefix}sapwc_orders";
  $ordersTransportGuideTableName = "{$wpdb->prefix}sapwc_orders_transportguides";
  $orderProductsTableName = "{$wpdb->prefix}sapwc_order_products";

  //QUERY PARA TRAER INFO DE ORDERHEADERS Y CUSTOMER:
  //SE DEBE ACTUALIZAR PARA OBTENER TRANSPORTGUIDE
  $orderHeadersAndCustomerQuery = "SELECT 
  orderS.order_id as mpOrder,
  orderGuide.transportGuide,
  orderS.customer_id
  FROM
  {$wpdb->prefix}wc_order_stats as orderS
  INNER JOIN {$wpdb->prefix}wc_customer_lookup as customer
    ON orderS.customer_id = customer.customer_id
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
  prod_extra_info.order_item_name as description
  FROM
  {$wpdb->prefix}wc_order_product_lookup as or_prod
  INNER JOIN {$wpdb->prefix}wc_product_meta_lookup as prod_info
  ON or_prod.product_id = prod_info.product_id
  INNER JOIN {$wpdb->prefix}woocommerce_order_items as prod_extra_info
  ON or_prod.order_item_id = prod_extra_info.order_item_id
  WHERE 
  or_prod.order_id = {$id} AND
  prod_extra_info.order_id = {$id}
  ";
  

  $orderHeadersAndCustomerResults = $wpdb->get_results($orderHeadersAndCustomerQuery, ARRAY_A);
  $orderItemsResult = $wpdb->get_results($orderItemsQuery, ARRAY_A);

  //funciones para estructurar datos a insertar en DB - TEMPORAL---------

  $estructureOrderProducts = function($orderProduct){
    return array(
      "product_qty" => $orderProduct["product_qty"],
      "product_id" => $orderProduct["product_id"],
      "mpOrder" => $orderProduct["mpOrder"]
    );
  };

  $estructuredOrderProducts = array_map($estructureOrderProducts, $orderItemsResult);

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
    $wpdb->insert($ordersTableName, $orderHeadersAndCustomerResults[0]);
    foreach ($estructuredOrderProducts as $key) {  
      $wpdb->insert($orderProductsTableName, $key);
    }
  }


  $order = wc_get_order( $id );
  $order_data = $order->get_data(); // The Order data

  
  
  $orderForRequestBody = array(
    "customer" => array(
      "name" => $order_data['billing']['first_name'] . $order_data['billing']['last_name'],
      "docNumber" => "", //falta docNumber
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

  return $orderForRequestBody;

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

    //DESARROLLO - PROBAR CREACION DE PEDIDO POR API
    // estructureAndInsertOrderInfo($id);


  //buscamos pedido por id extraido de los params de la request.
  //query del pedido
  $query = "SELECT 
  orderW.mpOrder, orderW.transportGuide,
  orderW.sapStatus as orderStatus, 
  CONCAT_WS(' ', customer.first_name, customer.last_name) as customer_fullname,
  customer.email, customer.city,
  customer.state as department
  FROM 
  {$ordersInternTable} as orderW
    INNER JOIN {$customersTable} as customer
    ON orderW.customer_id = customer.customer_id
  WHERE {$whereOrderQuery}";

  //ejecutamos query del pedido
  $orderById = $wpdb->get_results($query, ARRAY_A);

  //hacemos map para retornar info sin status
  $mapOrderFunc = function($order){
    return array(
      "mpOrder" => $order["mpOrder"],
      "transportGuide" => $order["transportGuide"],
      "customer_fullname" => $order["customer_fullname"],
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
        $update = $wpdb->update( 
          $ordersInternTable, 
          array("sapStatus" => $newSapStatus), 
          array("sapOrderId" => $id));
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

  //FUNCIONES DE CALLBACK PARA CADA ENDPOINT


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



//codigo a ejecutar al momento de ejecutarse correctamente el pago de un pedido
function getOrderInfoAfterCheckoutProcessed($order_id) {

  global $wpdb;

  $order = wc_get_order( $order_id );

  //OBTENEMOS PEDIDO, GUARDAMOS INTERNAMENTE Y RETORNAMOS DATA LISTA PARA ENVIARSE A API
  $dataToJson = estructureAndInsertOrderInfo( $order_id );

  echo "Info de orden:" . $dataToJson;



  echo "This is some custom text added by a function hooked to the 'woocommerce_thankyou' action.<br>";
  echo "The billing address postcode for the order is  " . $order-> get_billing_postcode() . ".";
}

add_action( 'woocommerce_thankyou', 'getOrderInfoAfterCheckoutProcessed' );


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
  orderS.id, orderS.mpOrder, orderS.transportGuide
  FROM
  {$ordersInternTable} as orderS
  WHERE
  orderS.sapStatus = 'despachado'
  ";

  $ordersSentResults = $wpdb->get_results($ordersSent, ARRAY_A);

  //en caso de haber pedidos despachados, por cada uno extraemos su estado de exxe y actualizamos
  if (sizeof($ordersSentResults) > 0) {
    foreach ($ordersSentResults as $key) {
      
      //En teoria aqui iria el codigo para extraer estado de guia de EXXE
      $exxeStatus = "statusExxe1";
      $order_id = $key["id"];

      //ejecutamos actualizacion
      $wpdb->update($ordersInternTable, array("exxeStatus" => $exxeStatus), array("id" => $order_id));
  
  
    }
  }
};

//anadimos custom hook con funcion de cron y lo programamos

/* add_action( 'sap_exxe_integration_cron', 'exxeCron');
if ( ! wp_next_scheduled( 'sap_exxe_integration_cron' ) ) {
  //scheduleamos a 5 segundos - DESARROLLO
  wp_schedule_event( time(), 'five_seconds', 'sap_exxe_integration_cron' );
  //scheduleamos a 1hora
  wp_schedule_event( time(), 'hourly', 'sap_exxe_integration_cron' );
} */







register_activation_hook(__FILE__, 'ActivateSAPIntegration');
register_deactivation_hook(__FILE__, 'DesactivateSAPIntegration');