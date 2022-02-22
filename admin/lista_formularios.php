<?php


global $wpdb;
      $ordersInternTable = "{$wpdb->prefix}sapwc_orders";

$valueFilter = "orderW.colorNumber != 5";
$value = "0";
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
            colorNumber LIKE '%{$busqueda}%')";
    }
}
if(isset($_POST["valuefilters"])){
	$value = $_POST["valuefilters"];
	if ($value != "0"){
        if ($value == "1") {
            $valueFilter = "orderW.colorNumber = {$value} OR
                            orderW.colorNumber = 2";
        }else{
            $valueFilter = "orderW.colorNumber = {$value}";
        }
             }else{
				 $valueFilter = "orderW.colorNumber != 5";
			 }
}
$pagina = 1;
if(isset($_POST["pagina"])){
	$pagina = $_POST["pagina"];	
}

//LIMITE POR PAGINA
$por_pagina = floatval(5);
//CALCULO PARA EL OFFSET DEL QUERY
$empieza = ($pagina-1)* $por_pagina;
//HACEMOS SPLIT DEL VALUE FILTER PARA INCLUIRLO EN EL CONTADOR DE TODOS LOS PEDIDOS
$replaceValueFilter = str_replace("orderW.", "", $valueFilter);
//QUERY PARA CONTAR TODOS LOS PEDIDOS CON LOS FILTROS SELECCIONADOS
//VARIABLE PARA DEFINIR FILTRO DE BUSQUEDA
$conditionalSearch = $busquedaFilter != '' ? "AND " . $busquedaFilter : '';
$query3 = "SELECT 
    mpOrder 
    FROM $ordersInternTable 
    WHERE {$replaceValueFilter} 
    {$conditionalSearch}
    ";

//QUERY PARA CANTIDAD DE TODOS LOS PEDIDOS SIN FILTROS
$queryCantidadAllOrders = "SELECT mpOrder FROM $ordersInternTable WHERE colorNumber != 5";
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
      orderW.orderDate,
      orderW.sapOrderDateShipped,
      orderW.totalPrice,
      orderW.exxeStatus,
	  orderAddress,
	  city,
	  email,
	  phoneNumber,
	  department,
      orderW.colorNumber
      FROM 
      {$ordersInternTable} as orderW
	  WHERE
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
      //Mas de 15 dias con novedad  :
      $novedadDelayedOrders = sizeof(getCardNumber(2));
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

  </style>
  
 
<div class="container-fluid">
<form id="filterOrders" method="POST">
<input type="hidden"  name="valuefilters" >
</form>

    <div class="row">
        <div class="col-lg-p col-sm-6" s>
            <div class="card-box bg-green Cardfilter" data-filterValue="4">
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
            <div class="card-box bg-blue" data-page="admin.php?page=sap-integration%2Fadmin%2FEntregados.php">
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
	<form  method="POST" class="form_search">
	<input type="text" name="busquedad" id="busquedad" placeholder="Buscar" style="line-height: 1.5;">
	
	<button type="submit" name="buscar" class="button btn_search"><span class="dashicons dashicons2 dashicons-search"></span>Buscar</button>
	<button type="submit" name="buscar" class="button btn_search reset"><span class="dashicons dashicons2 dashicons-image-rotate"></span>Resetear</button>
</form>

<div class="tablenav-pages"><span class="displaying-num"><?php echo $cantidad;?> elementos</span>
<span class="pagination-links">
<a  data-pageNumber="<?php echo 1; ?>"  class="first-page button buttonPagination <?php $isDisabled = $pagina == 1 ? "disabled" : ""; echo $isDisabled;  ?>"><span class="screen-reader-text">Primera página</span><span aria-hidden="true">«</span></a>
<a data-pageNumber="<?php echo $pagina == 1 ? 1: $pagina-1; ?>" class="prev-page button buttonPagination <?php $isDisabled = $pagina == 1 ? "disabled" : "";  echo $isDisabled; ?>"><span class="screen-reader-text">Página anterior</span><span aria-hidden="true">‹</span></a>
	
<span class="paging-input"><label for="current-page-selector" class="screen-reader-text">Página actual</label><input class="current-page" id="current-page-selector" type="text" name="paged" value="<?php echo $pagina; ?>" size="1" aria-describedby="table-paging"><span class="tablenav-paging-text"> de <span class="total-pages"><?php echo $cantidadPaginas; ?></span></span></span>
<a data-pageNumber="<?php echo $pagina+1; ?>" class="next-page button buttonPagination <?php $isDisabled = $pagina == $cantidadPaginas ? "disabled" : ""; echo $isDisabled; ?>"><span class="screen-reader-text">Página siguiente</span><span aria-hidden="true">›</span></a>
<a data-pageNumber="<?php echo $cantidadPaginas; ?>" class="last-page button buttonPagination <?php $isDisabled = $pagina == $cantidadPaginas ? "disabled" : "";  echo $isDisabled;  ?>"><span class="screen-reader-text">Última página</span><span aria-hidden="true">»</span></a></span>
</div>
</div>
<form id="formPagination" method="POST">
    <input type="hidden" name="pagina">
    <input type="hidden" value="<?php echo $value; ?>" name="valuefilters">
    <input type="hidden" value="<?php echo $busqueda; ?>" name="busquedad">
</form>


	

       <table class="wp-list-table widefat fixed striped pages"  >
                <thead class"xd">
				     <th style="width:20%;"># pedido</th>
                  
					<th>Telefono</th>
					<th>Fecha pedido</th>
                    <th>Fecha Envio</th>
					<th>Total</th>
					<th>Estado</th>		
                </thead>
                <tbody id="the-list">

		   <?php
			
				
				  foreach ($mainResults as $key => $value){
                    $nombre = $value['orderNumberName'];
                    $telefono = $value['phoneNumber'];
                    $fpedido = $value['orderDate'];
                    $fenvio = $value['sapOrderDateShipped'];
                    $precio = $value['totalPrice'];
					$status = $value['colorNumber'];
					$direccion = $value['orderAddress'];
					$ciudad = $value['city'];
					$correo = $value['email'];
					$departamento = $value['department'];
					$exxeStatus = $value['exxeStatus'];
					
					$fondo = "";
					if($status == 1){
					$fondo = "linear-gradient(to left top, #e53e3e, #ec504f, #f26160, #f77170, #fc8181);";
				    }if($status == 2){
					$fondo = "linear-gradient(to left top, #f76e11, #f97b1f, #fb882c, #fd9438, #ff9f45);";
				    }if($status == 3){
					$fondo = "linear-gradient(to left top, #d69e2e, #dfae39, #e7bf44, #efcf51, #f6e05e);";
				    }
					if($status == 4){
					$fondo = "linear-gradient(to left top, #48bb78, #41c18d, #3ec7a2, #43ccb4, #4fd1c5);";
				    }
					if($status == 5){
					$fondo = "linear-gradient(to left top, #3182ce, #3d8ed6, #499ade, #56a7e6, #63b3ed);";
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
                    <td>$fenvio</td>
                    <td>$precio</td>
					<td style='display: none;' class='orderContent' data-campo='direccion'>$direccion</td>
					<td style='display: none;' class='orderContent' data-campo='ciudad'>$ciudad</td>
					<td style='display: none;' class='orderContent' data-campo='correo'>$correo</td>
				    <td style='display: none;' class='orderContent' data-campo='departamento'>$departamento</td> 
				    <td style='display: none;' class='orderContent' data-campo='exxeStatus'>$exxeStatus</td> 
				    <td><div class='semaforo' data-colorValue='$status' style='background-image: $fondo;' ></td>
                    </tr>";
                }
            ?>
                </tbody>	
        </table>
		<div>
	</div>
	
			

<!-- Modal -->
<div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">
            <div class="d-flex justify-content-center align-items-center">
                <span>Pedido #<bold data-campo="pedido" class="orderInfo orderCustomerNumber">71</bold></span> 
                <div data-campo="exxeStatus" class="fs-6 orderInfo rounded ms-3 text-white p-2 bg-primary d-flex justify-content-center align-items-center">
                    En Proceso
                </div>  
            </div>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
      <h4>Detalles de facturación</h3>
	  <ul>
	  <li class="orderInfo orderCustomerName" data-campo="pedido">Velez Serna</li>
	  <li class="orderInfo" data-campo="direccion">Cra12#323-1b4</li>
	  <li class="orderInfo" data-campo="ciudad">Cali</li>
	  <li class="orderInfo" data-campo="departamento">Valle del cauca</li>
	  </ul>
        <div class="Card">
        <h5>Correo electronico</h4>
        <span class="orderInfo" data-campo="correo">infante1399@outlook.com</span>
        </div>
        <div class="Card">
        <h5>Telefono</h4>
        <span class="orderInfo" data-campo="telefono">43456789</span>
        </div>
	<table id="orderProductsTable" class="wp-list-table widefat fixed  pages">
    <thead>
	<th>Producto</th>
    <th>Cantidad</th>
    <th>Total</th>
    </thead>
	<tbody>
    <tr>
        <td>Tarjeta madre 2.0</td>
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

<script>

//FUNCIONALIDAD PARA IR A TAB ENTREGADOS AL DAR CLICK EN CARD
const cards = document.querySelectorAll("div[data-page]");
cards.forEach(card => {
	card.addEventListener("click", () => {
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

    //botones para abrir modal
    const openModalBtns = document.querySelectorAll(".openModalBtn");
    //FOOTER DEL MODAL
    const modalFooter = document.querySelector(".modal-footer");
    //BOTON PARA BORRAR
    const btnDeleteOrder = document.querySelector(".btnDeleteOrder");
    //tabla de productos
    const orderProductsTable = document.querySelector("#orderProductsTable");
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
            if (semaforoNumber == "2") {
                modalFooter.setAttribute("style", "display: block;");
            }else{
                modalFooter.setAttribute("style", "display: none;");
            }
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
                    matchElement[0].textContent = td.textContent;
                }
                //en caso de ser el elemento de status, colocar color de semaforo
                if (matchElement[0]?.getAttribute("data-campo") === "exxeStatus") {
                    matchElement[0].setAttribute("style", semaforoColor);
                }
            })

            // Hacemos peticion a API para extraer productos del pedido
            const myHeaders = new Headers();
            if (document.domain === "fncsap.ingeniosoft.co") {
                myHeaders.append("Authorization", "Basic dXNlclNBUDpIcllsIFpXWFggakc0VCBPYzNoIG95WDcgRE5RYgo=");                
            }else{
                //AQUI VA EL HEADER DE AUTHORIZATION PARA INSTALAR EN PRODUCTIVO
            }

            const requestOptions = {
            method: 'GET',
            headers: myHeaders,
            };

            const tbody = orderProductsTable.querySelector("tbody");
            tbody.innerHTML = `
            <td></td>
            <td><div class='loading'></div></td>
            <td></td>
            `;
            let tbodyHTML = "";

            fetch(`https://${document.domain}/wp-json/sapintegration/v1/orders/products/${orderNumber}`, requestOptions)
            .then(response => response.json())
            .then(result => {
                result?.products.forEach(({productName, quantity, price}) => {
                    tbodyHTML += 
                    ` <tr>
                        <td>${productName}</td>
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
        const myHeaders = new Headers();
        if (document.domain === "fncsap.ingeniosoft.co") {
            myHeaders.append("Authorization", "Basic dXNlclNBUDpIcllsIFpXWFggakc0VCBPYzNoIG95WDcgRE5RYgo=");                
        }else{
            //AQUI VA EL HEADER DE AUTHORIZATION PARA INSTALAR EN PRODUCTIVO
        }

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