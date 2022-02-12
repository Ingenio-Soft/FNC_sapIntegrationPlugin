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
$orderProductsTableName = "{$wpdb->prefix}sapwc_order_products";

  //CREAMOS TABLAS PARA MANEJO INTERNO DE PEDIDOS Y SUS PRODUCTOS


  $createOrdersTableQuery = "CREATE TABLE IF NOT EXISTS {$ordersTableName} (
    id INT NOT NULL AUTO_INCREMENT,
    transportGuide varchar(100) NOT NULL,
    mpOrder INT NOT NULL,
    customer_id INT NOT NULL,
    sapStatus varchar(100) NULL, 
    exxeStatus varchar(100) NULL,
    CONSTRAINT sapwc_orders_PK PRIMARY KEY (id)
  )
  ENGINE=MyISAM
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_520_ci;
  "; 

$wpdb->query($createOrdersTableQuery);


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


}

function DesactivateSAPIntegration(){

  global $wpdb;

  $ordersTableName = "{$wpdb->prefix}sapwc_orders";
  $orderProductsTableName = "{$wpdb->prefix}sapwc_order_products";
  //BORRAMOS TABLAS PARA FINES DE DESARROLLO - PRUEBAS

  $wpdb->query("DROP TABLE {$ordersTableName}");
  $wpdb->query("DROP TABLE {$orderProductsTableName}");
  

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
  $orderProductsTableName = "{$wpdb->prefix}sapwc_order_products";

  //QUERY PARA TRAER INFO DE ORDERHEADERS Y CUSTOMER:
  //SE DEBE ACTUALIZAR PARA OBTENER TRANSPORTGUIDE
  $orderHeadersAndCustomerQuery = "SELECT 
  orderS.order_id as mpOrder,
  orderS.customer_id
  FROM
  {$wpdb->prefix}wc_order_stats as orderS
  INNER JOIN {$wpdb->prefix}wc_customer_lookup as customer
  ON orderS.customer_id = customer.customer_id
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

  $estructureOrder = function($order){
    return array(
      "customer_id" => $order["customer_id"],
      "mpOrder" => $order["mpOrder"],
      "transportGuide" => "1234567654"
    );
  };

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
    $wpdb->insert($ordersTableName, array_map($estructureOrder, $orderHeadersAndCustomerResults)[0]);
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
      "transportGuide" => "", //falta anadirlo desde el plugin mentor shipping
      "mpOrder" => $orderHeadersAndCustomerResults[0]["mpOrder"], 
    ),
    "orderItems" => array_map("estructureOrderItems", $orderItemsResult)

  );

  return $orderForRequestBody;

}

//registramos nuevo endpoint en la API REST del WP
add_action( 'rest_api_init', function () {
    register_rest_route( 'sapintegration/v1', '/orders/(?P<id>\d+)', array(
      'methods' => 'POST',
      'callback' => 'changeOrderStatus',
      'args' => array(
        'id' => array(
          'validate_callback' => function($param, $request, $key) {
            return is_numeric( $param );
          }
        ),
      ),
      'permission_callback' => function () {
        return current_user_can( 'sap_change_status' );
      }
    ) );
  } );

  function changeOrderStatus($request){

    global $wpdb;

    $id = $request["id"];

    $ordersTable = "{$wpdb->prefix}wc_order_stats";
    $ordersInternTable = "{$wpdb->prefix}sapwc_orders";
    $customersTable = "{$wpdb->prefix}wc_customer_lookup";

    $ordersTableExists = $wpdb->query("SHOW TABLES like {$ordersInternTable}");
    $customersTableExists = $wpdb->query("SHOW TABLES like {$customersTable}");

    if (sizeof($ordersTableExists) > 0 && sizeof($customersTableExists) > 0 ) {
      //buscamos pedido por id extraido de los params de la request.
    $query = "SELECT 
    orderW.mpOrder, orderW.transportGuide, 
    CONCAT_WS(' ', customer.first_name, customer.last_name) as customer_fullname,
    customer.email, customer.city,
    customer.state as department
    FROM 
    {$ordersInternTable} as orderW
      INNER JOIN {$customersTable} as customer
      ON orderW.customer_id = customer.customer_id
    WHERE orderW.mpOrder = {$id}";

    $orderById = $wpdb->get_results($query, ARRAY_A);
    $data;
    $statusCode;

    try {
      if (sizeof($orderById) == 0) {
        $data = array(
          "status" => "404",
          "error" => "No se ha encontrado pedido por el ID especificado en la petición."
        );
        $statusCode = 404;
      }else{
  
        $newState = "despachado";
        $update = $wpdb->update( $ordersInternTable, array("sapStatus" => $newState), array("mpOrder" => $id));
  
        if ($update === false) {
          $data = array(
            "status" => "500",
            "error" => "Ocurrio un error al intentar actualizar el estado del pedido. Contáctese con el administrador del sitio"
          );
          $statusCode = 500;
        }elseif($update === 0){
  
          $data = array(
            "status" => "200",
            "error" => "El pedido ya ha sido actualizado al estado completado anteriormente.",
          );
          $statusCode = 200;
          
        }else{
  
          $data = array(
            "status" => "201",
            "pedido" => $orderById[0],
            "message" => "Se actualizó correctamente el pedido."
          );
          $statusCode = 201;
  
        }
  
      }
    } catch (\Throwable $th) {
      $data = array(
        "status" => "500",
        "error" => "Ocurrio un error al intentar actualizar el estado del pedido. Contáctese con el administrador del sitio. Info del error: {$th}", 
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







register_activation_hook(__FILE__, 'ActivateSAPIntegration');
register_deactivation_hook(__FILE__, 'DesactivateSAPIntegration');