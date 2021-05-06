<?php
    namespace Spiders\Commands;

    use Spiders\Models\CacheModel;
    use Symfony\Component\Console\Command\Command;
    use Symfony\Component\Console\Input\InputInterface;
    use Symfony\Component\Console\Output\OutputInterface;
    use Symfony\Component\Console\Input\InputArgument;
    use Symfony\Component\Console\Input\InputOption;
    use Symfony\Component\Console\Helper\Table;
    use Symfony\Component\Console\Helper\TableSeparator;
    use Symfony\Component\Console\Helper\TableCell;
    use Sunra\PhpSimple\HtmlDomParser;
    use Spiders\Helpers\Common;
    use Spiders\Helpers\Log;

    class BeautyCareChoices extends Command
    {
        const PRODUCT_TYPE_ID = 3;
        protected $site_info = [
            'store_name' => 'Beautycarechoices',
            'main_url' => '',
            'url_parts' => '',
            'profit_percent' => 1.15
        ];
        protected $current_object;
        protected $categories = [
            'hair_products' => [
                'cat_name' => 'Hair Products',
                'sub_cat' => [
                    'Volumizing',
                    'Hair Growth',
                    'Anti-Dandruff/Itch',
                    'Strengthening',
                    'Hold',
                    'Shine Enhancing',
                    'Bodifying /Body',
                    'Humidity Resistant',
                    'Anti-Frizz',
                    'UV Protection',
                    'Thickening',
                    'Color Preserving',
                    'Straightening',
                    'Smoothing',
                    'Clarifying',
                    'Re-Moisturizing',
                    'Color Enhancing',
                    'Texture',
                    'Heat & Thermal Protection'
                ]
            ],
            'skin_products' => [
                'cat_name' => 'Skin Products',
                'sub_cat' => [
                    'Lotions',
                    'Skin Serum',
                    'Skin Spray',
                    'Skin care Gel',
                    'Masque',
                    'Skin Oil',
                    'Exfoliant/Scrub',
                    'Cream',
                    'Waxing',
                    'Facial Cleansers',
                    'Files, Brushes, Tools',
                    'After Shave',
                    'Orthotics',
                    'Bath/Shower',
                    'Shave',
                    'Sunless or Enhanced Tan',
                    'Sun Protection - SPF'
                ]
            ],
            'cosmetics' => [
                'cat_name' => 'Cosmetics',
                'sub_cat' => [
                    'Lips',
                    'Eyes',
                    'Face',
                    'Makeup Remover',
                    'Tools and Brushes',
                    'Eye Shadows',
                    'Liners & Pencils',
                    'Mascara & Lash Care',
                    'Eye Brows'
                ]
            ],
            'nails_products' => [
                'cat_name' => 'Nails Products',
                'sub_cat' => [
                    'Men',
                    'Women',
                    'Nail Polish',
                    'Nail Polish Remover',
                    'Tools',
                    'Nail Treatments',
                    'Manicure/Pedicure',
                    'Base/Top Coats'
                ]
            ],
            'tools_and_accessories' => [
                'cat_name' => 'Tools & Accessories',
                'sub_cat' => [
                    'Brushes',
                    'Curling Irons',
                    'Flat Irons',
                    'Hair Dryers',
                    'Diffusers',
                    'Combs',
                    'Rollers',
                    'Styling Iron',
                    'Styling Tool Accessories',
                    'Clippers & Trimmers',
                    'Files & Buffers',
                    'Extra Accessories'
                ]
            ]
        ];
        protected $products_affected = array();


        /**
         * Configura los datos para el comando.
         */
        protected function configure()
        {
            $this
                ->setName('BeautyCareChoices:scrap')
                ->setDescription('Comienza el proceso de Scraping')
                ->setHelp('Debe suministrar la url de la sección de liquidación')
                ->addArgument('url', InputArgument::OPTIONAL, 'URL para Scraping')
            ;
        }

        /**
         * Ejecuta los pasos para scrapear el sitio y muestra por pantalla el progreso actual de cada paso.
         *
         * @param InputInterface $input
         * @param OutputInterface $output
         * @return int|null|void
         */
        protected function execute(InputInterface $input, OutputInterface $output)
        {
            if($input->getArgument('url')) {
                $this->site_info['main_url'] = $input->getArgument('url');
            } else { 
                $this->site_info['main_url'] = 'http://beautycarechoices.com/features/clearance.php';
            }
            $this->site_info['url_parts'] = parse_url($this->site_info['main_url']);
            $this->current_object = $this->get_html($this->site_info['main_url']);

            $log = Log::create('BeautyCareChoices');
            $log->info('++++++ SPIDERS. REPORT FOR SCRAPPING: Beauty Care Choices '.date('Y-m-d H:i:s').' ++++++');

            $output->writeln(PHP_EOL.'Step 1: Scraping Categories...');
            $categories = $this->get_categories($output, $log);

            if ( $categories ) {

                $output->writeln(PHP_EOL.'Step 2: Scraping Products Init Info...');
                $products_urls = $this->get_products_url($categories, $output, $log);

                if ( $products_urls ) {

                    $output->writeln(PHP_EOL.'Step 3: Scrap Remaining Info Products...');
                    $products = $this->get_products($products_urls, $output);

                    $output->writeln(PHP_EOL.'Step 4: Adding Scraped Products...');
                    foreach ( $products as $product) {
                        $this->save_products($product, $log);
                    }

                    $this->disableProducts($log);

                }

            }

            $output->writeln(PHP_EOL.'Scrapping proccess has finish.');
            $output->writeln('Check log file to see more details.');

            $log->info('++++++ END REPORT FOR SCRAPPING: Beauty Care Choices '.date('Y-m-d H:i:s').' ++++++');
        }

        /**
         * Obtiene un objeto scraping a utilizar
         *
         * @param $url  Enlace para scrapear.
         * @return bool|simple_html_dom  Devuelve un objeto simple_html_dom para trabajar.
         */
        public function get_html($url)
        {
            return HtmlDomParser::file_get_html($url);
        }

        /**
         * Obtiene el listado de categorias de productos en liquidacion para navegar.
         *
         * @return array  Listado de categorias a navegar.
         */
        public function get_categories(OutputInterface $output, $log)
        {
            $categories = $this
                ->current_object
                    ->find('div[id=newatt-navigation] a');

            if ( !is_null( $categories ) ) {

                foreach($categories as $category)
                {
                    $cat_id = explode('=', $category->href);
                    $cat[] = [
                        'cat_id' => $cat_id[1],
                        'cat_name' => $category->innertext,
                        'cat_url' => $this->site_info['main_url'].$category->href
                    ];
                }
                $output->writeln(count($cat) . ' Categories Found'.PHP_EOL);
                $output->writeln('Next step...');
                return $cat;

            } else {

                $output->writeln('ERROR: Categories cant be scrap from original site.');
                $log->info('ERROR: Categories cant be scrap from original site.');
                return false;

            }
        }

        /**
         * Obtiene los enlaces de los productos a visitar a partir de cada categoria de la tienda en el listado.
         *
         * @param $cats  Listado de categorias.
         * @return array  Listado de productos scrapeados.
         */
        public function get_products_url($cats, OutputInterface $output, $log)
        {
            $prods = array();

            foreach ($cats as $cat)
            {
                $this->current_object = $this->get_html($cat['cat_url']);

                $products = $this
                    ->current_object
                        ->find('div[class=product]');

                foreach ($products as $product)
                {
                    $product_url = $product->find('a[class=pw2-item-name]', 0)->href;
                    if ( !is_null( $product_url ) ) {

                        $product_url = $this->site_info['url_parts']['scheme'].'://' . $this->site_info['url_parts']['host'] . $product_url;
                        $item_code = md5($product_url);
                        $prods[] = [
                            'item_status' => 1,
                            'item_type_id' => self::PRODUCT_TYPE_ID,
                            'store_name' => $this->site_info['store_name'],
                            'item_title' => addslashes($product->find('a[class=pw2-item-name]', 0)->innertext),
                            'category' => $this->search_cat($cat['cat_name']),
                            'external_category' => 0,
                            'item_buy_url' => $product_url,
                            'item_code' => $item_code
                        ];

                    }
                }
            }

            if ( count($prods) > 0 ) {

                $unique_products = array_unique($prods, SORT_REGULAR );

                foreach ($unique_products as $key => $value){
                    if ($value['category'] == '') {
                        unset($unique_products[$key]);
                        continue;
                    }
                }

                $output->writeln(count($unique_products) . ' Products Found'.PHP_EOL);
                $output->writeln('Next step...');
                return $unique_products;

            } else {

                $output->writeln('ERROR: Products init info cant be scrap from original site.');
                $log->info('ERROR: Products init info cant be scrap from original site.');
                return false;

            }

        }

        /**
         * Obtiene el nombre de la categoría magento valida para el producto.
         *
         * @param $sub_cat  Nombre de la categoria de la tienda de origen.
         * @return string  Nombre de la categoría en magento.
         */
        public function search_cat($sub_cat)
        {
            foreach($this->categories as $category)
            {
                if (in_array($sub_cat, $category['sub_cat'])) {
                    return 'DRS/Health & Beauty/Beauty/'.$category['cat_name'];
                }
            }
        }

        /**
         * Obtiene el resto de los datos faltantes por scrapear.
         *
         * @param $prods  Listado de productos con los datos hasta ahora scrapeados.
         * @return mixed  Listado de productos con todos los datos necesarios a guardar.
         */
        public function get_products($prods, OutputInterface $output = NULL)
        {
            foreach ($prods as $key => $value)
            {
                $this->current_object = $this->get_html($value['item_buy_url']);

                $manufacturer_name = $this->current_object
                    ->find('span[class=breadcrumb_link]', 0)
                        ->plaintext;

                $prods[$key]['manufacturer_name'] = $this->get_product_vendor($manufacturer_name);

                $prods[$key]['item_url_slug'] = Common::createSlug($value['item_title'], $value['item_buy_url']);

                $prods[$key]['item_image_url'] = $this->site_info['url_parts']['scheme'].'://'.
                                                 $this->site_info['url_parts']['host'].
                                                 $this->current_object
                                                    ->find('div[id=prod-image-mobile] img', 0)
                                                        ->src;

                $prods[$key]['item_sku'] = trim(
                    strip_tags(
                        $this->current_object
                            ->find('div[id=prod-id]', 0)
                                ->innertext
                    )
                );

                $normal_price = str_replace(
                    '$',
                    '',
                    $this->current_object
                        ->find('div[class=prod_price_price] strike', 0)
                            ->innertext
                );
                $price = str_replace(
                    '$',
                    '',
                    $this->current_object
                        ->find('div[class=prod_price_price] span[class=prod_price_sale]', 0)
                            ->innertext
                );

                $prods[$key]['shipping_cost'] = ($price < 49) ? 7.95 : 0;

                $prods[$key]['item_last_price'] = round($price * $this->site_info['profit_percent'], 2);

                $prods[$key]['item_last_normal_price'] = round($normal_price * $this->site_info['profit_percent'], 2);

                $prods[$key]['item_description'] = $this->get_descripcion(
                    $this->current_object
                        ->find('div[class=prod_full]', 0)
                            ->outertext
                );
            }

            $output->writeln('Goted Info.'.PHP_EOL);
            $output->writeln('Next step...');

            return $prods;
        }

        /**
         * Obtiene la marca del producto a partir de las breadcumbs
         *
         * @param $breadcumb  Breadcumb desde la que se extraera la marca.
         * @return string  Marca del producto.
         */
        private function get_product_vendor($breadcumb)
        {
            $breadcumb = explode('&gt;', $breadcumb);
            return trim($breadcumb[1]);
        }

        /**
         * Limpia la descripción de un producto obtenida desde la tienda de origen.
         *
         * @param $text  Texto a limpiar.
         * @return mixed  Texto limpio.
         */
        private function get_descripcion($text)
        {
            $emStartPos = strpos($text,'<span class="prod_full_title">');
            $emEndPos = strpos($text,'</span>');

            if ($emStartPos && $emEndPos) {
                $emEndPos += 8;
                $len = $emEndPos - $emStartPos;

                return addslashes(substr_replace($text, '', $emStartPos, $len));
            }
        }

        /**
         * Guarda los productos obtenidos por scraping para almacenar en la base de datos.
         *
         * @param $product  Producto a actualizar o añadir en la tienda.
         * @param OutputInterface $output  muestra por consola.
         */
        private function save_products($product, $log)
        {
            $cache = new CacheModel();
            $product_exist = $cache->getProduct($product['item_code']);

            /* Actualizar producto */
            if($product_exist) {
                /* Se verifica si el producto posee otras categorias */
                $cache_categories = explode( ',', $product_exist['category'] );
                if(!in_array($product['category'], $cache_categories)) {
                    $cache_categories[] = $product['category'];
                    $product['category'] = implode(',', $cache_categories);
                }
                /* Se envia a la DB */
                $result = $cache->update( $product );
                if ( $result ) {
                    $this->products_affected[] = "'".$product['item_code']."'";
                    $log->info($product['item_code'].' Updated');
                }

            /* Nuevo producto */
            } else {
                /* Se envia a la DB */
                $result = $cache->insert( $product );
                if ( $result ) {
                    $this->products_affected[] = "'".$product['item_code']."'";
                    $log->info($product['item_code'].' Added');
                }
            }
        }

        /**
         * Permite desactivar o deshabilitar los productos que no fueron actualizados.
         *
         * @param $log para añadir al log los productos deshabilitados.
         */
        protected function disableProducts($log) {
            $cache = new CacheModel;
            $result = $cache->disableProducts( $this->site_info['store_name'],
                false,
                $this->products_affected );
            if($result) {
                $log->info('DISABLED PRODUCTS:'.$result);
                /*foreach ($this->products_affected as $key => $value) {
                    $log->info($value);
                }*/
            }
        }
    }