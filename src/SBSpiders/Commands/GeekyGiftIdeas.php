<?php
namespace Spiders\Commands;
 
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Helper\TableCell;
use Sunra\PhpSimple\HtmlDomParser;
use Spiders\Helpers\Webdriver;
use Spiders\Helpers\Common;
use Spiders\Helpers\Log;
use Spiders\Models\CacheModel;

class GeekyGiftIdeas extends Command
{
    /**
     * Identificador de tipo de producto en cache
     */
    const PRODUCT_TYPE_ID = 3;

    /**
    *   @var $info Definición de valores asociados a la tienda
    *   - Los valores definidos en esta sección aplican para todo el comando
    */
    protected $info = [
        'site_name'     => 'GeekyGiftIdeas',
        'site_base_url' => 'https://www.geekygiftideas.com',
        'site_url'      => 'https://www.geekygiftideas.com/category/gifts/',
        'profit_percent'=> 1.15
        
    ];



    /** 
    *   @var $categoryFilter Filtro por categoria 
    *   - Si no se especifica se procesaran todas las categorias definidas en $categories
    */
    protected $categoryFilter;


    /** 
    *   @var $categories Definición de categorias a obtener
    *   - Se definen con el id de la categoria en tienda y el string de categoria magento donde seran ubicadas 
    */
    protected $categories = [
  
        'gifts-for-men'             => 'DRS/Gifts/Gifts for Men', 
        'gifts-for-dad'             => 'DRS/Gifts/Gifts for Men', 
        'gifts-for-women'           => 'DRS/Gifts/Gifts for Women',
        'gifts-for-mom'           => 'DRS/Gifts/Gifts for Women',
        'gifts-for-kids'            => 'DRS/Gifts/Gifts for Kids',
        
        

    ];

    /**
     *  @var $tags Definición de tags utilizados para obtener información de los productos
     */
    protected $tags = [
        'pagination'            => '.pagination a',                                  // en caso de existir, indica la paginacion (acceder mediante ->href)
        'product_url'           => '.hover_anons h3 a',               // Tag de url de producto en listado acceder mediante href
        'name'                  => '.clearbox',                              // Tag del nombre de producto                            
        'special_price'         => '.rh_regular_price',                         // tag de precio de la oferta
        'normal_price'          => '.rh_regular_price',                        // tag del precio original (contiene el string  orig. $000)
        'description'           => '.post-inner p',                      // tag de descripcion corta del producto acceder mediante 0
        'image_first'           => '.top_featured_image img',            // Tag de Imagen principal acceder mediante ->getAttribute('data-src')
        'sku'                   => '.post-inner',                                  // tag de numero SKU se debe limpiar SKU #123123123
        'inventory'             => '.store-inventory-content',
        
 

    ];


    /**
     * @var $nosave Indica si se ejecutará sin realizar cambios en la cache
     */
    protected $nosave = false; 

    /**
     * @var $count Contadores de acciones con los productos obtenidos
     */ 
    protected $count = [
        'inserted'              => 0,
        'updated'               => 0,
        'disabled'              => 0,
        'invalid-title'         => 0,
        'invalid-description'   => 0,
        'invalid-price'         => 0,
        'invalid-image'         => 0,
        'invalid-item-code'     => 0,
        'invalid-shipping'      => 0,
        'invalid-stock'         => 0,
        'invalid-retail'        => 0,
        'invalid-sponsored'     => 0,
        'error-product-url'     => 0,
        'error-page-url'        => 0,
        'error-insert-product'  => 0,
        'error-update-product'  => 0
    ];

    /**
     * @var $log Log de productos procesados
     */ 
    protected $log = [
        'inserted'              => array(),
        'updated'               => array(),
        'disabled'              => array(),
        'invalid-title'         => array(),
        'invalid-description'   => array(),
        'invalid-price'         => array(),
        'invalid-image'         => array(),
        'invalid-item-code'     => array(),
        'invalid-shipping'      => array(),
        'invalid-stock'         => array(),
        'invalid-retail'        => array(),
        'invalid-sponsored'     => array(),
        'error-product-url'     => array(),
        'error-page-url'        => array(),
        'error-insert-product'  => array(),
        'error-update-product'  => array()
    ];

    /**
     * @var $products_affected Item Code de Productos insertados o actualizados 
     */
    protected $products_affected = array();

    /**
     * @var $products Registros de productos
     */ 
    protected $products = array();

    /* 
    *   Sección de configuración del comando
    *   - Se define el nombre de como debe ser invocado el comando, la descripción, texto de ayuda y los argumentos
    */
    protected function configure()
    {
        $this->setName('GeekyGiftIdeas')
                ->setDescription('GeekyGiftIdeas Products')
                ->setHelp('Scrapping products from GeekyGiftIdeas.')
                ->addArgument('category', InputArgument::OPTIONAL, 'Category to process')
                ->addOption('nosave', null, InputOption::VALUE_NONE, 'Run in test mode. No database save.');
    }

    /*
    *   Sección de ejecución del comando
    *   - Se validad los argumentos, y se inicia el proceso de scraping 
    */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /* Se verifica si se ha indicado el argumento 'category' */ 
        if($input->getArgument('category')) {
            if( array_key_exists( strtolower( trim( $input->getArgument('category') ) ), $this->categories ) ) {
                $this->categoryFilter = strtolower( trim( $input->getArgument('category') ) );
            } else {
                die('La categoria: "'.$input->getArgument('category').'" no existe'.PHP_EOL);
            }
        } else {
            $this->categoryFilter = FALSE;
        }

        if($input->getOption('nosave')) {
            $this->nosave = true;
        }
        
        $output->writeln('<bg=green;options=bold;fg=white>[ SPIDERS - GeekyGiftIdeas ]</>');

        if($this->nosave) {
            $output->writeln('<bg=blue;options=bold;fg=white>Run in Test mode - No save data.</>');
        }

        $this->runScraping($input,$output);

    }

    /**
     * Inicia el proceso del scrapping  
     */
    protected function runScraping( InputInterface $input, OutputInterface $output ) {   

         /* Si se ha definido argumento de categoria se limpia el array categories para solo dejar el seleccionado */
        if($this->categoryFilter) {
            foreach( $this->categories as $key => $val ) {
                if($key != $this->categoryFilter) {
                    unset($this->categories[$key]);
                }
            }
        }

        /* CICLO 1 - Se recorren las categorias */ 
        foreach( $this->categories as $cat => $det ) {
            if ($output->isVerbose()) {
                $output->writeln('<comment>Inicio de categoria: '.$cat.'</comment>');
            }
            $i=1;

            /* Max Pages se define a 1 inicialmente, en el ciclo se actualizará con la cantidad de paginas posibles */ 
            $max_pages = 1; 
            
            /* CICLO 2 - Se inicia el recorrido por la paginación del sitio */ 
            for ( $page = 1; $page <= $max_pages; $page++ ) {

                /* Se obtiene HTML de la pagina */ 
                $page_html = $this->getPageHtml($output,$cat,$page);
                 

               if(!$page_html) {
                    $output->writeln('<error>Error obteniendo Url</error>');
                    $this->log['error-page_url'] = $this->info['site_url'];
                    $this->count['error-page-url']++;
                    continue;
               
                }

                if($page == 1) {

                    /* Se obtiene la cantidad de paginaciones posibles */ 
                    $info_records = $this->getMaxPages($page_html);

                    /* Si no se consigue paginación se realiza el scrapping a los elementos en la pagina inicial */ 
                    if($info_records == 0) { 
                        if ($output->isVerbose()) {
                            $output->writeln('<comment>No se encontraron registros</comment>');
                        }

                        $max_pages = 1;
                        
                    } else {
                        $max_pages = $info_records;
                    
                    }

                    
                    $output->writeln('<info>Categoria: '.$cat.'</info>');
                    $output->writeln('<info>Paginacion: '.$max_pages.'</info>');
            
                }

                /* Imprime titulos por pagina */ 
                if ($output->isVerbose()) {
                    $output->writeln('<comment>Página: '.($page + 1).' de '.$max_pages.'</comment>');
                }


                /* Se recorren los productos de la pagina para obtener sus datos */ 

                $this->getProducts($page_html, 1, $output,$i,$cat );
            }


        }
            /* Desactivar productos que no han sido actualizados */ 
            
            if(!$this->nosave) {

                $this->disableProducts( $cat ); 
            } 


        /* Imprime resumen */ 
        $this->printLog( $output );
    }

    /** 
     *  @access Protected
     *  Función para obtener el codigo HTML del sitio en base a los parametros suministrados
     *
     *  @return simple_html_dom_parser object
     */
     protected function getPageHtml($output,$category,$page ) {
              try
            {    

                /*
                *   Se construye la URL,
                */
                $url = $this->info['site_url'].$category.'/page/'.$page.'/';
            
               /* 
                *   Se intenta obtener el html de la pagina 
                *   
                 */
                echo 'Url: '.$url.PHP_EOL; //se imprime la url
                $html = HtmlDomParser::file_get_html($url);
                return $html;
               
            }catch (\Exception $e) {
               echo 'ERROR:'.$e->getMessage();
            }



        }


      /**
     * @access protected
     * Función que genera la URL para acceder a la busqueda por categoria y filtros
     * 
     * @param $category_id Codigo de categoria a buscar
     * @param $page Numero de pagina a buscar
     * @return String URL 
     */
    protected function makePageUrl($category,$category_id){

       //https://www.biglots.com/c/weekly-deals/clearance/toys-clearance/_/N-220219001
       return $this->info['site_base_url']."/c/weekly-deals/clearance/".$category."/_/N-".$category_id; ///url formateada de la categoria
                                                   
    }

    /** 
    *   @access Protected 
    *   Calcular la cantidad de paginas que tiene la categoria 
    *
    *   @param $page_html  Código HTML de la pagina obtenida
    */     
    protected function getMaxPages($page_html) {

        $records = $page_html->find('*.pagination li');

        $array_contenido = array();
        foreach ($records as $key => $value) {
            $array_contenido[] = $value->plaintext;
        }



         if (filter_var($array_contenido[count($array_contenido) - 1], FILTER_VALIDATE_INT)) { //esta en la ultima posicion
             return $array_contenido[count($array_contenido) - 1];
         }else{
             if (filter_var($array_contenido[count($array_contenido) - 2], FILTER_VALIDATE_INT)) { //no esta en la ultima paginacion
              return $array_contenido[count($array_contenido) - 2];
             }else{
                return 0;
             }

         }
    }
    /**
     * @access protected 
     * Realiza la lectura de los productos resultantes en la busqueda y obtiene sus valores
     * 
     * @param $html_page    Objeto Simple Html dom parser con contenido del resultado de la busqueda
     * @param $cat          Codigo de categoria actual
     * @param $output       Output Interface para impresion en consola
     * @param $i            Contador de registros  
     * @return Int Contador del ultimo registro procesado
     */
    protected function getProducts($page_html,$category, $output,$i,$cat) {

        //print_r(count($page_html->posts));
        $posts = $page_html;
        //recorrido por cada uno de los articulos        
        foreach ($page_html->find($this->tags['product_url']) as $url) {
            $url_product = $url->href;
            $product = array();
            
            $product_html = $this->getProductHTML($url_product);

             /* Si no se obtiene resultado se continua con el siguiente producto */ 
            if(!$product_html) {
                $output->writeln('<error>Error obteniendo Url, se continua con siguiente producto</error>');
                $this->log['error-product-url'][] = $url->href; 
                $this->count['error-product-url']++;
                continue;
            }else{

                
                $product['item_last_normal_price']  = $this->getprice($product_html,$this->tags['normal_price']);
                
                if(!$product['item_last_normal_price']) {
                    $output->writeln('<error>Error obteniendo precio, se continua con siguiente producto</error>');
                    $this->log['invalid-price'][] = $url_product; 
                    $this->count['invalid-price']++;
                    continue;
                }else{
                     $product['item_buy_url'] = $this->getItemBuyUrlClean($product_html);
                     $amazon_url = $this->validAmazon($product['item_buy_url']);
                    if (!$amazon_url){ //verificar que el producto existe, ademas verificar el stock, en caso de existir
                        $output->writeln('<error>Error, el producto no pertenece al sitio web amazon, se continua con siguiente producto</error>');
                        $this->log['invalid-retail'][] = $url_product; 
                        $this->count['invalid-retail']++;
                        continue;           
                    }


                }
            }
            


            $product['item_last_price']         = $product['item_last_normal_price'];
            $product['category']                = $this->categories[$cat];
       
            $product['item_title']              = html_entity_decode(Common::getElement( $product_html, $this->tags['name'], 0, 'plaintext', 1 ));
            $product['item_description']        = Common::cleanString(Common::getElement( $product_html, $this->tags['description'], 0, 'plaintext', 1 ));
            $product['item_image_url']          = $this->getimage($product_html,$this->tags['image_first']);
            // FALTA SKU PREGUNTAR DANIEL
            $product['item_sku']                = $this->getProductSku($product_html,$this->tags['sku']);
            $product['item_url_slug']           = Common::createSlug( $product['item_title'], $product['item_buy_url'] );
            $product['item_code']               = md5( $product['item_buy_url'] );

            $site_name_md5                      = md5($this->info['site_name']);
            $product['store_public_id']         = substr($site_name_md5, -6);
            $product['service_group']           = $this->info['site_name'];
            $product['manufacturer_name']       = null;
            $product['shipping_cost']           = null;
            /* Si el producto supera la validación de guarda */
            if( $this->validProduct( $product ) ) {
                if(!$this->nosave) {
                    $this->saveProduct( $product );
                }
            }
           
            $this->printProduct( $product, $output, $i );

            /* Se elimina el array de producto antes de continuar con el siguiente */
            unset($product);

            $i++;
        }

        return;
    }



    /**
     * @access protected 
     * funcion para obtener la imagen
     * @param $url URL de la vista producto
     */
    protected function getimage($url,$tag) {
       
        return $url->find($tag,0)->getAttribute('data-src');

    }


    /**
     * @access protected 
     * funcion para obtener el producto en amazon (y verificarlo)
     * @param $url URL de la vista producto
     */
    protected function validAmazon($url) {
       
        $flag = explode('.',$url);
        if ($flag[1] == 'amazon') {
            return true;
        }else{
            return null;
        }
    }
    /**
     * @access protected 
     * funcion para obtener la categoria (spaces)
     * @param $url URL de la vista producto
     */
    protected function getUrlClean($url) {
        $url_clean = explode('?', $url);
        return $url_clean[0];
    }

    /**
     * @access protected 
     * funcion para obtener la categoria (spaces)
     * @param $url URL de la vista producto
     */
    protected function getCategory($url) { //REVISAR
        $bread[] = array();
        foreach ($url->find( $this->tags['category']) as $key => $value) {
            $bread[] = $value;
        }
        $category[] = array();
        for ($i=1; $i < count($bread) ; $i++) {
            $category[] = $bread[$i]->plaintext;
        }
        unset($category[0]);
        $category_clean = implode(" / ",$category);

        return $category_clean;

    }


    /**
     * @access protected 
     * Función para obtener el shipping siguiendo la regla
     * 1)si contiene la etiqueta shipping, return 0;
     * 2)si no tiene la etiqueta y el precio es > 45$ 0;
     * 3) en caso contrario shipping = 5$
     * @param $url URL de la vista producto
     */
    protected function getShipping($url,$price) {
        try {
            if ($url->find($this->tags['shipping_hide'])) { //si la etiqueta freeshipping esta oculta
               

                if($price >= 45){
                        return 0.00;

                }else{
                    return 5.00;
                }

            }else{
                if(!$url->find($this->tags['shipping_hide'])){
                   return 0.00;
                }
            
            }
        
        } catch (Exception $e) {
            echo 'ERROR:'.$e->getMessage().PHP_EOL;
        }

    }

    /**
     * @access protected 
     * Función para verificar que el articulo tenga retail
     * 
     * @param $url URL de la vista producto
     */
    protected function validRetail($url) {

      
            if ($url->find(".reference-price-value")) {
               
                return true;
            }else{
                return false;
            }
        
      

    }

    /**
     * @access protected 
     * Función para obtener el sku
     * 
     * @param $url URL de la vista producto
     */
    protected function getProductSku($product_html,$tag) {

        try {
            $sku = $product_html->find('.post-inner',0);
            $sku_exploded = explode('-', $sku);
            $sku_clean = $sku_exploded[1];
            return $sku_clean; //se envia la posicion del arreglo que contiene el codigo numerico
        
        } catch (Exception $e) {
            echo 'ERROR:'.$e->getMessage().PHP_EOL;
        }

    }

    /**
     * @access protected 
     * funcion para obtener la url limpia, sin referido en el enlace
     * 
     * @param $url URL de la vista producto
     */
    protected function getItemBuyUrlClean($url_product) {

            $url = $url_product->find('*[class=btn_offer_block re_track_btn]',0)->href;
            $url_exploded = explode('geekygiftideas', $url);
            $url_DRS_imploded = implode('drsspider', $url_exploded); //formatea la url con el tag
            return $url_DRS_imploded;
    }


    /**
     * @access protected 
     * Función para obtener el HTML de la vista de producto
     * 
     * @param $url URL de la vista producto
     */
    protected function getProductHTML($url) {


        try {


            /* 
            *  Se envia como segundo parametro el tag de un elemento para hacer clic 
            *  y obtener la ficha de especificaciones del producto
            */
            echo 'Producto: '.$url.PHP_EOL;

            return HtmlDomParser::file_get_html($url);    
        } catch (Exception $e) {
            echo 'ERROR: getproducthtml'.$e->getMessage().PHP_EOL;
        }


    }


        /**
     * @access Protected 
     * Función para obtener el precio formateado 
     * 
     * @param $html         Contenido html del producto
     * @param $product_name Nombre del producto para ser usado en caso de no existir contenido en descripcion.
     */
    protected function getPrice( $url,$tag ) {
        if ($url->find($tag,0)) {
            $price_string = $url->find($tag,0)->plaintext;
            return substr($price_string, 1);
        }else{
            return null;
        }
        

        


    }



    /**
     * @access Protected
     * Obtiene la imagen de un producto
     * @param $html Contenido html del producto
     */
    protected function getProductImage ( $html ) {

        $image = Common::getElement( $html, $this->tags['image_first'], 0, 'srcset' );

        if($image) {
            return "https:".$image;
        } else {
            return false;
        }

        

    }

    /**
     * Valida si el shipping es correcto en base a los valores definidos en $this->shippingValid
     */
    protected function validShipping( $html ) {

        /* Se predefine como shipping invalido antes de validar */ 
        $valid = FALSE; 

        /* Se busca la etiqueta que contiene el valor de shipping */ 
        if( Common::getElement( $html, $this->tags['shipping_info'], 0 ) ) {

            /* Se obtiene el texto plano del div que contiene el shipping */ 
            $shipping_info = trim(strtolower($html->find($this->tags['shipping_info'],0)->parent()->plaintext));

            /* Se verifica si el texto del shipping valido existe en el string */ 
            foreach($this->shippingValid as $value) {

                /* Se valida si existe el valor valido y adicional no esta el valor invalido */ 
                if(strpos( $shipping_info, $value ) !== FALSE && $shipping_info != $this->shippingInvalid){
                    $valid = TRUE;
                }

            }
        }

        return $valid;
    }

    /** 
     * Valida que el stock sea valido en base a lo definido en $this->stockInvalid
     */
    protected function validStock( $html ) {

        if(is_null($html->find('.out-of-stock',0))){ 
            return true;
        }else{
            return false;
        }
    }

    /**
     * Valida que un producto sea correcto, verificando que los campos principales tengan datos
     */
    protected function validProduct( $product ){

        /* Se verifica los valores principales, si alguno falta se invalida el producto */ 
        if( $product['item_title']              == null )  { $this->count['invalid-title']++;       $this->log['invalid-title'][]       = $product['item_buy_url'];   return false; } 
        if( $product['item_description']        == null )  { $this->count['invalid-description']++; $this->log['invalid-description'][] = $product['item_buy_url'];   return false; } 
        if( $product['item_last_normal_price']  == null )  { $this->count['invalid-price']++;       $this->log['invalid-price'][]       = $product['item_buy_url'];   return false; } 
        if( $product['item_image_url']          == null )  { $this->count['invalid-image']++;       $this->log['invalid-image'][]       = $product['item_buy_url'];   return false; } 
        if( $product['item_code']               == null )  { $this->count['invalid-item-code']++;   $this->log['invalid-item-code'][]   = $product['item_buy_url'];   return false; } 
        
        /* Si se superan las verificaciones previas, se valida el producto */ 
        return true;
  
    }
    
    /**
     * Guarda el producto en BD
     */
    protected function saveProduct( $product ){
        $cache = new CacheModel;
        $product_exist =  $cache->getProduct( $product['item_code'] );

        /* Se definen valores faltantes globales */ 
        $product['item_status']         = 1;
        $product['item_type_id']        = 1; 
        $product['store_name']          = $this->info['site_name'];
        $product['external_category']   = 0;

        /* Actualizar producto */ 
        if($product_exist) {
      
            /* Se verifica si el producto posee otras categorias */ 
            $cache_categories = explode( ',', $product_exist['category'] );
            if(!in_array($product['category'], $cache_categories)) {
                 $cache_categories_string = implode(',', $cache_categories);

                $product['category'] = $cache_categories_string.','.$product['category'];
                
            }
            /* Se envia a la DB */ 
            $result = $cache->update( $product );   
            /* Se actualizan contadores y log segun el resultado */   
            if( $result ) {
                /* Se agrega el item code a variable para luego desactivar los demas */ 
                $this->products_affected[] = "'".$product['item_code']."'";
                /* Se registra la url del producto en log que luego sera registrado en archivo */ 
                $this->log['updated'][] = $product['item_buy_url'];
                /* Aumenta el contador de productos actualizados */
                $this->count['updated']++;
            } else {
                $this->log['error-update-product'][] = $product['item_buy_url'];
                $this->count['error-update-product']++;
            }
        /* Nuevo producto */
        } else {
 
            /* Se envia a la DB */ 
            $result = $cache->insert( $product );
            if( $result ) {
                /* Se agrega el item code a variable para luego desactivar los demas */ 
                $this->products_affected[] = "'".$product['item_code']."'"; 
                /* Se registra la url del producto en log que luego sera registrado en archivo */ 
                $this->log['inserted'][] = $product['item_buy_url'];
                /* Aumenta el contador de productos actualizados */
                $this->count['inserted']++;
            } else {
                $this->log['error-insert-product'][] = $product['item_buy_url'];
                $this->count['error-update-product']++;
            }
        }
    }

    /**
     * Desactiva productos no actualizados en la categoria
     */
    protected function disableProducts( $cat ) {
        $cache = new CacheModel; 

        $result = $cache->disableProducts( $this->info['site_name'], 
                                           null, 
                                           $this->products_affected );
        if($result) {
           
            $this->count['disabled'] = $result; 
        } 
    }
 
    /**
     * Imprime en consola los datos obtenidos del producto
     */
    protected function printProduct( $product, $output, $i ) {

        $table = new Table($output);
        $table
            ->setHeaders(array('Nro', 'Campo', 'Valor'))
            ->setRows( array (
                array ( new TableCell((string) $i, array('rowspan' => 11)), 
                        'Product Name', 
                        substr($product['item_title'],0,100).'...' ),
                array ( 'Product Description', substr($product['item_description'],0,100).'...' ),
                array ( 'Product SKU', $product['item_sku'] ),
                array ( 'Product Code', $product['item_code'] ),
                array ( 'Product Normal Price', $product['item_last_normal_price'] ),
                array ( 'Product Special Price', $product['item_last_price'] ),
                array ( 'Product Slug Url', substr($product['item_url_slug'],0,100).'...' ),
               // array ( 'Product Shipping Cost', $product['shipping_cost'] ),
               // array ( 'Product Valid Stock', $product['valid_stock'] ),
            ) );
        $table->render();

    }

    /**
     * Imprime en archivo log y consola los resultados del scrapping
     */
    protected function printLog( $output ) {
        
        /* Imprime log en archivo */ 
        $recordLog = Log::create('GeekyGiftIdeas');
        $recordLog->info('++++++ SPIDERS. REPORT FOR SCRAPPING: GeekyGiftIdeas '.date('Y-m-d H:i:s').' ++++++');

        foreach( $this->log as $key => $values ){
            if(count($values) > 0) {
                $recordLog->info('«««««« Products: '.$key.' »»»»»»');
                $i = 1; 
                foreach($values as $value) {
                    $recordLog->info($i.' | '.$key.' | '.$value);
                    $i++;
                }
            }
        }
        if( $this->count['disabled'] > 0 ) {
            $recordLog->info('Disabled '.$this->count['disabled'].' products with no update info'); 
        }
        $recordLog->info('++++++ END REPORT FOR SCRAPPING: GeekyGiftIdeas '.date('Y-m-d H:i:s').' ++++++');
    
        /* Imprime resumen en pantalla */ 
        $output->writeln('Resume for scrapping:');
        $table = new Table($output);
        $table
            ->setHeaders(array('Count', 'Total'))
            ->setRows( array ( 
                array ('Products Inserted', $this->count['inserted']),
                array ('Products Updated', $this->count['updated']),
                array ('Products Disabled', $this->count['disabled']),
                array ('Invalid Product without Title', $this->count['invalid-title']),
                array ('Invalid Product without Description', $this->count['invalid-description']),
                array ('Invalid Product without Price', $this->count['invalid-price']),
                array ('Invalid Product without Image', $this->count['invalid-image']),
                array ('Invalid Product without Code', $this->count['invalid-item-code']),
                array ('Invalid Product without Shipping', $this->count['invalid-shipping']),
                array ('Invalid Product without Stock', $this->count['invalid-stock']),
                array ('Invalid Product without Retail', $this->count['invalid-retail']),
                array ('Error in Product URL', $this->count['error-product-url']),
                array ('Error in Pages URL', $this->count['error-page-url']),
                array ('Error Inserting Product', $this->count['error-insert-product']),
                array ('Error Updating Product', $this->count['error-update-product'])
             ) );
        $table->render();
        $output->writeln('For more details view the log file.');
    }
}