<?php
global $wpdb;
      $ordersInternTable = "{$wpdb->prefix}sapwc_orders";

//QUERY PARA OBTENER TODAS LAS CIUDADES
$queryCiudades = "SELECT wso.city as ciudad 
FROM {$ordersInternTable} as wso
GROUP BY
wso.city
ORDER BY
wso.city ASC
";
$resultCiudades = $wpdb->get_results($queryCiudades, ARRAY_A);

$filtroMes = "";
$filtroCiudad = "";
$selectFilters = "";
if (isset($_POST["filtroMes"]) || isset($_POST["filtroCiudad"])) {
  $filtroMes = $_POST["filtroMes"];
  $filtroCiudad = $_POST["filtroCiudad"];
  	$filtroMesQuery = "";
	$filtroCiudadQuery = "";
	$selectFiltersArray = [];
  if ($filtroMes !== null && $filtroMes !== "") {
	$filtroMesQuery = "
	MONTH(orderW.orderDate) = {$filtroMes} 
	";
	array_push($selectFiltersArray, $filtroMesQuery);
  }
   
  //Act
  if ($filtroCiudad !== null && $filtroCiudad !== "") {
	$filtroCiudadQuery = "
	orderW.city = '{$filtroCiudad}' 
	";
	array_push($selectFiltersArray, $filtroCiudadQuery);
  }
  
$selectFilters = implode(" AND ", $selectFiltersArray);
if ($selectFilters !== "" && $selectFilters !== null) {
	$selectFilters = $selectFilters . " AND ";
}	

}

$valueFilter = "(orderW.colorNumber != 5 OR ISNULL(orderW.colorNumber))";
$valueF = "0";
$busquedaFilter = "";
$busqueda = "";
if (isset($_POST['busquedad'])){
    $busqueda = $_POST['busquedad'];

    if($busqueda != ""){
        $busquedaFilter = "(
            mpOrder LIKE '%{$busqueda}%' OR 
            customerFullName LIKE '%{$busqueda}%' OR 
            phoneNumber LIKE '%{$busqueda}%' OR 
            orderDate LIKE '%{$busqueda}%' OR 
            sapOrderDateShipped LIKE '%{$busqueda}%' OR 
            totalPrice LIKE '%{$busqueda}%' OR
			totalPrice LIKE '%{$busqueda}%' OR 
			docNumber LIKE '%{$busqueda}%' OR 
            colorNumber LIKE '%{$busqueda}%')";
    }
}
if(isset($_POST["valuefilters"])){
	$valueF = $_POST["valuefilters"];
	if ($valueF != "0"){
        if ($valueF == "1") {
            $valueFilter = "orderW.colorNumber = {$valueF} OR
                            orderW.colorNumber = 2";
        }else{
            $valueFilter = "orderW.colorNumber = {$valueF}";
        }
             }else{
				 $valueFilter = "(orderW.colorNumber != 5 OR ISNULL(orderW.colorNumber))";
			 }
}
$pagina = 1;
if(isset($_POST["pagina"])){
	$pagina = intval($_POST["pagina"]);	
}

//LIMITE POR PAGINA
$por_pagina = floatval(10);
//CALCULO PARA EL OFFSET DEL QUERY
$empieza = ($pagina-1)* $por_pagina;
//HACEMOS SPLIT DEL VALUE FILTER PARA INCLUIRLO EN EL CONTADOR DE TODOS LOS PEDIDOS
$replaceValueFilter = str_replace("orderW.", "", $valueFilter);
$replaceSelectFilters = str_replace("orderW.", "", $selectFilters);
//QUERY PARA CONTAR TODOS LOS PEDIDOS CON LOS FILTROS SELECCIONADOS
//VARIABLE PARA DEFINIR FILTRO DE BUSQUEDA
// {$replaceSelectFilters}  
$conditionalSearch = $busquedaFilter != '' ? "AND " . $busquedaFilter : '';
$query3 = "SELECT 
    mpOrder 
    FROM {$ordersInternTable}
    WHERE
	{$replaceSelectFilters}
	{$replaceValueFilter} 
    {$conditionalSearch}
    ";

//QUERY PARA CANTIDAD DE TODOS LOS PEDIDOS SIN FILTROS
$queryCantidadAllOrders = "SELECT mpOrder FROM $ordersInternTable WHERE colorNumber != 5 OR ISNULL(colorNumber)";
//EJECUCION DE QUERY PARA CANTIDAD
$resultado = $wpdb->get_results($query3,ARRAY_A);
$cantidadAll = $wpdb->get_results($queryCantidadAllOrders,ARRAY_A);
//PARSEO A VALOR DECIMAL DE LA CANTIDAD
$cantidad = floatval(sizeof($resultado));
//CALCULO DE LAS PAGINAS PARA PAGINACION EN BASE A CANTIDAD Y LIMITE
$cantidadPaginas = ceil($cantidad / $por_pagina);


//queries:


      //QUERY PARA LA TABLA DEL DASHBOARD
      $mainQuery = "SELECT 
      CONCAT('#', orderW.mpOrder, ' - ', orderW.customerFullName) as orderNumberName,
      orderW.phoneNumber,
      orderW.sapOrderId,
      orderW.transportGuide,
      orderW.orderDate,
      orderW.totalPrice,
      orderW.exxeStatus,
	  orderW.exxeNovedadFifteenDays,
	  orderW.exxeError,
	  orderW.docNumber,
	  orderW.sapOrderDateShipped,
	  orderW.sapStatus,
	  orderAddress,
	  city,
	  email,
	  phoneNumber,
	  department,
      orderW.colorNumber
      FROM
      {$ordersInternTable} as orderW
	  WHERE
	  {$selectFilters}
	  {$valueFilter}
      {$conditionalSearch}
      ORDER BY 
          orderW.colorNumber ASC,
          orderW.exxeStatusUpdatedAt DESC
      LIMIT
	  {$por_pagina}
	  OFFSET
	  {$empieza}
      ";
      //array para el foreach
      $mainResults = $wpdb->get_results($mainQuery, ARRAY_A);


      //QUERY PARA CARD DASHBOARD - FUNCION
      function getCardNumber($status){
        global $wpdb;
        $ordersInternTable = "{$wpdb->prefix}sapwc_orders";

        $mainQuery = "SELECT 
        CONCAT('#', orderW.mpOrder, ' - ', orderW.customerFullName) as orderNumberName,
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
      //EN RETRASO  
      $delayedOrders = sizeof(getCardNumber(3));
      //Con novedades  :
      $novedadOrders = sizeof(getCardNumber(1));
 
      //Entregados  :
      $deliveredOrders = sizeof(getCardNumber(5));
	   //total
	  $totalCards = sizeof($mainResults);
	  
	  
	  
?>
 <div class="wrap">
        <?php
             echo "<h1 class='wp-heading-inline'>" . get_admin_page_title() . "</h1>";
        ?>
		 

 
 
  <style>

.btnDeleteOrder{
    min-width: 200px;
    max-width: 250px;
    width: 100%;
}

.card-box {
    position: relative;
    color: #fff !important;
    padding: 20px 10px 20px;
    margin: 20px 0px;
    border-radius: 7px;
	cursor: pointer;
	
}

.card-box:hover {
    text-decoration: none;
    color: #f1f1f1;
}
.card-box:hover .icon i {
    font-size: 100px;
    transition: 1s;
    -webkit-transition: 1s;
}
.card-box .inner {
    padding: 5px 10px 0 10px;
}
.card-box h3 {
    font-size:1.2vw;
    font-weight: 600;
    margin: 0 0 8px 0;
    white-space: nowrap;
    padding: 0;
	    color: #fff !important;
    text-align: left;
    z-index: 200;
	position: relative;
}
.card-box p {
    font-size: 2vw;
	line-height: 0.8;
    font-weight: bold;
}
.card-box .icon {
    position: absolute;
    top: auto;
    bottom: 5px;
    right: 5px;
    z-index: 0;
    font-size: 72px;
    color: rgba(0, 0, 0, 0.15);
}
.card-box .card-box-footer {
    position: absolute;
    left: 0px;
    bottom: 0px;
    text-align: center;
    padding: 3px 0;
    color: rgba(255, 255, 255, 0.8);
    background: rgba(0, 0, 0, 0.1);
    width: 100%;
    text-decoration: none;
}
.card-box:hover .card-box-footer {
    background: rgba(0, 0, 0, 0.3);
}
.bg-blue {
    background-image: linear-gradient(to left top, #3182ce, #3d8ed6, #499ade, #56a7e6, #63b3ed);
}
.bg-green {
    background-image: linear-gradient(to left top, #48bb78, #41c18d, #3ec7a2, #43ccb4, #4fd1c5);
}
.bg-orange {
    background-image: linear-gradient(to left top, #d69e2e, #dfae39, #e7bf44, #efcf51, #f6e05e);
}
.bg-red {
    background-image: linear-gradient(to left top, #e53e3e, #ec504f, #f26160, #f77170, #fc8181);
}

.bg-grey {
    background-image: linear-gradient(to left top, #d1d1d1, #d8d8d8, #dfdfdf, #e7e7e7, #eeeeee);
}

.icon > img{
	width: 90px;
	opacity: 0.2;
}

@media (min-width:992px) {
	.col-lg-p {
    flex: 0 0 auto;
    width: 20%;
}


}
.dashicons2, .dashicons-before:before {
    line-height: 2 !important;
    font-size: 15px !important;
}

.openModalBtn{
    transition: all .3s ease-in-out !important;
}

.openModalBtn:hover{
    color: var(--e-context-primary-color-dark) !important;
}

.semaforo{
  height:50px;
   width:50px;
   -moz-border-radius:50px;
   -webkit-border-radius:50px;
   border-radius:50px;
   margin:auto;
}

th{
text-align: center !important;
}
tr{
text-align: center !important;

}
td{
	vertical-align: middle !important;
}	

.modal-body h5,h4{
	font-weight: bolder;
}
.modal-body ul{
	padding-left: 0px;
}
.modal-body ul > li{
	margin-bottom: 0px;
}

.modal-body .Card{
	margin-bottom: 10px;
}

.modal-body th{
font-weight: bolder;
}

.loading{
    width: 30px;
    height: 30px;
    margin: 10px auto;
    border-radius: 50%;
    border: 4px solid var(--e-notice-dismiss-color);
    border-bottom-color: white;
    animation: loading .5s ease-in-out infinite;
}

@keyframes loading{
    from{
        transform: rotate(0deg);
    }
    to{
        transform: rotate(360deg);
    }
}

.loadingGlobal{
     position: fixed;
     top: 0;
     left: 0;
     background-color: white;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100%;
    width: 100%;   
}

@-webkit-keyframes truck-skew {
	 0%, 15%, 48%, 75%, 100% {
		 -webkit-transition-timing-function: cubic-bezier(0.215, 0.61, 0.355, 1);
		 -moz-transition-timing-function: cubic-bezier(0.215, 0.61, 0.355, 1);
		 -ms-transition-timing-function: cubic-bezier(0.215, 0.61, 0.355, 1);
		 -o-transition-timing-function: cubic-bezier(0.215, 0.61, 0.355, 1);
		 transition-timing-function: cubic-bezier(0.215, 0.61, 0.355, 1);
		 -webkit-transform: skewX(-15deg);
		 -moz-transform: skewX(-15deg);
		 -ms-transform: skewX(-15deg);
		 -o-transform: skewX(-15deg);
		 transform: skewX(-15deg);
	}
	 35%, 38% {
		 -webkit-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -moz-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -ms-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -o-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -webkit-transform: skewX(-13deg);
		 -moz-transform: skewX(-13deg);
		 -ms-transform: skewX(-13deg);
		 -o-transform: skewX(-13deg);
		 transform: skewX(-13deg);
	}
	 65% {
		 -webkit-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -moz-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -ms-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -o-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -webkit-transform: tskewX(-12deg);
		 -moz-transform: tskewX(-12deg);
		 -ms-transform: tskewX(-12deg);
		 -o-transform: tskewX(-12deg);
		 transform: tskewX(-12deg);
	}
	 85% {
		 -webkit-transform: skewX(-14deg);
		 -moz-transform: skewX(-14deg);
		 -ms-transform: skewX(-14deg);
		 -o-transform: skewX(-14deg);
		 transform: skewX(-14deg);
	}
}
 @keyframes truck-skew {
	 0%, 15%, 48%, 75%, 100% {
		 -webkit-transition-timing-function: cubic-bezier(0.215, 0.61, 0.355, 1);
		 -moz-transition-timing-function: cubic-bezier(0.215, 0.61, 0.355, 1);
		 -ms-transition-timing-function: cubic-bezier(0.215, 0.61, 0.355, 1);
		 -o-transition-timing-function: cubic-bezier(0.215, 0.61, 0.355, 1);
		 transition-timing-function: cubic-bezier(0.215, 0.61, 0.355, 1);
		 -webkit-transform: skewX(-15deg);
		 -moz-transform: skewX(-15deg);
		 -ms-transform: skewX(-15deg);
		 -o-transform: skewX(-15deg);
		 transform: skewX(-15deg);
	}
	 35%, 38% {
		 -webkit-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -moz-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -ms-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -o-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -webkit-transform: skewX(-13deg);
		 -moz-transform: skewX(-13deg);
		 -ms-transform: skewX(-13deg);
		 -o-transform: skewX(-13deg);
		 transform: skewX(-13deg);
	}
	 65% {
		 -webkit-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -moz-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -ms-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -o-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -webkit-transform: tskewX(-12deg);
		 -moz-transform: tskewX(-12deg);
		 -ms-transform: tskewX(-12deg);
		 -o-transform: tskewX(-12deg);
		 transform: tskewX(-12deg);
	}
	 85% {
		 -webkit-transform: skewX(-14deg);
		 -moz-transform: skewX(-14deg);
		 -ms-transform: skewX(-14deg);
		 -o-transform: skewX(-14deg);
		 transform: skewX(-14deg);
	}
}
 @-webkit-keyframes wheel-front-bounce {
	 0%, 20%, 53%, 80%, 100% {
		 -webkit-transition-timing-function: cubic-bezier(0.215, 0.61, 0.355, 1);
		 -moz-transition-timing-function: cubic-bezier(0.215, 0.61, 0.355, 1);
		 -ms-transition-timing-function: cubic-bezier(0.215, 0.61, 0.355, 1);
		 -o-transition-timing-function: cubic-bezier(0.215, 0.61, 0.355, 1);
		 transition-timing-function: cubic-bezier(0.215, 0.61, 0.355, 1);
		 -webkit-transform: translate3d(0, 0, 0);
		 -moz-transform: translate3d(0, 0, 0);
		 -ms-transform: translate3d(0, 0, 0);
		 -o-transform: translate3d(0, 0, 0);
		 transform: translate3d(0, 0, 0);
	}
	 40%, 43% {
		 -webkit-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -moz-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -ms-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -o-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -webkit-transform: translate3d(0, -10px, 0);
		 -moz-transform: translate3d(0, -10px, 0);
		 -ms-transform: translate3d(0, -10px, 0);
		 -o-transform: translate3d(0, -10px, 0);
		 transform: translate3d(0, -10px, 0);
	}
	 70% {
		 -webkit-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -moz-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -ms-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -o-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -webkit-transform: translate3d(0, -5px, 0);
		 -moz-transform: translate3d(0, -5px, 0);
		 -ms-transform: translate3d(0, -5px, 0);
		 -o-transform: translate3d(0, -5px, 0);
		 transform: translate3d(0, -5px, 0);
	}
	 90% {
		 -webkit-transform: translate3d(0, -1px, 0);
		 -moz-transform: translate3d(0, -1px, 0);
		 -ms-transform: translate3d(0, -1px, 0);
		 -o-transform: translate3d(0, -1px, 0);
		 transform: translate3d(0, -1px, 0);
	}
}
 @keyframes wheel-front-bounce {
	 0%, 20%, 53%, 80%, 100% {
		 -webkit-transition-timing-function: cubic-bezier(0.215, 0.61, 0.355, 1);
		 -moz-transition-timing-function: cubic-bezier(0.215, 0.61, 0.355, 1);
		 -ms-transition-timing-function: cubic-bezier(0.215, 0.61, 0.355, 1);
		 -o-transition-timing-function: cubic-bezier(0.215, 0.61, 0.355, 1);
		 transition-timing-function: cubic-bezier(0.215, 0.61, 0.355, 1);
		 -webkit-transform: translate3d(0, 0, 0);
		 -moz-transform: translate3d(0, 0, 0);
		 -ms-transform: translate3d(0, 0, 0);
		 -o-transform: translate3d(0, 0, 0);
		 transform: translate3d(0, 0, 0);
	}
	 40%, 43% {
		 -webkit-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -moz-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -ms-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -o-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -webkit-transform: translate3d(0, -10px, 0);
		 -moz-transform: translate3d(0, -10px, 0);
		 -ms-transform: translate3d(0, -10px, 0);
		 -o-transform: translate3d(0, -10px, 0);
		 transform: translate3d(0, -10px, 0);
	}
	 70% {
		 -webkit-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -moz-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -ms-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -o-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -webkit-transform: translate3d(0, -5px, 0);
		 -moz-transform: translate3d(0, -5px, 0);
		 -ms-transform: translate3d(0, -5px, 0);
		 -o-transform: translate3d(0, -5px, 0);
		 transform: translate3d(0, -5px, 0);
	}
	 90% {
		 -webkit-transform: translate3d(0, -1px, 0);
		 -moz-transform: translate3d(0, -1px, 0);
		 -ms-transform: translate3d(0, -1px, 0);
		 -o-transform: translate3d(0, -1px, 0);
		 transform: translate3d(0, -1px, 0);
	}
}
 @-webkit-keyframes wheel-back-bounce {
	 0%, 25%, 58%, 85%, 100% {
		 -webkit-transition-timing-function: cubic-bezier(0.215, 0.61, 0.355, 1);
		 -moz-transition-timing-function: cubic-bezier(0.215, 0.61, 0.355, 1);
		 -ms-transition-timing-function: cubic-bezier(0.215, 0.61, 0.355, 1);
		 -o-transition-timing-function: cubic-bezier(0.215, 0.61, 0.355, 1);
		 transition-timing-function: cubic-bezier(0.215, 0.61, 0.355, 1);
		 -webkit-transform: translate3d(0, 0, 0);
		 -moz-transform: translate3d(0, 0, 0);
		 -ms-transform: translate3d(0, 0, 0);
		 -o-transform: translate3d(0, 0, 0);
		 transform: translate3d(0, 0, 0);
	}
	 45%, 48% {
		 -webkit-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -moz-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -ms-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -o-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -webkit-transform: translate3d(0, -10px, 0);
		 -moz-transform: translate3d(0, -10px, 0);
		 -ms-transform: translate3d(0, -10px, 0);
		 -o-transform: translate3d(0, -10px, 0);
		 transform: translate3d(0, -10px, 0);
	}
	 75% {
		 -webkit-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -moz-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -ms-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -o-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -webkit-transform: translate3d(0, -5px, 0);
		 -moz-transform: translate3d(0, -5px, 0);
		 -ms-transform: translate3d(0, -5px, 0);
		 -o-transform: translate3d(0, -5px, 0);
		 transform: translate3d(0, -5px, 0);
	}
	 95% {
		 -webkit-transform: translate3d(0, -1px, 0);
		 -moz-transform: translate3d(0, -1px, 0);
		 -ms-transform: translate3d(0, -1px, 0);
		 -o-transform: translate3d(0, -1px, 0);
		 transform: translate3d(0, -1px, 0);
	}
}
 @keyframes wheel-back-bounce {
	 0%, 25%, 58%, 85%, 100% {
		 -webkit-transition-timing-function: cubic-bezier(0.215, 0.61, 0.355, 1);
		 -moz-transition-timing-function: cubic-bezier(0.215, 0.61, 0.355, 1);
		 -ms-transition-timing-function: cubic-bezier(0.215, 0.61, 0.355, 1);
		 -o-transition-timing-function: cubic-bezier(0.215, 0.61, 0.355, 1);
		 transition-timing-function: cubic-bezier(0.215, 0.61, 0.355, 1);
		 -webkit-transform: translate3d(0, 0, 0);
		 -moz-transform: translate3d(0, 0, 0);
		 -ms-transform: translate3d(0, 0, 0);
		 -o-transform: translate3d(0, 0, 0);
		 transform: translate3d(0, 0, 0);
	}
	 45%, 48% {
		 -webkit-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -moz-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -ms-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -o-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -webkit-transform: translate3d(0, -10px, 0);
		 -moz-transform: translate3d(0, -10px, 0);
		 -ms-transform: translate3d(0, -10px, 0);
		 -o-transform: translate3d(0, -10px, 0);
		 transform: translate3d(0, -10px, 0);
	}
	 75% {
		 -webkit-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -moz-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -ms-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -o-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -webkit-transform: translate3d(0, -5px, 0);
		 -moz-transform: translate3d(0, -5px, 0);
		 -ms-transform: translate3d(0, -5px, 0);
		 -o-transform: translate3d(0, -5px, 0);
		 transform: translate3d(0, -5px, 0);
	}
	 95% {
		 -webkit-transform: translate3d(0, -1px, 0);
		 -moz-transform: translate3d(0, -1px, 0);
		 -ms-transform: translate3d(0, -1px, 0);
		 -o-transform: translate3d(0, -1px, 0);
		 transform: translate3d(0, -1px, 0);
	}
}
 @-webkit-keyframes body-bounce {
	 0%, 15%, 48%, 75%, 100% {
		 -webkit-transition-timing-function: cubic-bezier(0.215, 0.61, 0.355, 1);
		 -moz-transition-timing-function: cubic-bezier(0.215, 0.61, 0.355, 1);
		 -ms-transition-timing-function: cubic-bezier(0.215, 0.61, 0.355, 1);
		 -o-transition-timing-function: cubic-bezier(0.215, 0.61, 0.355, 1);
		 transition-timing-function: cubic-bezier(0.215, 0.61, 0.355, 1);
		 -webkit-transform: translate3d(0, 0, 0);
		 -moz-transform: translate3d(0, 0, 0);
		 -ms-transform: translate3d(0, 0, 0);
		 -o-transform: translate3d(0, 0, 0);
		 transform: translate3d(0, 0, 0);
	}
	 35%, 38% {
		 -webkit-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -moz-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -ms-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -o-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -webkit-transform: translate3d(0, -10px, 0);
		 -moz-transform: translate3d(0, -10px, 0);
		 -ms-transform: translate3d(0, -10px, 0);
		 -o-transform: translate3d(0, -10px, 0);
		 transform: translate3d(0, -10px, 0);
	}
	 65% {
		 -webkit-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -moz-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -ms-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -o-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -webkit-transform: translate3d(0, -5px, 0);
		 -moz-transform: translate3d(0, -5px, 0);
		 -ms-transform: translate3d(0, -5px, 0);
		 -o-transform: translate3d(0, -5px, 0);
		 transform: translate3d(0, -5px, 0);
	}
	 85% {
		 -webkit-transform: translate3d(0, -1px, 0);
		 -moz-transform: translate3d(0, -1px, 0);
		 -ms-transform: translate3d(0, -1px, 0);
		 -o-transform: translate3d(0, -1px, 0);
		 transform: translate3d(0, -1px, 0);
	}
}
 @keyframes body-bounce {
	 0%, 15%, 48%, 75%, 100% {
		 -webkit-transition-timing-function: cubic-bezier(0.215, 0.61, 0.355, 1);
		 -moz-transition-timing-function: cubic-bezier(0.215, 0.61, 0.355, 1);
		 -ms-transition-timing-function: cubic-bezier(0.215, 0.61, 0.355, 1);
		 -o-transition-timing-function: cubic-bezier(0.215, 0.61, 0.355, 1);
		 transition-timing-function: cubic-bezier(0.215, 0.61, 0.355, 1);
		 -webkit-transform: translate3d(0, 0, 0);
		 -moz-transform: translate3d(0, 0, 0);
		 -ms-transform: translate3d(0, 0, 0);
		 -o-transform: translate3d(0, 0, 0);
		 transform: translate3d(0, 0, 0);
	}
	 35%, 38% {
		 -webkit-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -moz-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -ms-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -o-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -webkit-transform: translate3d(0, -10px, 0);
		 -moz-transform: translate3d(0, -10px, 0);
		 -ms-transform: translate3d(0, -10px, 0);
		 -o-transform: translate3d(0, -10px, 0);
		 transform: translate3d(0, -10px, 0);
	}
	 65% {
		 -webkit-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -moz-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -ms-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -o-transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 transition-timing-function: cubic-bezier(0.755, 0.05, 0.855, 0.06);
		 -webkit-transform: translate3d(0, -5px, 0);
		 -moz-transform: translate3d(0, -5px, 0);
		 -ms-transform: translate3d(0, -5px, 0);
		 -o-transform: translate3d(0, -5px, 0);
		 transform: translate3d(0, -5px, 0);
	}
	 85% {
		 -webkit-transform: translate3d(0, -1px, 0);
		 -moz-transform: translate3d(0, -1px, 0);
		 -ms-transform: translate3d(0, -1px, 0);
		 -o-transform: translate3d(0, -1px, 0);
		 transform: translate3d(0, -1px, 0);
	}
}
 @-webkit-keyframes gas-first-flow {
	 0% {
		 opacity: 0;
		 -webkit-transition-timing-function: linear;
		 -moz-transition-timing-function: linear;
		 -ms-transition-timing-function: linear;
		 -o-transition-timing-function: linear;
		 transition-timing-function: linear;
	}
	 50% {
		 opacity: 1;
		 -webkit-transition-timing-function: linear;
		 -moz-transition-timing-function: linear;
		 -ms-transition-timing-function: linear;
		 -o-transition-timing-function: linear;
		 transition-timing-function: linear;
		 -webkit-transform: translate3d(-20px, -3px, 0);
		 -moz-transform: translate3d(-20px, -3px, 0);
		 -ms-transform: translate3d(-20px, -3px, 0);
		 -o-transform: translate3d(-20px, -3px, 0);
		 transform: translate3d(-20px, -3px, 0);
	}
	 100% {
		 opacity: 0;
		 -webkit-transition-timing-function: linear;
		 -moz-transition-timing-function: linear;
		 -ms-transition-timing-function: linear;
		 -o-transition-timing-function: linear;
		 transition-timing-function: linear;
		 -webkit-transform: translate3d(-40px, -6px, 0);
		 -moz-transform: translate3d(-40px, -6px, 0);
		 -ms-transform: translate3d(-40px, -6px, 0);
		 -o-transform: translate3d(-40px, -6px, 0);
		 transform: translate3d(-40px, -6px, 0);
	}
}
 @keyframes gas-first-flow {
	 0% {
		 opacity: 0;
		 -webkit-transition-timing-function: linear;
		 -moz-transition-timing-function: linear;
		 -ms-transition-timing-function: linear;
		 -o-transition-timing-function: linear;
		 transition-timing-function: linear;
	}
	 50% {
		 opacity: 1;
		 -webkit-transition-timing-function: linear;
		 -moz-transition-timing-function: linear;
		 -ms-transition-timing-function: linear;
		 -o-transition-timing-function: linear;
		 transition-timing-function: linear;
		 -webkit-transform: translate3d(-20px, -3px, 0);
		 -moz-transform: translate3d(-20px, -3px, 0);
		 -ms-transform: translate3d(-20px, -3px, 0);
		 -o-transform: translate3d(-20px, -3px, 0);
		 transform: translate3d(-20px, -3px, 0);
	}
	 100% {
		 opacity: 0;
		 -webkit-transition-timing-function: linear;
		 -moz-transition-timing-function: linear;
		 -ms-transition-timing-function: linear;
		 -o-transition-timing-function: linear;
		 transition-timing-function: linear;
		 -webkit-transform: translate3d(-40px, -6px, 0);
		 -moz-transform: translate3d(-40px, -6px, 0);
		 -ms-transform: translate3d(-40px, -6px, 0);
		 -o-transform: translate3d(-40px, -6px, 0);
		 transform: translate3d(-40px, -6px, 0);
	}
}
 @-webkit-keyframes gas-last-flow {
	 0% {
		 opacity: 0;
		 -webkit-transition-timing-function: linear;
		 -moz-transition-timing-function: linear;
		 -ms-transition-timing-function: linear;
		 -o-transition-timing-function: linear;
		 transition-timing-function: linear;
		 -webkit-transform: translate3d(30px, 0px, 0);
		 -moz-transform: translate3d(30px, 0px, 0);
		 -ms-transform: translate3d(30px, 0px, 0);
		 -o-transform: translate3d(30px, 0px, 0);
		 transform: translate3d(30px, 0px, 0);
	}
	 50% {
		 opacity: 1;
		 -webkit-transition-timing-function: linear;
		 -moz-transition-timing-function: linear;
		 -ms-transition-timing-function: linear;
		 -o-transition-timing-function: linear;
		 transition-timing-function: linear;
		 -webkit-transform: translate3d(10px, -5px, 0);
		 -moz-transform: translate3d(10px, -5px, 0);
		 -ms-transform: translate3d(10px, -5px, 0);
		 -o-transform: translate3d(10px, -5px, 0);
		 transform: translate3d(10px, -5px, 0);
	}
	 100% {
		 opacity: 0;
		 -webkit-transition-timing-function: linear;
		 -moz-transition-timing-function: linear;
		 -ms-transition-timing-function: linear;
		 -o-transition-timing-function: linear;
		 transition-timing-function: linear;
		 -webkit-transform: translate3d(0px, -10px, 0);
		 -moz-transform: translate3d(0px, -10px, 0);
		 -ms-transform: translate3d(0px, -10px, 0);
		 -o-transform: translate3d(0px, -10px, 0);
		 transform: translate3d(0px, -10px, 0);
	}
}
 @keyframes gas-last-flow {
	 0% {
		 opacity: 0;
		 -webkit-transition-timing-function: linear;
		 -moz-transition-timing-function: linear;
		 -ms-transition-timing-function: linear;
		 -o-transition-timing-function: linear;
		 transition-timing-function: linear;
		 -webkit-transform: translate3d(30px, 0px, 0);
		 -moz-transform: translate3d(30px, 0px, 0);
		 -ms-transform: translate3d(30px, 0px, 0);
		 -o-transform: translate3d(30px, 0px, 0);
		 transform: translate3d(30px, 0px, 0);
	}
	 50% {
		 opacity: 1;
		 -webkit-transition-timing-function: linear;
		 -moz-transition-timing-function: linear;
		 -ms-transition-timing-function: linear;
		 -o-transition-timing-function: linear;
		 transition-timing-function: linear;
		 -webkit-transform: translate3d(10px, -5px, 0);
		 -moz-transform: translate3d(10px, -5px, 0);
		 -ms-transform: translate3d(10px, -5px, 0);
		 -o-transform: translate3d(10px, -5px, 0);
		 transform: translate3d(10px, -5px, 0);
	}
	 100% {
		 opacity: 0;
		 -webkit-transition-timing-function: linear;
		 -moz-transition-timing-function: linear;
		 -ms-transition-timing-function: linear;
		 -o-transition-timing-function: linear;
		 transition-timing-function: linear;
		 -webkit-transform: translate3d(0px, -10px, 0);
		 -moz-transform: translate3d(0px, -10px, 0);
		 -ms-transform: translate3d(0px, -10px, 0);
		 -o-transform: translate3d(0px, -10px, 0);
		 transform: translate3d(0px, -10px, 0);
	}
}
 #truck {
	 -webkit-animation-duration: 1s;
	 -moz-animation-duration: 1s;
	 -ms-animation-duration: 1s;
	 -o-animation-duration: 1s;
	 animation-duration: 1s;
	 -webkit-animation-iteration-count: infinite;
	 -moz-animation-iteration-count: infinite;
	 -ms-animation-iteration-count: infinite;
	 -o-animation-iteration-count: infinite;
	 animation-iteration-count: infinite;
	 -webkit-animation-fill-mode: both;
	 -moz-animation-fill-mode: both;
	 -ms-animation-fill-mode: both;
	 -o-animation-fill-mode: both;
	 animation-fill-mode: both;
	 -webkit-animation-name: truck-skew;
	 -moz-animation-name: truck-skew;
	 -ms-animation-name: truck-skew;
	 -o-animation-name: truck-skew;
	 animation-name: truck-skew;
	 width: 100px;
}
 #truck #wheel--front {
	 -webkit-animation-duration: 1s;
	 -moz-animation-duration: 1s;
	 -ms-animation-duration: 1s;
	 -o-animation-duration: 1s;
	 animation-duration: 1s;
	 -webkit-animation-iteration-count: infinite;
	 -moz-animation-iteration-count: infinite;
	 -ms-animation-iteration-count: infinite;
	 -o-animation-iteration-count: infinite;
	 animation-iteration-count: infinite;
	 -webkit-animation-fill-mode: both;
	 -moz-animation-fill-mode: both;
	 -ms-animation-fill-mode: both;
	 -o-animation-fill-mode: both;
	 animation-fill-mode: both;
	 -webkit-animation-name: wheel-front-bounce;
	 -moz-animation-name: wheel-front-bounce;
	 -ms-animation-name: wheel-front-bounce;
	 -o-animation-name: wheel-front-bounce;
	 animation-name: wheel-front-bounce;
}
 #truck #wheel--back {
	 -webkit-animation-duration: 1s;
	 -moz-animation-duration: 1s;
	 -ms-animation-duration: 1s;
	 -o-animation-duration: 1s;
	 animation-duration: 1s;
	 -webkit-animation-iteration-count: infinite;
	 -moz-animation-iteration-count: infinite;
	 -ms-animation-iteration-count: infinite;
	 -o-animation-iteration-count: infinite;
	 animation-iteration-count: infinite;
	 -webkit-animation-fill-mode: both;
	 -moz-animation-fill-mode: both;
	 -ms-animation-fill-mode: both;
	 -o-animation-fill-mode: both;
	 animation-fill-mode: both;
	 -webkit-animation-name: wheel-back-bounce;
	 -moz-animation-name: wheel-back-bounce;
	 -ms-animation-name: wheel-back-bounce;
	 -o-animation-name: wheel-back-bounce;
	 animation-name: wheel-back-bounce;
}
 #truck #body {
	 -webkit-animation-duration: 1s;
	 -moz-animation-duration: 1s;
	 -ms-animation-duration: 1s;
	 -o-animation-duration: 1s;
	 animation-duration: 1s;
	 -webkit-animation-iteration-count: infinite;
	 -moz-animation-iteration-count: infinite;
	 -ms-animation-iteration-count: infinite;
	 -o-animation-iteration-count: infinite;
	 animation-iteration-count: infinite;
	 -webkit-animation-fill-mode: both;
	 -moz-animation-fill-mode: both;
	 -ms-animation-fill-mode: both;
	 -o-animation-fill-mode: both;
	 animation-fill-mode: both;
	 -webkit-animation-name: body-bounce;
	 -moz-animation-name: body-bounce;
	 -ms-animation-name: body-bounce;
	 -o-animation-name: body-bounce;
	 animation-name: body-bounce;
	 fill: #A66E66;
}
 #truck #gas--first {
	 -webkit-animation-timing-function: linear;
	 -moz-animation-timing-function: linear;
	 -ms-animation-timing-function: linear;
	 -o-animation-timing-function: linear;
	 animation-timing-function: linear;
	 -webkit-animation-duration: 0.7s;
	 -moz-animation-duration: 0.7s;
	 -ms-animation-duration: 0.7s;
	 -o-animation-duration: 0.7s;
	 animation-duration: 0.7s;
	 -webkit-animation-iteration-count: infinite;
	 -moz-animation-iteration-count: infinite;
	 -ms-animation-iteration-count: infinite;
	 -o-animation-iteration-count: infinite;
	 animation-iteration-count: infinite;
	 -webkit-animation-fill-mode: both;
	 -moz-animation-fill-mode: both;
	 -ms-animation-fill-mode: both;
	 -o-animation-fill-mode: both;
	 animation-fill-mode: both;
	 -webkit-animation-name: gas-first-flow;
	 -moz-animation-name: gas-first-flow;
	 -ms-animation-name: gas-first-flow;
	 -o-animation-name: gas-first-flow;
	 animation-name: gas-first-flow;
	 fill: #dedede;
}
 #truck #gas--last {
	 -webkit-animation-timing-function: linear;
	 -moz-animation-timing-function: linear;
	 -ms-animation-timing-function: linear;
	 -o-animation-timing-function: linear;
	 animation-timing-function: linear;
	 -webkit-animation-duration: 0.8s;
	 -moz-animation-duration: 0.8s;
	 -ms-animation-duration: 0.8s;
	 -o-animation-duration: 0.8s;
	 animation-duration: 0.8s;
	 -webkit-animation-iteration-count: infinite;
	 -moz-animation-iteration-count: infinite;
	 -ms-animation-iteration-count: infinite;
	 -o-animation-iteration-count: infinite;
	 animation-iteration-count: infinite;
	 -webkit-animation-fill-mode: both;
	 -moz-animation-fill-mode: both;
	 -ms-animation-fill-mode: both;
	 -o-animation-fill-mode: both;
	 animation-fill-mode: both;
	 -webkit-animation-name: gas-last-flow;
	 -moz-animation-name: gas-last-flow;
	 -ms-animation-name: gas-last-flow;
	 -o-animation-name: gas-last-flow;
	 animation-name: gas-last-flow;
	 fill: #ececec;
}
 

#truck {

	width: 200px;
  display: block;
  position: absolute;
  top: 50%;
  left: 50%;
  margin-top: -61px;
  margin-left: -100px;

}

.wp-core-ui{
	margin: 0px 5px 0px 0px;
}
.filter{
	padding: 0px 5px;
}
.dang {background-color: #f35959}
.prim {background-color: #0275d8}
.succ {background-color: #5cb85c}
.warning {background-color: #f0ad4e;}
.bg-inactive {background-color:  #A9A9A9;}

.statis .cardStyle{
	position: relative;
	padding: 15px;
	overflow: hidden;
	border-radius: 3px;
	margin-bottom: 25px;
	height: 100% !important;
	width: 100% !important;
	display: flex !important;
	flex-direction: column !important;

}
.statis .cardStyle i {
	position: absolute;
	height: 70px;
	width: 68px;
	font-size: 20px;
	padding: 15px;
	top: -25px;
	left: -25px;
	background-color: rgba(255, 255, 255, 0.15) !important;
	line-height: 60px;
	text-align: right;
	border-radius: 50%;
	color: white;
}
.lead {
	font-size: 16px;
	font-weight: bold;
}

.statis .cardStyle h3{
	color:white;
	font-size: 1.1rem;
	font-weight: bold;
}
.statis .cardStyle p{
	color:white;
}

.statis .cardStyle h3::after {
	content: "";
	height: 2px;
	width: 70%;
	margin: auto;
	background-color: rgba(255, 255, 255, 0.12) !important;
	display: block;
	margin-top: 10px;
}
.btn-label {
	position: relative;
	left: -13px;
	display: inline-block;
	padding: 6px 12px;
	background: rgba(0, 0, 0, 0.15);
	border-radius: 3px 0 0 3px;
}

.messageSap ul{
	list-style-type: circle;
    list-style-position: inside;
    text-align: left;
}


.messageSap ul > li {
	font-size: 13px;
	color: white;
	padding: 5px 0px;
}
.b-b-default {
    border-bottom: 1px solid #e0e0e0;
}



.m-b-20 {
    margin-bottom: 20px
}

.p-b-5 {
    padding-bottom: 5px !important
}

.card .card-block p {
    line-height: 25px
}

.m-b-10 {
    margin-bottom: 10px
}

.text-muted {
    color: #919aa3 !important
}

.b-b-default {
    border-bottom: 1px solid #e0e0e0
}

.f-w-600 {
    font-weight: 600
}

.m-b-20 {
    margin-bottom: 20px
}

.m-t-40 {
    margin-top: 20px
}

.p-b-5 {
    padding-bottom: 5px !important
}

.m-b-10 {
    margin-bottom: 10px
}

.m-t-40 {
    margin-top: 20px
}

@media only screen and (min-width: 1400px) {
    p {
        font-size: 14px
    }
}
.text2 > h6{
	overflow-wrap: break-word !important;
}

.modalcla{
	max-width: 700px !important;
}

  </style>
  
 
<div class="container-fluid">
<form id="filterOrders" method="POST">
<input type="hidden"  name="valuefilters" >
</form>

    <div class="row">
        <div class="col-lg-p col-sm-6" s>
            <div class="card-box bg-blue Cardfilter" data-filterValue="4">
                <div class="inner">
                    <h3> Pedidos en proceso </h3>
                    <p>
				  <?php
					print_r ($inProcessOrders);
					?>
					</p>
                </div>
                <div class="icon">
                   
                 
					<img src="/wp-content/plugins/sap-integration/admin/img/recargar.png"></img>
				
                

                </div>
              
            </div>
        </div>
        <div class="col-lg-p col-sm-6">
            <div class="card-box bg-orange Cardfilter" data-filterValue="3">
                <div class="inner">
                    <h3> Pedidos con retraso </h3>
                    <p>
					  <?php
					print_r ($delayedOrders);
					?>
					</p>
                </div>
                <div class="icon">
                    	<img src="/wp-content/plugins/sap-integration/admin/img/retrasar.png"></img>
                </div>
              
            </div>
        </div>
        <div class="col-lg-p col-sm-6">
            
            <div class="card-box bg-red Cardfilter" data-filterValue="1">
                <div class="inner">
                    <h3> Pedidos con novedad </h3>
                    <p>
						<?php
					print_r ($novedadOrders + $novedadDelayedOrders);
					?>
					</p>
                </div>
                <div class="icon">
                  	<img src="/wp-content/plugins/sap-integration/admin/img/advertencia.png"></img>
                </div>
              
            </div>
        </div>
        <div class="col-lg-p col-sm-6">
            <div class="card-box bg-green" data-page="admin.php?page=sap-integration%2Fadmin%2FEntregados.php">
                <div class="inner">
                    <h3> Pedidos entregrados </h3>
                    <p> 
	                <?php
					print_r ($deliveredOrders);
					?>
					</p>
                </div>
                <div class="icon">
            <img src="/wp-content/plugins/sap-integration/admin/img/correcto.png"></img>
                </div>
             
            </div>
        </div>
     
    <div class="col-lg-p col-sm-6">
            <div class="card-box bg-grey Cardfilter" data-filterValue="0">
                <div class="inner">
                    <h3> Total pedidos</h3>
                    <p>
					<?php
					echo sizeof($cantidadAll);
					?>
					</p>
                </div>
                <div class="icon">
                 <img src="/wp-content/plugins/sap-integration/admin/img/portapapeles.png"></img>
                </div>
             
            </div>
        </div>
     
  
    </div>

</div>


<div class="d-flex justify-content-between mb-3 mt-3">
    <form method="POST" class="form_search">
      <input type="text" name="busquedad" id="busquedad" placeholder="Buscar" style="line-height: 1.5;">

      <button type="submit" name="buscar" class="button btn_search"><span
          class="dashicons dashicons2 dashicons-search"></span>Buscar</button>
      <button type="submit" name="buscar" class="button btn_search reset"><span
          class="dashicons dashicons2 dashicons-image-rotate"></span>Resetear</button>
    </form>

    <?php
      if(sizeof($mainResults) > 0){
        ?>
          <div class="tablenav-pages"><span class="displaying-num"><?php echo $cantidad;?> elementos</span>
      <span class="pagination-links">
        <a data-pageNumber="<?php echo 1; ?>"
          class="first-page button buttonPagination <?php $isDisabled = $pagina == 1 ? "disabled" : ""; echo $isDisabled;  ?>"><span
            class="screen-reader-text">Primera página</span><span aria-hidden="true">«</span></a>
        <a data-pageNumber="<?php echo $pagina == 1 ? 1: $pagina-1; ?>"
          class="prev-page button buttonPagination <?php $isDisabled = $pagina == 1 ? "disabled" : "";  echo $isDisabled; ?>"><span
            class="screen-reader-text">Página anterior</span><span aria-hidden="true">‹</span></a>

        <span class="paging-input"><label for="current-page-selector" class="screen-reader-text">Página
            actual</label><input class="current-page" id="current-page-selector" type="text" name="paged"
            value="<?php echo $pagina; ?>" size="1" aria-describedby="table-paging"><span class="tablenav-paging-text">
            de <span class="total-pages"><?php echo $cantidadPaginas; ?></span></span></span>
        <a data-pageNumber="<?php echo $pagina+1; ?>"
          class="next-page button buttonPagination <?php $isDisabled = $pagina == $cantidadPaginas ? "disabled" : ""; echo $isDisabled; ?>"><span
            class="screen-reader-text">Página siguiente</span><span aria-hidden="true">›</span></a>
        <a data-pageNumber="<?php echo $cantidadPaginas; ?>"
          class="last-page button buttonPagination <?php $isDisabled = $pagina == $cantidadPaginas ? "disabled" : "";  echo $isDisabled;  ?>"><span
            class="screen-reader-text">Última página</span><span aria-hidden="true">»</span></a></span>
    </div>
        <?php
      }
      ?>
  
 
	</div>
<form method="POST" >
	<div class="d-flex mb-3 mt-3">




	  <select id="my-select" class="wp-core-ui" name="filtroMes">
	  <option value="" >Elige un mes</option>
	  <option value="1" >Enero</option>
	  <option value="2" >Febrero</option>
	  <option value="3" >Marzo</option>
	  <option value="4" >Abril</option>
	  <option value="5" >Mayo</option>
	  <option value="6" >Junio</option>
	  <option value="7" >Junio</option>
	  <option value="8" >Agosto</option>
	  <option value="9" >Septiembre</option>
	  <option value="10" >Octubre</option>
	  <option value="11" >Noviembre</option>
	  <option value="12" >Diciembre</option>
	  </select>
	  <select id="my-select" class="wp-core-ui" name="filtroCiudad">
	  <option value="" >Elige una ciudad</option>
<?php
foreach ($resultCiudades as $key => $value) {
	?>
	 <option value="<?php echo $value["ciudad"]; ?>" ><?php echo $value["ciudad"]; ?></option>
	<?php
}

?>
	  </select>
	  <button type="submit" name="filtrar" class="button filter "><span
          class="dashicons dashicons2 dashicons-filter"></span>Filtrar</button>



	</div>

</form>



<form id="formPagination" method="POST">
    <input type="hidden" name="pagina">
	<input type="hidden" value="<?php echo $filtroMes; ?>" name="filtroMes">
	<input type="hidden" value="<?php echo $filtroCiudad; ?>" name="filtroCiudad">
    <input type="hidden" value="<?php echo $valueF; ?>" name="valuefilters">
    <input type="hidden" value="<?php echo $busqueda; ?>" name="busquedad">
</form>


	

       <table class="wp-list-table widefat fixed striped pages"  >
                <thead class"xd">
				     <th style="width:20%;"># pedido</th>
                  
					<th>Telefono</th>
					<th>Fecha pedido</th>
                    <th>Fecha Envio</th>
					<th>Total + Envío</th>
					<th>Estado</th>		
                </thead>
                <tbody id="the-list">

		   <?php
			if(sizeof($mainResults) == 0){

                echo "<tr>
                
                <td>No hay resultado</td>
                
                </tr>";
        
               }else{
                foreach ($mainResults as $key => $value){
                    $nombre = $value['orderNumberName'];
                    $telefono = $value['phoneNumber'];
                    $fpedido = $value['orderDate'];
                    $fenvio = $value['sapOrderDateShipped'];
                    $precio = "$" . number_format($value['totalPrice'], 2);
					$status = $value['colorNumber'];
					$direccion = $value['orderAddress'];
					$ciudad = $value['city'];
					$correo = $value['email'];
					$departamento = $value['department'];
					$exxeStatus = $value['exxeStatus'];
					$exxeNovedadFifteenDays = $value['exxeNovedadFifteenDays'];
	                $exxeError = $value['exxeError'];
	                $sapStatus = $value['sapStatus'];
					$docNumber = $value['docNumber'];
					$sapOrderDateShipped = $value['fecha'];
					$sapOrderId = $value['sapOrderId'];
					$transportGuide = $value['transportGuide'];
					
					$fondo = "";
					if($status == 1){
					$fondo = "linear-gradient(to left top, #e53e3e, #ec504f, #f26160, #f77170, #fc8181);";
				    }if($status == 3){
					$fondo = "linear-gradient(to left top, #d69e2e, #dfae39, #e7bf44, #efcf51, #f6e05e);";
				    }
					if($status == 4){
					$fondo = "linear-gradient(to left top, #3182ce, #3d8ed6, #499ade, #56a7e6, #63b3ed);";
				    }
					if($status == 5){
					$fondo = "linear-gradient(to left top, #48bb78, #41c18d, #3ec7a2, #43ccb4, #4fd1c5);";
				    }
                    echo"
                    <tr>
                    <td>
                        <a style='cursor: pointer;' class='openModalBtn text-primary d-flex w-100 justify-content-end align-items-center' data-bs-toggle='modal' data-bs-target='#exampleModal'>
                            <span data-campo='pedido' class='orderContent'>$nombre</span>
                            <span class='ms-4 dashicons dashicons-visibility'></span>
                            </a>
                    </td>
                    <td data-campo='telefono' class='orderContent' >$telefono</td>
                    <td>$fpedido</td>
                    <td class='OrderDate' data-orderDate='$fenvio'>$fenvio</td>
                    <td>$precio</td>
					<td style='display: none;' class='orderContent' data-campo='direccion'>$direccion</td>
					<td style='display: none;' class='orderContent' data-campo='ciudad'>$ciudad</td>
					<td style='display: none;' class='orderContent' data-campo='correo'>$correo</td>
				    <td style='display: none;' class='orderContent' data-campo='departamento'>$departamento</td> 
				    <td style='display: none;' class='orderContent exStatus' data-campo='exxeStatus'>$exxeStatus</td> 
				    <td><div class='semaforo' data-colorValue='$status' style='background-image: $fondo;' ></td>
					<td style='display: none;' class='Exx2' data-colorValue2='$exxeNovedadFifteenDays'></td> 
				    <td style='display: none;' class='ExxE' data-ExError='$exxeError'></td> 
					<td style='display: none;' class='sapE orderContent' data-SapStatus='$sapStatus' data-campo='sapStatus'>$sapStatus</td> 
					<td style='display: none;' class='orderContent' data-campo='docNumber'>$docNumber</td>
					<td style='display: none;' class='orderContent' data-campo='sapOrderId'>$sapOrderId</td>
					<td style='display: none;' class='orderContent' data-campo='transportGuide'>$transportGuide</td>
                    </tr>";
                }
               }
            ?>
                </tbody>	
        </table>
		<div>
	</div>
	
			

<!-- Modal -->
<div class="modal fade " id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modalcla">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">
            <div class="d-flex justify-content-center align-items-center">
                <span>Pedido #<bold data-campo="pedido" class="orderInfo orderCustomerNumber">71</bold></span> 
                <div class="modal-btnEnviar" style="display: none;">
               <button type="button" class="btn btn-labeled btn-warning btnReenviar" style="padding: 0px; width:180px;" >
                <span class="btn-label"><i class="fa fa-play-circle-o"></i></span>Reenviar pedido</button>
              </div>
            </div>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
	    <section class='statis text-center mb-2 mt-2'>
         
	    <div class="row d-flex">
    <div class="col-6 d-flex pl-5 pr-5 " style="padding: 0px 5px 0px 5px;" >
     <div class="card-sap cardStyle"  style="background-image: linear-gradient(to left top, #3182ce, #3d8ed6, #499ade, #56a7e6, #63b3ed);">
                            <i class="fa fa-random "></i>
                            <h3>Estado SAP</h3>
							<div class="h-100 d-flex justify-content-center flex-column">
                            	<p class="lead align-self-center orderInfo " data-campo='sapStatus'>Tradado parcialmente</p>
							</div>
							<div class="Novedad-card" style="display: none;">
							<p class="lead align-self-center">Mensajes de Error:</p>
								<div class="messageSap">
									<ul>
										<li>Mensaje de error 1</li>
										</li>Mensaje de error 2</li>
									</ul>
								</div>
			                </div>
                        </div>
    </div>
    <div class="col-6 d-flex " style="padding: 0px 5px 0px 5px;">
<div class="card-exxe cardStyle"  style="background-image: linear-gradient(to left top, #d1d1d1, #d8d8d8, #dfdfdf, #e7e7e7, #eeeeee);">
                            <i class="fa fa-truck"></i>
                            <h3>Estado de entrega</h3>
							
							<div class="h-100 d-flex justify-content-center flex-column">
							<div class="Novedad-card2" style="display: none;">
							<p class="lead align-self-center">Novedad</p>
							<p class="lead align-self-center">Tipo de Novedad:</p>
			                </div>

							<div class="divRetrasp" style="display: none;">
							<p class="lead align-self-center">Retraso</p>
							<p class="lead align-self-center">Tiempo de retraso:<br><span class="delayTime">15 a 20 dias</span></p>
			                </div>
							<p class="lead align-self-center orderInfo" data-campo='exxeStatus'>No ha sido despachado</p>
							</div>
                        </div>
    </div>


  </div>
  </section>
  <h5 class="m-b-20 p-b-5 b-b-default f-w-600">Información del pedido</h5>
  <table id="" class="table table-bordered">
    <thead>
	<th style="width: 25%;">Campo</th>
    <th>Información</th>
    </thead>
	<tbody>
    <tr>
        <td>Número de pedido</td>
		<td class="orderInfo orderCustomerNumber" data-campo="orderNumber" >36584</td>
    </tr>
	<tr>
        <td>Número de pedido en SAP</td>
		<td class="orderInfo " data-campo="sapOrderId" >550498435</td>
    </tr>
	<tr>
        <td>Número de Guía de Transporte</td>
		<td class="orderInfo " data-campo="transportGuide" >114023759</td>
    </tr>
	</tbody>
</table>
  <h5 class="m-b-20 p-b-5 b-b-default f-w-600">Información del cliente</h5>
  <table id="" class="table table-bordered">
    <thead>
	<th style="width: 25%;">Campo</th>
    <th>Información</th>
    </thead>
	<tbody>
    <tr>
        <td>Nombre</td>
		<td class="orderInfo orderCustomerName" data-campo="pedido" >Tarjeta madre 2.0</td>
    </tr>
	<tr>
        <td>Dirección</td>
		<td class="orderInfo " data-campo="direccion" >Tarjeta madre 2.0</td>
    </tr>
	<tr>
        <td>Ciudad</td>
		<td class="orderInfo " data-campo="ciudad" >Tarjeta madre 2.0</td>
    </tr>
	<tr>
        <td>Departamento</td>
		<td class="orderInfo " data-campo="departamento" >Tarjeta madre 2.0</td>
    </tr>
	<tr>
        <td>Correo Electrónico</td>
		<td class="orderInfo " data-campo="correo" >Tarjeta madre 2.0</td>
    </tr>
	<tr>
        <td>Número de documento</td>
		<td class="orderInfo " data-campo="docNumber" >Tarjeta madre 2.0</td>
    </tr>
	<tr>
        <td>Teléfono</td>
		<td class="orderInfo " data-campo="telefono" >Tarjeta madre 2.0</td>
    </tr>
	</tbody>
</table>
	
	
<table id="orderProductsTable" class="wp-list-table widefat fixed  pages">
    <thead>
	<th>Producto</th>
	<th>SKU</th>
    <th>Unidades del producto</th>
    <th>Total valor productos</th>
    </thead>
	<tbody>
    <tr>
        <td>Tarjeta madre 2.0</td>
        <td>45983</td>
        <td>1</td>
        <td>$10.00</td>
    </tr>
	</tbody>
</table>

      </div>
      <div class="modal-footer" style="display: none;">
        <button type="button" class="btnDeleteOrder btn btn-outline-danger">Eliminar Pedido</button>
      </div>
    </div>
  </div>
</div>

<div class="loadingGlobal" style="display: none; ">
     <svg version="1.1" id="truck" xmlns="https://www.w3.org/2000/svg" xmlns:xlink="https://www.w3.org/1999/xlink" x="0px" y="0px"
	 viewBox="0 0 370 225" enable-background="new 0 0 370 225" xml:space="preserve"  style="z-index:10;">
<path id="wheel--front" d="M300,170c13.8,0,25,11.2,25,25s-11.2,25-25,25s-25-11.2-25-25S286.2,170,300,170z M285,195
	c0,8.3,6.7,15,15,15s15-6.7,15-15s-6.7-15-15-15S285,186.7,285,195z"/>
<path id="wheel--back" d="M170,170c13.8,0,25,11.2,25,25s-11.2,25-25,25c-13.8,0-25-11.2-25-25S156.2,170,170,170z M155,195
	c0,8.3,6.7,15,15,15s15-6.7,15-15s-6.7-15-15-15S155,186.7,155,195z"/>
<path id="body" d="M345,50h-45V40H100v155h40c0-16.6,13.4-30,30-30s30,13.4,30,30h70c0-16.6,13.4-30,30-30s30,13.4,30,30h35v-75
	L345,50z M346.2,115h-45V65h35l10,40V115z"/>
<path id="gas--last" d="M39.7,168.2c-0.6,2.5-3.1,4-5.6,3.4c-0.5-0.1-1-0.4-1.5-0.6c-2.5,2.4-6.1,3.6-9.7,2.7
	c-3.4-0.8-5.9-3.2-7.2-6.1c-0.8,1-2.2,1.5-3.5,1.2c-1.5-0.4-2.5-1.6-2.6-3.1c-2.5-1.1-4-3.9-3.3-6.6c0.7-3.1,3.9-5,7-4.3
	c0,0,0.1,0,0.1,0c-0.4-1.5-0.5-3.1-0.1-4.8c1.2-5,6.2-8,11.1-6.8c3.8,0.9,6.4,4,7,7.6c1.3-0.4,2.8-0.5,4.3-0.2c4.3,1,7,5.4,6,9.7
	c-0.4,1.7-1.3,3.1-2.6,4.2C39.7,165.6,40,166.9,39.7,168.2z"/>
<path id="gas--first" d="M90.6,175.2c1.4-2,2.1-4.7,1.4-7.3c-1.1-4.8-6-7.8-10.8-6.7c-1,0.2-2,0.7-2.8,1.2c0-0.2-0.1-0.3-0.1-0.5
	c-1.1-4.8-6-7.8-10.8-6.7c-3.3,0.8-5.7,3.3-6.6,6.3c-1.8-0.6-3.8-0.8-5.9-0.3c-5.6,1.3-9.1,7-7.8,12.6c1,4.1,4.2,7,8.1,7.8
	c1.5,5.4,7,8.6,12.5,7.3c2.2-0.5,4-1.7,5.4-3.2c1.9,0.8,4.1,1,6.3,0.5c1.7-0.4,3.2-1.2,4.4-2.2c1.3,2.2,3.9,3.3,6.5,2.7
	c3.2-0.8,5.2-4,4.5-7.2C94.3,177.4,92.7,175.8,90.6,175.2z"/>
</svg>
</div>


<script>
const forms = document.querySelectorAll("form");
const loadingGlobal = document.querySelector(".loadingGlobal")
forms.forEach(form => {
     form.addEventListener("submit", () => {
        loadingGlobal.setAttribute("style", "display: flex; z-index:900;");

  })
})

//FUNCIONALIDAD PARA IR A TAB ENTREGADOS AL DAR CLICK EN CARD
const cards = document.querySelectorAll("div[data-page]");
cards.forEach(card => {
	card.addEventListener("click", () => {
		loadingGlobal.setAttribute("style", "display: flex; z-index:900;");
		let page = card.getAttribute("data-page");
		window.location.href = page;
	})
})

//FUNCIONALIDAD DE FILTRO PARA DASHBOARD POR CARDS
const formFilterOrders = document.querySelector("#filterOrders");
const cardsFilter = document.querySelectorAll(".Cardfilter");
cardsFilter.forEach(card => {
	card.addEventListener("click", () => {
        let filterValue = card.getAttribute("data-filterValue");
        formFilterOrders.firstElementChild.value = filterValue; 
		formFilterOrders.submit();
		loadingGlobal.setAttribute("style", "display: flex; z-index:900;");
	})
})

//FUNCIONALIDAD PARA EL BUSCADOR

const inputBusqueda = document.querySelector("#busquedad");
	const buttonReset = document.querySelector(".reset");
	buttonReset.addEventListener('click', () => {
		inputBusqueda.value = ""
	});

//FUNCIONALIDAD PARA LOS ELEMENTOS DE LA PAGINACION
const formPagination = document.querySelector("#formPagination ");
const btnsPagination = document.querySelectorAll(".buttonPagination");
btnsPagination.forEach(btn => {
	btn.addEventListener("click", () => {
        if (btn.classList.contains("disabled")) {
            return;
        }
		let btnPage = btn.getAttribute("data-pageNumber");
		formPagination.children[0].value = btnPage;
		formPagination.submit();
		loadingGlobal.setAttribute("style", "display: flex; z-index:900;");
	})
})

//FUNCIONALIDAD PARA EL INPUT PAGE DE LA PAGINACION
const inputPage = document.querySelector("#current-page-selector");
inputPage.addEventListener("keypress", (e) => {
	let value = inputPage.value;
	
	if(e.keyCode === 13){
		formPagination.children[0].value = value;
	    formPagination.submit();
	}
})

//funcionalidad para ver info del pedido y productos en modal al abrirlo

window.addEventListener("DOMContentLoaded", () => {
 // Hacemos peticion a API para extraer productos del pedido
 const myHeaders = new Headers();
            if (document.domain === "fncsap.ingeniosoft.co") {
                myHeaders.append("Authorization", "Basic dXNlclNBUDpIcllsIFpXWFggakc0VCBPYzNoIG95WDcgRE5RYgo=");                
            }else{
                //AQUI VA EL HEADER DE AUTHORIZATION PARA INSTALAR EN PRODUCTIVO
                myHeaders.append("Authorization", "Basic dXNlclNBUDp3WUpkIDFWekggSnBQbyBRaFRBIDUxeW0gaXRITA==");                
            }
    //botones para abrir modal
    const openModalBtns = document.querySelectorAll(".openModalBtn");
    //FOOTER DEL MODAL
    const modalFooter = document.querySelector(".modal-footer");
	const btnEnviar = document.querySelector(".modal-btnEnviar");
    //BOTON PARA BORRAR
	const cardExxe = document.querySelector(".card-exxe");
    const cardSap = document.querySelector(".card-sap");
    const btnDeleteOrder = document.querySelector(".btnDeleteOrder");
    //tabla de productos
    const orderProductsTable = document.querySelector("#orderProductsTable");
	const btnReenviar = document.querySelector(".btnReenviar");
	


    //elementos donde se mostrara la informacion
    const orderInfoElements = [...document.querySelectorAll(".orderInfo")];
    let orderNumber;
        
    openModalBtns.forEach(btn => {
        btn.addEventListener("click", (e) => {
            //extraemos elementos con info a extraer
            let orderContentElements = [...btn.parentElement.parentElement.querySelectorAll(".orderContent")];
            //extraemos color de semaforo
            let semaforoColor = btn.parentElement.parentElement.querySelector(".semaforo").getAttribute("style");
            let semaforoNumber = btn.parentElement.parentElement.querySelector(".semaforo").getAttribute("data-colorValue");
			
            //recorremos celdas y colocamos su textcontent en el elemento correspondiente, el cual hace match con su atributo data-campo
            orderContentElements.forEach(td => {
                let tdDataCampo = td.getAttribute("data-campo");
                let matchElement = orderInfoElements.filter(v => v.getAttribute("data-campo") === tdDataCampo);
                let tdTextSplit = tdDataCampo === "pedido" && td.textContent.trim().split("#")[1].split("-");
                //condicional para cuando es el numero/nombre de pedido, extraer el numero/nombre respectivamente
                if (
                    matchElement[0]?.classList.contains("orderCustomerNumber") && 
                    matchElement[1]?.classList.contains("orderCustomerName")
                ) {
                    matchElement[0].textContent = tdTextSplit[0].trim(); 
                    matchElement[1].textContent = tdTextSplit[1].trim(); 
                    orderNumber = tdTextSplit[0].trim();
                }else{
					if(matchElement[0]){
					matchElement[0].textContent = td.textContent;
					}
                }
            })

			//colocamos orderNumber en elemento de tabla
			const orderNumberTd = document.querySelector("td[data-campo='orderNumber']");
			orderNumberTd.textContent = orderNumber;

			let Exepedido = btn.parentElement.parentElement.querySelector(".Exx2").getAttribute("data-colorValue2");
			let ExError = btn.parentElement.parentElement.querySelector(".ExxE").getAttribute("data-ExError");
			let sapStatus = btn.parentElement.parentElement.querySelector(".sapE").getAttribute("data-SapStatus").toLowerCase();
			let exxeStatuts = btn.parentElement.parentElement.querySelector(".exStatus").textContent;
			let dateOrderSap = btn.parentElement.parentElement.querySelector(".OrderDate").getAttribute("data-orderDate");
			const delayDateSpan = document.querySelector(".delayTime");
			const novedadModal = document.querySelector(".Novedad-card");
			const novedadModalExe = document.querySelector(".Novedad-card2");
			const RetrasodModal = document.querySelector(".divRetrasp");


			  if (Exepedido == "1") {
                modalFooter.setAttribute("style", "display: block;");
            }else{
                modalFooter.setAttribute("style", "display: none;");
            }
			if(sapStatus == "despachado"){
				novedadModal.setAttribute("style", "display: none;");
				cardSap.setAttribute("style", "background-image: linear-gradient(to left top, #48bb78, #41c18d, #3ec7a2, #43ccb4, #4fd1c5);");
			}
			if (exxeStatuts == "" || exxeStatuts == null) {
				let exxeStatusElement = document.querySelector("p[data-campo='exxeStatus']");
				exxeStatusElement.innerHTML = "Aún no ha sido despachado";
				cardExxe.setAttribute("style", " background-image: linear-gradient(to left top, #d1d1d1, #d8d8d8, #dfdfdf, #e7e7e7, #eeeeee);");
			}   
		    if(semaforoNumber == "4") {
				novedadModal.setAttribute("style", "display: none;");
                 if((exxeStatuts == "" || exxeStatuts == null) && sapStatus != "despachado" ){
					cardExxe.setAttribute("style", " background-image: linear-gradient(to left top, #d1d1d1, #d8d8d8, #dfdfdf, #e7e7e7, #eeeeee);");
					cardSap.setAttribute("style", "background-image: linear-gradient(to left top, #3182ce, #3d8ed6, #499ade, #56a7e6, #63b3ed);");
				 }else if(sapStatus == "despachado"){
					cardExxe.setAttribute("style", "background-image: linear-gradient(to left top, #3182ce, #3d8ed6, #499ade, #56a7e6, #63b3ed);");	
				 }
			}else if(semaforoNumber == "3") {
				novedadModal.setAttribute("style", "display: none;");
				RetrasodModal.setAttribute("style", "display: block;");	          
				let Fechaform = dateOrderSap.split(" ")[0].split("-").join("-");
				let FechaSapMs  = new Date(Fechaform).getTime();
				function zero(n) {
            	return (n>9 ? '' : '0') + n;
                  }
            	let date  = new Date();
            	let strDate = date.getFullYear() + "-" + zero((date.getMonth()+1)) + "-" + zero(date.getDate());
				let FechaActualMs  = new Date(strDate).getTime();
			 	let diff = FechaSapMs - FechaActualMs;
			 	let dias = diff/(1000*60*60*24);
				delayDateSpan.textContent = Math.floor(Math.abs(dias)) + " días";

			 	cardExxe.setAttribute("style", "background-image: linear-gradient(to left top, #d69e2e, #dfae39, #e7bf44, #efcf51, #f6e05e);");

            }else if(semaforoNumber == "1" ){
			if (ExError == "1") {
				cardExxe.setAttribute("style", "background-image: linear-gradient(to left top, #e53e3e, #ec504f, #f26160, #f77170, #fc8181);");
				novedadModalExe.setAttribute("style", "display: block;");
			}else{
				novedadModalExe.setAttribute("style", "display: none;");
				//FUNCIONALIDAD BOTON REENVIAR
				btnEnviar.setAttribute("style", "display: block; padding-left: 5px;");
				btnReenviar.addEventListener("click", () => {

				const requestOptions = {
					method: 'POST',
					headers: myHeaders,
				};

				btnReenviar.classList.add("disabled")
				btnReenviar.innerHTML = "<div class='loading'></div>";

				fetch(`https://${document.domain}/wp-json/sapintegration/v1/orders/resend/${orderNumber}`, requestOptions)
						
					.then(response => response.json())
					.then(result => {
						btnReenviar.innerHTML = `<span class="btn-label"><i class="fa fa-play-circle-o"></i></span>Reenviar pedido</button>`;
						btnReenviar.classList.remove("disabled");
						if (result.result === true) {
							alert("El pedido se ha reenviado correctamente");
						}else{
							alert("Hubo un error el reenviar el pedido, por favor intentelo mas tarde");
						}
						window.location.reload();
					})
					.catch(error => {
						btnReenviar.innerHTML = `<span class="btn-label"><i class="fa fa-play-circle-o"></i></span>Reenviar pedido</button>`;
						btnReenviar.classList.remove("disabled");
						alert("Hubo un error el reenviar el pedido, por favor intentelo mas tarde");
					});

				})
				cardSap.setAttribute("style", "background-image: linear-gradient(to left top, #e53e3e, #ec504f, #f26160, #f77170, #fc8181);");
				if (sapStatus == "no se pudo enviar pedido a sap. por favor, reenvíe el pedido.") {
					novedadModal.setAttribute("style", "display: none;");
				}else{
					novedadModal.setAttribute("style", "display: block;");
				}


				cardExxe.setAttribute("style", "background-image: linear-gradient(to left top, #d1d1d1, #d8d8d8, #dfdfdf, #e7e7e7, #eeeeee);");

				//FUNCIONALIDAD MENSAJES DE ERROR
				const requestOptions = {
				method: 'GET',
				headers: myHeaders,
				};
			
				const ulSap = document.querySelector(".messageSap");
				
				const uL = ulSap.querySelector("ul");
				uL.innerHTML = `
				<div class="loading"></div>
				`;
				let uLHTML = "";

				fetch(`https://${document.domain}/wp-json/sapintegration/v1/orders/messages/${orderNumber}`, requestOptions)
				.then(response => response.json())
				.then(result => {
					result?.messages.forEach(({message}) => {
						uLHTML += 
						`
						<li>${message}</li>
						`;
					})
					uL.innerHTML = uLHTML;
				})
			}
            }
			if (semaforoNumber != "1" ) {
				novedadModal.setAttribute("style", "display: none;");
				novedadModalExe.setAttribute("style", "display: none;");
				btnEnviar.setAttribute("style", "display: none;");
			}
			if (semaforoNumber != "3" ) {
				RetrasodModal.setAttribute("style", "display: none;");
			}
            const requestOptions = {
            method: 'GET',
            headers: myHeaders,
            };

            const tbody = orderProductsTable.querySelector("tbody");
            tbody.innerHTML = `
            <td></td>
            <td style='transform: translateX(50%);'><div class='loading'></div></td>
            <td></td>
            <td></td>
            `;
            let tbodyHTML = "";

            fetch(`https://${document.domain}/wp-json/sapintegration/v1/orders/products/${orderNumber}`, requestOptions)
            .then(response => response.json())
            .then(result => {
                result?.products.forEach(({productName, quantity, price, sku}) => {
                    tbodyHTML += 
                    ` <tr>
                        <td>${productName}</td>
                        <td>${sku}</td>
                        <td>${quantity}</td>
                        <td>$${price}</td>
                    </tr>`;
                })
                tbody.innerHTML = tbodyHTML;
            })
            .catch(error => tbody.innerHTML = "");
        })
    })
    //FUNCIONALIDAD PARA BORRAR PEDIDO CUANDO ESTA EN ESTADO 2
    btnDeleteOrder.addEventListener("click", () => {

        const requestOptions = {
            method: 'POST',
            headers: myHeaders,
        };

        btnDeleteOrder.innerHTML = "<div class='loading'></div>";
        btnDeleteOrder.classList.add("disabled")

        fetch(`https://${document.domain}/wp-json/sapintegration/v1/orders/delete/${orderNumber}`, requestOptions)
            .then(response => response.json())
            .then(result => {
                if (result.result === true) {
                    btnDeleteOrder.innerHTML = "Eliminar Pedido";
                    btnDeleteOrder.classList.remove("disabled");
                    alert("El pedido ha sido eliminado correctamente");
                    window.location.reload();
                }
            })
            .catch(error => {
                btnDeleteOrder.innerHTML = "Eliminar Pedido";
                btnDeleteOrder.classList.remove("disabled");
            });

    })
	 
})



</script>

