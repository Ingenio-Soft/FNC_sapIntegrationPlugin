<?php
//RAMA SEBASTIAN

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

$pagina = 1;
if(isset($_POST["pagina"])){
	$pagina = $_POST["pagina"];	
}

//LIMITE POR PAGINA
$por_pagina = floatval(1);
//CALCULO PARA EL OFFSET DEL QUERY
$empieza = ($pagina-1)* $por_pagina;

//VARIABLE PARA DEFINIR FILTRO DE BUSQUEDA
$conditionalSearch = $busquedaFilter != '' ? "AND " . $busquedaFilter : '';
//QUERY PARA CANTIDAD DE TODOS LOS PEDIDOS SIN FILTROS
$queryCantidadAllOrders = "SELECT mpOrder FROM $ordersInternTable WHERE colorNumber = 5 {$conditionalSearch}";
//EJECUCION DE QUERY PARA CANTIDAD
$resultado = $wpdb->get_results($queryCantidadAllOrders,ARRAY_A);
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
    orderW.colorNumber = 5
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


	  
?>
<div class="wrap">
  <?php
             echo "<h1 class='wp-heading-inline'>" . get_admin_page_title() . "</h1>";
        ?>




  <style>
    .btnDeleteOrder {
      min-width: 200px;
      max-width: 250px;
      width: 100%;
    }


    .dashicons2,
    .dashicons-before:before {
      line-height: 2 !important;
      font-size: 15px !important;
    }

    .openModalBtn {
      transition: all .3s ease-in-out !important;
    }

    .openModalBtn:hover {
      color: var(--e-context-primary-color-dark) !important;
    }

    .semaforo {
      height: 50px;
      width: 50px;
      -moz-border-radius: 50px;
      -webkit-border-radius: 50px;
      border-radius: 50px;
      margin: auto;
    }

    th {
      text-align: center !important;
    }

    tr {
      text-align: center !important;

    }

    td {
      vertical-align: middle !important;
    }

    .modal-body h5,
    h4 {
      font-weight: bolder;
    }

    .modal-body ul {
      padding-left: 0px;
    }

    .modal-body ul>li {
      margin-bottom: 0px;
    }

    .modal-body .Card {
      margin-bottom: 10px;
    }

    .modal-body th {
      font-weight: bolder;
    }

    .loading {
      width: 30px;
      height: 30px;
      margin: 10px auto;
      border-radius: 50%;
      border: 4px solid var(--e-notice-dismiss-color);
      border-bottom-color: white;
      animation: loading .5s ease-in-out infinite;
    }

    @keyframes loading {
      from {
        transform: rotate(0deg);
      }

      to {
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
  </style>



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
            class="screen-reader-text">Primera p??gina</span><span aria-hidden="true">??</span></a>
        <a data-pageNumber="<?php echo $pagina == 1 ? 1: $pagina-1; ?>"
          class="prev-page button buttonPagination <?php $isDisabled = $pagina == 1 ? "disabled" : "";  echo $isDisabled; ?>"><span
            class="screen-reader-text">P??gina anterior</span><span aria-hidden="true">???</span></a>

        <span class="paging-input"><label for="current-page-selector" class="screen-reader-text">P??gina
            actual</label><input class="current-page" id="current-page-selector" type="text" name="paged"
            value="<?php echo $pagina; ?>" size="1" aria-describedby="table-paging"><span class="tablenav-paging-text">
            de <span class="total-pages"><?php echo $cantidadPaginas; ?></span></span></span>
        <a data-pageNumber="<?php echo $pagina+1; ?>"
          class="next-page button buttonPagination <?php $isDisabled = $pagina == $cantidadPaginas ? "disabled" : ""; echo $isDisabled; ?>"><span
            class="screen-reader-text">P??gina siguiente</span><span aria-hidden="true">???</span></a>
        <a data-pageNumber="<?php echo $cantidadPaginas; ?>"
          class="last-page button buttonPagination <?php $isDisabled = $pagina == $cantidadPaginas ? "disabled" : "";  echo $isDisabled;  ?>"><span
            class="screen-reader-text">??ltima p??gina</span><span aria-hidden="true">??</span></a></span>
    </div>
        <?php
      }
      ?>
  
  </div>
  <form id="formPagination" method="POST">
    <input type="hidden" name="pagina">
    <input type="hidden" value="<?php echo $value; ?>" name="valuefilters">
    <input type="hidden" value="<?php echo $busqueda; ?>" name="busquedad">
  </form>




  <table class="wp-list-table widefat fixed striped pages"  >
        <thead>
				 <th style="width:20%;"># pedido</th>
         <th>Telefono</th>
				 <th>Fecha pedido</th>
         <th>Fecha Envio</th>
				 <th>Total</th>
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
          $precio = $value['totalPrice'];
					$status = $value['colorNumber'];
					$direccion = $value['orderAddress'];
					$ciudad = $value['city'];
					$correo = $value['email'];
					$departamento = $value['department'];

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

				 
          </tr>";
                }
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
           
            </div>
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <h4>Detalles de facturaci??n</h3>
            <ul>
              <li class="orderInfo orderCustomerName" data-campo="pedido">Velez Serna</li>
              <li class="orderInfo" data-campo="direccion">Cra12#323-1b4</li>
              <li class="orderInfo" data-campo="ciudad">Cali</li>
              <li class="orderInfo" data-campo="departamento">Valle del cauca</li>
            </ul>
            <div class="Card">
              <h5>Correo electronico
          </h4>
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
    </div>
  </div>
</div>

<div class="loadingGlobal" style="display: none; ">
     <svg version="1.1" id="truck" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"
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

    //botones para abrir modal
    const openModalBtns = document.querySelectorAll(".openModalBtn");
    //tabla de productos
    const orderProductsTable = document.querySelector("#orderProductsTable");
    //elementos donde se mostrara la informacion
    const orderInfoElements = [...document.querySelectorAll(".orderInfo")];
    let orderNumber;
        
    openModalBtns.forEach(btn => {
        btn.addEventListener("click", (e) => {
            //extraemos elementos con info a extraer
            let orderContentElements = [...btn.parentElement.parentElement.querySelectorAll(".orderContent")];
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

})

</script>

<style>
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




</style>