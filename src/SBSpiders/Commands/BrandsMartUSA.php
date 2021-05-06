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
 
class BrandsMartUSA extends Command
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
        'site_name'     => 'BrandsMartUSA',
        'site_base_url' => 'https://www.brandsmartusa.com',
        'site_url'      => 'https://www.brandsmartusa.com/assembler?assemblerContentCollection=/content/Shared/ResultsList&No=%s&Nrpp=12&format=json&N=%s+3219349642&nocache=%s',
        'product_url'   => 'https://www.brandsmartusa.com/rest/model/atg/commerce/catalog/ProductCatalogActor/getGeneralProductDetails?pageName=pdp&productId=%s&nocache=%s',
        'profit_percent'=> 1.15
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
    *   @var $categories Definición de categorias a obtener
    *   - Se definen con el id de la categoria en tienda y el string de categoria magento donde seran ubicadas 
    */
    protected $categories = [
        'cell-phones'               => [ 'category_id' => '1956185335'],
        'audio-headphones'          => [ 'category_id' => '921173325'],
        'home-yard-office'          => [ 'category_id' => '3260850271'],
        'tv-home-theater'           => [ 'category_id' => '102951'],
        'mattresses'                => [ 'category_id' => '4013011689'],
        'wearable-tech'             => [ 'category_id' => '103201'],
        'furniture'                 => [ 'category_id' => '103709'],
        'health-fitness-beauty'     => [ 'category_id' => '775410431'],
        'kitchen-appliances'        => [ 'category_id' => '2716356358'],
        'small-kitchen-appliances'  => [ 'category_id' => '749061154'],
        'cameras-camcorders'        => [ 'category_id' => '102443'],
        'security-smart-home'       => [ 'category_id' => '2462500682'],
        'car-electronics-gps'       => [ 'category_id' => '103018'],
        'video-games-toys'          => [ 'category_id' => '2472306503'],
        'laundry-garment-care'      => [ 'category_id' => '198078129'],
        'computers-tablets'         => [ 'category_id' => '3739076870']
    ];
    
    /** 
    *   @var $imageInvalid Filtro de Imagenes no validas 
    *   - Si se detecta la imagen principal como invalida, se intentará obtener la secundaria, si esta disponible
    */ 
    /*
	protected $imageInvalid = [
        'https://i5.walmartimages.com/asr/0ce9154c-7902-432a-a32e-af279d75098f_1.7820165ac739ce745b4828ff162cf7d7.jpeg',
        'https://i5.walmartimages.com/asr/8a92fce5-aec1-4efc-986f-25c8953b7e80_1.fcaf2b35dfe0328a70c2eb30c5a7599d.jpeg'
    ];*/

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
        'invalid-offer'         => 0,
        'invalid-stock'         => 0,
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
        'invalid-offer'         => array(),
        'invalid-stock'         => array(),
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
        $this->setName('Brandsmartusa')
                ->setDescription('BrandsMart USA Products')
                ->setHelp('Scrapping products from BrandsMart USA Store.')
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
        
        $output->writeln('<bg=green;options=bold;fg=white>[ SPIDERS - BrandsMart USA ]</>');

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
            for ( $page = 0; $page < $max_pages; $page++ ) {

                /* Se obtiene HTML de la pagina */ 
                $page_html = $this->getPageHtml( $det['category_id'], $page, $output );

                if(!$page_html) {
                    if ($output->isVerbose()) {
                        $output->writeln('<error>Error obteniendo Url, se continua con siguiente página</error>');
                    }
                    $this->log['error-page_url'] = $this->makePageUrl( $det['category_id'], $page );
                    $this->count['error-page-url']++;
                    continue;
                }

                if($page == 0) {
                    /* Se obtiene la cantidad de paginaciones posibles */ 
                    $info_records = $this->getMaxPages($page_html);

                    /* Si no se consigue paginación se salta al siguiente offerFilter */ 
                    if(!$info_records) { 
                        if ($output->isVerbose()) {
                            $output->writeln('<comment>No se encontraron registros</comment>');
                        }
                        $max_pages = 0;
                        continue;
                    } else {
                        $max_pages = $info_records['pages'];
                    }

                    $output->writeln('<info>Categoria: '.$cat.', Registros: '.$info_records['records'].'</info>');
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
     *  @param  $category_id    Código de categoria
     *  @param  $page           Número de pagina a buscar
     *
     *  @return simple_html_dom_parser object
     */
    protected function getPageHtml( $category_id, $page, $output ) {
        try
        {	                    
            /*
            *	Se construye la URL en base a la categoria, filtro y pagina a consultar
            */
            $page_url =  $this->makePageUrl( $category_id, $page );

            /* 
            *   Se intenta obtener el html de la pagina 
            *   - Se implementa la clase Webdriver que a su vez hace uso de las herramientas externas Selenium y Google Chrome
            */
            if ($output->isVerbose()) {
                echo 'Url: '.$page_url.PHP_EOL;	
            }
            $result = file_get_contents($page_url);
            if($result === FALSE){
                return false;
            } else {
                return json_decode($result);
            }

        } catch (\Exception $e) {
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
    protected function makePageUrl($category_id, $page){

        $initial = $page * 12; 

        return str_replace('+','%20',sprintf( 
            $this->info['site_url'],                                // URL del sitio a consultar
            $initial,                                               // Registro inicial
            $category_id,                                           // Categoria
            round(microtime(true) * 1000)                           // Timestamp para el nocache
            ) 
        );                                              
    }

    /** 
    *	@access Protected 
    *   Calcular la cantidad de paginas que tiene la categoria 
    *
    *   @param $page_html  Código HTML de la pagina obtenida
    */     
    protected function getMaxPages($page_html) {

        $records = $page_html->contents[0]->{'totalNumRecs'};

        if($records > 0) {
            $infoPages['pages']   = (int) ( $records / 12 );
            if( ( $records / 12 ) > $infoPages['pages'] ) {
                $infoPages['pages']++;
            }
            $infoPages['records'] = $records;
            return $infoPages;
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
        
        $records = $page_html->contents[0]->records;
        
        foreach ( $records as $record) {
            $product = array();

            if($record->attributes->{'showAddButton'}[0] == 'true'){

                if( $record->attributes->{'priceYourTitle'}[0] == 'Clearance Price' || $record->attributes->{'onSale'}[0] == 'true') {            

                    $product_id = $record->attributes->{'productId'}[0];
                    $product_url = sprintf($this->info['product_url'], $product_id, round(microtime(true) * 1000) );

                    $result = file_get_contents($product_url);
                    if($result === FALSE) {
                        if ($output->isVerbose()) {
                            $output->writeln('<error>Error obteniendo Url, se continua con siguiente producto</error>');
                        }
                        $this->log['error-product-url'][] = $product_url; 
                        $this->count['error-product-url']++;
                        continue;
                    } 

                    $content = json_decode($result); 

                    /* Obtiene la categoria */
                    $breadCrumbs = $content->productDetailsJsonVariable->breadCrumb;
                    $category = array();
                    foreach($breadCrumbs as $breadCrumb) {
                        $category[]= $breadCrumb->breadCrumbLabel;
                    }
                    $product['category']                = Common::cleanString('BrandsMartUSA|'.$cat.'|'.implode('|',$category),1);   
                    $product['item_title']              = Common::cleanString($content->productDetailsJsonVariable->productName,1);
                    $product['item_description']        = Common::cleanString($content->productDetailsJsonVariable->productOverview,1);

                    unset($features);  
                    foreach( $content->productDetailsJsonVariable->topFeatures as $topFeature) {
                        $features[] = '<li>'.$topFeature.'</li>';
                    }

                    $product['item_description']       .= Common::cleanString('<br><h2>Features:</h2><br><ul>'.implode("",$features).'</ul>', 1 );    
                    $product['manufacturer_name']       = Common::cleanString($content->productDetailsJsonVariable->manufacturerName,1);
                    $product['item_sku']                = $content->productDetailsJsonVariable->manufacturerNumber;
                    $product['item_buy_url']            = $this->info['site_base_url'].$content->productDetailsJsonVariable->productUrl;
                    $product['item_url_slug']           = Common::createSlug( $product['item_title'], $product['item_buy_url'] );
                    $product['item_code']               = md5( $product['item_buy_url'] );
                    $product['shipping_cost']           = 0;

                    $normal_price = null; $special_price = null;
                    $normal_price = $content->productDetailsJsonVariable->prodPriceDetailsVo->productPriceSRP;
                    $special_price = $content->productDetailsJsonVariable->prodPriceDetailsVo->productPriceYour;

                    if($normal_price == 0 && $normal_price == null) {
                        $normal_price = $special_price;
                    }
                    if($special_price == 0 && $special_price == null) { 
                        $special_price = $normal_price;
                    }

                    if($normal_price) {
                        $product['item_last_normal_price'] = round( $normal_price * $this->info['profit_percent'], 2 );
                    } else {
                        $product['item_last_normal_price'] = null;
                    }

                    if($special_price) {
                        $product['item_last_price'] = round( $special_price * $this->info['profit_percent'], 2 );
                    } else {
                        $product['item_last_price'] = null;
                    }

                    $product['item_image_url'] = $this->info['site_base_url'].$content->productDetailsJsonVariable->largeImg;

                    /* Si el producto supera la validación de guarda */
                    if( $this->validProduct( $product ) ) {
                        if(!$this->nosave) {
                            $this->saveProduct( $product );
                        }
                    }
                    if ($output->isVerbose()) {
                        $this->printProduct( $product, $output, $i );
                    }
                    /* Se elimina el array de producto antes de continuar con el siguiente */ 
                    unset($product);
    
                } else {
                    //echo 'DESCARTADO NO ES CLEARANCE NI ONSALE'.PHP_EOL;
                    $this->count['invalid-offer']++;
                    $this->log['invalid-offer'][] = $this->info['site_base_url'].$record->attributes->{'product.pdpSeoUrl'}[0];
                }

            } else {
                //echo 'DESCARTADO NO POSEE STOCK'.PHP_EOL;
                $this->count['invalid-stock']++;
                $this->log['invalid-stock'][] = $this->info['site_base_url'].$record->attributes->{'product.pdpSeoUrl'}[0];
            }      
            /* Tiempo de espera opcional entre un producto y otro */ 
            //sleep(5);
            $i++;
        }
        return $i;
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
        $product['item_status']       = 1;
        $product['item_type_id']      = self::PRODUCT_TYPE_ID; 
        $product['store_name']        = $this->info['site_name'];
        $product['external_category'] = 1;

        /* Registrar Categoria en MatchCategory */ 
        $this->saveCategoryMatch($product['category']);

        /* Actualizar producto */ 
        if($product_exist) {
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
     * Registra categoria en CategoryMatch
     */
    protected function saveCategoryMatch($category) {
        $categoryMatch = new CacheModel;
        $result = $categoryMatch->insertCategoryMatch( $category );
    }

    /**
     * Desactiva productos no actualizados en la categoria
     */
    protected function disableProducts( $cat ) {
        $cache = new CacheModel; 
        $result = $cache->disableProducts( $this->info['site_name'],
                                           $cat, 
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
                array ( 'Product Category', $product['category'] )
            ) );
        $table->render();

    }

    /**
     * Imprime en archivo log y consola los resultados del scrapping
     */
    protected function printLog( $output ) {
        
        /* Imprime log en archivo */ 
        $recordLog = Log::create('brandsmartusa');
        $recordLog->info('++++++ SPIDERS. REPORT FOR SCRAPPING: BRANDSMART USA '.date('Y-m-d H:i:s').' ++++++');

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
        $recordLog->info('++++++ END REPORT FOR SCRAPPING: BRANDSMART USA '.date('Y-m-d H:i:s').' ++++++');
    
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
                array ('Invalid Product without Offer', $this->count['invalid-offer']),
                array ('Invalid Product without Stock', $this->count['invalid-stock']),
                array ('Error in Product URL', $this->count['error-product-url']),
                array ('Error in Pages URL', $this->count['error-page-url']),
                array ('Error Inserting Product', $this->count['error-insert-product']),
                array ('Error Updating Product', $this->count['error-update-product'])
             ) );
        $table->render();
        $output->writeln('For more details view the log file.');
    }
}