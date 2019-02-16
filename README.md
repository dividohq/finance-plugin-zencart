 -----------------
Installation Notes      
------------------



1. Copy the includes folder to your zencart folder. This contains all the required module files, Do not overwrite any original files while copying.



2. Login into your admin page. While viewing the payment modules in admin you will see the "Finance Payment" module in the list. Click the **[Install]** button.



3.Add the finance_main_handler.php file to zencart folder. 

4. You can set the configuration of this module in the Modules => Payment => Finance Payment.



5.To add options of finance plans for each product page, You have to edit few files.
   

a) You need to open the update_product.php under your admin folder and then /includes/modules/update_product.php and find the below code at the top of the file

`   if (isset($_GET['pID'])) $products_id = zen_db_prepare_input($_GET['pID']);`

   and insert the above code under above code.
```
   /* Finance payment module start */
     require(DIR_FS_CATALOG.'includes/modules/payment/financepayment.php');
     $finance = new financepayment();
     $finance->updatePlans($_GET['pID'],$_POST['financepayment']);
   /* Finance payment module end */ 
```

b) You need to open the collect_info.php under your admin/includes/modules/product/collect_info.php and find the below code at the bottom of the file
```
   <tr>
       <td class="main"><?php echo TEXT_PRODUCTS_SORT_ORDER; ?></td>
       <td class="main"><?php echo zen_draw_separator('pixel_trans.gif', '24', '15') . '&nbsp;' . zen_draw_input_field('products_sort_order', $pInfo->products_sort_order); ?></td>
   </tr>
```
   and insert the below code under above code.
```
<!-- Finance payment module start -->
   <?php require(DIR_FS_CATALOG.'includes/modules/payment/financepayment.php');
      $finance = new financepayment();
      echo $finance->getProductOptionsAdmin($_GET['pID']);
   ?>
<!-- Finance payment module end -->
```
