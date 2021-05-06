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
use Spiders\Helpers\Webdriver;
use Spiders\Helpers\Common;
use Spiders\Helpers\Log;
use Spiders\Models\CacheModel;

class OverStock extends Command
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
        'site_name'     => 'OverStock',
        'site_base_url' => 'https://www.overstock.com/',
        'site_url'      => 'https://www.overstock.com/Clearance,/clearance,/results.html',
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
        
        'home-garden'               => [ 'category_id' => '1'],
        'jewelry-watches'           => [ 'category_id' => '4'],
        'overstock-liquidations'    => [ 'category_id' => '45'],
        'bedding-bath'              => [ 'category_id' => '43'],
        'clothing-shoes'            => [ 'category_id' => '7'],
        'sports-toys'               => [ 'category_id' => '5'],
        'luggage-bags'              => [ 'category_id' => '33'],
        'food-gifts'                => [ 'category_id' => '9'],
        'electronics'               => [ 'category_id' => '2'],
        'holiday'                   => [ 'category_id' => '46'],
        'health-beauty'             => [ 'category_id' => '8'],
        'pet-supplies'              => [ 'category_id' => '37'],
        'baby'                      => [ 'category_id' => '35'],
        'emergency-preparedness'    => [ 'category_id' => '42'],
        'worldstock-fair-trade'     => [ 'category_id' => '6']
    ];

    /**
     *  @var $tags Definición de tags utilizados para obtener información de los productos
     */
    protected $tags = [
        'product_url'           => '.product-tile a',                               // Tag de url de producto en listado
        'name'                  => '.product-title h1',                             // Tag del nombre de producto                            
        'price_dollars'         => '.monetary-price-value .dollars',                // tag de precio
        'price_cents'           => '.monetary-price-value .cents',
        'price_other_retail'    => '.reference-price-value',                        //tag de precio de otros retails
        'description'           => '*[itemprop=description]',                       // tag de descripcion *[itemprop=description] div trae el primer div
        'manufacturer'          => '.product-title span a',                         // tag de manufacturer o brand
        'normal_price'          => '.monetary-price-value',                          // Tag de precio normal
        'image_first'           => '.hero-zoom-container img',                           // Tag de Imagen principal
        'sku'                   => '.description-item-number',                      // tag de numero SKU
        'shipping'              => '.message .success .free-shipping-message', // tag shipping
        'shipping_hide'         => '.shipping-messaging .hide',
        'stock'                 => '.out-of-stock-label',
        'category'              => '*[id=breadcrumbs] ul'
 

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
        $this->setName('overstock')
                ->setDescription('OverStock Products')
                ->setHelp('Scrapping products from OverStock.')
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
        
        $output->writeln('<bg=green;options=bold;fg=white>[ SPIDERS - OverStock ]</>');

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
                $page_html = $this->getPageHtml( $cat,$det['category_id'], $page, $output );
                 

                if(!$page_html) {
                    if ($output->isVerbose()) {
                        $output->writeln('<error>Error obteniendo Url, se continua con siguiente página</error>');
                    }
                    $this->log['error-page_url'] = $this->makePageUrl( $cat,$det['category_id'], $page );
                    $this->count['error-page-url']++;
                    continue;
                }

                if($page == 1) {

                    /* Se obtiene la cantidad de paginaciones posibles */ 
                    $info_records = $this->getMaxPages($page_html);

                    /* Si no se consigue paginación se realiza el scrapping a los elementos en la pagina inicial */ 
                    if($info_records['pages'] == 0) { 
                        if ($output->isVerbose()) {
                            $output->writeln('<comment>No se encontraron registros</comment>');
                        }

                        $max_pages = 1;
                        
                    } else {
                        $max_pages = $info_records['pages'];
                    
                    }
                    $reg = $info_records['records'];
                    $output->writeln('<info>Categoria: '.$cat.', Registros: '.$reg.'</info>');
                    $output->writeln('<info>Paginacion: '.$max_pages.'</info>');
            
                }

                /* Imprime titulos por pagina */ 
                if ($output->isVerbose()) {
                    $output->writeln('<comment>Página: '.($page + 1).' de '.$max_pages.'</comment>');
                }


                /* Se recorren los productos de la pagina para obtener sus datos */ 
                $i = $this->getProducts( $page_html, $cat, $output, $i );
            }

            /* Desactivar productos que no han sido actualizados */ 
            
            if(!$this->nosave) {

                $this->disableProducts( $cat ); 
            } 
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
    protected function getPageHtml( $category,$category_id, $page, $output ) {
          try
        {    

            /*
            *   Se construye la URL en base a la categoria,
            */
            $page_url =  $this->makePageUrl( $category,$category_id, $page );
           /*
            *   Se intenta obtener el html de la pagina 
            *   - Se implementa la clase Webdriver que a su vez hace uso de las herramientas externas Selenium y Google Chrome
             */

            echo 'Url: '.$page_url.PHP_EOL; 

            return Webdriver::getHtml($page_url); //se gener aqui el error

        } catch (\Exception $e) {
            echo 'ERRORs:'.$e->getMessage(); //el error que presenta
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
    protected function makePageUrl($category,$category_id, $page){

        //$initial = $page * 12; 

       return $this->info['site_base_url'].$category."/Clearance,/clearance,/".$category_id."/store.html?page=".$page; ///url formateada de la categoria
                                                   
    }

    /** 
    *   @access Protected 
    *   Calcular la cantidad de paginas que tiene la categoria 
    *
    *   @param $page_html  Código HTML de la pagina obtenida
    */     
    protected function getMaxPages($page_html) {



        if($page_html->find("#last-page")){ //si la pagina tiene mas de 5 paginaciones
            $maxrec = $page_html->find("#last-page"); //obtiene el div que contiene el numero de paginas en la categoria
            
            $records['pages'] = $maxrec[0]->innertext; //capturar el numero plano
        }else{
            if($page_html->find(".pagination-page-numbers")){ // en caso de no tener mas de 5 paginaciones
                $records['pages'] = count($page_html->find('.pagination-page-number'));

    

            }else{ //en caso de no tener ningun tipo de paginacion (menos de 60 productos en la categoria)
                $records['pages'] = 0;
              
            }

        }
        if ($page_html->find('.result-count')) {
            $reg = explode(' ', $page_html->find('.result-count',0)->innertext);
            $records['records'] = $reg[2];
        }else{
            $records['records'] = '';
        }
        return $records;


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
    protected function getProducts($page_html,$category, $output,$i) {

        foreach ( $page_html->find($this->tags['product_url'] ) as $url) {

            $product = array();

            /* Se obtiene el html de la pagina de producto */ 
            $product_html = $this->getProductHTML($url);

            /* Si no se obtiene resultado se continua con el siguiente producto */ 
            if(!$product_html) {
                $output->writeln('<error>Error obteniendo Url, se continua con siguiente producto</error>');
                $this->log['error-product-url'][] = $url->href; 
                $this->count['error-product-url']++;
                continue;
            }else{
                if(!$this->validStock($product_html)){
                     $output->writeln('<error>producto sin stock</error>');
                        $this->log['invalid-stock'][] = $url->href; 
                        $this->count['invalid-stock']++;                
                        continue;

                }else{

                    if(!$this->validRetail($product_html)){ 
                        $output->writeln('<error>producto no tiene retail</error>');
                        $this->log['invalid-retail'][] = $url->href; 
                        $this->count['invalid-retail']++;                
                        continue;
                    }

                }

            }

            $product['category']                = $this->getCategory($product_html);
           
            $product['item_title']              = html_entity_decode(Common::getElement( $product_html, $this->tags['name'], 0, 'plaintext', 1 ));
            $product['item_buy_url']            = $url->href;
            $price_retail                       = Common::getPrices( $product_html, $this->tags['price_other_retail'], 'plaintext',null,null,$this->info['profit_percent']  );
            $product['item_last_normal_price']  = $price_retail['normal'];
            $product['item_description']        = Common::getElement( $product_html, $this->tags['description'], 0, 'plaintext', 1 );

            $product['manufacturer_name']       = html_entity_decode(Common::getElement( $product_html, $this->tags['manufacturer'], 0, 'plaintext', 1 ));
            $product['item_last_price']         = $this->getPrice($product_html,$this->tags['price_dollars'],$this->tags['price_cents']);
            $product['item_sku']                = $this->getProductSku($product_html,$this->tags['sku']);
            $product['shipping_cost']           = $this->getShipping($product_html,$product['item_last_price']);
            $product['item_url_slug']           = Common::createSlug( $product['item_title'], $product['item_buy_url'] );
            $item_url                           = $this->getUrlClean($url->href); //es necesario eliminar el refid de la url

            $product['item_code']               = md5( $item_url );
            $product['item_image_url']          = $this->getProductImage( $product_html );


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
    protected function getCategory($url) {
        $category_string = Common::getElement( $url, $this->tags['category'], 0, 'plaintext', 1 );
        $category = trim(preg_replace(('/\s+/'), ' ', $category_string));
        return html_entity_decode($category);
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
    protected function getProductSku($url,$tag) {

        try {
            $sku_string =  Common::getElement($url,$tag, 0, 'innertext', 1); //se obtiene el elemento
            $sku = explode(" ",$sku_string); //se parsea a un arreglo

            return $sku[1]; //se envia la posicion del arreglo que contiene el codigo numerico
        
        } catch (Exception $e) {
            echo 'ERROR:'.$e->getMessage().PHP_EOL;
        }

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
                echo 'Producto: '.$url->href.PHP_EOL;
                return Webdriver::getHtml($url->href);       
        } catch (Exception $e) {
            echo 'ERROR:'.$e->getMessage().PHP_EOL;
        }


    }


        /**
     * @access Protected 
     * Función para obtener el precio formateado 
     * 
     * @param $html         Contenido html del producto
     * @param $product_name Nombre del producto para ser usado en caso de no existir contenido en descripcion.
     */
    protected function getPrice( $html, $dollar_tag,$cents_tag ) {

        $dollar = Common::getElement($html, $dollar_tag, 0, 'innertext', 1);
        $cents = Common::getElement($html, $cents_tag, 0, 'innertext', 1);
        $price_string = $dollar.'.'.$cents; 

        $price = floatval($price_string);
        return  round( $price * $this->info['profit_percent'], 2 ); 


    }



    /**
     * @access Protected
     * Obtiene la imagen de un producto
     * @param $html Contenido html del producto
     */
    protected function getProductImage ( $html ) {

        $image = Common::getElement( $html, $this->tags['image_first'], 0, 'src' );

        if($image) {

            return $image;

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

        if ($html->find($this->tags['stock'])) { //si consigue la etiqueta que advierte que no hay stock
            
            return false; //devuelve false, se debe saltar el producto
        }else{// sino, devuelve true, producto valido
            return true;
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
        $product['item_type_id']        = self::PRODUCT_TYPE_ID; 
        $product['store_name']          = $this->info['site_name'];
        $product['external_category']   = 1;

        /* Actualizar producto */ 
        if($product_exist) {
            /* Se verifica si el producto posee otras categorias */ 
            $cache_categories = explode( ',', $product_exist['category'] );
            if(!in_array($product['category'], $cache_categories)) {
                $product['category'] = $cache_categories.','.$product['category'];
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
                array ( 'Product Shipping Cost', $product['shipping_cost'] ),
               // array ( 'Product Valid Stock', $product['valid_stock'] ),
            ) );
        $table->render();

    }

    /**
     * Imprime en archivo log y consola los resultados del scrapping
     */
    protected function printLog( $output ) {
        
        /* Imprime log en archivo */ 
        $recordLog = Log::create('Overstock');
        $recordLog->info('++++++ SPIDERS. REPORT FOR SCRAPPING: OverStock '.date('Y-m-d H:i:s').' ++++++');

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
        $recordLog->info('++++++ END REPORT FOR SCRAPPING: OVERSTOCK '.date('Y-m-d H:i:s').' ++++++');
    
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