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
 
class WalmartElectronic extends Command
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
        'site_name'     => 'Beautycarechoices',
        'site_base_url' => 'http://beautycarechoices.com',
        'site_url'      => 'http://beautycarechoices.com/features/clearance.php?type=3099',
        'profit_percent'=> 1.15,

    ];

    /**
     *  @var $tags Definición de tags utilizados para obtener información de los productos
     */
    protected $tags = [
        //'records'		        => '.result-summary-container',                         // Tag que indica el total de registros de una categoria
        'product_url'           => '.pw2-item-details a',                    // Tag de url de producto en listado
        'name'                  => '.prod-header h1',                              // Tag del nombre de producto
        'description'           => '.prod_full p',                                       // Tag de la descripcion de producto
       // 'specifications'        => '.Specifications table',                             // Tag de la tabla de especificaciones
        //'specifications-click'  => '.SpecsTab ul li:first-child',                       // Tag del elemento click para acceder a la tabla de especificaciones
        'normal_price'          => '.prod_price_price strike',      // Tag de precio normal
        'special_price'         => '.prod_price_price span', // Tag de precio especial
        'image_first'           => '.prod-image-mobile img', // Tag de Imagen principal
        //'image_second'          => '.prod-ProductImage .slider .slider-frame img',      // Tag de Imagen secundaria
        'manufacturer'          => '.prod-header span',                              // Tag de manufacturer o brand 
       // 'sku'                   => '.wm-item-number',                                   // Tag de Sku del producto
       // 'shipping_info'	        => '.free-shipping-msg',                                // Tag de valor de shipping   
        //'price_exluded'         => '.product-offer-price .PriceRange',                  // Tag para excluir productos con rango de precio
        //'stock_excluded'        => '.prod-ProductOffer-oosMsg span'                     // Tag para excluir productos sin stock        
    ];

    /** 
    *   @var $categoryFilter Filtro por categoria 
    *   - Si no se especifica se procesaran todas las categorias definidas en $categories
    */
    protected $categoryFilter;

    /**
     * @var $nosave Indica si se ejecutará sin realizar cambios en la cache
     */
    protected $nosave = false; 

	/** 
	* 	@var $offerFilters Filtros a recorrer en la busqueda 
	* 	- Solo deben agregarse en este array filtros del bloque Special Offers en Walmart 
	*/ 
	protected $offerFilters = [ 
        'special_offers:Clearance',
		'special_offers:Special+Buy',
		'special_offers:Reduced+Price'
    ];

	/** 
	*	@var $fixedFilters Filtros que siempre seran aplicados 
	*	- Solo filtros ubicados en la barra izquierda de Walmart
	*/ 
	protected $fixedFilters = [
		'condition:New'
	];

    /** 
    *   @var $categories Definición de categorias a obtener de Walmart
    *   - Se definen con el id de la categoria en tienda y el string de categoria magento donde seran ubicadas 
    */
    protected $categories = [
	'volumizing'=>['category_id' =>'Beauty Care Choices | TIGI | Bed Head For Men Charge Up Thickening Shampoo | 8.45 oz', 'category_string' => 'DRS/volumizing' ],
       ];
    
    /** 
    *   @var $imageInvalid Filtro de Imagenes no validas de walmart 
    *   - Si se detecta la imagen principal como invalida, se intentará obtener la secundaria, si esta disponible
    */ 
	protected $imageInvalid = [
        'https://i5.walmartimages.com/asr/0ce9154c-7902-432a-a32e-af279d75098f_1.7820165ac739ce745b4828ff162cf7d7.jpeg',
        'https://i5.walmartimages.com/asr/8a92fce5-aec1-4efc-986f-25c8953b7e80_1.fcaf2b35dfe0328a70c2eb30c5a7599d.jpeg'
	];

    /**
     * @var $stockInvalid Define los valores a buscar en el stock para descartar productos
     */
    protected $stockInvalid = 'Out of stock';

    /**
     * @var $shippingValid Valores validos para el shipping
     */
    protected $shippingValid = [ 'free shipping', 'free 3-day shipping' ];

    /**
     * @var $shippingInvalid Define los valores a buscar en el shipping para descartar productos 
     */ 
    protected $shippingInvalid = 'over';

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
        $this->setName('beautycarechoices:volumizing')
                ->setDescription('beautycarechoices volumizing Products')
                ->setHelp('Scrapping volumizing products from beautycarechoices Store.')
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
        
        $output->writeln('<bg=green;options=bold;fg=white>[ SPIDERS - Beautycarechoices Volumizing ]</>');

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

            $output->writeln('<comment>Inicio de categoria: '.$cat.'</comment>');
            $i=1;

            /* CICLO 2 - Se recorren los filtros de ofertas */ 
            foreach( $this->offerFilters as $offer ) {

                $output->writeln('<comment>-- Filtro : '.$offer.'</comment>');

                /* Max Pages se define a 1 inicialmente, en el ciclo se actualizará con la cantidad de paginas posibles */ 
                $max_pages = 1; 
            
                /* CICLO 3 - Se inicia el recorrido por la paginación del sitio */ 
                for ( $page = 1; $page <= $max_pages; $page++ ) {

                    /* Se obtiene HTML de la pagina */ 
                    $page_html = $this->getPageHtml( $det['category_id'], $offer, $page );

                    if(!$page_html) {
                        $output->writeln('<error>Error obteniendo Url, se continua con siguiente página</error>');
                        $this->log['error-page_url'] = $this->makePageUrl( $det['category_id'], $offer, $page );
                        $this->count['error-page-url']++;
                        continue;
                    }

                    /* Se obtiene la cantidad de paginaciones posibles */ 
                    $info_records = $this->getMaxPages($page_html);

                    /* Si no se consigue paginación se salta al siguiente offerFilter */ 
                    if(!$info_records) { 
                        $output->writeln('<comment>No se encontraron registros</comment>');
                        $max_pages = 1;
                        continue;
                    } else {
                        $max_pages = $info_records['pages'];
                    }

                    /* Imprime titulos por pagina */ 
                    $output->writeln('<info>Categoria: '.$cat.', Filtro: '.$offer.', Registros: '.$info_records['records'].'</info>');
                    $output->writeln('<comment>Página: '.$page.' de '.$max_pages.'</comment>');
                    sleep(5); // Retardo para no saturar sitio objetivo

                    /* Se recorren los productos de la pagina para obtener sus datos */ 
                    $i = $this->getProducts( $page_html, $cat, $output, $i );
                    sleep(5);
                    
                }

            }

            /* Desactivar productos que no han sido actualizados de la categoria procesada */ 
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
     *  @param  $category_id    Código de categoria Walmart 
     *  @param  $offerFilter    Filtro de Oferta a aplicar
     *  @param  $page           Número de pagina a buscar
     *
     *  @return simple_html_dom_parser object
     */
    protected function getPageHtml( $category_id, $offerFilter, $page ) {
        try
        {	                    
            /*
            *	Se construye la URL en base a la categoria, filtro y pagina a consultar
            */
            $page_url =  $this->makePageUrl( $category_id, $offerFilter, $page );

            /* 
            *   Se intenta obtener el html de la pagina 
            *   - Se implementa la clase Webdriver que a su vez hace uso de las herramientas externas Selenium y Google Chrome
            */
            echo 'Url: '.$page_url.PHP_EOL;	
            return Webdriver::getHtml($page_url);

        } catch (\Exception $e) {
           echo 'ERROR:'.$e->getMessage();
        }
    }

    /**
     * @access protected
     * Función que genera la URL para acceder a la busqueda por categoria y filtros
     * 
     * @param $category_id Codigo de categoria a buscar
     * @param $offerFilter Filtros a establecer en la busqueda
     * @param $page Numero de pagina a buscar
     * @return String URL 
     */
    protected function makePageUrl($category_id, $offerFilter, $page){
        return str_replace('%2B','+',sprintf( 
            $this->info['site_url'],                                // URL del sitio a consultar
            $category_id,                                           // Id de Categoria
            $offerFilter.rawurlencode('||').                        // Filtro de Oferta
            rawurlencode( implode("||",$this->fixedFilters) ),      // Filtros Fijos
            $page ) );                                              // Numero de pagina
    }

    /** 
    *	@access Protected 
    *   Calcular la cantidad de paginas que tiene la categoria 
    *	- Obtiene el valor y lo asigna a la variable $hasta que controla el ciclo de paginación.
    *	- Si la paginación supera las 25 paginas, se asigna el valor 25 a la variable $hasta por limitación de Walmart
    *
    *   @param $page_html  Código HTML de la pagina obtenida
    */     
    protected function getMaxPages($page_html) {

        if( $page_html->find($this->tags['records'], 0 ) ){

            /* 
            *   Obtiene el valor de registros que contiene la categoria 
            *   - Se filtra para obtener un valor numerico
            */ 
            $records = $page_html->find($this->tags['records'],0)->find('span',1)->plaintext;
            preg_match("(\d+(?:\d{1,2})?)", str_replace(",","",$records), $total_records);

            /* Se dividen los registros entre 40 (paginas por producto que muestra walmart) */
            $pages = $total_records[0] / 40;

            /* Si el valor de paginas es un decimal, se le suma 1 a la paginacion */ 
			if( $pages > intval($pages) ){ 
                $pages++; 
            }

            /* Si el valor de paginas es superior a 25, se define en 25, ya que es el tome que permite paginar walmart */ 
			if( intval($pages > 16) ) { 
                $pages = 16; 
            } 

            return [ 'pages' => intval($pages), 'records' => $total_records[0] ];
            
        } else {
            return false;
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
    protected function getProducts($page_html, $cat, $output, $i) {
        
        foreach ( $page_html->find($this->tags['product_url'] ) as $url) {

            $product = array();

            /* Se verifica si el producto es patrocinado, lo cual no es valido */ 
            if(strpos($url->href, 'wpa_bd') !== FALSE) {
               // echo 'PRODUCTO DESCARTADO - SPONSORED'.PHP_EOL;
                $this->log['invalid-sponsored'][] = $this->info['site_base_url'].$url->href;
                $this->count['invalid-sponsored']++;
                continue;
            }

            /* Se obtiene el html de la pagina de producto */ 
            $product_html = $this->getProductHTML($url);

            /* Si no se obtiene resultado se continua con el siguiente producto */ 
            if(!$product_html) {
                $output->writeln('<error>Error obteniendo Url, se continua con siguiente producto</error>');
                $this->log['error-product-url'][] = $this->info['site_base_url'].$url->href; 
                $this->count['error-product-url']++;
                continue;
            }

            /* Captura de valores del producto */ 
            $product['category']                = $this->categories[$cat]['category_string'];
            $product['item_title']              = Common::getElement( $product_html, $this->tags['name'], 0, 'plaintext', 1 );
            $product['item_description']        = $this->getProductDescription($product_html, $product['item_title']);
            $product['manufacturer_name']       = Common::getElement( $product_html, $this->tags['manufacturer'], 0, 'plaintext', 1 );
            //$product['item_sku']                = Common::getElement( $product_html, $this->tags['sku'], 0, 'plaintext', 1, False, 'Walmart # ');
            $product['item_buy_url']            = $this->info['site_base_url'].$url->href;
            $product['item_url_slug']           = Common::createSlug( $product['item_title'], $product['item_buy_url'] );
            $product['item_code']               = md5( $product['item_buy_url'] );
            $product['shipping_cost']           = 0; // Free Shipping todos los productos en este particular 
            $product['item_image_url']          = $this->getProductImage( $product_html );
            $prices                             = Common::getPrices( $product_html, $this->tags['normal_price'], 'plaintext', $this->tags['special_price'], 'plaintext', $this->info['profit_percent'] );
            $product['item_last_normal_price']  = $prices['normal'];
            $product['item_last_price']         = $prices['special'];
            $product['valid_shipping']          = $this->validShipping( $product_html );
            $product['valid_stock']             = $this->validStock( $product_html );

			
            /* Si el producto supera la validación de guarda */
            if( $this->validProduct( $product ) ) {
                if(!$this->nosave) {
                    $this->saveProduct( $product );
                }
            }

            $this->printProduct( $product, $output, $i );

            /* Se elimina el array de producto antes de continuar con el siguiente */ 
            unset($product);

            /* Tiempo de espera opcional entre un producto y otro */ 
            sleep(5);
            $i++;
        }

        return $i;
    }

    /**
     * @access protected 
     * Función para obtener el HTML de la vista de producto
     * 
     * @param $url URL de la vista producto
     */
    protected function getProductHTML($url) {

        try	{
            /* 
            *  Se envia como segundo parametro el tag de un elemento para hacer clic 
            *  y obtener la ficha de especificaciones del producto
            */
            echo 'Producto: '.$this->info['site_base_url'].$url->href.PHP_EOL;
            return Webdriver::getHtml($this->info['site_base_url'].$url->href,$this->tags['specifications-click']);
        
        } catch (Exception $e) {
            echo 'ERROR:'.$e->getMessage().PHP_EOL;
        }

    }

    /**
     * @access Protected 
     * Función para obtener la descripción y contenido adicional de un producto
     * 
     * @param $html         Contenido html del producto
     * @param $product_name Nombre del producto para ser usado en caso de no existir contenido en descripcion.
     */
    protected function getProductDescription( $html, $product_name ) {

        /* Se obtiene la descripción corta y si existe la tabla de especificaciones se le concatena */ 
        $description    = Common::getElement($html, $this->tags['description'], 0, 'innertext', 2, True);
        $specifications = Common::getElement($html, $this->tags['specifications'], 0, 'outertext');
        //print_r($specifications);die;
        /* Si no existe descripcion se coloca el nombre del producto */ 
        if(!$description) {
            $description = $product_name; 
        }

        if($specifications) {
            $description.= '<br><h2>Specifications</h2>'.$specifications;
        }

        return $description; 

    }

    /**
     * @access Protected
     * Obtiene la imagen de un producto
     * @param $html Contenido html del producto
     */
    protected function getProductImage ( $html ) {

        $image = Common::getElement( $html, $this->tags['image_first'], 0, 'src' );

        if($image) {

            /* Eliminar el texto adicional en la url de la imagen */
            if( strstr( $image, '.jpeg?', true ) ) {	
                $image = strstr( $image, '.jpeg', true ) . '.jpeg';
            }

            $image = $this->validImage($image);

        } 

        /* Si no se obtuvo imagen o fue invalidada la principal, se intenta ubicar la secundaria */ 
        if(!$image) {
            $image = Common::getElement( $html, $this->tags['image_second'], 0, 'src' ); 
            if($image) {
                if ( filter_var( $image, FILTER_VALIDATE_URL ) == False ) {
                    $image = 'https:'.$image;
                }
            }
            $image = $this->validImage($image);
        }

        return $image;

    }

    /**
     * Valida si la imagen no posee una url invalida definida en $this->imageInvalid
     */
    protected function validImage( $image ) {

        /* Verificar que no venga una imagen invalida */
        foreach($this->imageInvalid as $invalid) {
            if( strpos($image,$invalid) !== FALSE ) {
                $image = false;
            }
        }
        return $image; 

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

        $info_stock = Common::getElement( $html, $this->tags['stock_excluded'], 0, 'outertext' );

        if (strpos($info_stock, $this->stockInvalid ) !== false) {
            return false;
        } else {
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
        if( $product['valid_shipping']          == false ) { $this->count['invalid-shipping']++;    $this->log['invalid-shipping'][]    = $product['item_buy_url'];   return false; } 
        if( $product['valid_stock']             == false ) { $this->count['invalid-stock']++;       $this->log['invalid-stock'][]       = $product['item_buy_url'];   return false; } 

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
        $product['external_category']   = 0;

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
                                           $this->categories[$cat]['category_string'], 
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
                array ( 'Product Manufacturer', $product['manufacturer_name'] ),
                array ( 'Product SKU', $product['item_sku'] ),
                array ( 'Product Code', $product['item_code'] ),
                array ( 'Product Normal Price', $product['item_last_normal_price'] ),
                array ( 'Product Special Price', $product['item_last_price'] ),
                array ( 'Product Slug Url', substr($product['item_url_slug'],0,100).'...' ),
                array ( 'Product Shipping Cost', $product['shipping_cost'] ),
                array ( 'Product Valid Shipping', $product['valid_shipping'] ),
                array ( 'Product Valid Stock', $product['valid_stock'] ),
            ) );
        $table->render();

    }

    /**
     * Imprime en archivo log y consola los resultados del scrapping
     */
    protected function printLog( $output ) {
        
        /* Imprime log en archivo */ 
        $recordLog = Log::create('walmart_electronics');
        $recordLog->info('++++++ SPIDERS. REPORT FOR SCRAPPING: Beautycarechoices Volumizing '.date('Y-m-d H:i:s').' ++++++');

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
        $recordLog->info('++++++ END REPORT FOR SCRAPPING: Beautycarechoices Volumizing '.date('Y-m-d H:i:s').' ++++++');
    
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
                array ('Invalid Product without Sponsored', $this->count['invalid-sponsored']),
                array ('Error in Product URL', $this->count['error-product-url']),
                array ('Error in Pages URL', $this->count['error-page-url']),
                array ('Error Inserting Product', $this->count['error-insert-product']),
                array ('Error Updating Product', $this->count['error-update-product'])
             ) );
        $table->render();
        $output->writeln('For more details view the log file.');
    }
}