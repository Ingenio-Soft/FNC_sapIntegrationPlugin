<?php

      //QUERY PARA CARD DASHBOARD - FUNCION
      function getCardNumber($status){
        global $wpdb;
        $ordersInternTable = "{$wpdb->prefix}sapwc_orders";

        $mainQuery = "SELECT 
		CONCAT_WS('#', orderW.mpOrder, '', orderW.customerFullName) as orderNumberName,
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

  
	   $EntregadoTable = getCardNumber(5);
	  
	  
?>
 <div class="wrap">
        <?php
             echo "<h1 class='wp-heading-inline'>" . get_admin_page_title() . "</h1>";
        ?>
		 

 

 

   <?php
   
       if (isset($_POST['buscar'])){
		   $busqueda = $_POST['busquedad'];
   
global $wpdb;
      $ordersInternTable = "{$wpdb->prefix}sapwc_orders";
	  
	  
    if($busqueda != ""){
	$query2 = "
 SELECT
CONCAT('#', orderW.mpOrder, orderW.customerFullName) as orderNumberName,
 orderW.phoneNumber,
 orderW.orderDate,
 orderW.sapOrderDateShipped,
 orderW.totalPrice,
 orderW.colorNumber
 FROM $ordersInternTable as orderW WHERE ( mpOrder LIKE '%{$busqueda}%' OR customerFullName LIKE '%{$busqueda}%' OR phoneNumber LIKE '%{$busqueda}%' OR orderDate LIKE '%{$busqueda}%' OR sapOrderDateShipped LIKE '%{$busqueda}%' OR totalPrice LIKE '%{$busqueda}%' OR colorNumber LIKE '%{$busqueda}%' )  ";
		 $EntregadoTable = $wpdb->get_results($query2,ARRAY_A);
	}
	
	   }
	  
    ?> 
	
<div style="padding: 20px 0px;">	   
	<form  method="POST" class="form_search">
	<input type="text" name="busquedad" id="busquedad" placeholder="Buscar">
	<input type="submit" name="buscar" class="button btn_search">
	<button type="submit" name="buscar" class="button btn_search reset">Reset</button>
</form>

<div class="tablenav-pages" style="float: right;"><span class="displaying-num">28 elementos</span>
<span class="pagination-links"><span class="tablenav-pages-navspan button disabled" aria-hidden="true">«</span>
<span class="tablenav-pages-navspan button disabled" aria-hidden="true">‹</span>
<span class="paging-input"><label for="current-page-selector" class="screen-reader-text">Página actual</label><input class="current-page" id="current-page-selector" type="text" name="paged" value="1" size="1" aria-describedby="table-paging"><span class="tablenav-paging-text"> de <span class="total-pages">2</span></span></span>
<a class="next-page button" href="https://sitio2.ingeniosoft.co/wp-admin/edit.php?post_type=shop_order&amp;paged=2"><span class="screen-reader-text">Página siguiente</span><span aria-hidden="true">›</span></a>
<a class="last-page button" href="https://sitio2.ingeniosoft.co/wp-admin/edit.php?post_type=shop_order&amp;paged=2"><span class="screen-reader-text">Última página</span><span aria-hidden="true">»</span></a></span>
</div>
</div>
<script>
	const inputBusqueda = document.querySelector("#busquedad");
	
	const buttonReset = document.querySelector(".reset");
	
	
	buttonReset.addEventListener('click', () => {
		inputBusqueda.value = ""
	});
	
</script>
	

       <table class="wp-list-table widefat fixed striped pages "  >
                <thead>
				     <th style="width:20%;"># pedido</th>
                  
					<th>Telefono</th>
					<th>Fecha pedido</th>
                    <th>Fecha Envio</th>
					<th>Total</th>
	
                </thead>
                <tbody id="the-list">

		   <?php
			
				  foreach ($EntregadoTable as $key => $value){
                    $nombre = $value['orderNumberName'];
                    $telefono = $value['phoneNumber'];
                    $fpedido = $value['orderDate'];
                    $fenvio = $value['sapOrderDateShipped'];
                    $precio = $value['totalPrice'];	
                    echo"
                    <tr>
                    <td ><a class='btn btn-primary' data-bs-toggle='modal' data-bs-target='#exampleModal'>$nombre</a></td>
                    <td>$telefono</td>
                    <td>$fpedido</td>
                    <td>$fenvio</td>
                    <td>$precio</td>
                    </tr>";
                }
            ?>
 

                </tbody>
				
				
				
        </table>

<style>


th{
text-align: center !important;
}
tr{
text-align: center !important;

}
td{
	vertical-align: middle !important;
}	

</style>
	
			


<!-- Modal -->
<div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Modal title</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        ...
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary">Save changes</button>
      </div>
    </div>
  </div>
</div>